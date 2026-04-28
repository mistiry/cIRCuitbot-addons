<?php

// In-memory deduplication: tracks recently asked questions within the configured window
$triviaRecentlyAsked = array();

function triviaSystem_startGame($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $triggers;
    global $config;
    global $dbconnection;
    global $triviaRecentlyAsked;

    $configfile   = parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];
    $topics       = $configfile['topics'];
    $topicArray   = array_values($topics);

    if(array_key_exists($activityName, $activeActivityArray)) {
        sendPRIVMSG($ircdata['location'], "Sorry, a game is already in progress!");
        return true;
    }

    $arg = trim($ircdata['commandargs']);
    if(!empty($arg)) {
        if($arg == "topics") {
            $topicsMessage = implode("  ", $topicArray);
            $prefix = triviaSystem_prefix();
            sendPRIVMSG($ircdata['location'], "{$prefix} " . stylizeText("Available Topics: ", "bold") . stylizeText($topicsMessage, "bold"));
            return true;
        }
        if(in_array($arg, $topicArray)) {
            $triviaTopic = $arg;
        } else {
            sendPRIVMSG($ircdata['location'], "That is not a valid topic. Not starting!");
            return true;
        }
    } else {
        $randKey     = array_rand($topicArray);
        $triviaTopic = $topicArray[$randKey];
    }

    // Load a question, skipping recently asked ones
    $windowSeconds  = (int)($configfile['noRepeatWindow'] ?? 60) * 60;
    $cutoff         = time() - $windowSeconds;
    $triviaRecentlyAsked = array_values(array_filter($triviaRecentlyAsked, function($e) use ($cutoff) {
        return $e['asked_at'] > $cutoff;
    }));

    $topicFile  = "{$config['addons_dir']}/modules/triviaSystem/{$triviaTopic}.topic";
    $json       = file_get_contents($topicFile);
    $jsonData   = json_decode($json, true);
    $allKeys    = array_keys($jsonData['questions']);

    $triviaQuestion = "";
    $triviaAnswer   = "";
    $triviaAltAnswers = array();
    $attemptsToLoad = 0;

    while($attemptsToLoad <= 10) {
        $randKey        = $allKeys[array_rand($allKeys)];
        $candidate      = $jsonData['questions'][$randKey]['question'] ?? "";
        $candidateHash  = sha1($candidate);

        if(strlen($candidate) < 10) {
            $attemptsToLoad++;
            continue;
        }

        $alreadySeen = false;
        foreach($triviaRecentlyAsked as $entry) {
            if($entry['hash'] === $candidateHash) {
                $alreadySeen = true;
                break;
            }
        }
        if($alreadySeen) {
            $attemptsToLoad++;
            continue;
        }

        $triviaQuestion   = $candidate;
        $triviaAnswer     = $jsonData['questions'][$randKey]['answer'] ?? "";
        $triviaAltAnswers = $jsonData['questions'][$randKey]['alt_answers'] ?? array();
        break;
    }

    if(empty($triviaQuestion)) {
        $fail = stylizeText(stylizeText("Apologies! Something went wrong. Please try again.", "bold"), "color_red");
        sendPRIVMSG($ircdata['location'], $fail);
        return true;
    }

    $triviaRecentlyAsked[] = array('hash' => sha1($triviaQuestion), 'asked_at' => time());

    // Record game start time and register wildcard answer checker
    $currentEpoch = time();
    $expiryTime   = $currentEpoch + (int)$configfile['questionTime'];
    $timerArray['triviaSystem_timeExpired'] = $expiryTime;

    if(!empty($configfile['hintsEnabled']) && $configfile['hintsEnabled'] == "true") {
        $hintTime = $currentEpoch + (int)floor((int)$configfile['questionTime'] / 2);
        $timerArray['triviaSystem_giveHint'] = $hintTime;
    }

    $triggers['*'][] = 'triviaSystem_checkAnswer';

    $activeActivityArray[$activityName] = array(
        'topic'        => $triviaTopic,
        'answer'       => $triviaAnswer,
        'alt_answers'  => $triviaAltAnswers,
        'start_time'   => $currentEpoch,
        'asker'        => $ircdata['usernickname'],
        'asker_host'   => $ircdata['userhostname'],
        'hint_given'   => false,
    );

    // Track games started and check for starter badges
    triviaSystem_incrementGamesStarted($ircdata['userhostname'], $ircdata['usernickname']);

    // Stylize and send intro
    $prefix      = triviaSystem_prefix();
    $introText   = stylizeText("{$ircdata['usernickname']} has started trivia. You have {$configfile['questionTime']} seconds to answer! The topic is:", "bold");
    $topicText   = stylizeText(stylizeText($triviaTopic, "color_pink"), "bold");
    sendPRIVMSG($ircdata['location'], "{$prefix} {$introText} {$topicText}");

    $questionText = stylizeText(stylizeText($triviaQuestion, "bold"), "color_yellow");
    sendPRIVMSG($ircdata['location'], $questionText);

    return true;
}

function triviaSystem_checkAnswer($ircdata) {
    global $activeActivityArray;
    global $config;

    $configfile   = parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];

    if(!array_key_exists($activityName, $activeActivityArray)) {
        return false;
    }

    $game    = $activeActivityArray[$activityName];
    $input   = trim($ircdata['fullmessage']);
    $isFuzzy = false;

    $allAnswers   = array_merge(array($game['answer']), $game['alt_answers']);
    $normalInput  = triviaSystem_normalizeAnswer($input);

    foreach($allAnswers as $ans) {
        $normalAns = triviaSystem_normalizeAnswer($ans);
        if($normalInput === $normalAns) {
            if($ans !== $game['answer']) {
                $isFuzzy = true;
            }
            triviaSystem_processWin($ircdata, $isFuzzy);
            return true;
        }
    }

    // Levenshtein fuzzy check — only for answers 6+ chars
    foreach($allAnswers as $ans) {
        $normalAns = triviaSystem_normalizeAnswer($ans);
        $ansLen    = strlen($normalAns);
        if($ansLen < 6) continue;
        $tolerance = (int)floor($ansLen / 6);
        if($tolerance < 1) continue;
        if(levenshtein($normalInput, $normalAns) <= $tolerance) {
            triviaSystem_processWin($ircdata, true);
            return true;
        }
    }

    return false;
}

function triviaSystem_normalizeAnswer($str) {
    $str = strtolower(trim($str));
    $str = preg_replace("/^(the|a|an)\s+/", "", $str);
    $str = preg_replace("/['\"\-\.,!?]/", "", $str);
    $str = preg_replace("/\s+/", " ", $str);
    return trim($str);
}

function triviaSystem_processWin($ircdata, $isFuzzy) {
    global $activeActivityArray;
    global $timerArray;
    global $triggers;
    global $config;

    $configfile   = parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];
    $game         = $activeActivityArray[$activityName];

    $timeToAnswer = round(time() - $game['start_time'], 2);
    $questionTime = (int)$configfile['questionTime'];
    $maxPoints    = max(1, (int)($configfile['maxPoints'] ?? 3));
    $tierSize     = ceil($questionTime / $maxPoints);
    $tier         = (int)ceil($timeToAnswer / $tierSize);
    $pointsAwarded = max(1, $maxPoints - $tier + 1);

    $congratsArray = array(
        'you got it with',
        'coming in clutch with',
        'nailed it with',
        'rekt everyone with',
        'ftmfw with',
        'obliterates the competition by answering',
        'destroys everyone with the answer',
        'takes the win with',
        'shows their dominance with the correct answer',
        'proves they arent just M$ Helldesk with',
    );
    $congratsText  = stylizeText($congratsArray[array_rand($congratsArray)], "bold");
    $usernameText  = stylizeText(stylizeText($ircdata['usernickname'], "color_light_green"), "bold");
    $topicText     = stylizeText(stylizeText($game['topic'], "color_pink"), "bold");
    $answerText    = stylizeText(stylizeText($game['answer'], "color_cyan"), "bold");
    $pointsText    = stylizeText("{$pointsAwarded}pt" . ($pointsAwarded !== 1 ? "s" : ""), "bold");

    $prefix = triviaSystem_prefix();
    sendPRIVMSG($ircdata['location'], "{$usernameText} {$congratsText} {$answerText}! They earned {$pointsText} in the {$topicText} topic.");

    // Update scores and collect any newly earned badges
    $newBadges = triviaSystem_updateScores(
        $ircdata['userhostname'],
        $ircdata['usernickname'],
        $game['topic'],
        $pointsAwarded,
        $timeToAnswer,
        $isFuzzy,
        $game['hint_given'],
        $configfile
    );

    // Check starter badges for the asker too (they may have hit a milestone this game)
    $askerBadges = triviaSystem_getNewStarterBadges($game['asker_host']);

    // Build and send badge announcement if anything was earned
    $badgeParts = array();
    if(!empty($newBadges)) {
        $badgeParts[] = stylizeText($ircdata['usernickname'], "color_light_green") . ": " . triviaSystem_formatBadgeList($newBadges, $config);
    }
    if(!empty($askerBadges) && $game['asker_host'] !== $ircdata['userhostname']) {
        $badgeParts[] = stylizeText($game['asker'], "color_light_green") . ": " . triviaSystem_formatBadgeList($askerBadges, $config);
    }
    if(!empty($badgeParts)) {
        $badgeMsg = stylizeText("Badges unlocked", "bold") . " — " . implode(" | ", $badgeParts);
        sendPRIVMSG($ircdata['location'], "{$prefix} {$badgeMsg}");
    }

    if(!empty($configfile['statsURL'])) {
        sendPRIVMSG($ircdata['location'], "{$prefix} " . stylizeText("Full leaderboard: " . $configfile['statsURL'], "bold"));
    }

    triviaSystem_cleanup($activityName);
    return true;
}

function triviaSystem_giveHint($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $config;

    $configfile   = parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];

    if(!array_key_exists($activityName, $activeActivityArray)) {
        unset($timerArray['triviaSystem_giveHint']);
        return true;
    }

    $game         = $activeActivityArray[$activityName];
    $hintStrength = $configfile['hintStrength'] ?? 'medium';

    // Use the longest available answer string for the hint
    $allAnswers   = array_merge(array($game['answer']), $game['alt_answers']);
    usort($allAnswers, function($a, $b) { return strlen($b) - strlen($a); });
    $hintSource   = $allAnswers[0];

    $hint  = triviaSystem_buildHint($hintSource, $hintStrength);
    $prefix = triviaSystem_prefix();
    $hintText = stylizeText("Hint:", "bold") . " " . stylizeText($hint, "color_yellow");
    sendPRIVMSG($config['channel'], "{$prefix} {$hintText}");

    $activeActivityArray[$activityName]['hint_given'] = true;
    unset($timerArray['triviaSystem_giveHint']);
    return true;
}

function triviaSystem_buildHint($answer, $strength) {
    $words  = explode(" ", $answer);
    $result = array();
    foreach($words as $word) {
        $len = strlen($word);
        if($len <= 1) {
            $result[] = "_";
            continue;
        }
        if($strength === 'light' || $len === 2) {
            $result[] = $word[0] . str_repeat(" _", $len - 1);
        } elseif($strength === 'medium') {
            if($len === 3) {
                $result[] = $word[0] . " _" . " " . $word[$len-1];
            } else {
                $result[] = $word[0] . str_repeat(" _", $len - 2) . " " . $word[$len-1];
            }
        } elseif($strength === 'heavy') {
            if($len <= 4) {
                $result[] = $word[0] . str_repeat(" _", $len - 2) . " " . $word[$len-1];
            } else {
                $result[] = $word[0] . $word[1] . str_repeat(" _", $len - 4) . " " . $word[$len-2] . $word[$len-1];
            }
        }
    }
    return implode("  ", $result);
}

function triviaSystem_timeExpired($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $config;

    $configfile   = parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];

    $answerText = "";
    if(array_key_exists($activityName, $activeActivityArray)) {
        $game       = $activeActivityArray[$activityName];
        $answerText = "The correct answer was: " . stylizeText($game['answer'], "bold");

        // Check starter badges for asker at expiry too
        $askerBadges = triviaSystem_getNewStarterBadges($game['asker_host']);
        if(!empty($askerBadges)) {
            $prefix    = triviaSystem_prefix();
            $badgeMsg  = stylizeText("Badges unlocked", "bold") . " — " . stylizeText($game['asker'], "color_light_green") . ": " . triviaSystem_formatBadgeList($askerBadges, $config);
            sendPRIVMSG($config['channel'], "{$prefix} {$badgeMsg}");
        }
    }

    $timesup = stylizeText(stylizeText("Time is up!", "bold"), "color_red");
    $prefix  = triviaSystem_prefix();
    sendPRIVMSG($config['channel'], "{$prefix} {$timesup} {$answerText}");

    triviaSystem_cleanup($activityName);
    return true;
}

function triviaSystem_cleanup($activityName) {
    global $activeActivityArray;
    global $timerArray;
    global $triggers;

    unset($activeActivityArray[$activityName]);
    unset($timerArray['triviaSystem_timeExpired']);
    unset($timerArray['triviaSystem_giveHint']);

    if(isset($triggers['*']) && is_array($triggers['*'])) {
        $triggers['*'] = array_values(array_filter($triggers['*'], function($f) {
            return $f !== 'triviaSystem_checkAnswer';
        }));
    }
}

function triviaSystem_updateScores($hostname, $nickname, $topic, $points, $timeToAnswer, $isFuzzy, $hintGiven, $configfile) {
    global $dbconnection;

    $hostname  = mysqli_real_escape_string($dbconnection, $hostname);
    $nickname  = mysqli_real_escape_string($dbconnection, $nickname);
    $topicSafe = mysqli_real_escape_string($dbconnection, $topic);
    $now       = time();

    // Resolve known_user_id
    $knownUserID = null;
    $userRecord  = getUserRecord($hostname);
    if($userRecord) {
        $knownUserID = (int)$userRecord['id'];
    }

    // Insert win history row
    $fuzzyInt = $isFuzzy ? 1 : 0;
    $knownIDVal = $knownUserID !== null ? $knownUserID : 'NULL';
    mysqli_query($dbconnection,
        "INSERT INTO trivia_wins (userhostname, known_user_id, topic, points_awarded, time_to_answer, was_fuzzy, timestamp) " .
        "VALUES ('{$hostname}', {$knownIDVal}, '{$topicSafe}', {$points}, {$timeToAnswer}, {$fuzzyInt}, {$now})"
    );

    // Update or insert player aggregate row
    $result = mysqli_query($dbconnection,
        "SELECT id, scores, total_wins, fastest_win FROM trivia WHERE userhostname = '{$hostname}' LIMIT 1"
    );

    if(mysqli_num_rows($result) > 0) {
        $row        = mysqli_fetch_assoc($result);
        $rowID      = (int)$row['id'];
        $scoresArr  = json_decode($row['scores'], true) ?? array();
        $totalWins  = (int)$row['total_wins'] + 1;
        $fastestWin = ($row['fastest_win'] === null || $timeToAnswer < (float)$row['fastest_win'])
                        ? $timeToAnswer : (float)$row['fastest_win'];

        $scoresArr[$topic] = ($scoresArr[$topic] ?? 0) + $points;
        $scoresSafe        = mysqli_real_escape_string($dbconnection, json_encode($scoresArr));
        $knownSet          = $knownUserID !== null ? ", known_user_id = {$knownUserID}" : "";

        mysqli_query($dbconnection,
            "UPDATE trivia SET lastusednickname = '{$nickname}', lastwintime = '{$now}', " .
            "scores = '{$scoresSafe}', total_wins = {$totalWins}, fastest_win = {$fastestWin}{$knownSet} " .
            "WHERE id = {$rowID}"
        );
    } else {
        $scoresArr  = array($topic => $points);
        $scoresSafe = mysqli_real_escape_string($dbconnection, json_encode($scoresArr));
        $knownIDVal = $knownUserID !== null ? $knownUserID : 'NULL';

        mysqli_query($dbconnection,
            "INSERT INTO trivia (userhostname, known_user_id, lastusednickname, scores, lastwintime, total_wins, fastest_win) " .
            "VALUES ('{$hostname}', {$knownIDVal}, '{$nickname}', '{$scoresSafe}', '{$now}', 1, {$timeToAnswer})"
        );
    }

    logEntry("Trivia win recorded: {$nickname}@{$hostname} topic={$topic} points={$points} time={$timeToAnswer}s fuzzy={$fuzzyInt}");

    return triviaSystem_checkAndAwardBadges($hostname, $topic, $points, $timeToAnswer, $isFuzzy, $hintGiven, $configfile);
}

function triviaSystem_checkAndAwardBadges($hostname, $topic, $points, $timeToAnswer, $isFuzzy, $hintGiven, $configfile) {
    global $dbconnection;

    $hostSafe = mysqli_real_escape_string($dbconnection, $hostname);

    $row = mysqli_fetch_assoc(mysqli_query($dbconnection,
        "SELECT total_wins, fastest_win, badges FROM trivia WHERE userhostname = '{$hostSafe}' LIMIT 1"
    ));
    if(!$row) return array();

    $earned     = json_decode($row['badges'], true) ?? array();
    $totalWins  = (int)$row['total_wins'];
    $maxPoints  = max(1, (int)($configfile['maxPoints'] ?? 3));
    $questionTime = (int)($configfile['questionTime'] ?? 30);
    $tierSize   = ceil($questionTime / $maxPoints);

    $newlyEarned = array();

    $ne = function($bid) use (&$earned) { return !array_key_exists($bid, $earned); };

    // First win
    if($ne('first_win') && $totalWins === 1) $newlyEarned[] = 'first_win';

    // Win milestones
    foreach(array(10, 25, 50, 100, 250, 500) as $n) {
        if($ne("wins_{$n}") && $totalWins >= $n) $newlyEarned[] = "wins_{$n}";
    }

    // Speed: quick (first tier)
    if($timeToAnswer <= $tierSize) {
        $quickCount = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND time_to_answer <= {$tierSize}"
        ))[0];
        if($ne('speed_quick') && $quickCount >= 10) $newlyEarned[] = 'speed_quick';
    }

    // Speed: flash (under 5s)
    if($timeToAnswer <= 5.0) {
        $flashCount = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND time_to_answer <= 5.0"
        ))[0];
        if($ne('speed_flash') && $flashCount >= 10) $newlyEarned[] = 'speed_flash';
    }

    // Speed: lightning (under 3s)
    if($timeToAnswer <= 3.0) {
        $lightningCount = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND time_to_answer <= 3.0"
        ))[0];
        if($ne('speed_lightning') && $lightningCount >= 10) $newlyEarned[] = 'speed_lightning';
    }

    // No hint needed (won before hint fired)
    if(!$hintGiven) {
        $noHintCount = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND time_to_answer <= " . floor($questionTime / 2)
        ))[0];
        if($ne('no_hint_10') && $noHintCount >= 10) $newlyEarned[] = 'no_hint_10';
    }

    // Max points milestones
    if($points === $maxPoints) {
        $maxPtsCount = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND points_awarded = {$maxPoints}"
        ))[0];
        foreach(array(5, 10, 25, 50) as $n) {
            if($ne("max_points_{$n}") && $maxPtsCount >= $n) $newlyEarned[] = "max_points_{$n}";
        }
    }

    // Topic mastery
    $topicSafe  = mysqli_real_escape_string($dbconnection, $topic);
    $topicWins  = (int)mysqli_fetch_row(mysqli_query($dbconnection,
        "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND topic='{$topicSafe}'"
    ))[0];
    foreach(array(5, 15, 25) as $n) {
        $bid = "topic_{$n}_{$topic}";
        if($ne($bid) && $topicWins >= $n) $newlyEarned[] = $bid;
    }

    // Polymath: 1+ win in 10+ distinct topics
    $distinctTopics = (int)mysqli_fetch_row(mysqli_query($dbconnection,
        "SELECT COUNT(DISTINCT topic) FROM trivia_wins WHERE userhostname='{$hostSafe}'"
    ))[0];
    if($ne('polymath') && $distinctTopics >= 10) $newlyEarned[] = 'polymath';

    // Polymath squared: 10+ wins in 10+ distinct topics
    if($ne('polymath_squared')) {
        $qualifiedTopics = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(*) FROM (SELECT topic FROM trivia_wins WHERE userhostname='{$hostSafe}' " .
            "GROUP BY topic HAVING COUNT(*) >= 10) sub"
        ))[0];
        if($qualifiedTopics >= 10) $newlyEarned[] = 'polymath_squared';
    }

    // Topic sweep: win in every currently configured topic
    if($ne('topic_sweep')) {
        $configTopics   = array_values($configfile['topics']);
        $winTopicsResult = mysqli_query($dbconnection,
            "SELECT DISTINCT topic FROM trivia_wins WHERE userhostname='{$hostSafe}'"
        );
        $winTopics = array();
        while($r = mysqli_fetch_row($winTopicsResult)) { $winTopics[] = $r[0]; }
        $missing = array_diff($configTopics, $winTopics);
        if(empty($missing)) {
            $newlyEarned[] = 'topic_sweep';
            // context stored below
        }
    }

    // Hat trick: 3 wins today
    $todayWins = (int)mysqli_fetch_row(mysqli_query($dbconnection,
        "SELECT COUNT(*) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND DATE(FROM_UNIXTIME(timestamp)) = CURDATE()"
    ))[0];
    if($ne('hat_trick') && $todayWins >= 3) $newlyEarned[] = 'hat_trick';

    // Full house: 5+ distinct topics today
    $todayTopics = (int)mysqli_fetch_row(mysqli_query($dbconnection,
        "SELECT COUNT(DISTINCT topic) FROM trivia_wins WHERE userhostname='{$hostSafe}' AND DATE(FROM_UNIXTIME(timestamp)) = CURDATE()"
    ))[0];
    if($ne('full_house') && $todayTopics >= 5) $newlyEarned[] = 'full_house';
    if($ne('renaissance') && $todayTopics >= 5) $newlyEarned[] = 'renaissance';

    // Time-based badges
    $hour = (int)date('G');
    if($ne('night_owl') && $hour >= 0 && $hour < 4) $newlyEarned[] = 'night_owl';
    if($ne('early_bird') && $hour >= 5 && $hour < 7) $newlyEarned[] = 'early_bird';

    // Weekend warrior: 5 distinct weekend dates with wins
    if($ne('weekend_warrior')) {
        $weekendDays = (int)mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(timestamp))) FROM trivia_wins " .
            "WHERE userhostname='{$hostSafe}' AND DAYOFWEEK(FROM_UNIXTIME(timestamp)) IN (1,7)"
        ))[0];
        if($weekendDays >= 5) $newlyEarned[] = 'weekend_warrior';
    }

    // Comeback: 14+ day gap from previous win
    if($ne('comeback')) {
        $prevWin = mysqli_fetch_row(mysqli_query($dbconnection,
            "SELECT timestamp FROM trivia_wins WHERE userhostname='{$hostSafe}' ORDER BY timestamp DESC LIMIT 1 OFFSET 1"
        ));
        if($prevWin && (time() - (int)$prevWin[0]) >= (14 * 86400)) $newlyEarned[] = 'comeback';
    }

    // Fuzzy win badge
    if($isFuzzy && $ne('fuzzy_win')) $newlyEarned[] = 'fuzzy_win';

    // Save newly earned badges
    if(!empty($newlyEarned)) {
        $now = time();
        foreach($newlyEarned as $bid) {
            $earned[$bid] = $now;
        }
        $badgesSafe = mysqli_real_escape_string($dbconnection, json_encode($earned));
        mysqli_query($dbconnection,
            "UPDATE trivia SET badges = '{$badgesSafe}' WHERE userhostname = '{$hostSafe}'"
        );
    }

    return $newlyEarned;
}

function triviaSystem_incrementGamesStarted($hostname, $nickname) {
    global $dbconnection;

    $hostSafe = mysqli_real_escape_string($dbconnection, $hostname);
    $nickSafe = mysqli_real_escape_string($dbconnection, $nickname);

    $result = mysqli_query($dbconnection, "SELECT id, games_started FROM trivia WHERE userhostname = '{$hostSafe}' LIMIT 1");
    if(mysqli_num_rows($result) > 0) {
        $row          = mysqli_fetch_assoc($result);
        $gamesStarted = (int)$row['games_started'] + 1;
        mysqli_query($dbconnection,
            "UPDATE trivia SET games_started = {$gamesStarted}, lastusednickname = '{$nickSafe}' WHERE id = {$row['id']}"
        );
    } else {
        mysqli_query($dbconnection,
            "INSERT INTO trivia (userhostname, lastusednickname, games_started) VALUES ('{$hostSafe}', '{$nickSafe}', 1)"
        );
    }
}

function triviaSystem_getNewStarterBadges($hostname) {
    global $dbconnection;

    $hostSafe = mysqli_real_escape_string($dbconnection, $hostname);
    $result   = mysqli_query($dbconnection,
        "SELECT games_started, badges FROM trivia WHERE userhostname = '{$hostSafe}' LIMIT 1"
    );
    if(!$result || mysqli_num_rows($result) === 0) return array();

    $row          = mysqli_fetch_assoc($result);
    $gamesStarted = (int)$row['games_started'];
    $earned       = json_decode($row['badges'], true) ?? array();
    $newlyEarned  = array();

    $ne = function($bid) use (&$earned) { return !array_key_exists($bid, $earned); };

    foreach(array(10 => 'starter_10', 50 => 'starter_50') as $n => $bid) {
        if($ne($bid) && $gamesStarted >= $n) $newlyEarned[] = $bid;
    }

    if(!empty($newlyEarned)) {
        $now = time();
        foreach($newlyEarned as $bid) { $earned[$bid] = $now; }
        $badgesSafe = mysqli_real_escape_string($dbconnection, json_encode($earned));
        mysqli_query($dbconnection, "UPDATE trivia SET badges = '{$badgesSafe}' WHERE userhostname = '{$hostSafe}'");
    }

    return $newlyEarned;
}

function triviaSystem_formatBadgeList($badgeIDs, $config) {
    $badgesJson = file_get_contents("{$config['addons_dir']}/modules/triviaSystem/badges.json");
    $badgeDefs  = json_decode($badgesJson, true) ?? array();
    $parts      = array();
    foreach($badgeIDs as $bid) {
        // Strip topic-specific prefix for display lookup
        $lookupID = $bid;
        if(preg_match('/^topic_\d+_(.+)$/', $bid, $m)) {
            $threshold = preg_match('/^topic_(\d+)_/', $bid, $tm) ? $tm[1] : '5';
            $lookupID  = "topic_{$threshold}_[topic]";
        }
        $def   = $badgeDefs[$bid] ?? $badgeDefs[$lookupID] ?? null;
        $label = $def ? "{$def['emoji']} {$def['name']}" : $bid;
        $parts[] = stylizeText($label, "bold");
    }
    return implode(", ", $parts);
}

function triviaSystem_prefix() {
    return stylizeText(stylizeText("-- TRIVIA --", "bold"), "color_green");
}

function triviaSystem_getStats($ircdata) {
    global $dbconnection;
    global $config;

    $arg      = trim($ircdata['commandargs']);
    $hostname = $ircdata['userhostname'];

    if(!empty($arg)) {
        $argSafe = mysqli_real_escape_string($dbconnection, '%' . $arg . '%');
        $lookup  = mysqli_query($dbconnection,
            "SELECT hostname FROM known_users WHERE nick_aliases LIKE '{$argSafe}' LIMIT 1"
        );
        if($lookup && mysqli_num_rows($lookup) > 0) {
            $hostname = mysqli_fetch_assoc($lookup)['hostname'];
        } else {
            sendPRIVMSG($ircdata['location'], triviaSystem_prefix() . " Could not find a user matching '" . htmlspecialchars($arg, ENT_QUOTES) . "'.");
            return true;
        }
    }

    $hostSafe = mysqli_real_escape_string($dbconnection, $hostname);
    $result   = mysqli_query($dbconnection,
        "SELECT lastusednickname, total_wins, fastest_win, scores, badges FROM trivia WHERE userhostname = '{$hostSafe}' LIMIT 1"
    );

    $prefix = triviaSystem_prefix();

    if(!$result || mysqli_num_rows($result) === 0) {
        sendPRIVMSG($ircdata['location'], "{$prefix} No trivia stats found for that user.");
        return true;
    }

    $row         = mysqli_fetch_assoc($result);
    $nick        = $row['lastusednickname'];
    $totalWins   = (int)$row['total_wins'];
    $fastestWin  = $row['fastest_win'] !== null ? round((float)$row['fastest_win'], 1) . "s" : "N/A";
    $scoresArr   = json_decode($row['scores'], true) ?? array();
    $earned      = json_decode($row['badges'], true) ?? array();
    $badgeCount  = count($earned);

    arsort($scoresArr);
    $topTopic    = !empty($scoresArr) ? array_key_first($scoresArr) . " (" . reset($scoresArr) . "pts)" : "N/A";

    $nickText   = stylizeText(stylizeText($nick, "color_light_green"), "bold");
    $statsLine  = "{$prefix} {$nickText} — Wins: " . stylizeText($totalWins, "bold") .
                  " | Best topic: " . stylizeText($topTopic, "bold") .
                  " | Fastest: " . stylizeText($fastestWin, "bold") .
                  " | Badges: " . stylizeText($badgeCount, "bold");

    sendPRIVMSG($ircdata['location'], $statsLine);

    if(!empty($config['statsURL'] ?? '') || !empty(parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf")['statsURL'])) {
        $confFile = parse_ini_file("{$config['addons_dir']}/modules/triviaSystem/module.conf");
        if(!empty($confFile['statsURL'])) {
            sendPRIVMSG($ircdata['location'], "{$prefix} Full stats: " . $confFile['statsURL']);
        }
    }

    return true;
}

function triviaSystem_getHiScores($ircdata) {
    global $dbconnection;

    $query  = "SELECT lastusednickname, scores FROM trivia";
    $result = mysqli_query($dbconnection, $query);

    if(mysqli_num_rows($result) > 0) {
        $topicArray = array();
        while($row = mysqli_fetch_assoc($result)) {
            $lastusednickname = $row['lastusednickname'];
            $scoresArray      = json_decode($row['scores'], true);
            if(empty($scoresArray) || !is_array($scoresArray)) continue;
            arsort($scoresArray);
            foreach($scoresArray as $topic => $score) {
                if(!array_key_exists($topic, $topicArray)) {
                    $topicArray[$topic] = array('nickname' => $lastusednickname, 'score' => $score);
                } else {
                    $current = $topicArray[$topic];
                    if($score > $current['score']) {
                        $topicArray[$topic] = array('nickname' => $lastusednickname, 'score' => $score);
                    } elseif($score == $current['score'] && !stristr($current['nickname'], $lastusednickname)) {
                        $topicArray[$topic]['nickname'] .= ", {$lastusednickname}";
                    }
                }
            }
        }

        $scoresMessage = "";
        foreach($topicArray as $topic => $details) {
            $scoresMessage .= "  " . stylizeText(stylizeText($topic, "color_cyan"), "bold") .
                              " ({$details['score']}pts: {$details['nickname']})  ";
        }

        $prefix  = triviaSystem_prefix();
        $message = stylizeText("{$prefix} Here are the top scores per topic!", "bold");
        sendPRIVMSG($ircdata['location'], $message);
        sendPRIVMSG($ircdata['location'], $scoresMessage);
    }
}

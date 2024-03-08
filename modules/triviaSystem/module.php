<?php
function triviaSystem_startGame($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $triggers;

    //Load configuration file and build topics array 
    $configfile = parse_ini_file("./modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];
    $topics = $configfile['topics'];
    $topicArray = array();
    foreach($topics as $topic) {
        array_push($topicArray, $topic);
    }

    //If this activity is already active, dont start again
    if(array_key_exists($activityName, $activeActivityArray)) {
        sendPRIVMSG($ircdata['location'],"Sorry, a game is already in progress!");
        return true;
    }

    //Determine if they passed a topic argument, else pick a random one
    $arg = trim($ircdata['commandargs']);
    if(!empty($arg)) {
        //If they are requesting the topics
        if($arg == "topics") {
            foreach($topicArray as $topic) {
                $topicsMessage .= " ".$topic." ";
            }
            $introText1 = stylizeText("-- TRIVIA --", "bold");
            $introText1 = stylizeText($introText1, "color_green");
            sendPRIVMSG($ircdata['location'], "".$introText1." Available Topics: ".stylizeText($topicsMessage,"bold")."");
            return true;
        }
        if(in_array($arg,$topicArray)) {
            $triviaTopic = $arg;
        } else {
            //they passed an arg but its not a valid topic
            sendPRIVMSG($ircdata['location'], "That is not a valid topic. Not starting!");
            return true;
        }
    } else {
        //No arg was passed, topic will be random
        $randKey = array_rand($topics);
        $triviaTopic = $topics[$randKey];
    }

    //Calculate timer expiration and set the timer
    $currentEpoch = time();
    $expiryTime = $currentEpoch + $configfile['questionTime'];
    $timerArray['triviaSystem_timeExpired'] = $expiryTime;

    //Load the trivia JSON and pick a random question/answer
    $attemptsToLoad = 0;
    while(strlen($triviaQuestion)<10 || $attemptsToLoad <= 10) {
        logEntry("Trivia Question is empty, attempt number ".$attemptsToLoad."");
        $topicFile = "./modules/triviaSystem/".$triviaTopic.".topic";
        $json = file_get_contents($topicFile);
        $jsonData = json_decode($json, true);
        $randKey = array_rand($jsonData['questions']);
        $triviaQuestion = $jsonData['questions'][$randKey]['question'];
        $triviaAnswer = $jsonData['questions'][$randKey]['answer'];
        $attemptsToLoad++;
    }

    logEntry("Found a trivia question: '".$triviaQuestion."'");

    if($attemptsToLoad <=10) {
        sendPRIVMSG($ircdata['location'], "".stylizeText(stylizeText("Apologies! Something has gone wrong and I have to abort this attempt. Please try again.", "bold"), "color_red")."");
        //Unset everything and end the game
        unset($activeActivityArray[$activityName]);
        unset($triggers[$answerTrigger]);
        unset($timerArray['triviaSystem_timeExpired']);
        return true;
    }


    //Load the answer into the triggers array
    $triggers[$triviaAnswer] = "triviaSystem_answerGiven";

    //Stylize the intro message
    $introText1 = stylizeText("-- TRIVIA --", "bold");
    $introText1 = stylizeText($introText1, "color_green");
    $introText2 = stylizeText("".$ircdata['usernickname']." has started trivia. You have ".$configfile['questionTime']." seconds to answer! The topic is:", "bold");
    $introText3 = stylizeText($triviaTopic, "color_pink");
    $introText3 = stylizeText($introText3, "bold");
    $introLine = "".$introText1." ".$introText2." ".$introText3."";

    //Stylize the question
    $triviaQuestion = stylizeText($triviaQuestion, "bold");
    $triviaQuestion = stylizeText($triviaQuestion, "color_yellow");

    //Set the activeActivityArray and message the channel to begin!
    $activeActivityArray[$activityName] = $triviaTopic;
    sendPRIVMSG($ircdata['location'], $introLine);
    sendPRIVMSG($ircdata['location'], $triviaQuestion);

    return true;
}

function triviaSystem_answerGiven($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $triggers;
    global $ircdata;

    //The correct answer was given to get here.
    //Congrats texts, chosen at random for some personality.
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
        'proves they arent just M$ Helldesk with'
    );
    $randKey = array_rand($congratsArray);
    $congratsText = $congratsArray[$randKey];
    $congratsText = stylizeText($congratsText,"bold");
    $usernameText = stylizeText($ircdata['usernickname'],"bold");
    $usernameText = stylizeText($usernameText,"color_light_green");

    //The topic for this question was
    $configfile = parse_ini_file("./modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];
    $triviaTopic = stylizeText($activeActivityArray[$activityName], "color_pink");
    $triviaTopic = stylizeText($triviaTopic, "bold");

    //The answer was
    $answerKey = array_search("triviaSystem_answerGiven", $triggers);
    if($answerKey !== false) {
        $correctAnswer = stylizeText($answerKey, "bold");
        $correctAnswer = stylizeText($correctAnswer, "color_cyan");
    }

    //Craft the message and send it
    $message = "".$usernameText." ".$congratsText." ".$correctAnswer."! They earned 1 point in the ".$triviaTopic." topic.";
    sendPRIVMSG($ircdata['location'], $message);

    //Update scores
    triviaSystem_updateScores($ircdata['userhostname'],$ircdata['usernickname'],$activeActivityArray[$activityName]);
    
    //Get the answer from the trigger array based on the known value 'triviaSystem_answerGiven'
    $answerTrigger = array_search("triviaSystem_answerGiven", $triggers);

    //Unset everything and end the game
    unset($activeActivityArray[$activityName]);
    unset($triggers[$answerTrigger]);
    unset($timerArray['triviaSystem_timeExpired']);

    return true;
}

function triviaSystem_timeExpired($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $config;
    global $triggers;


    //Activity should not be active anymore, remove from array
    $configfile = parse_ini_file("./modules/triviaSystem/module.conf");
    $activityName = $configfile['activityName'];
    unset($activeActivityArray[$activityName]);

    //Get the answer from the trigger array based on the known value 'triviaSystem_answerGiven'
    $answerKey = array_search("triviaSystem_answerGiven", $triggers);
    if($answerKey !== false) {
        $answerMsg = "The correct answer was: ".stylizeText($answerKey,"bold")."";
    }

    //Stylize
    $timesup = stylizeText("Time is up!", "bold");
    $timesup = stylizeText($timesup, "color_red");


    //Unset the timer
    unset($timerArray['triviaSystem_timeExpired']);

    //Unset the trigger and message the channel
    unset($triggers[$answerKey]);
    sendPRIVMSG($config['channel'], "".$timesup." ".$answerMsg."");

    return true;
}

function triviaSystem_getMyScores($ircdata) {
    global $dbconnection;
    global $ircdata;

    $hostname = $ircdata['userhostname'];
    logEntry("Getting scores for hostname '".$hostname."'");

    $query = "SELECT userhostname,lastusednickname,scores,lastwintime FROM trivia WHERE userhostname = '".$hostname."'";
    $result = mysqli_query($dbconnection,$query);

    $scoresMessage = "";
    if(mysqli_num_rows($result)>0) {
        while($row = mysqli_fetch_assoc($result)) {
            logEntry("Found row for hostname '".$hostname."'");
            $userhostname = $row['userhostname'];
            $lastusednickname = $row['lastusednickname'];
            $scores = unserialize($row['scores']);
            $lastwintime = $row['lastwintime'];
        }
        arsort($scores);
        foreach($scores as $topic => $score) {
            logEntry("Found score '".$score."' for topic '".$topic."'");
            $topicText = stylizeText(stylizeText($topic,"color_cyan"), "bold");
            $scoreText = "".$score."pts";
            $scoresMessage .= "  ".$topicText." (".$scoreText.")  ";
        }
    }

    $firstMessagePart = stylizeText("-- TRIVIA -- ", "color_green");
    $secondMessagePart = stylizeText("".$firstMessagePart." ".$ircdata['usernickname']." here are your scores!", "bold");

    sendPRIVMSG($ircdata['location'],"".$secondMessagePart."");
    sendPRIVMSG($ircdata['location'],"".$scoresMessage."");
    return true;
}

function triviaSystem_getHiScores($ircdata) {
    global $dbconnection;

    $query = "SELECT lastusednickname,scores FROM trivia";
    $result = mysqli_query($dbconnection,$query);

    if(mysqli_num_rows($result)>0) {
        $topicArray = array();
        while($row = mysqli_fetch_assoc($result)) {
            $lastusednickname = $row['lastusednickname'];
            $scores = $row['scores'];
            $scoresArray = unserialize($scores);
            if(empty($scoresArray)) {
                $scoresArray = array();
            }
            if(is_array($scoresArray)) {
                //is it a category in the array yet, if not add it
                arsort($scoresArray);
                foreach($scoresArray as $topic => $score) {
                    if(!array_key_exists($topic,$topicArray)) {
                        $topicArray[$topic] = array("nickname"=>$lastusednickname, "score"=>$score);
                        $newTopicNickname = $lastusednickname;
                    } else {
                        //get current lastusednickname and score, compare to current row
                        $newTopicNickname = $topicArray[$topic]['nickname'];
                        $topicScore = $topicArray[$topic]['score'];

                        if($topicScore == $score && stristr($newTopicNickname,$lastusednickname)) {
                            continue;
                        } elseif($topicScore < $score) {
                            $newTopicScore = $score;
                            $newTopicNickname = $lastusednickname;
                        } elseif($topicScore > $score) {
                            continue;
                        } elseif($topicScore == $score) {
                            $newTopicScore = $topicScore;
                            if($newTopicNickname == "") {
                                $newTopicNickname = "".$lastusednickname."";
                            } else {
                                $newTopicNickname = "".$newTopicNickname.", ".$lastusednickname."";
                            }
                        }
                        $topicArray[$topic] = array("nickname"=>$newTopicNickname, "score"=>$newTopicScore);
                    }
                    $topic = "";
                    $score = "";
                    $topicNickname = "";
                    $topicScore = "";
                }

            }
        }
        foreach($topicArray as $topic => $details) {
            $messagePiece = "".stylizeText(stylizeText($topic,"color_cyan"), "bold")." (".$details['score']."pts: ".$details['nickname'].")";
            $scoresMessage .= "  ".$messagePiece."  ";
        }
        $firstMessage = stylizeText("-- TRIVIA --", "color_green");
        $message = stylizeText("".$firstMessage." Here are the top users and scores for each available topic!", "bold");
        sendPRIVMSG($ircdata['location'],$message);
        sendPRIVMSG($ircdata['location'],$scoresMessage);
    }
}

function triviaSystem_updateScores($hostname,$nickname,$topic) {
    global $dbconnection;

    $query = "SELECT userhostname,lastusednickname,scores,lastwintime FROM trivia WHERE userhostname = '".$hostname."' LIMIT 1";
    $result = mysqli_query($dbconnection,$query);

    if(mysqli_num_rows($result)>0) {
        //user has existing entry
        while($row = mysqli_fetch_assoc($result)) {
            $userhostname = $row['userhostname'];
            $lastusednickname = $row['lastusednickname'];
            $scoresArray = unserialize($row['scores']);
            $lastwintime = $row['lastwintime'];

            //confirm hostname match
            if($userhostname == $hostname) {
                $newLastUsedNickname = $nickname;
                $newLastWinTime = time();
                $oldScore = $scoresArray[$topic];
                $newScore = ($oldScore + 1);
                unset($scoresArray[$topic]);
                $scoresArray[$topic] = $newScore;
                $newScoresArray = serialize($scoresArray);
                $query = "UPDATE trivia SET lastusednickname='".$newLastUsedNickname."', lastwintime='".$newLastWinTime."', scores='".$newScoresArray."' WHERE userhostname = '".$hostname."'";
            }
            $userhostname = "";
            $lastusednickname = "";
            $scoresArray = array();
            $lastwintime = "";
            $oldScore = "";
            $newScore = "";
        }
    } else {
        $newScoresArray[$topic] = 1;
        $newScoresArray = serialize($newScoresArray);
        $query = "INSERT INTO trivia(userhostname,lastusednickname,scores,lastwintime) VALUES('".$hostname."','".$nickname."','".$newScoresArray."','".time()."')";
    } 

    if(mysqli_query($dbconnection,$query)) {
        //succesfully updated scores
        logEntry("Updated Trivia Scores: '".$nickname."@".$hostname."' topic '".$topic."' score '".$newScoresArray."'");
    } else {
        //failed updating scores
        logEntry("Failed Updating Trivia Scores: '".$nickname."@".$hostname."' topic '".$topic."' score '".$newScoresArray."'");
    }
}
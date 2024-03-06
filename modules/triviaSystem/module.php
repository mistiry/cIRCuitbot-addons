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

    logEntry("Trivia Starting with topic '".$triviaTopic."'");
    sendPRIVMSG($ircdata['location'], "Starting Trivia! The topic is: ".$triviaTopic."");

    //Calculate timer expiration and set the timer
    $currentEpoch = time();
    $expiryTime = $currentEpoch + $configfile['questionTime'];
    $timerArray['triviaSystem_timeExpired'] = $expiryTime;

    //Load the trivia JSON and pick a random question/answer
    $topicFile = "./modules/triviaSystem/".$triviaTopic.".topic";
    $json = file_get_contents($topicFile);
    $jsonData = json_decode($json);
    $randKey = array_rand($jsonData['questions']);
    $triviaQuestion = $jsonData['questions'][$randKey]['question'];
    $triviaAnswer = $jsonData['questions'][$randKey]['answer'];

    //Load the answer into the triggers array
    $triggers[$triviaAnswer] = "triviaSystem_answerGiven";

    // Stylize the intro message
    $introText1 = stylizeText("-- T R I V I A --", "bold");
    $introText1 = stylizeText($introText1, "bg_green");
    $introText1 = stylizeText($introText1, "color_white");
    $introText2 = stylizeText("".$ircdata['username']." has started trivia. You have ".$configfile['questionTime']." seconds to answer! The topic is:", "bold");
    $introText3 = stylizeText($triviaTopic, "color_pink");
    $introText3 = stylizeText($introText3, "bold");
    $introLine = "".$introText1." ".$introText2." ".$introText3."";

    // Stylize the question
    $triviaQuestion = stylizeText($triviaQuestion, "bold");
    $triviaQuestion = stylizeText($triviaQuestion, "color_yellow");

    // Set the activeActivityArray and message the channel to begin!
    $activeActivityArray[$activityName] = $triviaTopic;
    sendPRIVMSG($ircdata['location'], $introLine);
    sendPRIVMSG($ircdata['location'], $triviaQuestion);

    return true;
}

function triviaSystem_answerGiven($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $triggers;

    //The correct answer was given to get here.
    //Congrats texts, chosen at random for some personality.
    $congratsArray = array(
        'you got it',
        'good job',
        'nice one',
        'congratulations',
        'congrats',
        'good work',
        'great job',
        'excellent answer',
        'great answer'
    );
    $randKey = array_rand($congratsArray);
    $congratsText = $congratsArray[$randKey];
    $congratsText = stylizeText($congratsText,"bold");
    $usernameText = stylizeText($ircdata['username'],"bold");
    $usernameText = stylizeText($usernameText,"color_light_green");

    //The topic for this question was
    $triviaTopic = stylizeText($activeActivityArray[$activityName], "color_pink");

    //Craft the message and send it
    $message = "".$usernameText." - ".$congratsText."! You earned 1 point in the ".$triviaTopic." topic.";
    sendPRIVMSG($ircdata['location'], $message);
    
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
    global $config;
    global $triggers;

    //Activity should not be active anymore, remove from array
    unset($activeActivityArray[$activityName]);

    //Get the answer from the trigger array based on the known value 'triviaSystem_answerGiven'
    $answerKey = array_search("triviaSystem_answerGiven", $triggers);
    if($answerKey !== false) {
        $answerMsg = "The correct answer was: ".stylizeText($answerKey,"bold")."";
    }

    //Unset the trigger and message the channel
    unset($triggers[$answerKey]);
    sendPRIVMSG($config['channel'], "Time is up! ".$answerMsg."");

    return true;
}
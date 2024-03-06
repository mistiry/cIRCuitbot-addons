<?php
function triviaSystem_mainFunc($ircdata) {
    global $isActivityActive;
    global $timerArray;
    global $triggers;

    if($isActivityActive == true) {
        sendPRIVMSG($ircdata['location'],"Sorry, a game is already in progress!");
        return true;
    }

    //Since this is the main function, we need to first determine where we are
    //in the game, and if we need to send the current call off to another function
    $arg = trim($ircdata['commandargs']);

    //Load all available trivia topics into array here based on the config file 
    $configfile = parse_ini_file("./modules/triviaSystem/module.conf");
    $topics = $configfile['topics'];
    $topicArray = array();
    foreach($topics as $topic) {
        array_push($topicArray, $topic);
    }

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

    //Start the trivia game!
    $isActivityActive = true;

    //Calculate timer expiration
    $currentEpoch = time();
    $expiryTime = $currentEpoch + $configfile['questionTime'];
    $timerArray['triviaSystem_timeExpired'] = $expiryTime;

    //Load the answers from the topic.topic file in $triviaTopic
    //$triggers['answer'] = "triviaSystem_answerGiven";
    return true;
}

function triviaSystem_timeExpired($ircdata) {
    global $isActivityActive;

    $isActivityActive = false;
    sendPRIVMSG($ircdata['location'],"Time is up!");
}
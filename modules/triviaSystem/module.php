<?php
function triviaSystem_mainFunc($ircdata) {
    global $dbconnection;

    //Since this is the main function, we need to first determine where we are
    //in the game, and if we need to send the current call off to another function
    $arg = trim($ircdata['commandargs']);

    //Load all available trivia topics into array here based on the config file 
    $configfile = parse_ini_file("./modules/triviaSystem/module.conf");
    $topics = $configfile['topics'];

    if(!empty($arg)) {
        if(in_array($arg,$topics)) {
            $triviaTopic = $arg
        } else {
            //they passed an arg but its not a valid topic
            sendPRIVMSG($ircdata['location'], "That is not a valid topic. Not starting!")
            return true;
        }
    } else {
        //No arg was passed, topic will be random
        $randKey = array_rand($topics);
        $triviaTopic = $topics[$randKey];
    }

    logEntry("Trivia Starting with topic '".$triviaTopic."'");
    sendPRIVMSG($ircdata['location'], "Starting Trivia! The topic is: ".$triviaTopic."");
}
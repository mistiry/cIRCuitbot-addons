<?php
function googleGemini_generateTextByTextPrompt($ircdata) {
    global $activeActivityArray;
    global $timerArray;

    $geminiBanner = stylizeText("-- GEMINI --", "bold");
    $geminiBanner = stylizeText($geminiBanner, "color_light_blue");

    //Config file parsing
    $configfile = parse_ini_file("./modules/googleGemini/module.conf");
    $apiKey = $configfile['geminiAPIkey'];
    $activityName = $configfile['activityName'];

    //If this activity is already active, dont run again
    if(array_key_exists($activityName, $activeActivityArray)) {
        $currentEpoch = time();
        $expiryTime = $timerArray['googleGemini_timeoutExpired'];
        $timeRemaining = $expiryTime - $currentEpoch;
        sendPRIVMSG($ircdata['location'],"Sorry, it has not been long enough since the last use ($timeRemaining seconds remaining)");
        return true;
    } else {
        //Set this as active activity
        $activeActivityArray[$activityName] = "generateTextByTextPrompt";

        //timer stuff, only one prompt every X time
        $currentEpoch = time();
        $expiryTime = $currentEpoch + $configfile['timeBetweenRequests'];
        $timerArray['googleGemini_timeoutExpired'] = $expiryTime;

        //The user prompt
        $geminiPrompt = trim($ircdata['commandargs']);

        $apiURL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=".$apiKey."";
        $curl = curl_init($apiURL);
        $requestJson = "{\"contents\":[{\"parts\":[{\"text\": \"".$geminiPrompt."\"}]}]}";
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestJson);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, RETURNTRANSFER, true);
        $geminiResult = json_decode(curl_exec($curl), true);
        curl_close($curl);
        $geminiResponse = trim($geminiResult["candidates"][0]["content"]["parts"][0]["text"]);

        if(strlen($geminiResponse) > 5) {
            sendPRIVMSG($ircdata['location'], $geminiResponse);
        } else {
            sendPRIVMSG($ircdata['location'], "".$geminiBanner." Something happened.");
        }
        return true;
    }
}

function googleGemini_timeoutExpired($data) {
    global $activeActivityArray;
    global $timerArray;

    //Config file parsing
    $configfile = parse_ini_file("./modules/googleGemini/module.conf");
    $activityName = $configfile['activityName'];

    unset($activeActivityArray[$activityName]);
    unset($timerArray['googleGemini_timeoutExpired']);
    return true;
}
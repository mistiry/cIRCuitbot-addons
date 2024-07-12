<?php
function googleGemini_generateTextByTextPrompt($ircdata) {
    global $activeActivityArray;
    global $timerArray;
    global $options;

    $geminiBanner = stylizeText("-- GEMINI --", "bold");
    $geminiBanner = stylizeText($geminiBanner, "color_light_blue");

    //Config file parsing
    $configfile = parse_ini_file("".$config['addons_dir']."/modules/googleGemini/module.conf");
    $apiKey = $configfile['geminiAPIkey'];
    $activityName = $configfile['activityName'];

    //If this activity is already active, dont run again
    if(array_key_exists($activityName, $activeActivityArray)) {
        $currentEpoch = time();
        $expiryTime = $timerArray['googleGemini_timeoutExpired'];
        $timeRemaining = $expiryTime - $currentEpoch;
        $timeoutText1 = stylizeText(stylizeText("Uh oh!","color_yellow"), "bold");
        $timeoutText2 = stylizeText("Not enough time has elapsed since the last run. Time remaining:", "bold");
        $timeoutText3 = stylizeText(stylizeText("".$timeRemaining."s", "color_cyan"), "bold");
        sendPRIVMSG($ircdata['location'],"".$geminiBanner." ".$timeoutText1." ".$timeoutText2." ".$timeoutText3."");
        return true;
    } else {
        //Set this as active activity
        $activeActivityArray[$activityName] = "generateTextByTextPrompt";

        //timer stuff, only one prompt every X time
        $currentEpoch = time();
        $expiryTime = $currentEpoch + $configfile['timeBetweenRequests'];
        $timerArray['googleGemini_timeoutExpired'] = $expiryTime;

        //The user prompt
        $geminiPrompt = trim(urlencode($ircdata['commandargs']));

        $apiURL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=".$apiKey."";
        $curl = curl_init($apiURL);
        $requestJson = "{\"contents\":[{\"parts\":[{\"text\": \"".$geminiPrompt."\"}]}]}";
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=".$apiKey."",
            CURLOPT_POSTFIELDS => $requestJson,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => 1
        ));
        
        $geminiResultJson = curl_exec($curl);
        $geminiResult = json_decode($geminiResultJson, true);

        $geminiResponse = trim($geminiResult["candidates"][0]["content"]["parts"][0]["text"]);
        //$geminiResponse = str_replace("\n","  ",$geminiResponse);

        //Create random hash to save the generated results to custom HTML output
        $outputToSave = trim($geminiResult["candidates"][0]["content"]["parts"][0]["text"]);

        $savedOutput = googleGemini_saveGeneratedOutput($geminiPrompt,$outputToSave);

        if(strlen($geminiResponse) > 5) {
            $currentEpoch = time();
            $expiryTime = $timerArray['googleGemini_timeoutExpired'];
            $timeRemaining = $expiryTime - $currentEpoch;
            $successText1 = stylizeText(stylizeText("Success!","color_light_green"), "bold");
            $successText2 = stylizeText("(next run available in ".$timeRemaining."s) - check out my response at", "bold");
            $successText3 = stylizeText(stylizeText("".$configfile['baseOutputUrl']."/view.php?gen=".$savedOutput."", "color_blue"), "bold");
            sendPRIVMSG($ircdata['location'], "".$geminiBanner." ".$successText1." ".$successText2." ".$successText3."");
        } else {
            $failText = stylizeText(stylizeText("Something Happened!","color_red"), "bold");
            sendPRIVMSG($ircdata['location'], "".$geminiBanner." ".$failText."");
        }
        curl_close($curl);
        return true;
    }
}

function googleGemini_saveGeneratedOutput($prompt,$output) {
    $filepath = "/var/www/html/gemini";
    $rand = rand();
    $rand = sha1($rand);
    $fullnewfile = "".$filepath."/".$rand.".geminiresult";
    file_put_contents($fullnewfile, "prompt:".$prompt."\n\n");
    file_put_contents($fullnewfile, $output, FILE_APPEND);
    return $rand;
}

function googleGemini_timeoutExpired($data) {
    global $activeActivityArray;
    global $timerArray;
    global $config;

    //Config file parsing
    $configfile = parse_ini_file("".$config['addons_dir']."/modules/googleGemini/module.conf");
    $activityName = $configfile['activityName'];

    unset($activeActivityArray[$activityName]);
    unset($timerArray['googleGemini_timeoutExpired']);
    return true;
}
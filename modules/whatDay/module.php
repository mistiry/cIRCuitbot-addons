<?php
function whatDay($data) {
    global $config;

    $dayData = parse_ini_file("".$config['addons_dir']."/modules/whatDay/module.conf");
    $today = date('l');
    switch($today) {
        case "Sunday":
            $textArray = $dayData['sundayText'];
            $linkArray = $dayData['sundayLink'];
            break;
        case "Monday":
            $textArray = $dayData['mondayText'];
            $linkArray = $dayData['mondayLink'];
            break;
        case "Tuesday":
            $textArray = $dayData['tuesdayText'];
            $linkArray = $dayData['tuesdayLink'];
            break;
        case "Wednesday":
            $textArray = $dayData['wednesdayText'];
            $linkArray = $dayData['wednesdayLink'];
            break;
        case "Thursday":
            $textArray = $dayData['thursdayText'];
            $linkArray = $dayData['thursdayLink'];
            break;
        case "Friday":
            $textArray = $dayData['fridayText'];
            $linkArray = $dayData['fridayLink'];
            break;
        case "Saturday":
            $textArray = $dayData['saturdayText'];
            $linkArray = $dayData['saturdayLink'];
            break;
    }

    $textList = array();
    $linkList = array();

    foreach($textArray as $text) {
        array_push($textList,$text);
    }
    foreach($linkArray as $link) {
        array_push($linkList,$link);
    }

    $randKeyText = array_rand($textList);
    $randKeyLink = array_rand($linkList);
    $text = $textList[$randKeyText];
    $link = $linkList[$randKeyLink];
    $message = "".$data['usernickname'].": ".$text." - ".$link."";
    sendPRIVMSG($data['location'],$message);
    return true;
}
<?php
function whatDay($data) {
    $dayData = parse_ini_file("./modules/whatDay/module.conf");
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
    $randKeyText = array_rand($textArray);
    $randKeyLink = array_rand($linkArray);
    $text = $textArray($randKeyText);
    $link = $linkArray($randKeyLink);
    $message = "".$data['usernickname'].": ".$text." - ".$link."";
    sendPRIVMSG($data['location'],$message);
    return true;
}
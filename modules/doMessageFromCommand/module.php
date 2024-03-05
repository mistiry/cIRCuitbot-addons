<?php
function getFirstWordFromCommand($ircdata) {
    global $config;
    global $firstword;

    $firstword = trim(str_replace($config['command_flag'],"",$firstword));

    //Debug check that the $firstword is alphanumeric
    // $pattern = "/^(\w)+/";
    // $isalpha = preg_match($pattern,$firstword);
    // echo "\nFirst word is alpha? ".$isalpha."\n";

    $firstword = preg_replace("/[^[:alnum:][:space:]]/u", '', $firstword);
    $firstword = preg_replace("/[^A-Za-z0-9 ]/", '', $firstword);

    return $firstword;
}

function replyNoTag($ircdata) {
    $commandGiven = getFirstWordFromCommand($ircdata);
    $options = parse_ini_file("./modules/doMessageFromCommand/".$commandGiven.".conf");
    $reply = $options['reply'];
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    return true;
}

function replyWithTag($ircdata) {
    $commandGiven = getFirstWordFromCommand($ircdata);
    $options = parse_ini_file("./modules/doMessageFromCommand/".$commandGiven.".conf");
    $reply = $options['reply'];
    $reply = "".$ircdata['usernickname']." - ".$reply."";
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    return true;
}

function replyRandomNoTag($ircdata) {
    $commandGiven = getFirstWordFromCommand($ircdata);
    $options = parse_ini_file("./modules/doMessageFromCommand/".$commandGiven.".conf");
    $replies = $options['replies'];
    $replyArray = array();
    foreach($replies as $reply) {
        array_push($replyArray,$reply);
    }
    $randkey = array_rand($replyArray);
    $reply = $replyArray[$randkey];
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    $replyArray = "";
    $randkey = "";
    return true;
}

function replyRandomWithTag($ircdata) {
    $commandGiven = getFirstWordFromCommand($ircdata);

    $inifile = "./modules/doMessageFromCommand/".$commandGiven.".conf";
    echo "\nINI File is '".$inifile."'\n";
    $options = parse_ini_file($inifile);
    $replies = $options['replies'];
    $replyArray = array();
    foreach($replies as $reply) {
        array_push($replyArray,$reply);
    }
    $randkey = array_rand($replyArray);
    $reply = $replyArray[$randkey];
    $reply = "".$ircdata['usernickname']." - ".$reply."";
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    $replyArray = "";
    $randkey = "";
    return true;
}

function replyTagOtherUser($ircdata) {
    $argpieces = explode(" ",$ircdata['commandargs']);
    $userToTag = trim($argpieces[0]);
    $userKnown = isKnownUser($userToTag);

    if($userKnown == "true" && strlen($userToTag)>1) {
        $commandGiven = getFirstWordFromCommand($ircdata);
        $options = parse_ini_file("./modules/doMessageFromCommand/".$commandGiven.".conf");
        $reply = $options['reply'];
        $reply = "".$userToTag." - ".$reply."";
        sendPRIVMSG($ircdata['location'], $reply);
        $options = "";
        $reply = "";
        return true;
    } else {
        $options = "";
        $reply = "";
        return false;
    }
}

function replyRandomTagOtherUser($ircdata) {
    $argpieces = explode(" ",$ircdata['commandargs']);
    $userToTag = $argpieces[0];
    $userKnown = isKnownUser($userToTag);

    if($userKnown == "true" && strlen($userToTag)>1) {
        $commandGiven = getFirstWordFromCommand($ircdata);
        $options = parse_ini_file("./modules/doMessageFromCommand/".$commandGiven.".conf");
        $replies = $options['replies'];
        $replyArray = array();
        foreach($replies as $reply) {
            array_push($replyArray,$reply);
        }
        $randkey = array_rand($replyArray);
        $reply = $replyArray[$randkey];
        $reply = "".$userToTag." - ".$reply."";
        sendPRIVMSG($ircdata['location'], $reply);
        $options = "";
        $reply = "";
        $replyArray = "";
        $randkey = "";
        return true;
    } else {
        $options = "";
        $reply = "";
        $replyArray = "";
        $randkey = "";
        return false;
    }
}

function isKnownUser($user) {
    global $dbconnection;
    $query = "SELECT id FROM known_users WHERE nick_aliases LIKE '%".$user."%' LIMIT 1";
    $result = mysqli_query($dbconnection, $query);
    if(mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            if(is_numeric($row['id'])) {
                $return = "true";
            }
        }
    } else {
        $return = "false";
    }
    return $return;
}
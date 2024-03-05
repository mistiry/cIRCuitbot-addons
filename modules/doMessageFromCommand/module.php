<?php
function getFirstWordFromCommand($ircdata) {
    global $config;
    // if($config['bridge_enabled'] == true && $ircdata['usernickname'] == $config['bridge_username']) {
    //     $bridgeMessage = trim($ircdata['fullmessage']);
    //     $bridgeMessage = trim(str_replace("".$config['bridge_left_delimeter']."".$bridgeUser."".$config['bridge_right_delimeter']."","",$bridgeMessage));
    //     logEntry("Bridge message after username replace was: \"".$bridgeMessage."\"");
    //     $bridgeMessagePieces = explode(" ",$bridgeMessage);
    //     logEntry("Bridge message pieces:");
    //     foreach($bridgeMessagePieces as $piece) {
    //         logEntry("    $piece");
    //     }
    //     $firstword = trim(strval($bridgeMessagePieces[1]));
    //     $firstword = preg_replace('[^\w\d\!]', '', $firstword);
    //     logEntry("Bridge message firstword was: \"".$firstword."\"");
    // } else {
    //     $messagearray = $ircdata['messagearray'];
    //     $firstword = trim($messagearray[1]);    
    // }

    $messagePieces = explode(" ",$ircdata['fullmessage']);
    $firstword = trim(strval($messagePieces[0]));
    $firstword = trim(str_replace($config['command_flag'],"",$firstword));
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
    echo "INI File is '".$inifile."'";
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
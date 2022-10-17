<?php
function replyNoTag($ircdata) {
    $options = parse_ini_file("./modules/doMessageFromCommand/module.conf");
    $reply = $options['reply'];
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    return true;
}

function replyWithTag($ircdata) {
    $options = parse_ini_file("./modules/doMessageFromCommand/module.conf");
    $reply = $options['reply'];
    $reply = "".$ircdata['usernickname'].": ".$reply."";
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    return true;
}

function replyRandomNoTag($ircdata) {
    $options = parse_ini_file("./modules/doMessageFromCommand/module.conf");
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
    $options = parse_ini_file("./modules/doMessageFromCommand/module.conf");
    $replies = $options['replies'];
    $replyArray = array();
    foreach($replies as $reply) {
        array_push($replyArray,$reply);
    }
    $randkey = array_rand($replyArray);
    $reply = $replyArray[$randkey];
    $reply = "".$ircdata['usernickname'].": ".$reply."";
    sendPRIVMSG($ircdata['location'], $reply);
    $options = "";
    $reply = "";
    $replyArray = "";
    $randkey = "";
    return true;
}

function replyTagOtherUser($ircdata) {
    $argpieces = explode(" ",$ircdata['commandargs']);
    $userToTag = $argpieces[0];
    $userKnown = isKnownUser($userToTag);

    if($userKnown == "true" && strlen($userToTag)>1) {
        $options = parse_ini_file("./modules/doMessageFromCommand/module.conf");
        $reply = $options['reply'];
        $reply = "".$userToTag.": ".$reply."";
        $options = "";
        $reply = "";
        sendPRIVMSG($ircdata['location'], $reply);
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
        $options = parse_ini_file("./modules/doMessageFromCommand/module.conf");
        $replies = $options['replies'];
        $replyArray = array();
        foreach($replies as $reply) {
            array_push($replyArray,$reply);
        }
        $randkey = array_rand($replyArray);
        $reply = $replyArray[$randkey];
        $reply = "".$userToTag.": ".$reply."";
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
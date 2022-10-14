<?php
function opCommandUserMode($data) {
    $botflags = getBotFlags($ircdata['usernickname']);
    if($botflags == "A" || $botflags == "O") {
        $messagearray = $ircdata['messagearray'];
        $firstword = trim($messagearray[1]);
        $user = $ircdata['commandargs'];
    
        //If they didnt give a user, then they are trying to give themselves the mode
        if($user == "") {
            $user = $ircdata['usernickname'];
        }

        switch($firstword) {
            case "!op":
                setMode("+","o",$user);
                break;
            case "!deop":
                setMode("-","o",$user);
                break;
            case "!v":
            case "!voice":
                setMode("+","v",$user);
                break;
            case "!dv":
            case "!devoice":
                setMode("-","v",$user);
                break;
            case "!q":
            case "!quiet":
                setMode("+","q",$user);
                break;
            case "!uq":
            case "!unquiet":
                setMode("-","q",$user);
                break;
        }
        logEntry("Admin user '".$ircdata['usernickname']."@".$ircdata['userhostname']."' requested '".$firstword."".$user."'");
    } else {
        logEntry("Denied non-admin user '".$ircdata['usernickname']."@".$ircdata['userhostname']."' requesting '".$firstword."".$user."'");
    }
    return true;
}
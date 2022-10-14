<?php
function opCommandUserMode($data) {
    echo "got data:\n";
    print_r($data);
    
    $botflags = getBotFlags($ircdata['user_nickname']);
    if($botflags == "A" || $botflags == "O") {
        $messagearray = $ircdata['messagearray'];
        $firstword = trim($messagearray[1]);
        $user = $ircdata['commandargs'];
    
        //If they didnt give a user, then they are trying to give themselves the mode
        if($user == "") {
            $user = $ircdata['user_nickname'];
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
        logEntry("Admin user '".$ircdata['user_nickname']."@".$ircdata['user_hostname']."' requested '".$firstword."".$user."'");
    } else {
        logEntry("Denied non-admin user '".$ircdata['user_nickname']."@".$ircdata['user_hostname']."' requesting '".$firstword."".$user."'");
    }
    return true;
}
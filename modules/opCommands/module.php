<?php
function opCommandUserMode($data) {
    echo "got data:\n";
    print_r($data);

    $botflags = getBotFlags($data['usernickname']);
    if($botflags == "A" || $botflags == "O") {
        $messagearray = $data['messagearray'];
        $firstword = trim($messagearray[1]);
        $user = $data['commandargs'];
    
        //If they didnt give a user, then they are trying to give themselves the mode
        if($user == "") {
            $user = $data['usernickname'];
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
        logEntry("Admin user '".$data['usernickname']."@".$data['userhostname']."' requested '".$firstword."".$user."'");
    } else {
        logEntry("Denied non-admin user '".$data['usernickname']."@".$data['userhostname']."' requesting '".$firstword."".$user."'");
    }
    return true;
}
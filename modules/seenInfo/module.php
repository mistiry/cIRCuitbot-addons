<?php
function getSeenInfo($ircdata) {
    global $dbconnection;

    $whoDisplay = trim($ircdata['commandargs']);

    if (strlen($whoDisplay) < 2) {
        sendPRIVMSG($ircdata['location'], "Please provide at least 2 characters to search.");
        return true;
    }

    $whoQuery = mysqli_real_escape_string($dbconnection, $whoDisplay);
    $whoQuery = str_replace(['%', '_'], ['\\%', '\\_'], $whoQuery);
    $query = "SELECT hostname,last_datatype,last_message,last_location,timestamp FROM known_users WHERE nick_aliases LIKE '%$whoQuery%' ORDER BY timestamp DESC LIMIT 1";

    $result = mysqli_query($dbconnection,$query);

    if(mysqli_num_rows($result) > 0) {
        if(mysqli_num_rows($result) > 1) {
            $message = "This is odd, it seems that your search returned multiple results. I can't show you seen data at this time - sorry!";
        }
        if(mysqli_num_rows($result) == 1) {
            while($row = mysqli_fetch_assoc($result)) {
                $hostname = $row['hostname'];
                $lastdatatype = $row['last_datatype'];
                $lastmessage = $row['last_message'];
                $last_location = $row['last_location'];
                $timelastseen = $row['timestamp'];

                switch($lastdatatype) {
                    case "PRIVMSG":
                        $message = "User '".$whoDisplay."' was last seen on ".$timelastseen." using hostname '".$hostname."' saying \"".$lastmessage."\"";
                        break;
                    case "QUIT":
                        $message = "User '".$whoDisplay."' was last seen on ".$timelastseen." using hostname '".$hostname."' quitting the channel.";
                        break;
                    case "JOIN":
                        $message = "User '".$whoDisplay."' was last seen on ".$timelastseen." using hostname '".$hostname."' joining the channel.";
                        break;
                }
            }
        }
    } else {
        $message = "Unable to find that user.";
    }
    sendPRIVMSG($ircdata['location'], $message);
    return true;
}
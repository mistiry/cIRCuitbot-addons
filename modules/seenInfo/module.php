<?php
function getSeenInfo($ircdata) {
    global $dbconnection;

    $who = trim($ircdata['commandargs']);
    $who = mysqli_real_escape_string($dbconnection, $who);
    $query = "SELECT hostname,last_datatype,last_message,last_location,timestamp FROM known_users WHERE nick_aliases LIKE '%$who%' ORDER BY timestamp DESC LIMIT 1";

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
                        $message = "User '".$who."' was last seen on ".$timelastseen." using hostname '".$hostname."' saying \"".$lastmessage."\"";
                        break;
                    case "QUIT":
                        $message = "User '".$who."' was last seen on ".$timelastseen." using hostname '".$hostname."' quitting the channel.";
                        break;
                    case "JOIN":
                        $message = "User '".$who."' was last seen on ".$timelastseen." using hostname '".$hostname."' joining the channel.";
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
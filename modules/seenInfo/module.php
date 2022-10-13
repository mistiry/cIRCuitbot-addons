<?php
function getSeenInfo($ircdata) {
    global $dbconnection;

    $who = trim($ircdata['commandargs']);
    $who = mysqli_real_escape_string($dbconnection, $who);
    $query = "SELECT hostname,last_datatype,last_message,last_location,timestamp FROM known_users WHERE nick_aliases LIKE '%$who%' ORDER BY timestamp DESC LIMIT 1";

    echo "running query ".$query."\n";

    $result = mysqli_query($dbconnection,$query);
                
    if(mysqli_num_rows($result) > 0) {
        if(mysqli_num_rows($result) > 1) {
            $return = "This is odd, it seems that your search returned multiple results. I can't show you seen data at this time - sorry!";
        }
        if(mysqli_num_rows($result) == 1) {
            while($row = mysqli_fetch_assoc($result)) {
                $hostname = $row['hostname'];
                $lastdatatype = $row['last_datatype'];
                $lastmessage = $row['last_message'];
                $last_location = $row['last_location'];
                $timelastseen = $row['timestamp'];

                print_r($row);

                switch($lastdatatype) {
                    case "PRIVMSG":
                        $return = "User '".$who."' was last seen on ".$timelastseen." using hostname '".$hostname."' saying \"".$lastmessage."\"";
                        break;
                    case "QUIT":
                        $return = "User '".$who."' was last seen on ".$timelastseen." using hostname '".$hostname."' quitting the channel.";
                        break;
                    case "JOIN":
                        $return = "User '".$who."' was last seen on ".$timelastseen." using hostname '".$hostname."' joining the channel.";
                        break;
                }
            }
        }
    } else {
        $return = "Unable to find that user.";
    }
    return $return;
}
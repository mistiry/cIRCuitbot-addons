<?php
function getStatsInfo($ircdata) {
    global $dbconnection;

    $who = trim($ircdata['usernickname']);
    $who = mysqli_real_escape_string($dbconnection, $who);
    $query = "SELECT id,nick_aliases,total_words,total_lines FROM known_users WHERE hostname = '".$ircdata['userhostname']."' LIMIT 1";

    $result = mysqli_query($dbconnection,$query);
    $nickaliases = array();

    if(mysqli_num_rows($result) > 0) {
        if(mysqli_num_rows($result) > 1) {
            $message = "This is odd, it seems that your search returned multiple results. I can't show you stats data at this time - sorry!";
        }
        if(mysqli_num_rows($result) == 1) {
            while($row = mysqli_fetch_assoc($result)) {
                $id = $row['id'];
                $nickaliases = unserialize($row['nick_aliases']);
                $totalwords = $row['total_words'];
                $totallines = $row['total_lines'];
                $nickcount = count($nickaliases);
                $totalnicks = $nickcount - 1;
            }

            $message = "".$ircdata['usernickname']." - I know you by ".$totalnicks." other nicknames. You are user #".$id." and have spoken ".$totalwords." words in ".$totallines." lines.";
        }
    } else {
        $message = "Unable to retrieve your stats at this time.";
    }
    sendPRIVMSG($ircdata['location'], $message);
    return true;
}
function getStatsInfoTop($ircdata) {
    global $dbconnection;

    $query = "SELECT nick_aliases,total_words,total_lines FROM known_users ORDER BY total_words DESC LIMIT 10";
    $result = mysqli_query($dbconnection,$query);
    $message = "";
    sendPRIVMSG($ircdata['location'], "** Top 10 Users by Total Words (shown as words/lines)**");
    if(mysqli_num_rows($result) > 0) {
        $count = 1;
        while($row = mysqli_fetch_assoc($result)) {
            $nickaliases = unserialize($row['nick_aliases']);
            $UserAlias = $nickaliases[0];
            $totalwords = number_format($row['total_words']);
            $totallines = number_format($row['total_lines']);

            $message .= "".$count.". ".$UserAlias." (".$totalwords."/".$totallines.") ";
            $count++;
        }
    } else {
        $message = "Unable to retrieve top stats at this time.";
    }
    sendPRIVMSG($ircdata['location'], $message);
    return true;
}
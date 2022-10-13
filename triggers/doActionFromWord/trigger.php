<?php
function doActionFromWord($data) {
    //We need to pull in the socket object so we can directly send an ACTION
    //with the proper control characters.
    global $socket;
    global $ircdata;

    $options = parse_ini_file("./triggers/doActionFromWord/trigger.conf");
    if($options['action'] != "") {
        fputs($socket, "PRIVMSG ".$ircdata['location']." :\001ACTION ".$options['action']."\001\n");
    }

    return true;
}
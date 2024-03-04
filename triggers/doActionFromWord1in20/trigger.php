<?php
function doActionFromWord1in20($ircdata) {
    //We need to pull in the socket object so we can directly send an ACTION
    //with the proper control characters.
    global $socket;
    global $ircdata;

    $options = parse_ini_file("./triggers/doActionFromWord/trigger.conf");

    if(rand(0,19) == "4") {
        if($options['action'] != "") {
            fputs($socket, "PRIVMSG ".$ircdata['location']." :\001ACTION ".$options['action']."\001\n");
        }
    }
    return true;
}
<?php
function doActionFromCommand($ircdata) {
    //We need to pull in the socket object so we can directly send an ACTION
    //with the proper control characters.
    global $socket;
    global $config;

    $options = parse_ini_file("".$config['addons_dir']."/modules/doActionFromCommand/module.conf");

    //Determine what to send
    switch($options['action']) {
        case "%COMMANDARGS%":
            if($ircdata['commandargs'] != "") {
                $action = str_replace(["\r", "\n", "\0"], '', $ircdata['commandargs']);
                fputs($socket, "PRIVMSG ".$ircdata['location']." :\001ACTION ".$action."\001\r\n");
            }
            break;
        case "%USERNICKNAME%":
            fputs($socket, "PRIVMSG ".$ircdata['location']." :\001ACTION ".$ircdata['usernickname']."\001\r\n");
            break;
        default:
            fputs($socket, "PRIVMSG ".$ircdata['location']." :\001ACTION ".$options['action']."\001\r\n");
    }
    return true;
}
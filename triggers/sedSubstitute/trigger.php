<?php
$sedBuffer = [];

function sedSubstitute($ircdata) {
    global $sedBuffer;
    global $config;

    static $bufferSize = null;
    if($bufferSize === null) {
        $configfile = parse_ini_file($config['addons_dir']."/triggers/sedSubstitute/trigger.conf");
        $bufferSize = (int)($configfile['bufferSize'] ?? 20);
    }

    $message = $ircdata['fullmessage'];
    $nick = $ircdata['usernickname'];

    // Detect s/pattern/replacement or s/pattern/replacement/
    if(substr($message, 0, 2) === 's/' && substr_count($message, '/') >= 2) {
        $parts = explode('/', $message, 4);
        $search = $parts[1] ?? '';
        $replace = $parts[2] ?? '';

        if($search === '') {
            return true;
        }

        for($i = count($sedBuffer) - 1; $i >= 0; $i--) {
            if(strstr($sedBuffer[$i]['message'], $search) !== false) {
                $result = str_replace($search, $replace, $sedBuffer[$i]['message']);
                sendPRIVMSG($ircdata['location'], $sedBuffer[$i]['nick'] . " meant: \"" . $result . "\"");
                break;
            }
        }
        return true;
    }

    // Not a sed command — add to buffer, skip CTCP messages (actions, etc.)
    if(substr($message, 0, 1) === "\x01") {
        return true;
    }
    $sedBuffer[] = ['nick' => $nick, 'message' => $message];
    if(count($sedBuffer) > $bufferSize) {
        $sedBuffer = array_slice($sedBuffer, -$bufferSize);
    }

    return true;
}

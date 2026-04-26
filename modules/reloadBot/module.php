<?php
function reloadBot_restart($ircdata) {
    $flags = getBotFlags($ircdata['userhostname']);
    if($flags !== "O" && $flags !== "A") {
        return true;
    }
    sendPRIVMSG($ircdata['location'], "Restarting...");
    exit(0);
}

function reloadBot_update($ircdata) {
    global $config;
    $flags = getBotFlags($ircdata['userhostname']);
    if($flags !== "O" && $flags !== "A") {
        return true;
    }

    exec("git -C " . escapeshellarg(getcwd()) . " pull 2>&1", $coreOut);
    exec("git -C " . escapeshellarg($config['addons_dir']) . " pull 2>&1", $addonsOut);

    $coreLine = trim($coreOut[0] ?? "No output");
    $addonsLine = trim($addonsOut[0] ?? "No output");

    sendPRIVMSG($ircdata['location'], "Core: " . $coreLine);
    sendPRIVMSG($ircdata['location'], "Addons: " . $addonsLine);
    sendPRIVMSG($ircdata['location'], "Restarting...");
    exit(0);
}

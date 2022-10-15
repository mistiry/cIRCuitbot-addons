<?php
function gitGud($data) {
    global $dbconnection;

    $usertogitgud = trim($data['commandargs']);
    $usertogitgud = mysqli_real_escape_string($dbconnection,$usertogitgud);
    $query = "SELECT id FROM known_users WHERE nick_aliases LIKE '%$usertogitgud%'";
    $result = mysqli_query($dbconnection,$query);
    if(mysqli_num_rows($result) > 0) {
        $ggarray = array(
            'Will you PLEASE',
            'Honestly you just need to',
            'ffs will you',
            'You need to',
            'OMG please'
        );
        $ggarraykey = array_rand($ggarray);
        $message = $ggarray[$ggarraykey];
        sendPRIVMSG($location, "".$usertogitgud.": ".$message."");
        usleep(rand(100,500));
        sendPRIVMSG($location, "`   ┌─┐┬┌┬┐  ┌─┐┬ ┬┌┬┐  `");
        usleep(rand(100,500));
        sendPRIVMSG($location, "`   │ ┬│ │   │ ┬│ │ ││  `");
        usleep(rand(100,500));
        sendPRIVMSG($location, "`   └─┘┴ ┴   └─┘└─┘─┴┘  `");
        return true;
    }
}
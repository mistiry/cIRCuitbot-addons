<?php
function parseURLfromMessage($ircdata) {
    global $config;

    if(stristr($ircdata['fullmessage'], "https://")) {
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#i', $ircdata['fullmessage'], $urlmatch);
        logEntry("Found URL in message: ".$urlmatch[0]."");
        foreach($urlmatch[0] as $url) {
            $urltitle = trim(getTitle($url));
            $urltitle = urldecode($urltitle);
            $urltitle = html_entity_decode($urltitle);
            $urltitle = preg_replace('/\r\n/','', $urltitle);
            $urltitle = preg_replace('/\r/','', $urltitle);
            $urltitle = preg_replace('/\n/','', $urltitle);
            $urltitle = preg_replace('/\n\r/','', $urltitle);
            logEntry("URL Title extracted: ".$urltitle."");
            if(strlen($urltitle)>5 && strlen($urltitle)<450) {
                $message = "".$ircdata['usernickname']." - URL Title - ".$urltitle."";
                sendPRIVMSG($config['channel'], "".$message."");                        
            }
        }
    }
    if(stristr($ircdata['fullmessage'], "http://")) {
        preg_match_all('#\bhttp?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#i', $ircdata['fullmessage'], $urlmatch);
        logEntry("Found URL in message: ".$urlmatch[0]."");
        foreach($urlmatch[0] as $url) {
            $urltitle = trim(getTitle($url));
            $urltitle = urldecode($urltitle);
            $urltitle = html_entity_decode($urltitle);
            $urltitle = preg_replace('/\r\n/','', $urltitle);
            $urltitle = preg_replace('/\r/','', $urltitle);
            $urltitle = preg_replace('/\n/','', $urltitle);
            $urltitle = preg_replace('/\n\r/','', $urltitle);
            logEntry("URL Title extracted: ".$urltitle."");
            if(strlen($urltitle)>5 && strlen($urltitle)<450) {
                $message = "".$ircdata['usernickname']." - URL Title - ".$urltitle."";
                sendPRIVMSG($config['channel'], "".$message."");                        
            }
        }
    }
    return true;
}

function getTitle($url) {
    $badExtensions = array(
        '.exe',
        '.pdf',
        '.sh',
        '.py',
        '.pl',
        '.tcl',
        '.bat',
    );
    
    foreach($badExtensions as $filecheck) {
        if(stristr($url,$filecheck)) {
            $title = "Unable to parse URL that points to a ".$filecheck." file.";
            return $title;
        }
    }

    $page = file_get_contents(trim($url), NULL, NULL, NULL, 262144);
    $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $page, $match) ? $match[1] : null;
    return $title;
}
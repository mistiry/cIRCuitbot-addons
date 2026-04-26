<?php
function doGoogleSearch($data) {
    $search = trim($data['commandargs']);
    $baseurl = "https://www.google.com/search?q=";
    $searchterm = myUrlEncode($search);
    $searchurl = "".$baseurl."".$searchterm."";

    if($search == "") {
        return false;
    } else {
        //get some values
        $ch = curl_init($searchurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $rawhtml = curl_exec($ch);
        curl_close($ch);
        $urlRaw = get_string_between($rawhtml, "/url?q=", "\">");
        if(empty($urlRaw)) {
            sendPRIVMSG($data['location'],"".$data['usernickname']." - No results found.");
            return true;
        }
        $urlRaw = urldecode($urlRaw);
        $urlRaw = str_replace("&amp;","&",$urlRaw);
        $urlParts = explode("&sa",$urlRaw);
        $url2 = $urlParts[0];
        $title = getURLTitle($url2);

        sendPRIVMSG($data['location'],"".$data['usernickname']." - ".$title." - ".$url2."");
        return true;
    }
}

function myUrlEncode($string) {
    $entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
    $replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]");
    return str_replace($entities, $replacements, urlencode($string));
}

function getURLTitle($url) {
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
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, trim($url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $page = curl_exec($ch);
    curl_close($ch);
    if (!$page) { return null; }
    $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $page, $match) ? $match[1] : null;
    return $title;
}

function get_string_between($string, $start, $end) {
    $startPos = strpos($string, $start);
    if ($startPos === false) { return ""; }
    $startPos += strlen($start);
    $endPos = strpos($string, $end, $startPos);
    if ($endPos === false) { return ""; }
    return substr($string, $startPos, $endPos - $startPos);
}
<?php
function doGoogleSearch($data) {
    $search = trim($data['commandargs']);
    $baseurl = "https://www.google.com/search?q=";
    $searchterm = myUrlEncode($search);
    $searchurl = "".$baseurl."".$searchterm."";

    //get some values
    $randval = rand(00000000,99999999);
    $tempfile = ".googlesearch_".$randval."";
    exec("lynx -source ".$searchurl." > ".$tempfile."");
    $rawhtml = exec("cat ".$tempfile."");
    $htmlpieces = explode("?q",$rawhtml);
    $url = get_string_between($rawhtml,"/url?q=","\">");
    $url = urldecode($url);
    $url = str_replace("&amp;","&",$url);
    $url = explode("&sa",$url);
    $url2 = $url[0];
    $title = getTitle($url2);

    sendPRIVMSG($data['location'],"".$data['usernickname']." - ".$title." - ".$url2."");
    exec("rm -f ".$tempfile."");
    return true;
}

function myUrlEncode($string) {
    $entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
    $replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]");
    return str_replace($entities, $replacements, urlencode($string));
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
    $page = file_get_contents(trim($url));
    $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $page, $match) ? $match[1] : null;
    return $title;
}
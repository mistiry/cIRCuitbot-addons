<?php
function parseURLfromMessage($ircdata) {
    global $config;

    if(stristr($ircdata['fullmessage'], "https://") || stristr($ircdata['fullmessage'], "http://")) {
        $messagePieces = explode(" ", $ircdata['fullmessage']);
        foreach($messagePieces as $piece) {
            if(stristr($piece,"http")) {
                $url = $piece;
            }
        }

        logEntry("Found URL in message: ".$url."");
        $urltitle = trim(getTitle($url));
        logEntry("URL Title extracted: ".$urltitle."");
        if(strlen($urltitle)>5 && strlen($urltitle)<450) {
            $urlBanner = stylizeText("-- URL --", "bold");
            $urlBanner = stylizeText($urlBanner, "color_purple");
            $message = "".$urlBanner." ".$urltitle."";
            sendPRIVMSG($config['channel'], "".$message."");                        
        }
    }

    return true;
}

function getTitle($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    } else {
        //Get info to determine extension
        $parsedUrl = parse_url($url);
        $extension = pathinfo($parsedUrl['path'], PATHINFO_EXTENSION);
        
        // List of disallowed file extensions
        $disallowedExtensions = ['pdf', 'exe', 'sh', 'cmd', 'js', 'py', 'bat', 'pl', 'rb', 'ps1', 'vbs', 'msi', 'tcl'];
        if (in_array(strtolower($extension), $disallowedExtensions)) {
            $message = "Unable to parse URL that points to ".$extension." file.";
            return $message;
        }

        // List of disallowed domains, useful if using something like the parseYoutubeURL trigger
        $disallowedDomains = ['xyoutube.com'];
        foreach($disallowedDomains as $domainCheck) {
            if(stristr($url, $domainCheck)) {
                logEntry("Domain is disallowed.");
                return false;
            }
        }
    
        // Use cURL to fetch the content of the webpage
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        // curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a timeout for the request
        // curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        // $html = curl_exec($ch);
        // if (curl_errno($ch)) {
        //     logEntry(curl_error($ch));
        //     return false;
        // }
        // curl_close($ch);

        $html = file_get_contents($url);

        // Extract the title from the HTML
        $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $html, $match) ? $match[1] : null;
        $title = trim($title);
        return $title;

        // if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $html, $matches)) {
        //     $title = $matches[1];
        //     print_r($matches);
        //     echo $title; 
        //     // Trim and strip non-printable characters
        //     $title = trim($title);
        //     //$title = preg_replace('/[\x00-\x1F\x7F]/u', '', $title);
        //     return $title;
        // } else {
        //     return false;
        // }
    }
}
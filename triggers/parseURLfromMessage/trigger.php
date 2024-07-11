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
            $message = "".$ircdata['usernickname']." - URL Title - ".$urltitle."";
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
        
        // Use cURL to fetch the content of the webpage
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a timeout for the request
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        $html = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        curl_close($ch);

        // Extract the title from the HTML
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = $matches[1];
            // Trim and strip non-printable characters
            $title = trim($title);
            $title = preg_replace('/[\x00-\x1F\x7F]/u', '', $title);
            return $title;
        } else {
            return false;
        }
    }
}
<?php
function parseURLfromMessage($ircdata) {
    global $config;

    //Config file parsing
    $configfile = parse_ini_file("".$config['addons_dir']."/triggers/parseURLfromMessage/trigger.conf");
    $parseYouTube = $configfile['parseyoutube'];
    $youtubeAPIKey = $configfile['youtubeAPIKey'];

    if(stristr($ircdata['fullmessage'], "https://") || stristr($ircdata['fullmessage'], "http://")) {
        $messagePieces = explode(" ", $ircdata['fullmessage']);
        foreach($messagePieces as $piece) {
            if(stristr($piece,"http")) {
                $url = $piece;
            }
        }
        logEntry("Found URL in message: ".$url."");

        if($parseYouTube == "true") {
            if(stristr($url, "youtube.com") || stristr($url, "yt.com")) {
                getYouTubeInfo($youtubeAPIKey, $url);
            }
        } else {
            $urltitle = trim(getTitle($url));
            logEntry("URL Title extracted: ".$urltitle."");
            if(strlen($urltitle)>5 && strlen($urltitle)<450) {
                $urlBanner = stylizeText("-- URL --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_purple");
                $message = "".$urlBanner." ".$urltitle."";
                sendPRIVMSG($config['channel'], "".$message."");                        
            }
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

        $html = file_get_contents(trim($url), NULL, NULL, NULL, 524288);

        // Extract the title from the HTML
        $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $html, $match) ? $match[1] : null;
        $title = trim($title);
        return $title;
    }
}

function getYouTubeInfo($youtubeAPIKey, $url) {
    global $config;

    // Extract the video ID from the URL
    parse_str(parse_url($url, PHP_URL_QUERY), $urlParams);
    $videoId = $urlParams['v'];
    logEntry("YouTube Video ID from URL: ".$videoId."");

    // API endpoint to get video details
    $apiUrl = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&part=snippet,contentDetails&key={$youtubeAPIKey}";
    logEntry("Retrieving Video from API Call to ".$apiUrl."");

    // Get the response from the API
    $response = file_get_contents($apiUrl);
    $videoData = json_decode($response, true);

    if (isset($videoData['items']) && count($videoData['items']) > 0) {
        $videoInfo = $videoData['items'][0];

        // Extract title, channel name, and duration
        $title = stylizeText($videoInfo['snippet']['title'], "bold");
        $channelName = stylizeText($videoInfo['snippet']['channelTitle'], "color_cyan");
        $channelName = stylizeText($channelName, "bold");
        $duration = formatDuration($videoInfo['contentDetails']['duration']);
        $urlBanner = stylizeText("-- YouTube --", "bold");
        $urlBanner = stylizeText($urlBanner, "color_red");
        $message = "".$urlBanner." ".$title." from ".$channelName." (".$duration.")";
        sendPRIVMSG($config['channel'], "".$message."");
        return true;
    }
    return true;
}

// Helper function to format the ISO 8601 duration (PT#M#S)
function formatDuration($isoDuration) {
    $interval = new DateInterval($isoDuration);
    return $interval->format('%H:%I:%S');
}
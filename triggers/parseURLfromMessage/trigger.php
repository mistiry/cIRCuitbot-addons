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

        if($parseYouTube == "true" && ( stristr($url, "youtube.com") || stristr($url, "yt.com") ) ) {
            getYouTubeInfo($youtubeAPIKey, $url);
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

        // Create a context with a custom User-Agent header
        $contextOptions = [
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36\r\n"
            ]
        ];
        $context = stream_context_create($contextOptions);

        $html = file_get_contents($url, false, $context);

        // Create a new DOMDocument instance
        $dom = new DOMDocument();

        // Suppress warnings due to malformed HTML by using @
        @$dom->loadHTML($html);

        // Find the title tag
        $titleTags = $dom->getElementsByTagName('title');

        // Get the title content
        if ($titleTags->length > 0) {
            $title = trim($titleTags->item(0)->nodeValue);
            return $title;
        }
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

    // Get the response from the API
    $response = file_get_contents($apiUrl);
    $videoData = json_decode($response, true);

    if (isset($videoData['items']) && count($videoData['items']) > 0) {
        $videoInfo = $videoData['items'][0];

        // Extract title, channel name, and duration
        $title = stylizeText($videoInfo['snippet']['title'], "bold");
        $channelName = stylizeText($videoInfo['snippet']['channelTitle'], "color_yellow");
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
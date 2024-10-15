<?php
function parseURLfromMessage($ircdata) {
    global $config;

    //Config file parsing
    $configfile = parse_ini_file("".$config['addons_dir']."/triggers/parseURLfromMessage/trigger.conf");
    
    //Get info to determine extension
    $parsedUrl = parse_url($url);
    $extension = pathinfo($parsedUrl['path'], PATHINFO_EXTENSION);
    $disallowedExtensions = $configfile['disallowedExtensions'];
    
    // List of disallowed file extensions
    if (in_array(strtolower($extension), $disallowedExtensions)) {
        logEntry("URL '".$url."' points to an invalid extension.");
        return true;
    }

    //If you set 'parseyoutube' to true you MUST provide an API key or bad things will happen
    $parseYouTube = $configfile['parseyoutube'];
    $youtubeAPIKey = $configfile['youtubeAPIKey'];
    $youtubeDomains = $configfile['youtubeDomains'];

    //If you se 'parsereddit' to true you MUST provide a client id and secret or bad things will happen
    $parsereddit = $configfile['parsereddit'];
    $redditclientid = $configfile['redditClientID'];
    $redditclientsecret = $configfile['redditclientsecret'];
    $redditDomains = $configfile['redditDomains'];

    //First, detect if a URL was seen, then figure out if we need to send to custom parser or not
    if(stristr($ircdata['fullmessage'], "https://") || stristr($ircdata['fullmessage'], "http://")) {
        $messagePieces = explode(" ", $ircdata['fullmessage']);
        foreach($messagePieces as $piece) {
            if(stristr($piece,"http")) {
                $url = $piece;
            }
        }
        logEntry("Found URL in message: ".$url."");
        $parsedomain = parse_url($url);
        $domain = $parsedomain['host'];
        logEntry("URL is at domain: ".$domain."");

        //YouTube
        if($parseYouTube == "true" && in_array($domain,$youtubeDomains)) {
            $title = getYouTubeInfo($youtubeAPIKey,$url);
            $urlBanner = stylizeText("-- YouTube --", "bold");
            $urlBanner = stylizeText($urlBanner, "color_red");
            $message = "".$urlBanner." ".$urltitle."";
            sendPRIVMSG($config['channel'], "".$message."");
            return true;
        } elseif($parseYouTube == "false" && in_array($domain,$youtubeDomains)) {
            return true;
        }

        if($parsereddit == "true" && in_array($domain,$redditDomains)) {
            $title = getRedditInfo($redditclientid, $redditclientsecret, $url);
            $urlBanner = stylizeText("-- URL --", "bold");
            $urlBanner = stylizeText($urlBanner, "color_purple");
            $message = "".$urlBanner." ".$urltitle."";
            sendPRIVMSG($config['channel'], "".$message."");
            return true;
        } elseif($parsereddit == "false" && in_array($domain,$redditDomains)) {
            return true;
        }

        //Default Parser
        $title = getTitle($url);
        $urlBanner = stylizeText("-- URL --", "bold");
        $urlBanner = stylizeText($urlBanner, "color_purple");
        $message = "".$urlBanner." ".$urltitle."";
        sendPRIVMSG($config['channel'], "".$message.""); 
    }
    return true;
}

function getTitle($url) {
    global $config;

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    } else {


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
        $message = "".$title." from ".$channelName." (".$duration.")";
        return $message;
    }
    return true;
}

// Helper function to format the ISO 8601 duration (PT#M#S)
function formatDuration($isoDuration) {
    $interval = new DateInterval($isoDuration);
    return $interval->format('%H:%I:%S');
}
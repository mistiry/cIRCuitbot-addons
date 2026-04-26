<?php
function parseURLfromMessage($ircdata) {
    global $config;

    //Config file parsing
    $configfile = parse_ini_file("".$config['addons_dir']."/triggers/parseURLfromMessage/trigger.conf");
    
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
            $message = "".$urlBanner." ".$title."";
            sendPRIVMSG($config['channel'], "".$message."");
            return true;
        } elseif($parseYouTube == "false" && in_array($domain,$youtubeDomains)) {
            return true;
        }

        if($parsereddit == "true" && (in_array($domain,$redditDomains) || stristr($domain,"reddit.com"))) {
            $title = getRedditTitle($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- Reddit --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_orange");
                sendPRIVMSG($config['channel'], "".$urlBanner." ".$title."");
            }
            return true;
        } elseif($parsereddit == "false" && (in_array($domain,$redditDomains) || stristr($domain,"reddit.com"))) {
            return true;
        }

        //Default Parser
        $title = getTitle($url);
        $urlBanner = stylizeText("-- URL --", "bold");
        $urlBanner = stylizeText($urlBanner, "color_purple");
        $message = "".$urlBanner." ".$title."";
        sendPRIVMSG($config['channel'], "".$message.""); 
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);
        if (!$html) { return false; }
        // Extract the title from the HTML
        $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $html, $match) ? $match[1] : null;
        $title = trim($title);
        return $title;
    }
}

function getRedditTitle($url) {
    global $config;
    $configfile = parse_ini_file("".$config['addons_dir']."/triggers/parseURLfromMessage/trigger.conf");
    $clientId = $configfile['redditClientID'];
    $clientSecret = $configfile['redditClientSecret'];

    // Get an application-only OAuth token
    $ch = curl_init("https://www.reddit.com/api/v1/access_token");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_USERPWD => $clientId . ":" . $clientSecret,
        CURLOPT_USERAGENT => "cIRCuitbot/1.0 by /u/mistiry",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $tokenResult = curl_exec($ch);
    curl_close($ch);
    if (!$tokenResult) { return null; }
    $tokenData = json_decode($tokenResult, true);
    $token = $tokenData['access_token'] ?? null;
    if (!$token) { return null; }

    // Fetch the post via the OAuth API endpoint
    $postPath = rtrim(preg_replace('/[?#].*$/', '', parse_url(trim($url), PHP_URL_PATH)), '/') . '.json';
    $ch = curl_init("https://oauth.reddit.com" . $postPath);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $token],
        CURLOPT_USERAGENT => "cIRCuitbot/1.0 by /u/mistiry",
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) { return null; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return null; }
    $title = $data[0]['data']['children'][0]['data']['title'] ?? null;
    $subreddit = $data[0]['data']['children'][0]['data']['subreddit_name_prefixed'] ?? null;
    if ($title && $subreddit) {
        return stylizeText($title, "bold") . " (" . $subreddit . ")";
    }
    return $title;
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
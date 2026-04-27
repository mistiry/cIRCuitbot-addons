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

    $parsetwitter = $configfile['parsetwitter'];
    $twitterDomains = $configfile['twitterDomains'];

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
            if($title !== null) {
                $urlBanner = stylizeText("-- YouTube --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_red");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
                return true;
            }
            // null means unrecognized URL type (channel, playlist, etc.) — fall through to default parser
        } elseif($parseYouTube == "false" && in_array($domain,$youtubeDomains)) {
            return true;
        }

        if($parsereddit == "true" && (in_array($domain,$redditDomains) || stristr($domain,"reddit.com"))) {
            $title = getRedditTitle($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- REDDIT --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_red");
                sendPRIVMSG($config['channel'], "".$urlBanner." ".$title."");
            }
            return true;
        } elseif($parsereddit == "false" && (in_array($domain,$redditDomains) || stristr($domain,"reddit.com"))) {
            return true;
        }

        //X / Twitter
        if($parsetwitter == "true" && in_array($domain, $twitterDomains)) {
            $title = getTwitterInfo($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- X/Twitter --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_blue");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
            }
            return true;
        } elseif($parsetwitter == "false" && in_array($domain, $twitterDomains)) {
            return true;
        }

        //Default Parser
        $title = getTitle($url);
        if (!empty($title)) {
            $urlBanner = stylizeText("-- URL --", "bold");
            $urlBanner = stylizeText($urlBanner, "color_purple");
            sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
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
        $disallowedExtensions = ['pdf', 'exe', 'sh', 'cmd', 'js', 'py', 'bat', 'pl', 'rb', 'ps1', 'vbs', 'msi', 'tcl',
                                  'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'mp4', 'mp3', 'mov', 'avi', 'mkv', 'webm'];
        if (in_array(strtolower($extension), $disallowedExtensions)) {
            return false;
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: max-age=0',
        ]);
        $html = curl_exec($ch);
        curl_close($ch);
        if (!$html) { return false; }

        // 1. Try <title> tag
        $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $html, $match) ? $match[1] : null;
        $title = html_entity_decode(trim((string)$title), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Fall back to og:title (handles JS-rendered sites that set OG tags server-side)
        if (empty($title)) {
            if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $match) ||
                preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\'][^>]*>/i', $html, $match)) {
                $title = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // 3. Fall back to twitter:title
        if (empty($title)) {
            if (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $match) ||
                preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:title["\'][^>]*>/i', $html, $match)) {
                $title = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if (empty($title)) { return false; }
        return $title;
    }
}

function getTwitterInfo($url) {
    $apiUrl = "https://publish.twitter.com/oembed?url=" . urlencode($url) . "&omit_script=true";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) { return null; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['html'])) { return null; }

    // Extract tweet text from the <p> inside the blockquote
    preg_match('/<p[^>]*>(.*?)<\/p>/is', $data['html'], $m);
    $tweetText = html_entity_decode(trim(strip_tags($m[1] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $author = $data['author_name'] ?? '';
    if (empty($tweetText)) { return null; }

    // Truncate long tweets
    if (strlen($tweetText) > 200) {
        $tweetText = substr($tweetText, 0, 197) . '...';
    }

    return stylizeText("@{$author}", "bold") . ": {$tweetText}";
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
        CURLOPT_SSL_VERIFYPEER => true,
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
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) { return null; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return null; }
    $title = $data[0]['data']['children'][0]['data']['title'] ?? null;
    if ($title) {
        return stylizeText($title, "bold");
    }
    return null;
}

function getYouTubeInfo($youtubeAPIKey, $url) {
    global $config;

    $path = parse_url($url, PHP_URL_PATH);

    // Channel URLs — return null so the caller falls through to the generic title parser
    if (preg_match('/^\/@|^\/channel\/|^\/user\/|^\/c\//', $path)) {
        return null;
    }

    // Extract video ID: standard watch?v=ID, shorts /shorts/ID
    parse_str(parse_url($url, PHP_URL_QUERY), $urlParams);
    $videoId = $urlParams['v'] ?? null;

    if (!$videoId && preg_match('/\/shorts\/([a-zA-Z0-9_-]+)/', $path, $m)) {
        $videoId = $m[1];
    }

    // youtu.be short URLs: the path itself is the video ID (e.g. youtu.be/sCzdecygpmg)
    if (!$videoId && preg_match('/^\/([a-zA-Z0-9_-]+)$/', $path, $m)) {
        $videoId = $m[1];
    }

    if (!$videoId) {
        return null;
    }

    logEntry("YouTube Video ID from URL: ".$videoId."");

    $apiUrl = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&part=snippet,contentDetails&key={$youtubeAPIKey}";
    $response = file_get_contents($apiUrl);
    $videoData = json_decode($response, true);

    if (isset($videoData['items']) && count($videoData['items']) > 0) {
        $videoInfo = $videoData['items'][0];
        $title = stylizeText($videoInfo['snippet']['title'], "bold");
        $channelName = stylizeText($videoInfo['snippet']['channelTitle'], "color_yellow");
        $channelName = stylizeText($channelName, "bold");
        $duration = formatDuration($videoInfo['contentDetails']['duration']);
        return $title . " from " . $channelName . " (" . $duration . ")";
    }

    return null;
}

// Helper function to format the ISO 8601 duration (PT#M#S)
function formatDuration($isoDuration) {
    $interval = new DateInterval($isoDuration);
    return $interval->format('%H:%I:%S');
}
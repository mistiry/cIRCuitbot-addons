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

    $parsestackexchange = $configfile['parsestackexchange'];
    $stackexchangeDomains = $configfile['stackexchangeDomains'];

    $parsebluesky = $configfile['parsebluesky'];
    $blueskyDomains = $configfile['blueskyDomains'];

    $parsespotify = $configfile['parsespotify'];
    $spotifyDomains = $configfile['spotifyDomains'];

    $parsetiktok = $configfile['parsetiktok'];
    $tiktokDomains = $configfile['tiktokDomains'];

    $parsesteam = $configfile['parsesteam'];
    $steamDomains = $configfile['steamDomains'];

    $parsewikipedia = $configfile['parsewikipedia'];
    $wikipediaDomains = $configfile['wikipediaDomains'];

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

        //Stack Exchange
        $isStackExchange = in_array($domain, $stackexchangeDomains) || stristr($domain, 'stackexchange.com');
        if($parsestackexchange == "true" && $isStackExchange) {
            $title = getStackExchangeInfo($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- Stack Exchange --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_orange");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
            }
            return true;
        } elseif($parsestackexchange == "false" && $isStackExchange) {
            return true;
        }

        //Bluesky
        if($parsebluesky == "true" && in_array($domain, $blueskyDomains)) {
            $title = getBlueskyInfo($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- Bluesky --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_light_blue");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
            }
            return true;
        } elseif($parsebluesky == "false" && in_array($domain, $blueskyDomains)) {
            return true;
        }

        //Spotify
        if($parsespotify == "true" && in_array($domain, $spotifyDomains)) {
            $title = getSpotifyInfo($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- Spotify --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_green");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
            }
            return true;
        } elseif($parsespotify == "false" && in_array($domain, $spotifyDomains)) {
            return true;
        }

        //TikTok
        if($parsetiktok == "true" && in_array($domain, $tiktokDomains)) {
            $title = getTikTokInfo($url);
            if(!empty($title)) {
                $urlBanner = stylizeText("-- TikTok --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_pink");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
            }
            return true;
        } elseif($parsetiktok == "false" && in_array($domain, $tiktokDomains)) {
            return true;
        }

        //Steam
        if($parsesteam == "true" && in_array($domain, $steamDomains)) {
            $title = getSteamInfo($url);
            if($title !== null) {
                $urlBanner = stylizeText("-- Steam --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_teal");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
                return true;
            }
            // null means non-app URL (homepage, tags, etc.) — fall through to default parser
        } elseif($parsesteam == "false" && in_array($domain, $steamDomains)) {
            return true;
        }

        //Wikipedia
        $isWikipedia = in_array($domain, $wikipediaDomains) || stristr($domain, 'wikipedia.org');
        if($parsewikipedia == "true" && $isWikipedia) {
            $title = getWikipediaInfo($url);
            if($title !== null) {
                $urlBanner = stylizeText("-- Wikipedia --", "bold");
                $urlBanner = stylizeText($urlBanner, "color_light_grey");
                sendPRIVMSG($config['channel'], $urlBanner . " " . $title);
                return true;
            }
            // null means Special/Talk/User page — fall through to default parser
        } elseif($parsewikipedia == "false" && $isWikipedia) {
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
    // Normalize to bare status URL — strip /photo/1, /mediaviewer, etc. after the status ID
    $url = preg_replace('~(https?://(?:x|twitter)\.com/[^/?#]+/status/\d+).*$~i', '$1', $url);
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

function getSteamInfo($url) {
    preg_match('~/app/(\d+)~', $url, $m);
    $appId = $m[1] ?? null;
    if (!$appId) { return null; }

    $ch = curl_init("https://store.steampowered.com/api/appdetails?appids={$appId}&cc=us&l=en");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "cIRCuitbot/1.0",
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => '',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) { return null; }
    $outer = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($outer[$appId]['data'])) { return null; }

    $data  = $outer[$appId]['data'];
    $name  = $data['name'] ?? null;
    if (!$name) { return null; }

    if (!empty($data['price_overview']['final_formatted'])) {
        $price = $data['price_overview']['final_formatted'];
    } elseif (!empty($data['is_free'])) {
        $price = "Free to Play";
    } else {
        $price = null;
    }

    $meta   = $data['metacritic']['score'] ?? null;
    $genres = !empty($data['genres']) ? implode(', ', array_slice(array_column($data['genres'], 'description'), 0, 3)) : null;

    $parts = [stylizeText($name, "bold")];
    if ($price)  { $parts[] = $price; }
    if ($meta)   { $parts[] = "Metacritic: {$meta}"; }
    if ($genres) { $parts[] = $genres; }
    return implode(' | ', $parts);
}

function getWikipediaInfo($url) {
    preg_match('~https?://([a-z]+)\.wikipedia\.org/wiki/(.+)~i', $url, $m);
    if (empty($m[2])) { return null; }
    $lang  = $m[1];
    $title = $m[2];

    // Strip fragment, query string from title
    $title = preg_replace('/[?#].*$/', '', $title);
    if (empty($title)) { return null; }

    $ch = curl_init("https://{$lang}.wikipedia.org/api/rest_v1/page/summary/" . $title);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "cIRCuitbot/1.0 (https://github.com/mistiry/cIRCuitbot)",
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => '',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$response || $httpCode !== 200) { return null; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return null; }

    $pageTitle   = $data['title'] ?? null;
    $description = $data['description'] ?? null;
    if (!$pageTitle) { return null; }

    $out = stylizeText($pageTitle, "bold");
    if (!empty($description)) { $out .= ": {$description}"; }
    return $out;
}

function getStackExchangeInfo($url) {
    $host = preg_replace('/^www\./', '', parse_url($url, PHP_URL_HOST));
    preg_match('~/questions/(\d+)~', $url, $m);
    $questionId = $m[1] ?? null;
    if (!$questionId) { return null; }

    if (preg_match('/^(.+?)\.stackexchange\.com$/', $host, $sm)) {
        $site = $sm[1];
    } else {
        $site = preg_replace('/\.(com|net|org)$/', '', $host);
    }

    $ch = curl_init("https://api.stackexchange.com/2.3/questions/{$questionId}?site={$site}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "cIRCuitbot/1.0",
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => '',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) { return null; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['items'][0])) { return null; }

    $q       = $data['items'][0];
    $title   = html_entity_decode($q['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $score   = $q['score'] ?? 0;
    $answers = $q['answer_count'] ?? 0;
    $accepted = !empty($q['accepted_answer_id']) ? ' ✓' : '';
    return stylizeText($title, "bold") . " (+" . $score . " | " . $answers . " ans" . $accepted . ")";
}

function getBlueskyInfo($url) {
    if (!preg_match('~bsky\.app/profile/([^/]+)/post/([^/?#]+)~', $url, $m)) { return null; }
    [, $handle, $rkey] = $m;

    $ch = curl_init("https://public.api.bsky.app/xrpc/com.atproto.identity.resolveHandle?handle=" . urlencode($handle));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => "cIRCuitbot/1.0",
        CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) { return null; }
    $did = json_decode($resp, true)['did'] ?? null;
    if (!$did) { return null; }

    $atUri = "at://{$did}/app.bsky.feed.post/{$rkey}";
    $ch = curl_init("https://public.api.bsky.app/xrpc/app.bsky.feed.getPostThread?uri=" . urlencode($atUri) . "&depth=0");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => "cIRCuitbot/1.0",
        CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) { return null; }
    $td = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return null; }

    $post   = $td['thread']['post']['record'] ?? null;
    $author = $td['thread']['post']['author']['displayName'] ?? ($td['thread']['post']['author']['handle'] ?? '');
    if (!$post || empty($post['text'])) { return null; }

    $text = $post['text'];
    if (strlen($text) > 200) { $text = substr($text, 0, 197) . '...'; }
    return stylizeText("@{$author}", "bold") . ": {$text}";
}

function getSpotifyInfo($url) {
    $ch = curl_init("https://open.spotify.com/oembed?url=" . urlencode($url));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) { return null; }
    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['title'])) { return null; }

    $path = parse_url($url, PHP_URL_PATH);
    if (stristr($path, '/track/'))         { $type = "Track"; }
    elseif (stristr($path, '/album/'))     { $type = "Album"; }
    elseif (stristr($path, '/playlist/'))  { $type = "Playlist"; }
    elseif (stristr($path, '/artist/'))    { $type = "Artist"; }
    else                                   { $type = ""; }

    $label = $type ? "{$type}: " : "";
    return $label . stylizeText($data['title'], "bold");
}

function getTikTokInfo($url) {
    $ch = curl_init("https://www.tiktok.com/oembed?url=" . urlencode($url));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) { return null; }
    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['title'])) { return null; }

    $author = $data['author_name'] ?? '';
    $title  = $data['title'];
    if (strlen($title) > 200) { $title = substr($title, 0, 197) . '...'; }
    return stylizeText("@{$author}", "bold") . ": {$title}";
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
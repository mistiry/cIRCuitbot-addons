<?php
function doGoogleSearch($data) {
    global $config;
    $search = trim($data['commandargs']);

    if($search == "") {
        return false;
    }

    $configfile = parse_ini_file($config['addons_dir']."/modules/doGoogleSearch/module.conf");
    $apiKey = $configfile['braveAPIKey'];

    $ch = curl_init("https://api.search.brave.com/res/v1/web/search?q=" . urlencode($search) . "&count=1");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Accept-Encoding: gzip",
            "X-Subscription-Token: " . $apiKey,
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        sendPRIVMSG($data['location'], $data['usernickname'] . " - No results found.");
        return true;
    }

    $results = json_decode($response, true);
    $firstResult = $results['web']['results'][0] ?? null;

    if (!$firstResult) {
        sendPRIVMSG($data['location'], $data['usernickname'] . " - No results found.");
        return true;
    }

    $title = $firstResult['title'];
    $url = $firstResult['url'];
    sendPRIVMSG($data['location'], $data['usernickname'] . " - " . $title . " - " . $url);
    return true;
}

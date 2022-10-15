<?php
function getUrbanDictionary($data) {
    $encodedsearch = trim(preg_replace("/\s+/", "+", $data['commandargs']));
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.urbandictionary.com/v0/define?term=".$encodedsearch."",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $message = "Unable to call UrbanDictionary API.";
    } else {
        $json = json_decode($response);
        $definitions = $json->{'list'};
        $randval = rand(0,count($definitions));
        $def = $definitions[$randval]->{'definition'};
        $definition = preg_replace('/\s+/', ' ', trim($def));
        $definition = str_replace("[","",$definition);
        $definition = str_replace("]","",$definition);
        $link = preg_replace('/\s+/', ' ', trim($definitions[$randval]->{'permalink'}));

        //trim the def down to single message
        if(strlen($definition)>256) {
            $definition = substr($definition,0,256);
            $definition = "".$definition."...";
        }

        if($definition == "" && $link == "") {
            $message = "".$data['usernickname'].": Sorry, no definition could be found for '".$search."'.";
        } else {
            $message = "".$data['usernickname'].": ".$definition." (".$link.")"; 
        }
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}
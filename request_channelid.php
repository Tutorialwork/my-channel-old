<?php
if(isset($_GET["token"])){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/youtube/v3/channels?part=id&mine=true&key=AIzaSyAn5LqKE4NN80StCKofpw_QxPzcdZaLOY0");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$_GET["token"], 'Accept: application/json'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    $result_array = json_decode($result);
    foreach($result_array as $arrays){
        if(is_array($arrays)){
            foreach($arrays as $item){
                echo $item->id;
                exit;
            }
        }
    }
} else {
    http_response_code(400);
    exit;
}
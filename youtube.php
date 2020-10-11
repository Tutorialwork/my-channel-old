<?php
$JSONRequest = file_get_contents("php://input");
$request = json_decode($JSONRequest, TRUE);
if(empty($request) || (!isset($request))){
    http_response_code(400);
    exit;
}

$guid = '2a2c4dea-9fd7-4da2-899e-1e2eca4387f5';
$userId = $request["context"]["System"]["user"]["userId"];
$useridShort = str_replace("amzn1.ask.account.", "", $userId);

include('valid_request.php');
$valid = validate_request( $guid, $useridShort );
if ( ! $valid['success'] )  {
    error_log( 'Request failed: ' . $valid['message'] );
    header("HTTP/1.1 400 Bad Request");
    die();
}

function isUserExists($userId){
    require("mysql.php");
    $stmt = $mysql->prepare("SELECT * FROM mychannel WHERE userid = ?");
    $stmt->execute([$userId]);
    return $stmt->rowCount() != 0;
}
function createPIN($userId, $code){
    require("mysql.php");
    $stmt = $mysql->prepare("INSERT INTO mychannel (userid, verifyCode) VALUES (?, ?)");
    $stmt->execute([$userId, $code]);
}
function hasPIN($userId){
    require("mysql.php");
    $stmt = $mysql->prepare("SELECT * FROM mychannel WHERE userid = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if($row["verifyCode"] != null){
        return true;
    } else {
        return false;
    }
}
function getPIN($userId){
    require("mysql.php");
    $stmt = $mysql->prepare("SELECT * FROM mychannel WHERE userid = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row["verifyCode"];
}
function getChannelID($userId){
    require("mysql.php");
    $stmt = $mysql->prepare("SELECT * FROM mychannel WHERE userid = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row["channel"];
}
function speak($text){
    $response = ["version" => "1.0",
        "response" => [
            "outputSpeech" =>  [
                "type" => "SSML",
                "text" => "<speak>".$text."</speak>",
                "ssml" => "<speak>".$text."</speak>"
            ],
            "shouldEndSession" => true
        ]
    ];
    return $response;
}
function speakCard($speaktext, $title, $text){
    $response = ["version" => "1.0",
        "response" => [
            "outputSpeech" =>  [
                "type" => "SSML",
                "text" => "<speak>".$speaktext."</speak>",
                "ssml" => "<speak>".$speaktext."</speak>"
            ],
            "card" => [
                "type" => "Standard",
                "title" => $title,
                "text" => $text
            ],
            "shouldEndSession" => true
        ]
    ];
    return $response;
}
function speakCardImage($speaktext, $title, $text, $smallimage, $largeimage){
    $response = ["version" => "1.0",
        "response" => [
            "outputSpeech" =>  [
                "type" => "SSML",
                "text" => "<speak>".$speaktext."</speak>",
                "ssml" => "<speak>".$speaktext."</speak>"
            ],
            "card" => [
                "type" => "Standard",
                "title" => $title,
                "text" => $text,
                "image" => [
                    "smallImageUrl" => $smallimage,
                    "largeImageUrl" => $largeimage
                ]
            ],
            "shouldEndSession" => true
        ]
    ];
    return $response;
}
function speechSetupAPL($text, $pinCode, $qrText){
    return ["version" => "1.0",
        "response" => [
            "outputSpeech" =>  [
                "type" => "SSML",
                "text" => "<speak>" . $text . "</speak>",
                "ssml" => "<speak>" . $text . "</speak>"
            ],
            "directives" => [
                [
                    "type" => "Alexa.Presentation.APL.RenderDocument",
                    "token" => "setup",
                    "document" => [
                        "type" => "APL",
                        "version" => "1.4",
                        "theme" => "dark",
                        "mainTemplate" => [
                            "items" => [
                                [
                                    "type" => "Container",
                                    "width" => "100%",
                                    "height" => "100%",
                                    "items" => [
                                        [
                                            "type" => "Text",
                                            "text" => $qrText,
                                            "textAlign" => "center",
                                            "fontSize" => "25dp",
                                            "bottom" => "20"
                                        ],
                                        [
                                            "type" => "Image",
                                            "width" => "300dp",
                                            "height" => "300dp",
                                            "source" => "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://skills.tutorialwork.dev/mychannel.php?code=" . $pinCode,
                                            "align" => "center"
                                        ]
                                    ],
                                    "alignItems" => "center",
                                    "direction" => "column",
                                    "justifyContent" => "center"
                                ]
                            ]
                        ],
                    ],
                ]
            ],
            "shouldEndSession" => true
        ]
    ];
}
function cancelRequest($response){
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$intent = !empty($request["request"]["intent"]["name"]) ? $request["request"]["intent"]["name"] : "default";
$type = $request["request"]["type"];
$requestId = $request["request"]["requestId"];
$language = $request["request"]["locale"];
$device = !empty($request["context"]["System"]["device"]) ? $request["context"]["System"]["device"] : null;
//English: en-US
//German: de-DE

$translations = file_get_contents("languages/".$language.".json");
$translations = json_decode($translations);

function hasAPLSupport($deviceData){
    if(isset($deviceData["supportedInterfaces"]["Alexa.Presentation.APL"])){
        return true;
    } else {
        return false;
    }
}

if($type == "LaunchRequest"){
    if(isUserExists($userId) && !hasPIN($userId)){
        $cmds = array(
            $translations->startup_cmd_1,
            $translations->startup_cmd_1,
            $translations->startup_cmd_2,
            $translations->startup_cmd_3,
            $translations->startup_cmd_4,
        );
        $response = speak($translations->startup.$cmds[array_rand($cmds)]);
    } else {
        $code = "";
        if(!hasPIN($userId)){
            $code = rand(100000, 999999);
            createPIN($userId, $code);
        } else {
            $code = getPIN($userId);
        }
        if(hasAPLSupport($device)){
            $response = speechSetupAPL($translations->setup_with_apl, $code, $translations->setup_qrcode_title);
        } else {
            $response = speakCard(
                $translations->setup_speak,
                $translations->setup_card_title,
                $translations->setup_card_text . $code
            );
        }
    }
}

if($type == "SessionEndedRequest"){
    $response = '
    {
        "type": "SessionEndedRequest",
        "requestId": "'.$requestId.'",
        "timestamp": "' . date("c") . '",
        "reason": "USER_INITIATED "
      }
    ';
}

if($type == "IntentRequest"){
    if($intent == "lastvideo"){
        /*
        Wenn Views, Likes wenn eins über dem Durchschnitt ist GUT wenn beide über Durchschnitt SEHR GUT
        */
        if(isUserExists($userId)){
            require("config.php");
            $request_row = file_get_contents("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=".getChannelID($userId)."&order=date&key=".$APIKey);
            $request = json_decode($request_row);
            $views = 0;
            $likes = 0;
            $dislikes = 0;
            $comments = 0;
            $lastvideo_views = 0;
            $lastvideo_likes = 0;
            $lastvideo_title = null;
            foreach ($request->items as $data2){
                $videoId = $data2->id->videoId;
                $videorequest_row = file_get_contents("https://www.googleapis.com/youtube/v3/videos?part=statistics&id=".$videoId."&key=".$APIKey);
                $videorequest = json_decode($videorequest_row);
                foreach ($videorequest->items as $data){
                    $views = $views + $data->statistics->viewCount;
                    $likes = $likes + $data->statistics->likeCount;
                    $dislikes = $dislikes + $data->statistics->dislikeCount;
                    $comments = $comments + $data->statistics->commentCount;
                    if($lastvideo_views == 0){
                        $lastvideo_views = $data->statistics->viewCount;
                        $lastvideo_likes = $data->statistics->likeCount;
                        $lastvideo_title = $data2->snippet->title;
                        $pup_date = $data2->snippet->publishedAt;
                        $datearray = explode("T", $pup_date);
                        $pup_timestamp = strtotime($datearray[0]);
                        $now = time();
                        $dif = $now - $pup_timestamp;
                        if($dif < 86400){ //24h
                            $response = speak($translations->last_video_error);
                            cancelRequest($response);
                        }
                    }
                }
            }
            $durchschnitt = round($views/5);
            $likedurschnitt = round($likes/5);
            if($lastvideo_views > $durchschnitt || $lastvideo_likes > $likedurschnitt){
                //Mindestens eins ist besser als Durchschnitt Likes und/oder Aufrufe
                if($lastvideo_views > $durchschnitt && $lastvideo_likes > $likedurschnitt){
                    //Beides ist besser als Durchschnitt - SEHR GUT
                    $response = speak(str_replace([
                        "%title%",
                        "%views%",
                        "%likes%"
                    ], [
                        $lastvideo_title,
                        $lastvideo_views,
                        $lastvideo_likes
                    ], $translations->video_popularity_very_good));
                } else {
                    //GUT
                    if($lastvideo_views > $durchschnitt){
                        $response = speak(str_replace([
                            "%title%",
                            "%views%",
                            "%average%"
                        ], [
                            $lastvideo_title,
                            $lastvideo_views,
                            $durchschnitt
                        ], $translations->video_popularity_good_views));
                    } else if($lastvideo_likes > $likedurschnitt){
                        $response = speak(str_replace([
                            "%title%",
                            "%likes%",
                            "%average%"
                        ], [
                            $lastvideo_title,
                            $lastvideo_likes,
                            $likedurschnitt
                        ], $translations->video_popularity_good_likes));
                    }
                }
            } else {
                //Video schlecht angekommen
                $response = speak(str_replace([
                    "%title%",
                    "%views%",
                    "%likes%"
                ], [
                    $lastvideo_title,
                    $lastvideo_views,
                    $lastvideo_likes
                ], $translations->video_popularity_bad));
            }
        } else {
            $response = speakError("NO_CHANNELID");
        }
    }
    if($intent == "subscribers"){
        if(isUserExists($userId)){
            require("config.php");
            $request_row = file_get_contents("https://www.googleapis.com/youtube/v3/channels?part=statistics&id=".getChannelID($userId)."&key=".$APIKey);
            $request = json_decode($request_row);
            foreach($request->items as $data){
                $response = speak(str_replace("%subs%", $data->statistics->subscriberCount, $translations->current_subscribers));
            }
        } else {
            $response = speakError("NO_CHANNELID");
        }
    }
    if($intent == "submilestones"){
        if(isUserExists($userId)){
            require('simple_html_dom.php');
            $url = 'https://socialblade.com/youtube/channel/'.getChannelID($userId).'/futureprojections/subscribers';
            $context = stream_context_create(array(
                'http' => array(
                    'header' => array('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36'),
                ),
            ));
            $html = file_get_html($url, false, $context);
            $sub_milestones = array();
            $i = 0;
            foreach ($html->find('div[class="TableMonthlyStats"]') as $div) {
                if ($i == 1 || $i == 2) {
                    $sub_milestones[] = str_replace(",", "", $div->innertext);
                }
                $i++;
                if ($i == 5) {
                    $i = 0;
                }
            }

            $days = 0;
            if(isset($sub_milestones[0])){
                $days = (int) str_replace(" days", "", $sub_milestones[0]);
            }

            $currentDate = new DateTime();
            $milestoneDate = new DateTime("+" . $days . " days");
            $interval = $currentDate->diff($milestoneDate);
            $time_formatted = $interval->format('%y ' . $translations->years . ' ' . $translations->and . ' %m ' . $translations->months);
            $time_formatted = str_replace($translations->and . " 0 " . $translations->months, "", $time_formatted);

            $response = speak(str_replace([
                "%milestone%",
                "%time%",
            ], [
                $sub_milestones[1],
                $time_formatted,
            ], $translations->next_milestone));
        } else {
            $response = speakError("NO_CHANNELID");
        }
    }
    if($intent == "listsubmilestones"){
        if(isUserExists($userId)){
            require('simple_html_dom.php');
            $url = 'https://socialblade.com/youtube/channel/'.getChannelID($userId).'/futureprojections/subscribers';
            $context = stream_context_create(array(
                'http' => array(
                    'header' => array('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36'),
                ),
            ));
            $html = file_get_html($url, false, $context);
            $sub_milestones = array();
            $i = 0;
            foreach ($html->find('div[class="TableMonthlyStats"]') as $div) {
                if ($i == 1 || $i == 2) {
                    $sub_milestones[] = str_replace(",", "", $div->innertext);
                }
                $i++;
                if ($i == 5) {
                    $i = 0;
                }
            }
    
            $subs = array();
            $time = array();
    
            $count = 0;
            foreach ($sub_milestones as $values){
                if($count == 0){
                    $time[] = $values;
                } else if($count == 1){
                    $subs[] = $values;
                    $count = -1;
                }
                $count++;
            }

            $milestones_str = $translations->next_milestones.' <break time="250ms"/>';

            foreach ($subs as $index=>$values){
                $days = (int) str_replace(" days", "", $time[$index]);

                $currentDate = new DateTime();
                $milestoneDate = new DateTime("+" . $days . " days");
                $interval = $currentDate->diff($milestoneDate);
                $time_formatted = $interval->format('%y ' . $translations->years . ' ' . $translations->and . ' %m ' . $translations->months);
                $time_formatted = str_replace($translations->and . " 0 " . $translations->months, "", $time_formatted);

                $milestones_str .= ' <break time="500ms"/> ' . $values . ' '.$translations->subs_in.'  <break time="250ms"/> ' . $time_formatted;
            }

            $response = speak($milestones_str);
        } else {
            $response = speakError("NO_CHANNELID");
        }
    }
    if($intent == "channelstats"){
        if(isUserExists($userId)){
            require("config.php");
            $request_row = file_get_contents("https://www.googleapis.com/youtube/v3/channels?part=statistics&id=".getChannelID($userId)."&key=".$APIKey);
            $request = json_decode($request_row);
            foreach($request->items as $data){
                $response = speak(str_replace([
                    "%subs%",
                    "%videos%",
                    "%views%",
                ], [
                    $data->statistics->subscriberCount,
                    $data->statistics->videoCount,
                    $data->statistics->viewCount
                ], $translations->channel_stats));
            }
        } else {
            $response = speakError("NO_CHANNELID");
        }
    }
}

function speakError($error){
    switch ($error){
        case 'NO_CHANNELID':
            return speak($translations->setup_missing);
            break;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
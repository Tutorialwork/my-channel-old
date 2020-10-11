<?php

require "simple_html_dom.php";

require "ResponseBuilder.php";
require "Database.php";
require "ApiKey.php";

class RequestHandler{

    private $request;
    private $response;
    private $translations;

    /**
     * RequestHandler constructor.
     * @param $request
     */
    public function __construct($request){
        $this->request = $request;
    }

    public function handleRequest(){
        $database = new Database();
        $api = new ApiKey();
        $builder = new ResponseBuilder();

        $channelId = $this->getChannelId($database, $this->request->getUserId());

        switch ($this->request->getIntent()){
            case "lastvideo":
                if($this->hasUserData($database, $this->request->getUserId())){

                    $request = file_get_contents("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . $channelId . "&order=date&key=" . $api->getKey());
                    $request = json_decode($request);
                    $views = 0;
                    $likes = 0;
                    $dislikes = 0;
                    $comments = 0;
                    $lastvideo_views = 0;
                    $lastvideo_likes = 0;
                    $lastvideo_title = null;
                    $blockedCalculation = false;

                    foreach ($request->items as $data2){
                        $videoId = $data2->id->videoId;
                        $videorequest = file_get_contents("https://www.googleapis.com/youtube/v3/videos?part=statistics&id=" . $videoId . "&key=" . $api->getKey());
                        $videorequest = json_decode($videorequest);

                        foreach ($videorequest->items as $data){
                            $views += $data->statistics->viewCount;
                            $likes += $data->statistics->likeCount;
                            $dislikes += $data->statistics->dislikeCount;
                            $comments += $data->statistics->commentCount;
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
                                    $builder->speechText($this->translations->last_video_error);
                                    $this->applyBuilder($builder);
                                    break;
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
                            $builder->speechText(str_replace([
                                "%title%",
                                "%views%",
                                "%likes%"
                            ], [
                                $lastvideo_title,
                                $lastvideo_views,
                                $lastvideo_likes
                            ], $this->translations->video_popularity_very_good));
                        } else {
                            //GUT
                            if($lastvideo_views > $durchschnitt){
                                $builder->speechText(str_replace([
                                    "%title%",
                                    "%views%",
                                    "%average%"
                                ], [
                                    $lastvideo_title,
                                    $lastvideo_views,
                                    $durchschnitt
                                ], $this->translations->video_popularity_good_views));
                            } else if($lastvideo_likes > $likedurschnitt){
                                $builder->speechText(str_replace([
                                    "%title%",
                                    "%likes%",
                                    "%average%"
                                ], [
                                    $lastvideo_title,
                                    $lastvideo_likes,
                                    $likedurschnitt
                                ], $this->translations->video_popularity_good_likes));
                            }
                        }
                    } else {
                        //Video schlecht angekommen
                        $builder->speechText(str_replace([
                            "%title%",
                            "%views%",
                            "%likes%"
                        ], [
                            $lastvideo_title,
                            $lastvideo_views,
                            $lastvideo_likes
                        ], $this->translations->video_popularity_bad));
                    }

                } else {
                    $builder->speechText($this->translations->setup_missing);
                }

                $this->applyBuilder($builder);
                break;
            case "subscribers":
                if($this->hasUserData($database, $this->request->getUserId())){
                    $request = file_get_contents("https://www.googleapis.com/youtube/v3/channels?part=statistics&id=" . $channelId . "&key=" . $api->getKey());
                    $request = json_decode($request);
                    foreach($request->items as $data){
                        $builder->speechText(str_replace("%subs%", $data->statistics->subscriberCount, $this->translations->current_subscribers));
                    }
                } else {
                    $builder->speechText($this->translations->setup_missing);
                }

                $this->applyBuilder($builder);
                break;
            case "submilestones":

                if($this->hasUserData($database, $this->request->getUserId())){
                    $url = 'https://socialblade.com/youtube/channel/' . $channelId . '/futureprojections/subscribers';
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
                    $time_formatted = $interval->format('%y ' . $this->translations->years . ' ' . $this->translations->and . ' %m ' . $this->translations->months);
                    $time_formatted = str_replace($this->translations->and . " 0 " . $this->translations->months, "", $time_formatted);

                    $builder->speechText(str_replace([
                        "%milestone%",
                        "%time%",
                    ], [
                        $sub_milestones[1],
                        $time_formatted,
                    ], $this->translations->next_milestone));
                } else {
                    $builder->speechText($this->translations->setup_missing);
                }

                $this->applyBuilder($builder);
                break;
            case "listsubmilestones":
                if($this->hasUserData($database, $this->request->getUserId())){
                    $url = 'https://socialblade.com/youtube/channel/' . $channelId . '/futureprojections/subscribers';
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

                    $milestones_str = $this->translations->next_milestones.' <break time="250ms"/>';

                    foreach ($subs as $index=>$values){
                        $days = (int) str_replace(" days", "", $time[$index]);

                        $currentDate = new DateTime();
                        $milestoneDate = new DateTime("+" . $days . " days");
                        $interval = $currentDate->diff($milestoneDate);
                        $time_formatted = $interval->format('%y ' . $this->translations->years . ' ' . $this->translations->and . ' %m ' . $this->translations->months);
                        $time_formatted = str_replace($this->translations->and . " 0 " . $this->translations->months, "", $time_formatted);

                        $milestones_str .= ' <break time="500ms"/> ' . $values . ' '.$this->translations->subs_in.'  <break time="250ms"/> ' . $time_formatted;
                    }

                    $builder->speechText($milestones_str);
                } else {
                    $builder->speechText($this->translations->setup_missing);
                }

                $this->applyBuilder($builder);
                break;
            case "channelstats":
                if($this->hasUserData($database, $this->request->getUserId())){
                    $request = file_get_contents("https://www.googleapis.com/youtube/v3/channels?part=statistics&id=" . $channelId . "&key=" . $api->getKey());
                    $request = json_decode($request);
                    foreach($request->items as $data){
                        $builder->speechText(str_replace([
                            "%subs%",
                            "%videos%",
                            "%views%",
                        ], [
                            $data->statistics->subscriberCount,
                            $data->statistics->videoCount,
                            $data->statistics->viewCount
                        ], $this->translations->channel_stats));
                    }
                } else {
                    $builder->speechText($this->translations->setup_missing);
                }

                $this->applyBuilder($builder);
                break;
            default:
                /*
                 * Launch request
                 */
                if($this->hasUserData($database, $this->request->getUserId())){
                    $userData = $this->getUserData($database, $this->request->getUserId());
                    if($userData["verifyCode"] == null){
                        $cmds = [
                            $this->translations->startup_cmd_1,
                            $this->translations->startup_cmd_2,
                            $this->translations->startup_cmd_3,
                            $this->translations->startup_cmd_4,
                        ];
                        $builder->speechText($this->translations->startup . $cmds[array_rand($cmds)]);
                    } else {
                        //Repeat pin
                        $code = $userData["verifyCode"];

                        if($this->hasAPLSupport()){
                            $builder->speechAPL($this->translations->setup_with_apl, $code, $this->translations->setup_qrcode_title);
                        } else {
                            $builder->speechCard(
                                $this->translations->setup_speak,
                                $this->translations->setup_card_title,
                                $this->translations->setup_card_text . $code
                            );
                        }
                    }
                } else {
                    //Create pin
                    $code = rand(100000, 999999);
                    $stmt = $database->getMysql()->prepare("INSERT INTO mychannel (userid, verifyCode) VALUES (?, ?)");
                    $stmt->execute([$this->request->getUserId(), $code]);

                    if($this->hasAPLSupport()){
                        $builder->speechAPL($this->translations->setup_with_apl, $code, $this->translations->setup_qrcode_title);
                    } else {
                        $builder->speechCard(
                            $this->translations->setup_speak,
                            $this->translations->setup_card_title,
                            $this->translations->setup_card_text . $code
                        );
                    }
                }

                $this->applyBuilder($builder);
                break;
        }
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function applyBuilder($builder){
        $this->response = $builder->getResponse();
    }

    private function hasAPLSupport(){
        if(isset($this->request->getDevice()["supportedInterfaces"]["Alexa.Presentation.APL"])){
            return true;
        } else {
            return false;
        }
    }

    public function loadTranslations(){
        $translations = file_get_contents("languages/" . $this->request->getLanguage() . ".json");
        $translations = json_decode($translations);
        $this->translations = $translations;
    }

    private function hasUserData($database, $userId){
        $stmt = $database->getMySql()->prepare("SELECT * FROM mychannel WHERE userid = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount() != 0;
    }

    private function getChannelId($database, $userId){
        $stmt = $database->getMySql()->prepare("SELECT * FROM mychannel WHERE userid = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row["channel"];
    }

    private function getUserData($database, $userId){
        $stmt = $database->getMySql()->prepare("SELECT * FROM mychannel WHERE userid = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

}
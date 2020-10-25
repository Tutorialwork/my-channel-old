<?php


class ResponseBuilder{

    private $response;

    public function speechText($text){
        $this->response = ["version" => "1.0",
            "response" => [
                "outputSpeech" =>  [
                    "type" => "SSML",
                    "text" => "<speak>".$text."</speak>",
                    "ssml" => "<speak>".$text."</speak>"
                ],
                "shouldEndSession" => true
            ]
        ];
    }

    public function speechTextAndReprompt($text, $promptText, $data){
        $this->response = ["version" => "1.0",
            "sessionAttributes" => $data,
            "response" => [
                "outputSpeech" =>  [
                    "type" => "SSML",
                    "text" => "<speak>".$text."</speak>",
                    "ssml" => "<speak>".$text."</speak>"
                ],
                "shouldEndSession" => false,
                "reprompt" => [
                    "outputSpeech" =>  [
                        "type" => "SSML",
                        "text" => "<speak>".$promptText."</speak>",
                        "ssml" => "<speak>".$promptText."</speak>"
                    ],
                ],
            ]
        ];
    }

    public function speechCard($text, $cardTitle, $cardText, $cardImage){
        $this->response = ["version" => "1.0",
            "response" => [
                "outputSpeech" =>  [
                    "type" => "SSML",
                    "text" => "<speak>".$text."</speak>",
                    "ssml" => "<speak>".$text."</speak>"
                ],
                "card" => [
                    "type" => "Standard",
                    "title" => $cardTitle,
                    "text" => $cardText,
                    "image" => [
                        "smallImageUrl" => $cardImage,
                        "largeImageUrl" => $cardImage
                    ]
                ],
                "shouldEndSession" => true
            ]
        ];
    }

    public function speechAPL($text, $pinCode, $qrText){
        $this->response = ["version" => "1.0",
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
                                                "source" => "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://skills.tutorialwork.dev/mychannel/" . $pinCode,
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

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }



}
<?php

require "valid_request.php";

require "Request.php";
require "RequestHandler.php";

$JSONRequest = file_get_contents("php://input");
$request = json_decode($JSONRequest, TRUE);
if(empty($request) || (!isset($request))){
    http_response_code(400);
    exit;
}

$guid = '2a2c4dea-9fd7-4da2-899e-1e2eca4387f5';
$userId = $request["context"]["System"]["user"]["userId"];
$useridShort = str_replace("amzn1.ask.account.", "", $userId);

$valid = validate_request( $guid, $useridShort );
if (!$valid['success']) {
    error_log( 'Request failed: ' . $valid['message'] );
    header("HTTP/1.1 400 Bad Request");
    die();
}

$intent = !empty($request["request"]["intent"]["name"]) ? $request["request"]["intent"]["name"] : "default";
$type = $request["request"]["type"];
$requestId = $request["request"]["requestId"];
$language = $request["request"]["locale"];
$device = !empty($request["context"]["System"]["device"]) ? $request["context"]["System"]["device"] : null;

$request = new Request($intent, $useridShort, $type, $requestId, $language, $device);
$requestHandler = new RequestHandler($request);
$requestHandler->loadTranslations();
$requestHandler->handleRequest();
$response = $requestHandler->getResponse();

if($response == null){
    error_log("Empty response");
}

header('Content-Type: application/json');
echo json_encode($response);
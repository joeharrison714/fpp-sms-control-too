<?php
include_once "/opt/fpp/www/config.php";
include_once "sms-common.php";

$number = $argv[1];
$message = $argv[2];

if (strlen($number) == 0){
	throw new Exception('No number specified.');
}

if (strlen($message) == 0){
	throw new Exception('No message specified.');
}

$pluginJson = convertAndGetSettings();

$logLevel = getLogLevel($pluginJson);
$logFile = $settings['logDirectory']."/".$pluginName."-outgoing.log";

logInfo("Log Level: " . $logLevel);
logInfo("Send Command Number: " . $number);
logInfo("Send Command Message: " . $message);

$voipmsApiUsername = returnIfExists($pluginJson, "voipmsApiUsername");
$voipmsApiPassword = returnIfExists($pluginJson, "voipmsApiPassword");
$voipmsDid = returnIfExists($pluginJson, "voipmsDid");

executeSend($number, $message);

function executeSend($number, $message){
    global $voipmsDid;
    sendMessageLocal($voipmsDid, $number, $message);
}

function sendMessageLocal($did, $destination, $message){
    if (strlen($did) == 0){
        throw new Exception("No did specified to send from.");
    }

    if (strlen($destination) == 0){
        throw new Exception("No destination specified to send to.");
    }

    if (strlen($message) == 0){
        throw new Exception("No message specified.");
    }

    global $apiBasePath,$voipmsApiUsername,$voipmsApiPassword;
    $url = $apiBasePath . "/rest.php";
    $options = array(
        'http' => array(
        'method'  => 'GET'
        )
    );
    $getdata = http_build_query(
        array(
        'api_username' => $voipmsApiUsername,
        'api_password' => $voipmsApiPassword,
        'method'=>'sendSMS',
        'did'=>$did,
        'dst'=>$destination,
        'message'=>$message
         )
    );
    logInfo("Sending SMS to: " . $destination);
    $context = stream_context_create( $options );
    logDebug("API Request: " . $url ."?" .$getdata);
    //$result = file_get_contents( $url ."?" .$getdata, false, $context );
    //logDebug("API response: " . $result);
    //return json_decode( $result );
}

?>
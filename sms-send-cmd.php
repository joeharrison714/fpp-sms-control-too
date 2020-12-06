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

$voipmsApiUsername = returnIfExists($pluginJson, "voipmsApiUsername");
$voipmsApiPassword = returnIfExists($pluginJson, "voipmsApiPassword");
$voipmsDid = returnIfExists($pluginJson, "voipmsDid");

executeSend($number, $message);

function executeSend($number, $message){
    global $voipmsDid;
    sendMessage($voipmsDid, $number, $message);
}

?>
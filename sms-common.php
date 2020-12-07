<?php
include_once "/opt/fpp/www/config.php";

$pluginName = "fpp-sms-control-too";
$apiBasePath = "https://voip.ms/api/v1";

function returnIfExists($json, $setting) {
    if ($json == null) {
        return "";
    }
    if (array_key_exists($setting, $json)) {
        return $json[$setting];
    }
    return "";
}

function convertAndGetSettings() {
    global $settings, $pluginName;
        
    $cfgFile = $settings['configDirectory'] . "/plugin." . $pluginName . ".json";
    if (file_exists($cfgFile)) {
        $j = file_get_contents($cfgFile);
        $json = json_decode($j, true);
        return $json;
    }
    $j = "{\"keywords\": [] }";
    return json_decode($j, true);
}

function getBool($pluginJson, $name){
    $enabledSetting = returnIfExists($pluginJson, $name);

    $isEnabled = false;
    if (is_bool($enabledSetting)){
        $isEnabled = $enabledSetting;
    }
    else{
        $isEnabled = $enabledSetting == "true" ? true : false;
    }

    return $isEnabled;
}

function getLogLevel($pluginJson){
    $logLevel = "INFO";
    $logLevelSetting = returnIfExists($pluginJson, "logLevel");
    if ($logLevelSetting == "DEBUG"){
        $logLevel = "DEBUG";
    }
    return $logLevel;
}

function logDebug($data){
    global $logLevel;
    if ($logLevel == "DEBUG"){
        logEntry($data);
    }
    //echo $data . "\n";
}
function logInfo($data){
    global $logLevel;
    if ($logLevel == "INFO" || $logLevel == "DEBUG"){
        logEntry($data);
    }
    //echo $data . "\n";
}
function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

function sendMessage($did, $destination, $message){
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
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logDebug("API response: " . $result);
    return json_decode( $result );
}
?>
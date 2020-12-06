<?php
include_once "sms-common.php";

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$sleepTime = 5;

$pluginJson = convertAndGetSettings();

$enabledSetting = returnIfExists($pluginJson, "enabled");

$isEnabled = false;
if (is_bool($enabledSetting)){
    $isEnabled = $enabledSetting;
}
else{
    $isEnabled = $enabledSetting == "true" ? true : false;
}

$logLevel = "INFO";
$logLevelSetting = returnIfExists($pluginJson, "logLevel");
if ($logLevelSetting == "DEBUG"){
    $logLevel = "DEBUG";
}

if($isEnabled == 1) {
    echo "Starting SMS Control Plugin\n";
    logInfo("Starting SMS Control Plugin");

    $voipmsApiUsername = returnIfExists($pluginJson, "voipmsApiUsername");
    $voipmsApiPassword = returnIfExists($pluginJson, "voipmsApiPassword");
    $voipmsDid = returnIfExists($pluginJson, "voipmsDid");
    $messageSuccess = returnIfExists($pluginJson, "messageSuccess");
    $messageFail = returnIfExists($pluginJson, "messageFail");

    $smsKeywords = array();
    foreach($pluginJson["keywords"] as $item) {
        $thisKeyword = $item["keyword"];
        echo("This Keyword: " . $thisKeyword . "\n");

        if (strlen($thisKeyword) > 0){
            $smsKeywords[$thisKeyword] = $item;
        }
    }

    try{
        logInfo("Voip.ms username: " . $voipmsApiUsername);
        if (strlen($voipmsApiUsername)==0){
            throw new Exception('No voip.ms username specified.');
        }

        logInfo("Voip.ms password: " . "<<redacted>>");
        if (strlen($voipmsApiPassword)==0){
            throw new Exception('No voip.ms password specified.');
        }

        logInfo("Voip.ms DID: " . $voipmsDid);
        logInfo("Success message: " . $messageSuccess);
        logInfo("Fail message: " . $messageFail);
    
        if (count($smsKeywords) == 0){
            throw new Exception('No keywords defined.');
        }

    } catch (Exception $e) {
        logInfo($e->getMessage());
        die;
    }

    executeKeywordCommand($smsKeywords["START"]);

}else {
    logInfo("SMS Control Plugin is disabled");
}

function executeKeywordCommand($data){
    $url = "http://127.0.0.1/api/command/";

    if (strlen($data["command"]) > 0){
        //$url .= urlencode($data["command"]);
        $url .= $data["command"];
        $url = str_replace(' ', '%20', $url);

        echo "URL: " . $url . "\n";

        $getUrl = $url;
        foreach($data["args"] as $arg) {
            $getUrl .= "/" . $arg;
        }

        $postJson = json_encode($data["args"]);

        echo "postJson: " . $postJson . "\n";
        
        $opts = array('http' =>
            array(
                'method'  => 'POST',
		        'header'  => 'Content-Type: application/json',
                'content' => $postJson
            )
        );
        
        $context  = stream_context_create($opts);

        logInfo("Calling " . $url);
        
        $response = file_get_contents($url, false, $context);


        // $options = array(
        //     'http' => array(
        //     'method'  => 'GET'
        //     )
        // );
        // $context = stream_context_create( $options );
        // $getUrl = str_replace(' ', '%20', $getUrl);
        // logDebug("API Request: " . $getUrl);
        // $response = file_get_contents( $getUrl, false, $context );
        
        logDebug("API Response: " . $response);
    }
}

function getFppStatus() {
    $result=file_get_contents("http://127.0.0.1/api/fppd/status");
    return json_decode( $result );
  }


function logDebug($data){
    global $logLevel;
    if ($logLevel == "DEBUG"){
        logEntry($data);
    }
    echo $data . "\n";
}
function logInfo($data){
    global $logLevel;
    if ($logLevel == "INFO" || $logLevel == "DEBUG"){
        logEntry($data);
    }
}
function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

?>
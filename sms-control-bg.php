<?php
include_once "sms-common.php";

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$sleepTime = 5;
$apiBasePath = "https://voip.ms/api/v1";
$oldestMessageAge = $sleepTime * 4;
$lastProcessedMessageDate = (new DateTime())->setTimestamp(0);

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

    //executeKeywordCommand($smsKeywords["START"]);

    while(true) {
        try{
            doCheck();
        } catch (Exception $e) {
            logInfo('Exception: ' . $e->getMessage());
        }

        logDebug("Sleeping");
        sleep(5);
    }

}else {
    logInfo("SMS Control Plugin is disabled");
}

function doCheck(){
    $messageResponse = getMessages();
    logDebug("API Response Status: " . $messageResponse->status);

    if ($messageResponse->status == "success"){
        processMessages($messageResponse);
    }
}


function getMessages(){
    global $apiBasePath,$voipmsApiUsername,$voipmsApiPassword,$voipmsDid;
    $url = $apiBasePath . "/rest.php";
    $options = array(
        'http' => array(
        'method'  => 'GET'
        )
    );

    $paramsArray = array(
        'api_username' => $voipmsApiUsername,
        'api_password' => $voipmsApiPassword,
        'method'=>'getSMS',
        'type'=>'1',
    );
    if (strlen($voipmsDid) > 0){
        $paramsArray["did"] = $voipmsDid;
    }

    $getdata = http_build_query(
        $paramsArray
    );
    $context = stream_context_create( $options );
    logDebug("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logDebug("API response: " . $result);
    return json_decode( $result );
}


function processMessages($messageResponse){
    global $startCommand, $oldestMessageAge, $lastProcessedMessageDate;

    foreach($messageResponse->sms as $item) {
        try{
            $id = $item->id;
            $date = $item->date;
            $did = $item->did;
            $contact = $item->contact;
            $message = trim($item->message);
            logDebug("Message ID: " . $id);

            $action = "ignored";

            $now = new DateTime('now');
            $datetime = new DateTime( $date );
            $diffInSeconds = $now->getTimestamp() - $datetime->getTimestamp();
            logDebug("Message Age: " . $diffInSeconds);
            logDebug("Last Processed Message Date: " . $lastProcessedMessageDate->format('Y-m-d H:i:s'));

            if ($diffInSeconds > $oldestMessageAge){
                $action = "too old";
                logDebug("Message older than oldest age");
            }
            elseif($datetime <= $lastProcessedMessageDate){
                $action = "too old";
                logDebug("Message older than last processed message date");
            }
            else {
                // if (strcasecmp($message, $startCommand) == 0) {
                //     $action = "start show";
                //     if ($shouldStart){
                //         $action = "start show (duplicate)";
                //     }
                //     $shouldStart = true;
                //     $respondTo[$contact] = $did;
                // }
                // else{
                //     logInfo("Unknown message: " . $message);
                // }

                // saveMessageToCsv($id, $date, $did, $contact, $message, $action);
            }

            if ($datetime > $lastProcessedMessageDate){
                $lastProcessedMessageDate = $datetime;
                logDebug("Setting Last Processed Message Date to " . $lastProcessedMessageDate->format('Y-m-d H:i:s'));
            }

            //deleteMessage($id);

        } catch (Exception $e) {
            logInfo('Failed on processing message: ' . $e->getMessage());
        }
    }

    //logInfo("Will respond to: " . json_encode($respondTo));
}

function executeKeywordCommand($data){
    $url = "http://127.0.0.1/api/command/";

    if (strlen($data["command"]) > 0){
        $url .= $data["command"];
        $url = str_replace(' ', '%20', $url);

        // $getUrl = $url;
        // foreach($data["args"] as $arg) {
        //     $getUrl .= "/" . $arg;
        // }

        $postJson = json_encode($data["args"]);
        logDebug("Post JSON: " . $postJson);
        
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
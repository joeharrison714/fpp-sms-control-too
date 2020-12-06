<?php
include_once "sms-common.php";

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$messagesCsvFile = $settings['logDirectory']."/".$pluginName."-messages.csv";
$sleepTime = 5;
$apiBasePath = "https://voip.ms/api/v1";
$oldestMessageAge = $sleepTime * 4;
$lastProcessedMessageDate = (new DateTime())->setTimestamp(0);

$pluginJson = convertAndGetSettings();
$keywordData = array();
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
    $messageInvalid = returnIfExists($pluginJson, "messageInvalid");
    $messageCondition = returnIfExists($pluginJson, "messageCondition");
    
    foreach($pluginJson["keywords"] as $item) {
        $thisKeyword = strtoupper(trim($item["keyword"]));
        logDebug("This Keyword: " . $thisKeyword . "\n");

        if (strlen($thisKeyword) > 0){
            $smsKeywords[$thisKeyword] = $item;
            $item["keyword"] = $thisKeyword;
            array_push($keywordData,$item);
        }
    }
    var_dump($keywordData);

    try{
        logDebug("Voip.ms username: " . $voipmsApiUsername);
        if (strlen($voipmsApiUsername)==0){
            throw new Exception('No voip.ms username specified.');
        }

        logDebug("Voip.ms password: " . "<<redacted>>");
        if (strlen($voipmsApiPassword)==0){
            throw new Exception('No voip.ms password specified.');
        }

        logDebug("Voip.ms DID: " . $voipmsDid);
        logDebug("Success message: " . $messageSuccess);
        logDebug("Invalid message: " . $messageInvalid);
        logDebug("Unmet conditions message: " . $messageCondition);
    
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

    $messagesToProcess = array();

    foreach($messageResponse->sms as $item) {
        try{
            $id = $item->id;
            $date = $item->date;
            $did = $item->did;
            $contact = $item->contact;
            $message = trim($item->message);

            $logLine = "Message ID: " . $id;

            $now = new DateTime('now');
            $datetime = new DateTime( $date );
            $diffInSeconds = $now->getTimestamp() - $datetime->getTimestamp();
            $logLine .= "\t" . "Message Age: " . $diffInSeconds;
            $willProcess = false;

            if ($diffInSeconds > $oldestMessageAge){
                $logLine .= "\t" . "Message older than oldest age";
            }
            elseif($datetime <= $lastProcessedMessageDate){
                $logLine .= "\t" . "Message older than last processed message date";
            }
            else {
                $thisMessage = trim(strtoupper($message));

                if (strlen($thisMessage) > 0){
                    array_push($messagesToProcess, $item);
                    $willProcess = true;

                    $logLine .= "\t" . "To be processed!";
                }
                else{
                    $logLine .= "\t" . "Message empty";
                }
            }
            if ($willProcess){
                logInfo($logLine);
            }
            else{
                logDebug($logLine);
            }

            //deleteMessage($id);

        } catch (Exception $e) {
            logInfo('Failed on processing message: ' . $e->getMessage());
        }
    }

    foreach($messagesToProcess as $item) {
        $id = $item->id;
        $date = $item->date;
        $did = $item->did;
        $contact = $item->contact;
        $message = trim($item->message);

        $datetime = new DateTime( $date );

        try{
            saveMessageToCsv($id, $date, $did, $contact, $message);

            processMessage($item);

            if ($datetime > $lastProcessedMessageDate){
                $lastProcessedMessageDate = $datetime;
                logDebug("Setting Last Processed Message Date to " . $lastProcessedMessageDate->format('Y-m-d H:i:s'));
            }
        } catch (Exception $e) {
            logInfo('Failed on processing message: ' . $id . " " . $e->getMessage());
        }
    }
}

function processMessage($smsMessage){
    global $keywordData, $messageSuccess, $messageInvalid, $messageCondition;

    $thisMessage = trim(strtoupper($smsMessage->message));

    $matchedAny = false;
    $allConditionsMetOfAny = false;

    $toExecute = array();

    $fppStatus = getFppStatus();
    $currentStatus = $fppStatus->scheduler->status;
    logDebug("Current status: " . $currentStatus);
    $currentlyPlaying = $fppStatus->current_playlist->playlist;
    logDebug("Currently playing: " . $currentlyPlaying);

    foreach($keywordData as $item) {
        if (strcmp($item["keyword"], $thisMessage) == 0){
            $matchedAny = true;

            $matchesAllConditions = true;

            if (strlen($item["statusCondition"]) > 0){
                if (strcmp($currentStatus, $item["statusCondition"]) != 0){
                    logDebug("Does not match status condition");
                    $matchesAllConditions = false;
                }
            }

            if (strlen($item["playlistCondition"]) > 0){
                if (strcmp($currentlyPlaying, $item["playlistCondition"]) != 0){
                    logDebug("Does not match playlist condition");
                    $matchesAllConditions = false;
                }
            }

            if ($matchesAllConditions){
                $allConditionsMetOfAny = true;

                array_push($toExecute, $item);
            }
        }
    }

    $did = $smsMessage->did;
    $contact = $smsMessage->contact;

    if (!$matchedAny){
        sendMessage($did, $contact, $messageInvalid);
    }
    else{
        if (!$allConditionsMetOfAny){
            sendMessage($did, $contact, $messageCondition);
        }
        else{
            foreach($toExecute as $toExecuteItem) {
                executeKeywordCommand($toExecuteItem);
            }
            sendMessage($did, $contact, $messageSuccess);
        }
    }
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
        
        logDebug("FPP API Response: " . $response);
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
    echo $data . "\n";
}
function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

function saveMessageToCsv($id, $date, $did, $contact, $message) {

    global $messagesCsvFile;
    
    if (!file_exists($messagesCsvFile)) {
        $csvHeaderWrite= fopen($messagesCsvFile, "a") or die("Unable to open file!");
        fwrite($csvHeaderWrite, "id,date,did,contact,message" ."\n");
        fclose($csvHeaderWrite);
    }

    $esc = str_replace("\"","\"\"",$message);
	
	$csvWrite= fopen($messagesCsvFile, "a") or die("Unable to open file!");
	fwrite($csvWrite, $id . "," . $date . "," . $did . "," . $contact . "," . "\"" . $esc . "\"" . "\n");
	fclose($csvWrite);
}
?>
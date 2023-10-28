<?php
include_once "sms-common.php";
include_once "/opt/fpp/www/common.php"; //Alows use of FPP Functions

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$messagesCsvFile = $settings['logDirectory']."/".$pluginName."-messages.csv";
$sleepTime = 5;
$oldestMessageAge = $sleepTime * 4;
$lastProcessedMessageDate = (new DateTime())->setTimestamp(0);

$pluginJson = convertAndGetSettings();
$keywordData = array();

$isEnabled = getBool($pluginJson, "enabled");
$logLevel = getLogLevel($pluginJson);

if($isEnabled == 1) {
    echo "Starting SMS Control Plugin\n";
    logInfo("Starting SMS Control Plugin");
    logInfo("Log Level: " . $logLevel);

    $voipmsApiUsername = returnIfExists($pluginJson, "voipmsApiUsername");
    $voipmsApiPassword = returnIfExists($pluginJson, "voipmsApiPassword");
    $voipmsDid = returnIfExists($pluginJson, "voipmsDid");
    $messageSuccess = returnIfExists($pluginJson, "messageSuccess");
    $messageInvalid = returnIfExists($pluginJson, "messageInvalid");
    $messageCondition = returnIfExists($pluginJson, "messageCondition");

    $messageAppendResponse = getBool($pluginJson, "messageAppendResponse");
    $adminNumbersText = returnIfExists($pluginJson, "adminNumbers");
    
    foreach($pluginJson["keywords"] as $item) {
        $thisKeyword = strtoupper(trim($item["keyword"]));
        logDebug("This Keyword: " . $thisKeyword . "\n");

        if (strlen($thisKeyword) > 0){
            $smsKeywords[$thisKeyword] = $item;
            $item["keyword"] = $thisKeyword;
            array_push($keywordData,$item);
        }
    }
    
    $adminNumbers = array();
    foreach(explode(",", $adminNumbersText) as $an) {
        $tan = trim($an);
        if (strlen($tan) > 0){
            array_push($adminNumbers, $tan);
        }
    }
    logDebug("Admin numbers: " . var_export($adminNumbers, true));
    

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
        logDebug("Append command response: " . ($messageAppendResponse ? 'true' : 'false'));
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
    global $pluginName;

    $messageResponse = getMessages();
    logDebug("API Response Status: " . $messageResponse->status);

    WriteSettingToFile("last_status",urlencode($messageResponse->status),$pluginName."-api-response");

    if ($messageResponse->status == "success"){
        processMessages($messageResponse);
    }
    elseif ($messageResponse->status == "ip_not_enabled") {
        logInfo("WARNING: voip.ms api is not enabled for this IP Address");
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
    global $keywordData, $messageSuccess, $messageAppendResponse, $messageInvalid, $messageCondition, $adminNumbers;

    $thisMessage = trim(strtoupper($smsMessage->message));

    $matchedAny = false;
    $allConditionsMetOfAny = false;

    $toExecute = array();

    $fppStatus = getFppStatus();
    $currentStatus = $fppStatus->scheduler->status;
    logDebug("Current status: " . $currentStatus);
    $currentlyPlaying = $fppStatus->current_playlist->playlist;
    logDebug("Currently playing: " . $currentlyPlaying);

    $contact = $smsMessage->contact;

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

            if (strlen($item["senderCondition"]) > 0){
                if (!in_array($contact, $adminNumbers)){
                    logDebug("Does not match sender condition (" . $contact . ")");
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
    

    if (!$matchedAny){
        sendMessage($did, $contact, $messageInvalid);
    }
    else{
        if (!$allConditionsMetOfAny){
            sendMessage($did, $contact, $messageCondition);
        }
        else{
            $response = "";
            foreach($toExecute as $toExecuteItem) {
                $response = executeKeywordCommand($toExecuteItem);
            }
            $sm = $messageSuccess;
            if ($messageAppendResponse){
                $sm .= " " .  $response;
                $sm = trim($sm);
            }
            sendMessage($did, $contact, $sm);
        }
    }
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

        return $response;
    }
}

function getFppStatus() {
    $result=file_get_contents("http://127.0.0.1/api/fppd/status");
    return json_decode( $result );
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
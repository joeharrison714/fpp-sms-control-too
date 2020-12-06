<?php
include_once "/opt/fpp/www/config.php";

$pluginName = "fpp-sms-control-too";

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

?>

<?php
include_once "Parsedown.php";

$result=file_get_contents("/home/fpp/media/plugins/fpp-sms-control-too/README.md");

$Parsedown = new Parsedown();

$md = $Parsedown->text($result);
?>

<?= $md ?>
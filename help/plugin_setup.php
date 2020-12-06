
<?php
include_once "Parsedown.php";

$result=file_get_contents("https://raw.githubusercontent.com/joeharrison714/fpp-sms-control-too/master/README.md");

$Parsedown = new Parsedown();

$md = $Parsedown->text($result);
?>

<?= $md ?>

<?php
include_once "Parsedown.php";

$result=file_get_contents("../README.md");

$Parsedown = new Parsedown();

$md = $Parsedown->text($result);
?>

<?= $md ?>
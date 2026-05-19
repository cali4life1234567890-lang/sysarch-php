<?php
header('Content-Type: text/plain');
echo shell_exec('git status');
?>

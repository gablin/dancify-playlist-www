<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

header('Location: ./insert-ding/');
?>
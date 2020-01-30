<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

header('Location: ./insert-ding/');
?>
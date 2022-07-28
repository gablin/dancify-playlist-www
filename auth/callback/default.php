<?php
require '../../autoload.php';

// TODO: check for errors

$session = createApiSession();
$session->requestAccessToken($_GET['code']);
updateTokens($session);

// Record login
connectDb();
$api = createWebApi($session);
$user_sql = escapeSqlValue(getThisUserId($api));
queryDb("INSERT INTO logins (user, timestamp) VALUES ('$user_sql', NOW())");

header('Location: /app/');
die();
?>

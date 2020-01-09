<?php
require '../../autoload.php';

// TODO: check for errors

$session = createApiSession();
$session->requestAccessToken($_GET['code']);
updateTokens($session);

header('Location: /app/');
die();
?>

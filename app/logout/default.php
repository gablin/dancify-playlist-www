<?php
require '../../autoload.php';

ensureSession();
closeSession();
header('Location: /');
?>

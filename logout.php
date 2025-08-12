<?php
require_once 'config/config.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->logout();
?>

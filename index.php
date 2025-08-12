<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
//require_once '../../config/config.php';
//require_once '../../config/database.php';

// Si no está logueado, redirigir al login
if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

// Redirigir al dashboard
redirect(SITE_URL . '/dashboard.php');
?>

<?php
// Configuración general del sistema
define('SITE_URL', 'http://localhost/constructora_v2');
define('SITE_NAME', 'Sistema Constructora');
define('SITE_VERSION', '1.0.0');

// Configuración de sesiones (siempre ANTES de session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS
    session_start();
}

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de errores (cambiar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Roles del sistema
define('ROLE_ADMIN', 'administrador');
define('ROLE_RESPONSABLE', 'responsable_obra');
define('ROLE_EMPLEADO', 'empleado');

// Estados generales
define('ESTADO_ACTIVO', 'activo');
define('ESTADO_INACTIVO', 'inactivo');

// Funciones de utilidad
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

function has_permission($required_roles) {
    if (!is_logged_in()) return false;
    
    $user_role = get_user_role();
    if (is_array($required_roles)) {
        return in_array($user_role, $required_roles);
    }
    return $user_role === $required_roles;
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>

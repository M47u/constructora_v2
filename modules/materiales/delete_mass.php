<?php
// Configuración general del sistema
//define('SITE_URL', 'https://pyfsasoftware.com.ar/constructora'); // Descomentar en producción
define('SITE_URL', 'http://localhost/constructora_v2'); //Descomentar en local
define('SITE_NAME', 'Sistema San Simon');
define('SITE_VERSION', '2.0.0');

// Configuración de sesiones (siempre ANTES de session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1); // Cambiado a 1 para HTTPS
    session_start();
}

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');
ini_set('date.timezone', 'America/Argentina/Buenos_Aires');

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

// Funciones de utilidad para fechas y horas
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

function get_current_date($format = 'Y-m-d') {
    return date($format);
}

function get_current_datetime($format = 'Y-m-d H:i:s') {
    return date($format);
}

function is_date_valid($date) {
    return strtotime($date) !== false;
}

function add_days_to_date($date, $days, $format = 'Y-m-d') {
    return date($format, strtotime($date . " +$days days"));
}

function get_date_difference($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

// Incluir configuración y (si aplica) conexión a DB
require_once __DIR__ . '/../../config/config.php';
// require_once __DIR__ . '/../../config/database.php'; // descomentar/ajustar según tu proyecto

header('Content-Type: application/json');

// Comprobar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener payload (soportamos formulario tradicional o JSON)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $input = $json;
    }
}

// Verificar CSRF
$token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Verificar usuario y permisos (ajusta roles según necesites)
if (!is_logged_in() || !has_permission(ROLE_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener IDs
$ids = $input['ids'] ?? [];
if (!is_array($ids) || count($ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron elementos para eliminar']);
    exit;
}

// Sanitizar IDs (enteros únicos)
$ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
$ids = array_unique($ids);
if (count($ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'IDs inválidos']);
    exit;
}

// Intentar eliminar usando PDO o mysqli (adapta si tu proyecto usa otra conexión)
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM materiales WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // mysqli no soporta bind_param dinámico fácilmente; construiremos la query con enteros seguros
        $safeIds = implode(',', $ids);
        $sql = "DELETE FROM materiales WHERE id IN ($safeIds)";
        $res = $conn->query($sql);
        $deleted = ($res === true) ? $conn->affected_rows : 0;
    } else {
        // Si no hay conexión, intenta incluir archivo de conexión (adapta ruta)
        // require_once __DIR__ . '/../../config/database.php';
        echo json_encode(['success' => false, 'message' => 'No hay conexión a la base de datos. Ajusta delete_mass.php para incluir tu DB.']);
        exit;
    }

    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    exit;
}
?>
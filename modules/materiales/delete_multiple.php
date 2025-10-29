<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden eliminar en masa
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect('list.php');
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    redirect('list.php');
}

// Sanitizar IDs
$ids = array_map('intval', $ids);
$ids = array_filter($ids);

if (empty($ids)) {
    redirect('list.php');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "UPDATE materiales SET estado = ? WHERE id_material IN ($placeholders)";
    $stmt = $conn->prepare($query);

    // Primer parámetro es estado, luego los ids
    $params = array_merge([ESTADO_INACTIVO], $ids);
    $stmt->execute($params);

    $_SESSION['success'] = "Se eliminaron " . count($ids) . " materiales exitosamente.";
} catch (Exception $e) {
    error_log("Error al eliminar materiales: " . $e->getMessage());
    $_SESSION['error'] = "Error al eliminar los materiales.";
}

redirect('list.php');
?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Verificar permisos
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

// Verificar método POST y CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect('list.php');
}

$ids = $_POST['ids'] ?? [];
if (empty($ids) || !is_array($ids)) {
    redirect('list.php');
}

// Sanitizar IDs
$ids = array_map('intval', $ids);
$ids = array_filter($ids);

if (!empty($ids)) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Preparar consulta
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $query = "UPDATE materiales SET estado = 'inactivo' WHERE id_material IN ($placeholders)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($ids);
        
        $_SESSION['success'] = "Se eliminaron " . count($ids) . " materiales exitosamente.";
    } catch (Exception $e) {
        error_log("Error al eliminar materiales: " . $e->getMessage());
        $_SESSION['error'] = "Error al eliminar los materiales.";
    }
}

redirect('list.php');

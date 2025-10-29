<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden eliminar
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/modules/materiales/list.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/modules/materiales/list.php');
}

// Verificar CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    // CSRF inválido
    redirect(SITE_URL . '/modules/materiales/list.php?error=csrf');
}

// Obtener IDs seleccionados
$raw_ids = $_POST['selected_ids'] ?? [];
$ids = array_filter(array_map('intval', (array)$raw_ids), function($v){ return $v > 0; });

if (empty($ids)) {
    redirect(SITE_URL . '/modules/materiales/list.php?deleted=0');
}

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM materiales WHERE id_material IN ($placeholders)");
    $stmt->execute($ids);
    $deleted = $stmt->rowCount();
    $conn->commit();
    redirect(SITE_URL . '/modules/materiales/list.php?deleted=' . (int)$deleted);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Error al eliminar materiales: " . $e->getMessage());
    redirect(SITE_URL . '/modules/materiales/list.php?error=delete');
}
?>

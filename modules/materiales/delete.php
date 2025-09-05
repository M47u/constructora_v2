<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden eliminar materiales
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$material_id = (int)($_GET['id'] ?? 0);

if ($material_id <= 0) {
    redirect(SITE_URL . '/modules/materiales/list.php');
}

try {
    // Iniciar transacción
    $conn->beginTransaction();

    // Obtener información del material antes de eliminarlo
    $get_material_info_query = "SELECT id_material, nombre_material, estado FROM materiales WHERE id_material = ?";
    $get_material_info_stmt = $conn->prepare($get_material_info_query);
    $get_material_info_stmt->execute([$material_id]);
    $material_info = $get_material_info_stmt->fetch();

    if (!$material_info) {
        $_SESSION['error_message'] = 'Material no encontrado.';
        $conn->rollBack();
        redirect(SITE_URL . '/modules/materiales/list.php');
    }

    $nombre_material = $material_info['nombre_material'];
    $estado_actual = $material_info['estado'];

    // Verificar si el material ya está inactivo
    if ($estado_actual === 'inactivo') {
        $_SESSION['error_message'] = "El material '{$nombre_material}' ya está inactivo.";
        $conn->rollBack();
        redirect(SITE_URL . '/modules/materiales/list.php');
    }

    // Cambiar estado del material a inactivo (eliminación lógica)
    $update_material_query = "UPDATE materiales SET estado = 'inactivo', fecha_actualizacion = NOW() WHERE id_material = ?";
    $update_material_stmt = $conn->prepare($update_material_query);
    $result = $update_material_stmt->execute([$material_id]);

    if ($result) {
        $conn->commit();
        $_SESSION['success_message'] = "Material '{$nombre_material}' eliminado exitosamente (desactivado).";
    } else {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error al eliminar el material.';
    }
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error al eliminar material: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error interno del servidor al eliminar el material.';
}

redirect(SITE_URL . '/modules/materiales/list.php');
?>
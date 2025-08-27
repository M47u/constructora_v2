<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden eliminar unidades
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$unidad_id = (int)($_GET['id'] ?? 0);

if ($unidad_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

try {
    // Iniciar transacción
    $conn->beginTransaction();

    // Obtener id_herramienta y estado_actual de la unidad antes de eliminarla
    $get_unit_info_query = "SELECT id_herramienta, estado_actual FROM herramientas_unidades WHERE id_unidad = ?";
    $get_unit_info_stmt = $conn->prepare($get_unit_info_query);
    $get_unit_info_stmt->execute([$unidad_id]);
    $unit_info = $get_unit_info_stmt->fetch();

    if (!$unit_info) {
        $_SESSION['error_message'] = 'Unidad de herramienta no encontrada.';
        $conn->rollBack();
        redirect(SITE_URL . '/modules/herramientas/list.php');
    }

    $id_herramienta = $unit_info['id_herramienta'];
    $estado_actual = $unit_info['estado_actual'];

    // Verificar si la unidad está prestada o en mantenimiento
    if ($estado_actual === 'prestada' || $estado_actual === 'mantenimiento') {
        $_SESSION['error_message'] = "No se puede eliminar la unidad porque está actualmente {$estado_actual}. Por favor, cambie su estado antes de eliminarla.";
        $conn->rollBack();
        redirect(SITE_URL . '/modules/herramientas/view.php?id=' . $id_herramienta);
    }

    // Eliminar la unidad individual
    $delete_unit_query = "DELETE FROM herramientas_unidades WHERE id_unidad = ?";
    $delete_unit_stmt = $conn->prepare($delete_unit_query);
    $result = $delete_unit_stmt->execute([$unidad_id]);

    if ($result) {
        // El trigger tr_herramientas_stock_delete se encarga automáticamente de actualizar el stock_total
        $conn->commit();
        $_SESSION['success_message'] = 'Unidad de herramienta eliminada exitosamente.';
    } else {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error al eliminar la unidad de herramienta.';
    }
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error al eliminar unidad de herramienta: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error interno del servidor al eliminar la unidad.';
}

redirect(SITE_URL . '/modules/herramientas/view.php?id=' . $id_herramienta);
?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden eliminar herramientas
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$herramienta_id = (int)($_GET['id'] ?? 0);

if ($herramienta_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

try {
    // Iniciar transacción
    $conn->beginTransaction();

    // 1. Verificar si hay unidades de esta herramienta prestadas o en mantenimiento
    // Esto es crucial para evitar eliminar herramientas que están en uso activo.
    $check_units_query = "SELECT COUNT(*) FROM herramientas_unidades 
                          WHERE id_herramienta = ? AND estado_actual IN ('prestada', 'mantenimiento')";
    $check_units_stmt = $conn->prepare($check_units_query);
    $check_units_stmt->execute([$herramienta_id]);
    $active_units_count = $check_units_stmt->fetchColumn();

    if ($active_units_count > 0) {
        $_SESSION['error_message'] = "No se puede eliminar el tipo de herramienta porque tiene {$active_units_count} unidades actualmente prestadas o en mantenimiento. Por favor, asegúrese de que todas las unidades estén disponibles o fuera de servicio antes de eliminar el tipo.";
        $conn->rollBack();
        redirect(SITE_URL . '/modules/herramientas/list.php');
    }

    // 2. Eliminar unidades individuales asociadas (CASCADE en DB, pero lo hacemos explícito para control)
    $delete_units_query = "DELETE FROM herramientas_unidades WHERE id_herramienta = ?";
    $delete_units_stmt = $conn->prepare($delete_units_query);
    $delete_units_stmt->execute([$herramienta_id]);

    // 3. Eliminar el tipo de herramienta
    $delete_tool_query = "DELETE FROM herramientas WHERE id_herramienta = ?";
    $delete_tool_stmt = $conn->prepare($delete_tool_query);
    $result = $delete_tool_stmt->execute([$herramienta_id]);

    if ($result) {
        $conn->commit();
        $_SESSION['success_message'] = 'Tipo de herramienta y sus unidades eliminados exitosamente.';
    } else {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error al eliminar el tipo de herramienta.';
    }
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error al eliminar herramienta: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error interno del servidor al eliminar la herramienta.';
}

redirect(SITE_URL . '/modules/herramientas/list.php');
?>

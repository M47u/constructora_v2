<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$database = new Database();
$conn = $database->getConnection();

$id_pedido = $_GET['id'] ?? 0;

if (!$id_pedido) {
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

try {
    // Verificar que el pedido existe y está en estado pendiente
    $stmt = $conn->prepare("SELECT id_pedido, estado FROM pedidos_materiales WHERE id_pedido = ?");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        redirect(SITE_URL . '/modules/pedidos/list.php');
    }
    
    if ($pedido['estado'] !== 'pendiente') {
        $_SESSION['error'] = "Solo se pueden eliminar pedidos en estado pendiente.";
        redirect(SITE_URL . '/modules/pedidos/view.php?id=' . $id_pedido);
    }
    
    // Verificar permisos (solo el solicitante o admin puede eliminar)
    $stmt_permisos = $conn->prepare("SELECT id_solicitante FROM pedidos_materiales WHERE id_pedido = ?");
    $stmt_permisos->execute([$id_pedido]);
    $pedido_permisos = $stmt_permisos->fetch();
    
    if ($pedido_permisos['id_solicitante'] != $_SESSION['user_id'] && !has_permission(ROLE_ADMIN)) {
        redirect(SITE_URL . '/modules/pedidos/list.php');
    }
    
    $conn->beginTransaction();
    
    // Eliminar seguimiento
    $stmt_seguimiento = $conn->prepare("DELETE FROM seguimiento_pedidos WHERE id_pedido = ?");
    $stmt_seguimiento->execute([$id_pedido]);
    
    // Eliminar detalles
    $stmt_detalles = $conn->prepare("DELETE FROM detalle_pedido WHERE id_pedido = ?");
    $stmt_detalles->execute([$id_pedido]);
    
    // Eliminar pedido
    $stmt_pedido = $conn->prepare("DELETE FROM pedidos_materiales WHERE id_pedido = ?");
    $stmt_pedido->execute([$id_pedido]);
    
    $conn->commit();
    
    $_SESSION['success'] = "El pedido ha sido eliminado exitosamente.";
    redirect(SITE_URL . '/modules/pedidos/list.php');
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error al eliminar pedido: " . $e->getMessage());
    $_SESSION['error'] = "Error al eliminar el pedido.";
    redirect(SITE_URL . '/modules/pedidos/list.php');
}
?>

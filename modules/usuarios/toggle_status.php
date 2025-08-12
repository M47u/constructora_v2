<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden cambiar estado de usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$id_usuario = $_GET['id'] ?? 0;

if (!$id_usuario) {
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

// No permitir que un usuario se desactive a sí mismo
if ($id_usuario == $_SESSION['user_id']) {
    $_SESSION['error'] = 'No puedes cambiar tu propio estado';
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

try {
    // Obtener estado actual del usuario
    $stmt = $conn->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        $_SESSION['error'] = 'Usuario no encontrado';
        redirect(SITE_URL . '/modules/usuarios/list.php');
    }

    // Cambiar estado
    $nuevo_estado = $usuario['estado'] === 'activo' ? 'inactivo' : 'activo';
    
    $stmt = $conn->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
    $stmt->execute([$nuevo_estado, $id_usuario]);

    $accion = $nuevo_estado === 'activo' ? 'activado' : 'desactivado';
    $_SESSION['success'] = "Usuario $accion exitosamente";

} catch (Exception $e) {
    error_log("Error al cambiar estado del usuario: " . $e->getMessage());
    $_SESSION['error'] = 'Error al cambiar el estado del usuario';
}

redirect(SITE_URL . '/modules/usuarios/list.php');
?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden eliminar usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

$id_usuario = $_GET['id'] ?? 0;

if (!$id_usuario) {
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

// No permitir que un usuario se elimine a sí mismo
if ($id_usuario == $_SESSION['user_id']) {
    $_SESSION['error'] = 'No puedes eliminar tu propia cuenta';
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

try {
    // Verificar si el usuario existe
    $stmt = $conn->prepare("SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        $_SESSION['error'] = 'Usuario no encontrado';
        redirect(SITE_URL . '/modules/usuarios/list.php');
    }

    // Verificar si el usuario tiene registros relacionados
    $stmt_check = $conn->prepare("
        SELECT 
            COUNT(DISTINCT t.id_tarea) as tareas,
            COUNT(DISTINCT p.id_prestamo) as prestamos
        FROM usuarios u
        LEFT JOIN tareas t ON u.id_usuario = t.id_responsable
        LEFT JOIN prestamos_herramientas p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?
    ");
    $stmt_check->execute([$id_usuario]);
    $relaciones = $stmt_check->fetch();

    if ($relaciones['tareas'] > 0 || $relaciones['prestamos'] > 0) {
        $_SESSION['error'] = 'No se puede eliminar el usuario porque tiene registros relacionados (tareas o préstamos)';
        redirect(SITE_URL . '/modules/usuarios/list.php');
    }

    // Eliminar usuario
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);

    $_SESSION['success'] = "Usuario {$usuario['nombre']} {$usuario['apellido']} eliminado exitosamente";

} catch (Exception $e) {
    error_log("Error al eliminar usuario: " . $e->getMessage());
    $_SESSION['error'] = 'Error al eliminar el usuario';
}

redirect(SITE_URL . '/modules/usuarios/list.php');
?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN])) {
    redirect(SITE_URL . '/dashboard.php?error=sin_permisos');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?error=' . urlencode('ID inválido.'));
}

$database = new Database();
$conn = $database->getConnection();

$tarea = $conn->query("SELECT titulo FROM tareas_recurrentes WHERE id_tarea_recurrente = $id")->fetch();
if (!$tarea) {
    redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?error=' . urlencode('Tarea recurrente no encontrada.'));
}

// Check for active (non-finalized) assignments
$activas = (int)$conn->query("
    SELECT COUNT(*) FROM tareas
    WHERE id_tarea_recurrente = $id AND estado IN ('pendiente','en_proceso')
")->fetchColumn();

if ($activas > 0) {
    redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?error=' . urlencode(
        'No se puede eliminar: la tarea tiene ' . $activas . ' asignación(es) activa(s).'
    ));
}

try {
    // Nullify FK in completed tasks before deleting
    $conn->prepare("UPDATE tareas SET id_tarea_recurrente = NULL WHERE id_tarea_recurrente = ?")
         ->execute([$id]);
    $conn->prepare("DELETE FROM tareas_recurrentes WHERE id_tarea_recurrente = ?")
         ->execute([$id]);

    redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?success=' . urlencode('Tarea recurrente eliminada correctamente.'));
} catch (Exception $e) {
    redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
}

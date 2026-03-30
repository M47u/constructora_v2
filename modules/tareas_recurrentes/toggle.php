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

$tarea = $conn->query("SELECT estado FROM tareas_recurrentes WHERE id_tarea_recurrente = $id")->fetch();
if (!$tarea) {
    redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?error=' . urlencode('Tarea recurrente no encontrada.'));
}

$nuevo_estado = ($tarea['estado'] === 'activa') ? 'inactiva' : 'activa';
$conn->prepare("UPDATE tareas_recurrentes SET estado = ? WHERE id_tarea_recurrente = ?")
     ->execute([$nuevo_estado, $id]);

$msg = ($nuevo_estado === 'activa') ? 'Tarea recurrente activada.' : 'Tarea recurrente desactivada.';
redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?success=' . urlencode($msg));

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN])) {
    redirect(SITE_URL . '/dashboard.php?error=sin_permisos');
}

$page_title = 'Tareas Recurrentes';

$database = new Database();
$conn = $database->getConnection();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

try {
    $tareas = $conn->query("
        SELECT tr.*,
               u.nombre AS creador_nombre, u.apellido AS creador_apellido,
               COUNT(t.id_tarea)                                        AS total_asignaciones,
               COUNT(CASE WHEN t.estado = 'finalizada' THEN 1 END)     AS total_finalizadas,
               COUNT(CASE WHEN t.estado IN ('pendiente','en_proceso') THEN 1 END) AS activas_ahora
        FROM tareas_recurrentes tr
        JOIN usuarios u ON u.id_usuario = tr.id_creador
        LEFT JOIN tareas t ON t.id_tarea_recurrente = tr.id_tarea_recurrente
        GROUP BY tr.id_tarea_recurrente
        ORDER BY tr.estado ASC, tr.titulo ASC
    ")->fetchAll();
} catch (Exception $e) {
    $tareas = [];
    $error = 'Error al cargar las tareas recurrentes.';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-arrow-repeat"></i> Tareas Recurrentes</h1>
    <a href="create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nueva Tarea Recurrente
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if ($tareas): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Título</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Asignaciones</th>
                        <th>En curso</th>
                        <th>Creada por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tareas as $t): ?>
                    <tr class="<?php echo $t['estado'] === 'inactiva' ? 'table-secondary' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($t['titulo']); ?></strong>
                            <?php if ($t['descripcion']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($t['descripcion'], 0, 80, '…')); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $p = ['baja' => ['secondary','arrow-down-circle'], 'media' => ['info','dash-circle'], 'alta' => ['warning','arrow-up-circle'], 'urgente' => ['danger','exclamation-triangle']];
                            $pc = $p[$t['prioridad']] ?? ['secondary','dash-circle'];
                            ?>
                            <span class="badge bg-<?php echo $pc[0]; ?>">
                                <i class="bi bi-<?php echo $pc[1]; ?>"></i>
                                <?php echo ucfirst($t['prioridad']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($t['estado'] === 'activa'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Activa</span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-pause-circle"></i> Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?php echo $t['total_asignaciones']; ?></span>
                            <small class="text-muted d-block"><?php echo $t['total_finalizadas']; ?> finalizadas</small>
                        </td>
                        <td class="text-center">
                            <?php if ($t['activas_ahora'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?php echo $t['activas_ahora']; ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($t['creador_nombre'] . ' ' . $t['creador_apellido']); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?php echo $t['id_tarea_recurrente']; ?>"
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="toggle.php?id=<?php echo $t['id_tarea_recurrente']; ?>"
                                   class="btn btn-outline-<?php echo $t['estado'] === 'activa' ? 'warning' : 'success'; ?>"
                                   title="<?php echo $t['estado'] === 'activa' ? 'Desactivar' : 'Activar'; ?>">
                                    <i class="bi bi-<?php echo $t['estado'] === 'activa' ? 'pause-circle' : 'play-circle'; ?>"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $t['id_tarea_recurrente']; ?>"
                                   class="btn btn-outline-danger btn-delete"
                                   data-item-name="la tarea recurrente &quot;<?php echo htmlspecialchars($t['titulo']); ?>&quot;"
                                   title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-arrow-repeat text-muted" style="font-size:3rem;"></i>
            <h5 class="mt-3 text-muted">No hay tareas recurrentes</h5>
            <p class="text-muted">Cree tareas recurrentes para que los usuarios puedan autoasignárselas.</p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Primera Tarea Recurrente
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

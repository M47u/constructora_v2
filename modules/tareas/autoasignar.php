<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Autoasignarme una Tarea Recurrente';

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$errors  = [];
$success = '';

// Handle self-assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $id_tr = (int)($_POST['id_tarea_recurrente'] ?? 0);

        // Validate the recurring task exists and is active
        $tr = $conn->prepare("SELECT * FROM tareas_recurrentes WHERE id_tarea_recurrente = ? AND estado = 'activa'");
        $tr->execute([$id_tr]);
        $tarea_rec = $tr->fetch();

        if (!$tarea_rec) {
            $errors[] = 'Tarea recurrente no válida o inactiva.';
        } else {
            // Check the user doesn't already have this task in pending/in-process
            $existing = $conn->prepare("
                SELECT COUNT(*) FROM tareas
                WHERE id_tarea_recurrente = ? AND id_empleado = ? AND estado IN ('pendiente','en_proceso')
            ");
            $existing->execute([$id_tr, $user_id]);
            if ((int)$existing->fetchColumn() > 0) {
                $errors[] = 'Ya tienes una asignación activa de esta tarea.';
            } else {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO tareas (titulo, descripcion, prioridad, tipo, estado, id_empleado, id_asignador, id_tarea_recurrente)
                        VALUES (?, ?, ?, 'recurrente', 'pendiente', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tarea_rec['titulo'],
                        $tarea_rec['descripcion'],
                        $tarea_rec['prioridad'],
                        $user_id,
                        $user_id,
                        $id_tr,
                    ]);
                    $id_nueva = $conn->lastInsertId();
                    redirect(SITE_URL . '/modules/tareas/view.php?id=' . $id_nueva . '&success=' . urlencode('Tarea asignada correctamente. ¡Mucho éxito!'));
                } catch (Exception $e) {
                    $errors[] = 'Error al asignar la tarea: ' . $e->getMessage();
                }
            }
        }
    }
}

// Load active recurring tasks catalog
$catalogo = $conn->query("
    SELECT tr.*,
           COUNT(t.id_tarea) AS mis_activas
    FROM tareas_recurrentes tr
    LEFT JOIN tareas t ON t.id_tarea_recurrente = tr.id_tarea_recurrente
                       AND t.id_empleado = $user_id
                       AND t.estado IN ('pendiente','en_proceso')
    WHERE tr.estado = 'activa'
    GROUP BY tr.id_tarea_recurrente
    ORDER BY tr.prioridad DESC, tr.titulo ASC
")->fetchAll();

$prioridad_cfg = [
    'baja'    => ['secondary', 'arrow-down-circle'],
    'media'   => ['info',      'dash-circle'],
    'alta'    => ['warning',   'arrow-up-circle'],
    'urgente' => ['danger',    'exclamation-triangle'],
];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-lightning-charge"></i> Autoasignarme una Tarea Recurrente</h1>
    <a href="list.php" class="btn btn-outline-secondary">
        <i class="bi bi-list-task"></i> Mis Tareas
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
        <li><?php echo htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Seleccioná una tarea del catálogo para autoasignártela. Una vez asignada, aparecerá en tu lista de tareas y podrás marcarla como finalizada cuando la completes.
</div>

<?php if ($catalogo): ?>
<form method="POST" id="formAutoasignar">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="id_tarea_recurrente" id="id_tarea_recurrente" value="">

    <div class="row g-3">
        <?php foreach ($catalogo as $tr): ?>
        <?php $pc = $prioridad_cfg[$tr['prioridad']] ?? ['secondary', 'dash-circle']; ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 <?php echo $tr['mis_activas'] > 0 ? 'border-warning' : ''; ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0"><?php echo htmlspecialchars($tr['titulo']); ?></h6>
                        <span class="badge bg-<?php echo $pc[0]; ?> ms-2 flex-shrink-0">
                            <i class="bi bi-<?php echo $pc[1]; ?>"></i>
                            <?php echo ucfirst($tr['prioridad']); ?>
                        </span>
                    </div>

                    <?php if ($tr['descripcion']): ?>
                    <p class="card-text text-muted small flex-grow-1">
                        <?php echo htmlspecialchars($tr['descripcion']); ?>
                    </p>
                    <?php else: ?>
                    <div class="flex-grow-1"></div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <?php if ($tr['mis_activas'] > 0): ?>
                        <span class="badge bg-warning text-dark w-100 py-2">
                            <i class="bi bi-clock"></i> Ya tenés esta tarea activa
                        </span>
                        <?php else: ?>
                        <button type="button" class="btn btn-primary w-100 btn-asignar"
                                data-id="<?php echo $tr['id_tarea_recurrente']; ?>"
                                data-titulo="<?php echo htmlspecialchars($tr['titulo']); ?>">
                            <i class="bi bi-plus-circle"></i> Asignarme esta tarea
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</form>

<?php else: ?>
<div class="text-center py-5">
    <i class="bi bi-arrow-repeat text-muted" style="font-size:3rem;"></i>
    <h5 class="mt-3 text-muted">No hay tareas recurrentes disponibles</h5>
    <p class="text-muted">El administrador aún no ha creado tareas recurrentes activas.</p>
</div>
<?php endif; ?>

<!-- Confirmation Modal -->
<div class="modal fade" id="modalConfirmar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-lightning-charge"></i> Confirmar autoasignación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                ¿Querés asignarte la tarea <strong id="modalTituloTarea"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmar">
                    <i class="bi bi-check-circle"></i> Sí, asignarme
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-asignar').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('modalTituloTarea').textContent = '"' + this.dataset.titulo + '"';
        document.getElementById('id_tarea_recurrente').value = this.dataset.id;
        new bootstrap.Modal(document.getElementById('modalConfirmar')).show();
    });
});
document.getElementById('btnConfirmar')?.addEventListener('click', function () {
    document.getElementById('formAutoasignar').submit();
});
</script>

<?php include '../../includes/footer.php'; ?>

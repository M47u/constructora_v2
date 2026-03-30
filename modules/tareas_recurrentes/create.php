<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN])) {
    redirect(SITE_URL . '/dashboard.php?error=sin_permisos');
}

$page_title = 'Nueva Tarea Recurrente';

$database = new Database();
$conn = $database->getConnection();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $titulo      = trim(sanitize_input($_POST['titulo'] ?? ''));
        $descripcion = trim(sanitize_input($_POST['descripcion'] ?? ''));
        $prioridad   = sanitize_input($_POST['prioridad'] ?? 'media');
        $estado      = sanitize_input($_POST['estado'] ?? 'activa');

        if (empty($titulo)) {
            $errors[] = 'El título es obligatorio.';
        }
        if (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'])) {
            $errors[] = 'Prioridad inválida.';
        }
        if (!in_array($estado, ['activa', 'inactiva'])) {
            $errors[] = 'Estado inválido.';
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO tareas_recurrentes (titulo, descripcion, prioridad, estado, id_creador)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$titulo, $descripcion ?: null, $prioridad, $estado, $_SESSION['user_id']]);
                redirect(SITE_URL . '/modules/tareas_recurrentes/list.php?success=' . urlencode('Tarea recurrente creada correctamente.'));
            } catch (Exception $e) {
                $errors[] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-plus-circle"></i> Nueva Tarea Recurrente</h1>
    <a href="list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
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

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

            <div class="mb-3">
                <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="titulo" name="titulo" maxlength="200" required
                       value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>">
                <div class="form-text">Nombre de la actividad (ej. "Barrer depósito", "Limpieza de herramientas")</div>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                          placeholder="Detalle de la tarea..."><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="prioridad" class="form-label">Prioridad</label>
                    <select class="form-select" id="prioridad" name="prioridad">
                        <?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'] as $v => $l): ?>
                        <option value="<?php echo $v; ?>" <?php echo (($_POST['prioridad'] ?? 'media') === $v) ? 'selected' : ''; ?>>
                            <?php echo $l; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="activa"    <?php echo (($_POST['estado'] ?? 'activa') === 'activa')    ? 'selected' : ''; ?>>Activa</option>
                        <option value="inactiva"  <?php echo (($_POST['estado'] ?? '') === 'inactiva') ? 'selected' : ''; ?>>Inactiva</option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar
                </button>
                <a href="list.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

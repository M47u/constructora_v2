<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$id_transporte = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_transporte <= 0) {
    redirect(SITE_URL . '/modules/transportes/list.php');
}

$page_title = 'Baja de Transporte';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

try {
    $stmt_t = $conn->prepare("SELECT id_transporte, marca, modelo, matricula, estado, observaciones FROM transportes WHERE id_transporte = ?");
    $stmt_t->execute([$id_transporte]);
    $transporte = $stmt_t->fetch();

    if (!$transporte) {
        redirect(SITE_URL . '/modules/transportes/list.php');
    }
} catch (Exception $e) {
    error_log('Error al cargar transporte para baja: ' . $e->getMessage());
    redirect(SITE_URL . '/modules/transportes/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad invalido';
    } else {
        $motivo = sanitize_input($_POST['motivo_baja'] ?? '');

        try {
            $observacion_baja = '';
            if ($motivo !== '') {
                $observacion_baja = '[BAJA ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['user_name'] ?? 'usuario') . '] ' . $motivo;
            } else {
                $observacion_baja = '[BAJA ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['user_name'] ?? 'usuario') . '] Vehiculo fuera de servicio.';
            }

            $observaciones_nuevas = $observacion_baja;
            if (!empty($transporte['observaciones'])) {
                $observaciones_nuevas = $transporte['observaciones'] . "\n" . $observacion_baja;
            }

            $query = "UPDATE transportes SET estado = 'fuera_servicio', observaciones = ? WHERE id_transporte = ?";
            $stmt = $conn->prepare($query);
            $ok = $stmt->execute([$observaciones_nuevas, $id_transporte]);

            if ($ok) {
                $success_message = 'El vehiculo fue marcado como fuera de servicio';
                $stmt_t->execute([$id_transporte]);
                $transporte = $stmt_t->fetch();
            } else {
                $errors[] = 'No se pudo aplicar la baja';
            }
        } catch (Exception $e) {
            error_log('Error al dar de baja transporte: ' . $e->getMessage());
            $errors[] = 'Error interno del servidor';
        }
    }
}

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0"><i class="bi bi-slash-circle"></i> Baja de Transporte</h1>
        <div class="d-flex gap-2">
            <a href="view.php?id=<?php echo (int)$id_transporte; ?>" class="btn btn-outline-info">
                <i class="bi bi-eye"></i> Ver
            </a>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Error:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
    <div class="mt-2">
        <a href="list.php" class="btn btn-sm btn-success">Ir al listado</a>
        <a href="view.php?id=<?php echo (int)$id_transporte; ?>" class="btn btn-sm btn-outline-success">Ver detalle</a>
    </div>
</div>
<?php endif; ?>

<div class="card border-danger">
    <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle"></i> Confirmar baja logica
    </div>
    <div class="card-body">
        <p class="mb-2">
            Vas a marcar como <strong>fuera de servicio</strong> el vehiculo:
        </p>
        <ul class="mb-3">
            <li><strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($transporte['marca'] . ' ' . $transporte['modelo']); ?></li>
            <li><strong>Matricula:</strong> <?php echo htmlspecialchars($transporte['matricula']); ?></li>
            <li><strong>Estado actual:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $transporte['estado']))); ?></li>
        </ul>

        <?php if (($transporte['estado'] ?? '') === 'fuera_servicio'): ?>
        <div class="alert alert-warning mb-3">
            <i class="bi bi-info-circle"></i> Este vehiculo ya se encuentra fuera de servicio.
        </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('¿Confirmas dejar este vehiculo fuera de servicio?');">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="mb-3">
                <label for="motivo_baja" class="form-label">Motivo de baja (opcional)</label>
                <textarea class="form-control" id="motivo_baja" name="motivo_baja" rows="3" placeholder="Ejemplo: rotura de motor, siniestro, baja definitiva"></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-slash-circle"></i> Dejar fuera de servicio
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

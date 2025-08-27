<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden actualizar el estado de unidades
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Actualizar Estado de Unidad';

$database = new Database();
$conn = $database->getConnection();

$unidad_id = (int)($_GET['id'] ?? 0);

if ($unidad_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

$errors = [];
$success_message = '';

try {
    // Obtener datos de la unidad y su herramienta
    $query = "SELECT hu.*, h.marca, h.modelo, h.id_herramienta
              FROM herramientas_unidades hu
              JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
              WHERE hu.id_unidad = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$unidad_id]);
    $unidad = $stmt->fetch();

    if (!$unidad) {
        redirect(SITE_URL . '/modules/herramientas/list.php');
    }

} catch (Exception $e) {
    error_log("Error al obtener unidad para actualizar estado: " . $e->getMessage());
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        $nuevo_estado = $_POST['estado_actual'];
        $observaciones = sanitize_input($_POST['observaciones']); // Se podría agregar un campo de observaciones a la tabla de unidades si se desea

        // Validaciones
        if (!in_array($nuevo_estado, ['disponible', 'prestada', 'mantenimiento', 'perdida', 'dañada'])) {
            $errors[] = 'Estado inválido';
        }

        // El trigger tr_herramientas_stock_update se encarga automáticamente de actualizar el stock_total
        // cuando cambia el estado de una unidad

        // Si no hay errores, actualizar en la base de datos
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                $query = "UPDATE herramientas_unidades SET 
                         estado_actual = ?
                         WHERE id_unidad = ?";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $nuevo_estado, 
                    $unidad_id
                ]);

                if ($result) {
                    $conn->commit();
                    $success_message = 'Estado de la unidad actualizado exitosamente';
                    // Actualizar datos locales
                    $unidad['estado_actual'] = $nuevo_estado;
                } else {
                    $conn->rollBack();
                    $errors[] = 'Error al actualizar el estado de la unidad';
                }
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error al actualizar estado de unidad: " . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }
    }
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-arrow-clockwise"></i> Actualizar Estado de Unidad
            </h1>
            <div>
                <a href="view.php?id=<?php echo $unidad['id_herramienta']; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-eye"></i> Ver Herramienta
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
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
        <a href="view.php?id=<?php echo $unidad['id_herramienta']; ?>" class="btn btn-sm btn-success">Ver Herramienta</a>
        <a href="list.php" class="btn btn-sm btn-outline-success">Ver Todas las Herramientas</a>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-qr-code"></i> Unidad: <?php echo htmlspecialchars($unidad['qr_code']); ?> (<?php echo htmlspecialchars($unidad['marca'] . ' ' . $unidad['modelo']); ?>)
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Tipo de Herramienta</h6>
                        <p><?php echo htmlspecialchars($unidad['marca'] . ' ' . $unidad['modelo']); ?></p>
                        
                        <h6 class="text-muted">Código QR</h6>
                        <p class="fs-5"><?php echo htmlspecialchars($unidad['qr_code']); ?></p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Estado Actual</h6>
                        <p>
                            <?php
                            $estado_class = '';
                            switch ($unidad['estado_actual']) {
                                case 'disponible': $estado_class = 'bg-success'; break;
                                case 'prestada': $estado_class = 'bg-warning text-dark'; break;
                                case 'mantenimiento': $estado_class = 'bg-info'; break;
                                case 'perdida': $estado_class = 'bg-danger'; break;
                                case 'dañada': $estado_class = 'bg-danger'; break;
                            }
                            ?>
                            <span class="badge <?php echo $estado_class; ?> fs-6">
                                <?php echo ucfirst(str_replace('_', ' ', $unidad['estado_actual'])); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="estado_actual" class="form-label">Nuevo Estado *</label>
                        <select class="form-select" id="estado_actual" name="estado_actual" required>
                            <option value="disponible" <?php echo $unidad['estado_actual'] === 'disponible' ? 'selected' : ''; ?>>
                                🟢 Disponible
                            </option>
                            <option value="prestada" <?php echo $unidad['estado_actual'] === 'prestada' ? 'selected' : ''; ?>>
                                🟡 Prestada
                            </option>
                            <option value="mantenimiento" <?php echo $unidad['estado_actual'] === 'mantenimiento' ? 'selected' : ''; ?>>
                                🔵 Mantenimiento
                            </option>
                            <option value="perdida" <?php echo $unidad['estado_actual'] === 'perdida' ? 'selected' : ''; ?>>
                                ⚫ Perdida
                            </option>
                            <option value="dañada" <?php echo $unidad['estado_actual'] === 'dañada' ? 'selected' : ''; ?>>
                                🔴 Dañada
                            </option>
                        </select>
                        <div class="invalid-feedback">
                            Por favor seleccione un estado.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones (Opcional)</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                  maxlength="500"></textarea>
                        <div class="form-text">Agregue comentarios sobre el cambio de estado.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?php echo $unidad['id_herramienta']; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Guía de Estados
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-success">🟢 Disponible</span>
                    <p class="small text-muted mt-1">Lista para ser prestada o utilizada.</p>
                </div>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark">🟡 Prestada</span>
                    <p class="small text-muted mt-1">Actualmente en uso en una obra o por un empleado.</p>
                </div>
                <div class="mb-3">
                    <span class="badge bg-info">🔵 Mantenimiento</span>
                    <p class="small text-muted mt-1">Requiere reparación o revisión.</p>
                </div>
                <div class="mb-3">
                    <span class="badge bg-danger">🔴 Dañada</span>
                    <p class="small text-muted mt-1">No funcional, requiere reparación mayor o descarte.</p>
                </div>
                <div class="mb-3">
                    <span class="badge bg-dark">⚫ Perdida</span>
                    <p class="small text-muted mt-1">No se encuentra en el inventario.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

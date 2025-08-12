<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden crear devoluciones
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Registrar Devolución de Herramientas';

$database = new Database();
$conn = $database->getConnection();

$prestamo_id = (int)($_GET['prestamo_id'] ?? 0);

if ($prestamo_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/prestamos.php');
}

$errors = [];
$success_message = '';

try {
    // Obtener datos del préstamo
    $query_prestamo = "SELECT p.*, 
                              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido,
                              obra.nombre_obra
                       FROM prestamos p 
                       JOIN usuarios emp ON p.id_empleado = emp.id_usuario
                       JOIN obras obra ON p.id_obra = obra.id_obra
                       WHERE p.id_prestamo = ?";
    
    $stmt_prestamo = $conn->prepare($query_prestamo);
    $stmt_prestamo->execute([$prestamo_id]);
    $prestamo = $stmt_prestamo->fetch();

    if (!$prestamo) {
        redirect(SITE_URL . '/modules/herramientas/prestamos.php');
    }

    // Verificar si ya hay una devolución registrada para este préstamo
    $check_devolucion_query = "SELECT id_devolucion FROM devoluciones WHERE id_prestamo = ?";
    $check_devolucion_stmt = $conn->prepare($check_devolucion_query);
    $check_devolucion_stmt->execute([$prestamo_id]);
    if ($check_devolucion_stmt->rowCount() > 0) {
        $_SESSION['error_message'] = 'Ya existe una devolución registrada para este préstamo.';
        redirect(SITE_URL . '/modules/herramientas/view_prestamo.php?id=' . $prestamo_id);
    }

    // Obtener las herramientas que fueron prestadas en este préstamo y que aún están 'prestada'
    $query_herramientas_prestadas = "SELECT dp.id_unidad, hu.qr_code, h.marca, h.modelo, dp.condicion_retiro
                                     FROM detalle_prestamo dp
                                     JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
                                     JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                                     WHERE dp.id_prestamo = ? AND hu.estado_actual = 'prestada'";
    
    $stmt_herramientas_prestadas = $conn->prepare($query_herramientas_prestadas);
    $stmt_herramientas_prestadas->execute([$prestamo_id]);
    $herramientas_prestadas = $stmt_herramientas_prestadas->fetchAll();

    if (empty($herramientas_prestadas)) {
        $_SESSION['info_message'] = 'Todas las herramientas de este préstamo ya han sido devueltas o su estado ha sido actualizado.';
        redirect(SITE_URL . '/modules/herramientas/view_prestamo.php?id=' . $prestamo_id);
    }

} catch (Exception $e) {
    error_log("Error al cargar datos para devolución: " . $e->getMessage());
    $errors[] = 'Error al cargar datos necesarios para el formulario.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $observaciones_devolucion = sanitize_input($_POST['observaciones_devolucion']);
        $unidades_devueltas = $_POST['unidades_devueltas'] ?? [];
        $condicion_devolucion = $_POST['condicion_devolucion'] ?? [];
        $observaciones_unidad = $_POST['observaciones_unidad'] ?? [];

        // Validaciones
        if (empty($unidades_devueltas)) {
            $errors[] = 'Debe seleccionar al menos una herramienta para devolver';
        } else {
            foreach ($unidades_devueltas as $unidad_id) {
                if (!isset($condicion_devolucion[$unidad_id]) || empty($condicion_devolucion[$unidad_id])) {
                    $errors[] = 'Debe especificar la condición de devolución para todas las herramientas seleccionadas.';
                    break;
                }
                if (!in_array($condicion_devolucion[$unidad_id], ['excelente', 'buena', 'regular', 'mala', 'dañada', 'perdida'])) {
                    $errors[] = 'Condición de devolución inválida para alguna herramienta.';
                    break;
                }
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // 1. Insertar la devolución principal
                $query_devolucion = "INSERT INTO devoluciones (id_prestamo, id_recibido_por, observaciones_devolucion) 
                                     VALUES (?, ?, ?)";
                $stmt_devolucion = $conn->prepare($query_devolucion);
                $result_devolucion = $stmt_devolucion->execute([
                    $prestamo_id, $_SESSION['user_id'], $observaciones_devolucion
                ]);

                if (!$result_devolucion) {
                    throw new Exception('Error al crear el registro de devolución.');
                }
                $id_devolucion = $conn->lastInsertId();

                // 2. Insertar los detalles de la devolución y actualizar el estado de las unidades
                foreach ($unidades_devueltas as $unidad_id) {
                    $condicion = $condicion_devolucion[$unidad_id];
                    $obs_unidad = sanitize_input($observaciones_unidad[$unidad_id] ?? '');
                    
                    // Insertar detalle
                    $query_detalle = "INSERT INTO detalle_devolucion (id_devolucion, id_unidad, condicion_devolucion, observaciones_devolucion) 
                                      VALUES (?, ?, ?, ?)";
                    $stmt_detalle = $conn->prepare($query_detalle);
                    $result_detalle = $stmt_detalle->execute([$id_devolucion, $unidad_id, $condicion, $obs_unidad]);

                    if (!$result_detalle) {
                        throw new Exception('Error al insertar detalle de devolución para unidad ' . $unidad_id);
                    }

                    // Actualizar estado de la unidad
                    $new_unit_status = ($condicion === 'excelente' || $condicion === 'buena' || $condicion === 'regular') ? 'disponible' : $condicion;
                    $query_update_unit = "UPDATE herramientas_unidades SET estado_actual = ? WHERE id_unidad = ?";
                    $stmt_update_unit = $conn->prepare($query_update_unit);
                    $result_update_unit = $stmt_update_unit->execute([$new_unit_status, $unidad_id]);

                    if (!$result_update_unit) {
                        throw new Exception('Error al actualizar estado de unidad ' . $unidad_id);
                    }
                }

                $conn->commit();
                $success_message = 'Devolución de herramientas registrada exitosamente.';
                // Limpiar formulario
                $_POST = [];
                // Redirigir a la vista de la devolución
                redirect(SITE_URL . '/modules/herramientas/view_devolucion.php?id=' . $id_devolucion);

            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error al crear devolución: " . $e->getMessage());
                $errors[] = 'Error interno del servidor: ' . $e->getMessage();
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
                <i class="bi bi-box-arrow-down"></i> Registrar Devolución para Préstamo #<?php echo $prestamo['id_prestamo']; ?>
            </h1>
            <a href="view_prestamo.php?id=<?php echo $prestamo['id_prestamo']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Préstamo
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
        <a href="view_devolucion.php?id=<?php echo $id_devolucion; ?>" class="btn btn-sm btn-success">Ver Devolución</a>
        <a href="prestamos.php" class="btn btn-sm btn-outline-success">Ver Todos los Préstamos</a>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-info-circle"></i> Información del Préstamo
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Empleado que Retiró</h6>
                <p><strong><?php echo htmlspecialchars($prestamo['empleado_nombre'] . ' ' . $prestamo['empleado_apellido']); ?></strong></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Obra Destino</h6>
                <p><strong><?php echo htmlspecialchars($prestamo['nombre_obra']); ?></strong></p>
            </div>
        </div>
        <h6 class="text-muted">Fecha de Retiro</h6>
        <p><?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_retiro'])); ?></p>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-box-arrow-down-right"></i> Registrar Devolución
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="mb-3">
                <label class="form-label">Herramientas a Devolver *</label>
                <?php if (!empty($herramientas_prestadas)): ?>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <?php foreach ($herramientas_prestadas as $item): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body p-3">
                                <div class="form-check">
                                    <input class="form-check-input tool-checkbox" type="checkbox" 
                                           value="<?php echo $item['id_unidad']; ?>" 
                                           id="unidad_dev_<?php echo $item['id_unidad']; ?>" 
                                           name="unidades_devueltas[]"
                                           <?php echo (isset($_POST['unidades_devueltas']) && in_array($item['id_unidad'], $_POST['unidades_devueltas'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="unidad_dev_<?php echo $item['id_unidad']; ?>">
                                        <?php echo htmlspecialchars($item['marca'] . ' ' . $item['modelo']); ?>
                                    </label>
                                    <p class="mb-1 text-muted small">QR: <?php echo htmlspecialchars($item['qr_code']); ?></p>
                                    <p class="mb-1 text-muted small">Condición al retiro: <?php echo ucfirst($item['condicion_retiro']); ?></p>
                                </div>
                                <div class="mt-2 condition-select" style="display: <?php echo (isset($_POST['unidades_devueltas']) && in_array($item['id_unidad'], $_POST['unidades_devueltas'])) ? 'block' : 'none'; ?>;">
                                    <label for="condicion_dev_<?php echo $item['id_unidad']; ?>" class="form-label small mb-1">Condición de Devolución *</label>
                                    <select class="form-select form-select-sm mb-2" 
                                            id="condicion_dev_<?php echo $item['id_unidad']; ?>" 
                                            name="condicion_devolucion[<?php echo $item['id_unidad']; ?>]">
                                        <option value="">Seleccionar</option>
                                        <option value="excelente" <?php echo (isset($_POST['condicion_devolucion'][$item['id_unidad']]) && $_POST['condicion_devolucion'][$item['id_unidad']] === 'excelente') ? 'selected' : ''; ?>>Excelente</option>
                                        <option value="buena" <?php echo (isset($_POST['condicion_devolucion'][$item['id_unidad']]) && $_POST['condicion_devolucion'][$item['id_unidad']] === 'buena') ? 'selected' : ''; ?>>Buena</option>
                                        <option value="regular" <?php echo (isset($_POST['condicion_devolucion'][$item['id_unidad']]) && $_POST['condicion_devolucion'][$item['id_unidad']] === 'regular') ? 'selected' : ''; ?>>Regular</option>
                                        <option value="mala" <?php echo (isset($_POST['condicion_devolucion'][$item['id_unidad']]) && $_POST['condicion_devolucion'][$item['id_unidad']] === 'mala') ? 'selected' : ''; ?>>Mala</option>
                                        <option value="dañada" <?php echo (isset($_POST['condicion_devolucion'][$item['id_unidad']]) && $_POST['condicion_devolucion'][$item['id_unidad']] === 'dañada') ? 'selected' : ''; ?>>Dañada</option>
                                        <option value="perdida" <?php echo (isset($_POST['condicion_devolucion'][$item['id_unidad']]) && $_POST['condicion_devolucion'][$item['id_unidad']] === 'perdida') ? 'selected' : ''; ?>>Perdida</option>
                                    </select>
                                    <label for="obs_unit_<?php echo $item['id_unidad']; ?>" class="form-label small mb-1">Obs. Unidad</label>
                                    <textarea class="form-control form-control-sm" id="obs_unit_<?php echo $item['id_unidad']; ?>" name="observaciones_unidad[<?php echo $item['id_unidad']; ?>]" rows="1" maxlength="255"><?php echo isset($_POST['observaciones_unidad'][$item['id_unidad']]) ? htmlspecialchars($_POST['observaciones_unidad'][$item['id_unidad']]) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> No hay herramientas pendientes de devolución para este préstamo.
                </div>
                <?php endif; ?>
                <div class="invalid-feedback">
                    Por favor seleccione al menos una herramienta y su condición de devolución.
                </div>
            </div>

            <div class="mb-3">
                <label for="observaciones_devolucion" class="form-label">Observaciones Generales de Devolución</label>
                <textarea class="form-control" id="observaciones_devolucion" name="observaciones_devolucion" rows="3" 
                          maxlength="500"><?php echo isset($_POST['observaciones_devolucion']) ? htmlspecialchars($_POST['observaciones_devolucion']) : ''; ?></textarea>
                <div class="form-text">Cualquier detalle adicional sobre la devolución de las herramientas.</div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="view_prestamo.php?id=<?php echo $prestamo['id_prestamo']; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Registrar Devolución
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.tool-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const conditionSelect = this.closest('.card-body').querySelector('.condition-select');
        if (this.checked) {
            conditionSelect.style.display = 'block';
            conditionSelect.querySelector('select').setAttribute('required', 'required');
        } else {
            conditionSelect.style.display = 'none';
            conditionSelect.querySelector('select').removeAttribute('required');
            conditionSelect.querySelector('select').value = ''; // Clear selection
            conditionSelect.querySelector('textarea').value = ''; // Clear observations
        }
    });
});

// Initial check for pre-selected items (e.g., after form submission with errors)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tool-checkbox').forEach(checkbox => {
        const conditionSelect = checkbox.closest('.card-body').querySelector('.condition-select');
        if (checkbox.checked) {
            conditionSelect.style.display = 'block';
            conditionSelect.querySelector('select').setAttribute('required', 'required');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

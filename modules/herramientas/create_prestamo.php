<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden crear préstamos
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nuevo Préstamo de Herramientas';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

// Obtener empleados y obras activas
try {
    $stmt_empleados = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $empleados = $stmt_empleados->fetchAll();

    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras WHERE estado IN ('planificada', 'en_progreso') ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();

    // Obtener herramientas disponibles (unidades)
    $stmt_unidades = $conn->query("SELECT hu.id_unidad, hu.qr_code, h.marca, h.modelo 
                                   FROM herramientas_unidades hu
                                   JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                                   WHERE hu.estado_actual = 'disponible'
                                   ORDER BY h.marca, h.modelo, hu.qr_code");
    $unidades_disponibles = $stmt_unidades->fetchAll();

} catch (Exception $e) {
    error_log("Error al cargar datos para préstamo: " . $e->getMessage());
    $empleados = [];
    $obras = [];
    $unidades_disponibles = [];
    $errors[] = 'Error al cargar datos necesarios para el formulario.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $id_empleado = (int)$_POST['id_empleado'];
        $id_obra = (int)$_POST['id_obra'];
        $observaciones_retiro = sanitize_input($_POST['observaciones_retiro']);
        $unidades_seleccionadas = $_POST['unidades_seleccionadas'] ?? [];
        $condicion_retiro = $_POST['condicion_retiro'] ?? [];

        // Validaciones
        if (empty($id_empleado)) {
            $errors[] = 'Debe seleccionar un empleado';
        }
        if (empty($id_obra)) {
            $errors[] = 'Debe seleccionar una obra';
        }
        if (empty($unidades_seleccionadas)) {
            $errors[] = 'Debe seleccionar al menos una herramienta para prestar';
        } else {
            foreach ($unidades_seleccionadas as $unidad_id) {
                if (!isset($condicion_retiro[$unidad_id]) || empty($condicion_retiro[$unidad_id])) {
                    $errors[] = 'Debe especificar la condición de retiro para todas las herramientas seleccionadas.';
                    break;
                }
                if (!in_array($condicion_retiro[$unidad_id], ['excelente', 'buena', 'regular', 'mala'])) {
                    $errors[] = 'Condición de retiro inválida para alguna herramienta.';
                    break;
                }
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // 1. Insertar el préstamo principal
                $query_prestamo = "INSERT INTO prestamos (id_empleado, id_obra, id_autorizado_por, observaciones_retiro) 
                                   VALUES (?, ?, ?, ?)";
                $stmt_prestamo = $conn->prepare($query_prestamo);
                $result_prestamo = $stmt_prestamo->execute([
                    $id_empleado, $id_obra, $_SESSION['user_id'], $observaciones_retiro
                ]);

                if (!$result_prestamo) {
                    throw new Exception('Error al crear el registro de préstamo.');
                }
                $id_prestamo = $conn->lastInsertId();

                // 2. Insertar los detalles del préstamo y actualizar el estado de las unidades
                foreach ($unidades_seleccionadas as $unidad_id) {
                    $condicion = $condicion_retiro[$unidad_id];
                    
                    // Insertar detalle
                    $query_detalle = "INSERT INTO detalle_prestamo (id_prestamo, id_unidad, condicion_retiro) 
                                      VALUES (?, ?, ?)";
                    $stmt_detalle = $conn->prepare($query_detalle);
                    $result_detalle = $stmt_detalle->execute([$id_prestamo, $unidad_id, $condicion]);

                    if (!$result_detalle) {
                        throw new Exception('Error al insertar detalle de préstamo para unidad ' . $unidad_id);
                    }

                    // Actualizar estado de la unidad a 'prestada'
                    $query_update_unit = "UPDATE herramientas_unidades SET estado_actual = 'prestada' WHERE id_unidad = ?";
                    $stmt_update_unit = $conn->prepare($query_update_unit);
                    $result_update_unit = $stmt_update_unit->execute([$unidad_id]);

                    if (!$result_update_unit) {
                        throw new Exception('Error al actualizar estado de unidad ' . $unidad_id);
                    }
                }

                $conn->commit();
                $success_message = 'Préstamo de herramientas registrado exitosamente.';
                // Limpiar formulario
                $_POST = [];
                // Recargar unidades disponibles
                $stmt_unidades = $conn->query("SELECT hu.id_unidad, hu.qr_code, h.marca, h.modelo 
                                               FROM herramientas_unidades hu
                                               JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                                               WHERE hu.estado_actual = 'disponible'
                                               ORDER BY h.marca, h.modelo, hu.qr_code");
                $unidades_disponibles = $stmt_unidades->fetchAll();

            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error al crear préstamo: " . $e->getMessage());
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
                <i class="bi bi-plus-circle"></i> Nuevo Préstamo de Herramientas
            </h1>
            <a href="prestamos.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Préstamos
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
        <a href="prestamos.php" class="btn btn-sm btn-success">Ver Todos los Préstamos</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Registrar Otro Préstamo</button>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-box-arrow-up-right"></i> Detalles del Préstamo
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_empleado" class="form-label">Empleado que Retira *</label>
                    <select class="form-select" id="id_empleado" name="id_empleado" required>
                        <option value="">Seleccionar empleado</option>
                        <?php foreach ($empleados as $empleado): ?>
                        <option value="<?php echo $empleado['id_usuario']; ?>" 
                                <?php echo (isset($_POST['id_empleado']) && $_POST['id_empleado'] == $empleado['id_usuario']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione el empleado que retira.
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="id_obra" class="form-label">Obra Destino *</label>
                    <select class="form-select" id="id_obra" name="id_obra" required>
                        <option value="">Seleccionar obra</option>
                        <?php foreach ($obras as $obra): ?>
                        <option value="<?php echo $obra['id_obra']; ?>" 
                                <?php echo (isset($_POST['id_obra']) && $_POST['id_obra'] == $obra['id_obra']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione la obra destino.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Herramientas a Prestar *</label>
                <?php if (!empty($unidades_disponibles)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                    <?php foreach ($unidades_disponibles as $unidad): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body p-3">
                                <div class="form-check">
                                    <input class="form-check-input tool-checkbox" type="checkbox" 
                                           value="<?php echo $unidad['id_unidad']; ?>" 
                                           id="unidad_<?php echo $unidad['id_unidad']; ?>" 
                                           name="unidades_seleccionadas[]"
                                           <?php echo (isset($_POST['unidades_seleccionadas']) && in_array($unidad['id_unidad'], $_POST['unidades_seleccionadas'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="unidad_<?php echo $unidad['id_unidad']; ?>">
                                        <?php echo htmlspecialchars($unidad['marca'] . ' ' . $unidad['modelo']); ?>
                                    </label>
                                    <p class="mb-1 text-muted small">QR: <?php echo htmlspecialchars($unidad['qr_code']); ?></p>
                                </div>
                                <div class="mt-2 condition-select" style="display: <?php echo (isset($_POST['unidades_seleccionadas']) && in_array($unidad['id_unidad'], $_POST['unidades_seleccionadas'])) ? 'block' : 'none'; ?>;">
                                    <label for="condicion_<?php echo $unidad['id_unidad']; ?>" class="form-label small mb-1">Condición de Retiro *</label>
                                    <select class="form-select form-select-sm" 
                                            id="condicion_<?php echo $unidad['id_unidad']; ?>" 
                                            name="condicion_retiro[<?php echo $unidad['id_unidad']; ?>]">
                                        <option value="">Seleccionar</option>
                                        <option value="excelente" <?php echo (isset($_POST['condicion_retiro'][$unidad['id_unidad']]) && $_POST['condicion_retiro'][$unidad['id_unidad']] === 'excelente') ? 'selected' : ''; ?>>Excelente</option>
                                        <option value="buena" <?php echo (isset($_POST['condicion_retiro'][$unidad['id_unidad']]) && $_POST['condicion_retiro'][$unidad['id_unidad']] === 'buena') ? 'selected' : ''; ?>>Buena</option>
                                        <option value="regular" <?php echo (isset($_POST['condicion_retiro'][$unidad['id_unidad']]) && $_POST['condicion_retiro'][$unidad['id_unidad']] === 'regular') ? 'selected' : ''; ?>>Regular</option>
                                        <option value="mala" <?php echo (isset($_POST['condicion_retiro'][$unidad['id_unidad']]) && $_POST['condicion_retiro'][$unidad['id_unidad']] === 'mala') ? 'selected' : ''; ?>>Mala</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> No hay herramientas disponibles para prestar en este momento.
                    <br>
                    <a href="list.php" class="alert-link">Ver inventario de herramientas</a>
                </div>
                <?php endif; ?>
                <div class="invalid-feedback">
                    Por favor seleccione al menos una herramienta y su condición.
                </div>
            </div>

            <div class="mb-3">
                <label for="observaciones_retiro" class="form-label">Observaciones de Retiro</label>
                <textarea class="form-control" id="observaciones_retiro" name="observaciones_retiro" rows="3" 
                          maxlength="500"><?php echo isset($_POST['observaciones_retiro']) ? htmlspecialchars($_POST['observaciones_retiro']) : ''; ?></textarea>
                <div class="form-text">Cualquier detalle adicional sobre el retiro de las herramientas.</div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="prestamos.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Registrar Préstamo
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

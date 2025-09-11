<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Editar Obra';

$database = new Database();
$conn = $database->getConnection();

$obra_id = (int)($_GET['id'] ?? 0);

if ($obra_id <= 0) {
    redirect(SITE_URL . '/modules/obras/list.php');
}

// Obtener datos de la obra
try {
    $query = "SELECT * FROM obras WHERE id_obra = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$obra_id]);
    $obra = $stmt->fetch();

    if (!$obra) {
        redirect(SITE_URL . '/modules/obras/list.php');
    }

    // Verificar permisos específicos
    if (!has_permission(ROLE_ADMIN) && $_SESSION['user_id'] != $obra['id_responsable']) {
        redirect(SITE_URL . '/modules/obras/list.php');
    }

    // Obtener responsables
    $stmt_responsables = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE rol IN ('administrador', 'responsable_obra') AND estado = 'activo' ORDER BY nombre, apellido");
    $responsables = $stmt_responsables->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener obra: " . $e->getMessage());
    redirect(SITE_URL . '/modules/obras/list.php');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_obra = trim($_POST['nombre_obra'] ?? '');
    $cliente = trim($_POST['cliente'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $localidad = trim($_POST['localidad'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    $estado = $_POST['estado'] ?? '';
    $id_responsable = (int)($_POST['id_responsable'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    $errors = [];

    // Validaciones
    if (empty($nombre_obra)) {
        $errors[] = "El nombre de la obra es obligatorio";
    }

    if (empty($cliente)) {
        $errors[] = "El cliente es obligatorio";
    }

    if (empty($direccion)) {
        $errors[] = "La dirección es obligatoria";
    }

    if (empty($localidad)) {
        $errors[] = "La localidad es obligatoria";
    }

    if (empty($provincia)) {
        $errors[] = "La provincia es obligatoria";
    }

    if (empty($fecha_inicio)) {
        $errors[] = "La fecha de inicio es obligatoria";
    }

    if (!empty($fecha_fin) && $fecha_fin < $fecha_inicio) {
        $errors[] = "La fecha de fin no puede ser anterior a la fecha de inicio";
    }

    if (!in_array($estado, ['planificada', 'en_progreso', 'finalizada', 'cancelada'])) {
        $errors[] = "Estado inválido";
    }

    if ($id_responsable <= 0) {
        $errors[] = "Debe seleccionar un responsable";
    }

    // Verificar que el responsable existe y está activo
    if (empty($errors)) {
        try {
            $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND rol IN ('administrador', 'responsable_obra') AND estado = 'activo'");
            $stmt_check->execute([$id_responsable]);
            if (!$stmt_check->fetch()) {
                $errors[] = "El responsable seleccionado no es válido";
            }
        } catch (Exception $e) {
            $errors[] = "Error al validar el responsable";
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            $update_query = "UPDATE obras SET 
                           nombre_obra = ?, 
                           cliente = ?, 
                           direccion = ?, 
                           localidad = ?, 
                           provincia = ?, 
                           fecha_inicio = ?, 
                           fecha_fin = ?, 
                           estado = ?, 
                           id_responsable = ?, 
                           observaciones = ?,
                           fecha_modificacion = NOW()
                           WHERE id_obra = ?";

            $stmt_update = $conn->prepare($update_query);
            $stmt_update->execute([
                $nombre_obra,
                $cliente,
                $direccion,
                $localidad,
                $provincia,
                $fecha_inicio,
                $fecha_fin ?: null,
                $estado,
                $id_responsable,
                $observaciones,
                $obra_id
            ]);

            $conn->commit();

            $_SESSION['success_message'] = "Obra actualizada exitosamente";
            redirect(SITE_URL . '/modules/obras/view.php?id=' . $obra_id);

        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error al actualizar obra: " . $e->getMessage());
            $errors[] = "Error al actualizar la obra. Intente nuevamente.";
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
                <i class="bi bi-pencil-square"></i> Editar Obra
            </h1>
            <div>
                <a href="view.php?id=<?php echo $obra['id_obra']; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Ver Detalles
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información de la Obra
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_obra" class="form-label">
                                Nombre de la Obra <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nombre_obra" name="nombre_obra" 
                                   value="<?php echo htmlspecialchars($obra['nombre_obra']); ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="cliente" class="form-label">
                                Cliente <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="cliente" name="cliente" 
                                   value="<?php echo htmlspecialchars($obra['cliente']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="direccion" class="form-label">
                            Dirección <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="direccion" name="direccion" 
                               value="<?php echo htmlspecialchars($obra['direccion']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="localidad" class="form-label">
                                Localidad <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="localidad" name="localidad" 
                                   value="<?php echo htmlspecialchars($obra['localidad']); ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="provincia" class="form-label">
                                Provincia <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="provincia" name="provincia" 
                                   value="<?php echo htmlspecialchars($obra['provincia']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_inicio" class="form-label">
                                Fecha de Inicio <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                   value="<?php echo $obra['fecha_inicio']; ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="fecha_fin" class="form-label">
                                Fecha de Fin
                            </label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                   value="<?php echo $obra['fecha_fin']; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="">Seleccionar estado</option>
                                <option value="planificada" <?php echo $obra['estado'] === 'planificada' ? 'selected' : ''; ?>>Planificada</option>
                                <option value="en_progreso" <?php echo $obra['estado'] === 'en_progreso' ? 'selected' : ''; ?>>En Progreso</option>
                                <option value="finalizada" <?php echo $obra['estado'] === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                <option value="cancelada" <?php echo $obra['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="id_responsable" class="form-label">
                                Responsable <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="id_responsable" name="id_responsable" required>
                                <option value="">Seleccionar responsable</option>
                                <?php foreach ($responsables as $responsable): ?>
                                <option value="<?php echo $responsable['id_usuario']; ?>" 
                                        <?php echo $obra['id_responsable'] == $responsable['id_usuario'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($responsable['nombre'] . ' ' . $responsable['apellido']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" 
                                  placeholder="Observaciones adicionales de la obra..."><?php echo htmlspecialchars($obra['observaciones'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?php echo $obra['id_obra']; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Obra
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información Adicional
            </div>
            <div class="card-body">
                <h6 class="text-muted">Fecha de Creación</h6>
                <p class="mb-3"><?php echo date('d/m/Y H:i', strtotime($obra['fecha_creacion'])); ?></p>

                <?php if ($obra['fecha_modificacion']): ?>
                <h6 class="text-muted">Última Modificación</h6>
                <p class="mb-3"><?php echo date('d/m/Y H:i', strtotime($obra['fecha_modificacion'])); ?></p>
                <?php endif; ?>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Nota:</strong> Los cambios en el estado de la obra pueden afectar los pedidos y préstamos asociados.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación de fechas
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');

    function validarFechas() {
        if (fechaInicio.value && fechaFin.value) {
            if (fechaFin.value < fechaInicio.value) {
                fechaFin.setCustomValidity('La fecha de fin no puede ser anterior a la fecha de inicio');
            } else {
                fechaFin.setCustomValidity('');
            }
        }
    }

    fechaInicio.addEventListener('change', validarFechas);
    fechaFin.addEventListener('change', validarFechas);
});
</script>

<?php include '../../includes/footer.php'; ?>

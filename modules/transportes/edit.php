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

$page_title = 'Editar Transporte';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

try {
    $stmt_users = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $usuarios = $stmt_users->fetchAll();
} catch (Exception $e) {
    $usuarios = [];
}

try {
    $stmt_t = $conn->prepare("SELECT * FROM transportes WHERE id_transporte = ?");
    $stmt_t->execute([$id_transporte]);
    $transporte = $stmt_t->fetch();

    if (!$transporte) {
        redirect(SITE_URL . '/modules/transportes/list.php');
    }
} catch (Exception $e) {
    error_log('Error al cargar transporte para editar: ' . $e->getMessage());
    redirect(SITE_URL . '/modules/transportes/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad invalido';
    } else {
        $marca = sanitize_input($_POST['marca'] ?? '');
        $modelo = sanitize_input($_POST['modelo'] ?? '');
        $matricula = strtoupper(sanitize_input($_POST['matricula'] ?? ''));
        $id_encargado = !empty($_POST['id_encargado']) ? (int)$_POST['id_encargado'] : null;
        $estado = sanitize_input($_POST['estado'] ?? '');

        $anio = !empty($_POST['anio']) ? (int)$_POST['anio'] : null;
        $color = sanitize_input($_POST['color'] ?? '');
        $tipo_vehiculo = sanitize_input($_POST['tipo_vehiculo'] ?? 'camioneta');
        $capacidad_carga = $_POST['capacidad_carga'] !== '' ? (float)$_POST['capacidad_carga'] : null;
        $kilometraje = $_POST['kilometraje'] !== '' ? (int)$_POST['kilometraje'] : 0;
        $fecha_vencimiento_vtv = !empty($_POST['fecha_vencimiento_vtv']) ? $_POST['fecha_vencimiento_vtv'] : null;
        $fecha_vencimiento_seguro = !empty($_POST['fecha_vencimiento_seguro']) ? $_POST['fecha_vencimiento_seguro'] : null;
        $observaciones = sanitize_input($_POST['observaciones'] ?? '');

        if ($marca === '') $errors[] = 'La marca es obligatoria';
        if ($modelo === '') $errors[] = 'El modelo es obligatorio';
        if ($matricula === '') $errors[] = 'La matricula es obligatoria';

        if (!in_array($estado, ['disponible', 'en_uso', 'mantenimiento', 'fuera_servicio'], true)) {
            $errors[] = 'Estado invalido';
        }

        if (!in_array($tipo_vehiculo, ['camion', 'camioneta', 'auto', 'moto', 'otro'], true)) {
            $errors[] = 'Tipo de vehiculo invalido';
        }

        if ($kilometraje < 0) {
            $errors[] = 'El kilometraje no puede ser negativo';
        }

        if (empty($errors)) {
            try {
                $check_query = "SELECT id_transporte FROM transportes WHERE matricula = ? AND id_transporte <> ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$matricula, $id_transporte]);
                if ($check_stmt->rowCount() > 0) {
                    $errors[] = 'Ya existe otro transporte con esa matricula';
                }
            } catch (Exception $e) {
                $errors[] = 'No se pudo validar la matricula';
            }
        }

        if (empty($errors)) {
            try {
                $query = "UPDATE transportes SET
                            marca = ?,
                            modelo = ?,
                            matricula = ?,
                            id_encargado = ?,
                            estado = ?,
                            `año` = ?,
                            color = ?,
                            tipo_vehiculo = ?,
                            capacidad_carga = ?,
                            kilometraje = ?,
                            fecha_vencimiento_vtv = ?,
                            fecha_vencimiento_seguro = ?,
                            observaciones = ?
                          WHERE id_transporte = ?";

                $stmt = $conn->prepare($query);
                $ok = $stmt->execute([
                    $marca,
                    $modelo,
                    $matricula,
                    $id_encargado,
                    $estado,
                    $anio,
                    $color !== '' ? $color : null,
                    $tipo_vehiculo,
                    $capacidad_carga,
                    $kilometraje,
                    $fecha_vencimiento_vtv,
                    $fecha_vencimiento_seguro,
                    $observaciones !== '' ? $observaciones : null,
                    $id_transporte
                ]);

                if ($ok) {
                    $success_message = 'Transporte actualizado exitosamente';
                    $stmt_t->execute([$id_transporte]);
                    $transporte = $stmt_t->fetch();
                } else {
                    $errors[] = 'No se pudo actualizar el transporte';
                }
            } catch (Exception $e) {
                error_log('Error al actualizar transporte: ' . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }
    }
}

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0"><i class="bi bi-pencil"></i> Editar Transporte</h1>
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
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="bi bi-truck"></i> Datos del Transporte</div>
    <div class="card-body">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="marca">Marca *</label>
                    <input class="form-control" type="text" id="marca" name="marca" maxlength="100" required
                           value="<?php echo htmlspecialchars($transporte['marca'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="modelo">Modelo *</label>
                    <input class="form-control" type="text" id="modelo" name="modelo" maxlength="100" required
                           value="<?php echo htmlspecialchars($transporte['modelo'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="matricula">Matricula *</label>
                    <input class="form-control" type="text" id="matricula" name="matricula" maxlength="20" required
                           style="text-transform: uppercase;"
                           value="<?php echo htmlspecialchars($transporte['matricula'] ?? ''); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="id_encargado">Encargado</label>
                    <select class="form-select" id="id_encargado" name="id_encargado">
                        <option value="">Sin encargado asignado</option>
                        <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?php echo (int)$usuario['id_usuario']; ?>"
                            <?php echo ((string)($transporte['id_encargado'] ?? '') === (string)$usuario['id_usuario']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="estado">Estado *</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <?php
                        $estados = ['disponible' => 'Disponible', 'en_uso' => 'En uso', 'mantenimiento' => 'Mantenimiento', 'fuera_servicio' => 'Fuera de servicio'];
                        foreach ($estados as $valor => $label):
                        ?>
                        <option value="<?php echo $valor; ?>" <?php echo (($transporte['estado'] ?? '') === $valor) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="tipo_vehiculo">Tipo vehiculo *</label>
                    <select class="form-select" id="tipo_vehiculo" name="tipo_vehiculo" required>
                        <?php
                        $tipos = ['camion' => 'Camion', 'camioneta' => 'Camioneta', 'auto' => 'Auto', 'moto' => 'Moto', 'otro' => 'Otro'];
                        foreach ($tipos as $valor => $label):
                        ?>
                        <option value="<?php echo $valor; ?>" <?php echo (($transporte['tipo_vehiculo'] ?? 'camioneta') === $valor) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="anio">Ano</label>
                    <input class="form-control" type="number" id="anio" name="anio" min="1950" max="2100"
                           value="<?php echo !empty($transporte['año']) ? (int)$transporte['año'] : ''; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="color">Color</label>
                    <input class="form-control" type="text" id="color" name="color" maxlength="50"
                           value="<?php echo htmlspecialchars($transporte['color'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="capacidad_carga">Capacidad carga (kg)</label>
                    <input class="form-control" type="number" id="capacidad_carga" name="capacidad_carga" min="0" step="0.01"
                           value="<?php echo $transporte['capacidad_carga'] !== null ? htmlspecialchars((string)$transporte['capacidad_carga']) : ''; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="kilometraje">Kilometraje</label>
                    <input class="form-control" type="number" id="kilometraje" name="kilometraje" min="0"
                           value="<?php echo (int)($transporte['kilometraje'] ?? 0); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="fecha_vencimiento_vtv">Vencimiento VTV</label>
                    <input class="form-control" type="date" id="fecha_vencimiento_vtv" name="fecha_vencimiento_vtv"
                           value="<?php echo !empty($transporte['fecha_vencimiento_vtv']) ? htmlspecialchars($transporte['fecha_vencimiento_vtv']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="fecha_vencimiento_seguro">Vencimiento seguro</label>
                    <input class="form-control" type="date" id="fecha_vencimiento_seguro" name="fecha_vencimiento_seguro"
                           value="<?php echo !empty($transporte['fecha_vencimiento_seguro']) ? htmlspecialchars($transporte['fecha_vencimiento_seguro']) : ''; ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="observaciones">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($transporte['observaciones'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('matricula').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});
</script>

<?php include '../../includes/footer.php'; ?>

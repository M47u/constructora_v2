<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden crear obras
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nueva Obra';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

// Obtener responsables disponibles
try {
    $stmt = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE rol IN ('administrador', 'responsable_obra') AND estado = 'activo' ORDER BY nombre, apellido");
    $responsables = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error al obtener responsables: " . $e->getMessage());
    $responsables = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $nombre_obra = sanitize_input($_POST['nombre_obra']);
        $provincia = sanitize_input($_POST['provincia']);
        $localidad = sanitize_input($_POST['localidad']);
        $direccion = sanitize_input($_POST['direccion']);
        $id_responsable = (int)$_POST['id_responsable'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $cliente = sanitize_input($_POST['cliente']);
        $estado = $_POST['estado'];

        // Validaciones
        if (empty($nombre_obra)) {
            $errors[] = 'El nombre de la obra es obligatorio';
        }
        if (empty($provincia)) {
            $errors[] = 'La provincia es obligatoria';
        }
        if (empty($localidad)) {
            $errors[] = 'La localidad es obligatoria';
        }
        if (empty($direccion)) {
            $errors[] = 'La dirección es obligatoria';
        }
        if (empty($id_responsable)) {
            $errors[] = 'Debe seleccionar un responsable';
        }
        if (empty($fecha_inicio)) {
            $errors[] = 'La fecha de inicio es obligatoria';
        }
        if (empty($cliente)) {
            $errors[] = 'El cliente es obligatorio';
        }
        if (!in_array($estado, ['planificada', 'en_progreso', 'finalizada', 'cancelada'])) {
            $errors[] = 'Estado inválido';
        }

        // Validar fechas
        if (!empty($fecha_inicio) && !empty($fecha_fin)) {
            if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
                $errors[] = 'La fecha de fin no puede ser anterior a la fecha de inicio';
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $query = "INSERT INTO obras (nombre_obra, provincia, localidad, direccion, id_responsable, fecha_inicio, fecha_fin, cliente, estado) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $nombre_obra, $provincia, $localidad, $direccion, 
                    $id_responsable, $fecha_inicio, $fecha_fin, $cliente, $estado
                ]);

                if ($result) {
                    $success_message = 'Obra creada exitosamente';
                    // Limpiar formulario
                    $_POST = [];
                } else {
                    $errors[] = 'Error al crear la obra';
                }
            } catch (Exception $e) {
                error_log("Error al crear obra: " . $e->getMessage());
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
                <i class="bi bi-plus-circle"></i> Nueva Obra
            </h1>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Listado
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
        <a href="list.php" class="btn btn-sm btn-success">Ver Todas las Obras</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Crear Otra Obra</button>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-building-add"></i> Información de la Obra
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="nombre_obra" class="form-label">Nombre de la Obra *</label>
                    <input type="text" class="form-control" id="nombre_obra" name="nombre_obra" 
                           value="<?php echo isset($_POST['nombre_obra']) ? htmlspecialchars($_POST['nombre_obra']) : ''; ?>" 
                           required maxlength="200">
                    <div class="invalid-feedback">
                        Por favor ingrese el nombre de la obra.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="estado" class="form-label">Estado *</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="">Seleccionar estado</option>
                        <option value="planificada" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'planificada') ? 'selected' : ''; ?>>Planificada</option>
                        <option value="en_progreso" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'en_progreso') ? 'selected' : ''; ?>>En Progreso</option>
                        <option value="finalizada" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'finalizada') ? 'selected' : ''; ?>>Finalizada</option>
                        <option value="cancelada" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione un estado.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cliente" class="form-label">Cliente *</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" 
                           value="<?php echo isset($_POST['cliente']) ? htmlspecialchars($_POST['cliente']) : ''; ?>" 
                           required maxlength="200">
                    <div class="invalid-feedback">
                        Por favor ingrese el nombre del cliente.
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="id_responsable" class="form-label">Responsable *</label>
                    <select class="form-select" id="id_responsable" name="id_responsable" required>
                        <option value="">Seleccionar responsable</option>
                        <?php foreach ($responsables as $responsable): ?>
                        <option value="<?php echo $responsable['id_usuario']; ?>" 
                                <?php echo (isset($_POST['id_responsable']) && $_POST['id_responsable'] == $responsable['id_usuario']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($responsable['nombre'] . ' ' . $responsable['apellido']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione un responsable.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="provincia" class="form-label">Provincia *</label>
                    <input type="text" class="form-control" id="provincia" name="provincia" 
                           value="<?php echo isset($_POST['provincia']) ? htmlspecialchars($_POST['provincia']) : ''; ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese la provincia.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="localidad" class="form-label">Localidad *</label>
                    <input type="text" class="form-control" id="localidad" name="localidad" 
                           value="<?php echo isset($_POST['localidad']) ? htmlspecialchars($_POST['localidad']) : ''; ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese la localidad.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="direccion" class="form-label">Dirección *</label>
                    <input type="text" class="form-control" id="direccion" name="direccion" 
                           value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>" 
                           required>
                    <div class="invalid-feedback">
                        Por favor ingrese la dirección.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : ''; ?>" 
                           required>
                    <div class="invalid-feedback">
                        Por favor seleccione la fecha de inicio.
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : ''; ?>">
                    <div class="form-text">Opcional - puede completarse más tarde</div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Obra
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

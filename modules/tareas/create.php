<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden crear tareas
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nueva Tarea';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

// Obtener empleados disponibles
try {
    $stmt = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $empleados = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error al obtener empleados: " . $e->getMessage());
    $empleados = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $id_empleado = (int)$_POST['id_empleado'];
        $titulo = sanitize_input($_POST['titulo']);
        $descripcion = sanitize_input($_POST['descripcion']);
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        $prioridad = $_POST['prioridad'];
        $observaciones = sanitize_input($_POST['observaciones']);

        // Validaciones
        if (empty($id_empleado)) {
            $errors[] = 'Debe seleccionar un empleado';
        }
        if (empty($titulo)) {
            $errors[] = 'El título es obligatorio';
        }
        if (empty($descripcion)) {
            $errors[] = 'La descripción es obligatoria';
        }
        if (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'])) {
            $errors[] = 'Prioridad inválida';
        }

        // Validar fecha de vencimiento
        if (!empty($fecha_vencimiento) && strtotime($fecha_vencimiento) < strtotime(date('Y-m-d'))) {
            $errors[] = 'La fecha de vencimiento no puede ser anterior a hoy';
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $query = "INSERT INTO tareas (id_empleado, titulo, descripcion, fecha_vencimiento, prioridad, id_asignador, observaciones) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $id_empleado, $titulo, $descripcion, $fecha_vencimiento, 
                    $prioridad, $_SESSION['user_id'], $observaciones
                ]);

                if ($result) {
                    $success_message = 'Tarea creada y asignada exitosamente';
                    // Limpiar formulario
                    $_POST = [];
                } else {
                    $errors[] = 'Error al crear la tarea';
                }
            } catch (Exception $e) {
                error_log("Error al crear tarea: " . $e->getMessage());
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
                <i class="bi bi-plus-circle"></i> Nueva Tarea
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
        <a href="list.php" class="btn btn-sm btn-success">Ver Todas las Tareas</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Crear Otra Tarea</button>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-clipboard-plus"></i> Información de la Tarea
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="titulo" class="form-label">Título de la Tarea *</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" 
                           value="<?php echo isset($_POST['titulo']) ? htmlspecialchars($_POST['titulo']) : ''; ?>" 
                           required maxlength="200">
                    <div class="invalid-feedback">
                        Por favor ingrese el título de la tarea.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="prioridad" class="form-label">Prioridad *</label>
                    <select class="form-select" id="prioridad" name="prioridad" required>
                        <option value="">Seleccionar prioridad</option>
                        <option value="baja" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'baja') ? 'selected' : ''; ?>>
                            🔵 Baja
                        </option>
                        <option value="media" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'media') ? 'selected' : 'selected'; ?>>
                            🟡 Media
                        </option>
                        <option value="alta" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'alta') ? 'selected' : ''; ?>>
                            🟠 Alta
                        </option>
                        <option value="urgente" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'urgente') ? 'selected' : ''; ?>>
                            🔴 Urgente
                        </option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione una prioridad.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_empleado" class="form-label">Asignar a *</label>
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
                        Por favor seleccione un empleado.
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                    <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" 
                           value="<?php echo isset($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : ''; ?>"
                           min="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text">Opcional - deje vacío si no tiene fecha límite</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción *</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="4" 
                          required maxlength="1000"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                <div class="invalid-feedback">
                    Por favor ingrese la descripción de la tarea.
                </div>
            </div>

            <div class="mb-3">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                          maxlength="500"><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                <div class="form-text">Información adicional o instrucciones especiales</div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Tarea
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

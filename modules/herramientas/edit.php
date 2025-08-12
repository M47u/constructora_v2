<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden editar herramientas
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Editar Tipo de Herramienta';

$database = new Database();
$conn = $database->getConnection();

$herramienta_id = (int)($_GET['id'] ?? 0);

if ($herramienta_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

$errors = [];
$success_message = '';

try {
    // Obtener datos del tipo de herramienta
    $query = "SELECT * FROM herramientas WHERE id_herramienta = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$herramienta_id]);
    $herramienta = $stmt->fetch();

    if (!$herramienta) {
        redirect(SITE_URL . '/modules/herramientas/list.php');
    }
} catch (Exception $e) {
    error_log("Error al obtener herramienta para editar: " . $e->getMessage());
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $marca = sanitize_input($_POST['marca']);
        $modelo = sanitize_input($_POST['modelo']);
        $descripcion = sanitize_input($_POST['descripcion']);
        $condicion_general = $_POST['condicion_general'];

        // Validaciones
        if (empty($marca)) {
            $errors[] = 'La marca es obligatoria';
        }
        if (empty($modelo)) {
            $errors[] = 'El modelo es obligatorio';
        }
        if (empty($descripcion)) {
            $errors[] = 'La descripción es obligatoria';
        }
        if (!in_array($condicion_general, ['excelente', 'buena', 'regular', 'mala'])) {
            $errors[] = 'Condición general inválida';
        }

        // Verificar si el tipo de herramienta ya existe (marca y modelo), excluyendo la herramienta actual
        if (empty($errors)) {
            try {
                $check_query = "SELECT id_herramienta FROM herramientas WHERE marca = ? AND modelo = ? AND id_herramienta != ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$marca, $modelo, $herramienta_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $errors[] = 'Ya existe otro tipo de herramienta con esta marca y modelo';
                }
            } catch (Exception $e) {
                error_log("Error al verificar herramienta duplicada: " . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }

        // Si no hay errores, actualizar en la base de datos
        if (empty($errors)) {
            try {
                $query = "UPDATE herramientas SET 
                         marca = ?, 
                         modelo = ?, 
                         descripcion = ?, 
                         condicion_general = ?,
                         fecha_actualizacion = CURRENT_TIMESTAMP
                         WHERE id_herramienta = ?";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $marca, $modelo, $descripcion, $condicion_general, $herramienta_id
                ]);

                if ($result) {
                    $success_message = 'Tipo de herramienta actualizado exitosamente';
                    // Actualizar datos locales
                    $herramienta['marca'] = $marca;
                    $herramienta['modelo'] = $modelo;
                    $herramienta['descripcion'] = $descripcion;
                    $herramienta['condicion_general'] = $condicion_general;
                } else {
                    $errors[] = 'Error al actualizar el tipo de herramienta';
                }
            } catch (Exception $e) {
                error_log("Error al actualizar herramienta: " . $e->getMessage());
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
                <i class="bi bi-pencil"></i> Editar Tipo de Herramienta
            </h1>
            <div>
                <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-eye"></i> Ver Detalles
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Listado
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
        <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-sm btn-success">Ver Herramienta</a>
        <a href="list.php" class="btn btn-sm btn-outline-success">Ver Todos los Tipos</a>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-tools"></i> Información del Tipo de Herramienta
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="marca" class="form-label">Marca *</label>
                    <input type="text" class="form-control" id="marca" name="marca" 
                           value="<?php echo htmlspecialchars($herramienta['marca']); ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese la marca de la herramienta.
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="modelo" class="form-label">Modelo *</label>
                    <input type="text" class="form-control" id="modelo" name="modelo" 
                           value="<?php echo htmlspecialchars($herramienta['modelo']); ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese el modelo de la herramienta.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción *</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" 
                          required maxlength="500"><?php echo htmlspecialchars($herramienta['descripcion']); ?></textarea>
                <div class="invalid-feedback">
                    Por favor ingrese una descripción.
                </div>
            </div>

            <div class="mb-3">
                <label for="condicion_general" class="form-label">Condición General *</label>
                <select class="form-select" id="condicion_general" name="condicion_general" required>
                    <option value="excelente" <?php echo ($herramienta['condicion_general'] === 'excelente') ? 'selected' : ''; ?>>Excelente</option>
                    <option value="buena" <?php echo ($herramienta['condicion_general'] === 'buena') ? 'selected' : ''; ?>>Buena</option>
                    <option value="regular" <?php echo ($herramienta['condicion_general'] === 'regular') ? 'selected' : ''; ?>>Regular</option>
                    <option value="mala" <?php echo ($herramienta['condicion_general'] === 'mala') ? 'selected' : ''; ?>>Mala</option>
                </select>
                <div class="invalid-feedback">
                    Por favor seleccione la condición general.
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

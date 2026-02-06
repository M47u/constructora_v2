<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden crear herramientas
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nuevo Tipo de Herramienta';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

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
        if (!es_condicion_valida($condicion_general)) {
            $errors[] = 'Condición general inválida';
        }

        // Verificar si el tipo de herramienta ya existe (marca y modelo)
        if (empty($errors)) {
            try {
                $check_query = "SELECT id_herramienta FROM herramientas WHERE marca = ? AND modelo = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$marca, $modelo]);
                
                if ($check_stmt->rowCount() > 0) {
                    $errors[] = 'Ya existe un tipo de herramienta con esta marca y modelo';
                }
            } catch (Exception $e) {
                error_log("Error al verificar herramienta: " . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $query = "INSERT INTO herramientas (marca, modelo, descripcion, condicion_general, stock_total) 
                         VALUES (?, ?, ?, ?, 0)"; // Stock total inicial es 0
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $marca, $modelo, $descripcion, $condicion_general
                ]);

                if ($result) {
                    $success_message = 'Tipo de herramienta creado exitosamente. Ahora puede agregar unidades.';
                    // Limpiar formulario
                    $_POST = [];
                } else {
                    $errors[] = 'Error al crear el tipo de herramienta';
                }
            } catch (Exception $e) {
                error_log("Error al crear herramienta: " . $e->getMessage());
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
                <i class="bi bi-plus-circle"></i> Nuevo Tipo de Herramienta
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
        <a href="list.php" class="btn btn-sm btn-success">Ver Todos los Tipos</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Crear Otro Tipo</button>
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
                           value="<?php echo isset($_POST['marca']) ? htmlspecialchars($_POST['marca']) : ''; ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese la marca de la herramienta.
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="modelo" class="form-label">Modelo *</label>
                    <input type="text" class="form-control" id="modelo" name="modelo" 
                           value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese el modelo de la herramienta.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción *</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" 
                          required maxlength="500"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                <div class="invalid-feedback">
                    Por favor ingrese una descripción. 
                </div>
            </div>

            <div class="mb-3">
                <label for="condicion_general" class="form-label">Condición General *</label>
                <select class="form-select" id="condicion_general" name="condicion_general" required>
                    <option value="">Seleccionar condición</option>
                    <?php foreach (CONDICIONES_HERRAMIENTAS as $codigo => $nombre): ?>
                        <option value="<?php echo $codigo; ?>" 
                                <?php echo (isset($_POST['condicion_general']) && $_POST['condicion_general'] === $codigo) ? 'selected' : ($codigo === 'nueva' ? 'selected' : ''); ?>>
                            <?php echo $nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">
                    Por favor seleccione la condición general.
                </div>
                <small class="form-text text-muted">
                    <i class="bi bi-info-circle"></i> Seleccione "Nueva" si es la primera vez que se registra este tipo de herramienta.
                </small>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Tipo
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

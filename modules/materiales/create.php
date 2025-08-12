<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden crear materiales
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nuevo Material';

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
        $nombre_material = sanitize_input($_POST['nombre_material']);
        $stock_actual = (int)$_POST['stock_actual'];
        $stock_minimo = (int)$_POST['stock_minimo'];
        $precio_referencia = (float)$_POST['precio_referencia'];
        $unidad_medida = sanitize_input($_POST['unidad_medida']);

        // Validaciones
        if (empty($nombre_material)) {
            $errors[] = 'El nombre del material es obligatorio';
        }
        if ($stock_actual < 0) {
            $errors[] = 'El stock actual no puede ser negativo';
        }
        if ($stock_minimo < 0) {
            $errors[] = 'El stock mínimo no puede ser negativo';
        }
        if ($precio_referencia < 0) {
            $errors[] = 'El precio de referencia no puede ser negativo';
        }
        if (empty($unidad_medida)) {
            $errors[] = 'La unidad de medida es obligatoria';
        }

        // Verificar si el material ya existe
        if (empty($errors)) {
            try {
                $check_query = "SELECT id_material FROM materiales WHERE nombre_material = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$nombre_material]);
                
                if ($check_stmt->rowCount() > 0) {
                    $errors[] = 'Ya existe un material con ese nombre';
                }
            } catch (Exception $e) {
                error_log("Error al verificar material: " . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $query = "INSERT INTO materiales (nombre_material, stock_actual, stock_minimo, precio_referencia, unidad_medida) 
                         VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $nombre_material, $stock_actual, $stock_minimo, $precio_referencia, $unidad_medida
                ]);

                if ($result) {
                    $success_message = 'Material creado exitosamente';
                    // Limpiar formulario
                    $_POST = [];
                } else {
                    $errors[] = 'Error al crear el material';
                }
            } catch (Exception $e) {
                error_log("Error al crear material: " . $e->getMessage());
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
                <i class="bi bi-plus-circle"></i> Nuevo Material
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
        <a href="list.php" class="btn btn-sm btn-success">Ver Todos los Materiales</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Crear Otro Material</button>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-box-seam-fill"></i> Información del Material
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="nombre_material" class="form-label">Nombre del Material *</label>
                    <input type="text" class="form-control" id="nombre_material" name="nombre_material" 
                           value="<?php echo isset($_POST['nombre_material']) ? htmlspecialchars($_POST['nombre_material']) : ''; ?>" 
                           required maxlength="200">
                    <div class="invalid-feedback">
                        Por favor ingrese el nombre del material.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="unidad_medida" class="form-label">Unidad de Medida *</label>
                    <select class="form-select" id="unidad_medida" name="unidad_medida" required>
                        <option value="">Seleccionar unidad</option>
                        <option value="unidad" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'unidad') ? 'selected' : ''; ?>>Unidad</option>
                        <option value="kg" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'kg') ? 'selected' : ''; ?>>Kilogramo (kg)</option>
                        <option value="m" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'm') ? 'selected' : ''; ?>>Metro (m)</option>
                        <option value="m2" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'm2') ? 'selected' : ''; ?>>Metro cuadrado (m²)</option>
                        <option value="m3" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'm3') ? 'selected' : ''; ?>>Metro cúbico (m³)</option>
                        <option value="litro" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'litro') ? 'selected' : ''; ?>>Litro</option>
                        <option value="bolsa" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'bolsa') ? 'selected' : ''; ?>>Bolsa</option>
                        <option value="barra" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'barra') ? 'selected' : ''; ?>>Barra</option>
                        <option value="rollo" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'rollo') ? 'selected' : ''; ?>>Rollo</option>
                        <option value="caja" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] === 'caja') ? 'selected' : ''; ?>>Caja</option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione una unidad de medida.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="stock_actual" class="form-label">Stock Actual *</label>
                    <input type="number" class="form-control" id="stock_actual" name="stock_actual" 
                           value="<?php echo isset($_POST['stock_actual']) ? $_POST['stock_actual'] : '0'; ?>" 
                           required min="0" step="1">
                    <div class="invalid-feedback">
                        Por favor ingrese el stock actual.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="stock_minimo" class="form-label">Stock Mínimo *</label>
                    <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" 
                           value="<?php echo isset($_POST['stock_minimo']) ? $_POST['stock_minimo'] : '0'; ?>" 
                           required min="0" step="1">
                    <div class="invalid-feedback">
                        Por favor ingrese el stock mínimo.
                    </div>
                    <div class="form-text">Cantidad mínima para generar alertas de stock bajo</div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="precio_referencia" class="form-label">Precio de Referencia *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="precio_referencia" name="precio_referencia" 
                               value="<?php echo isset($_POST['precio_referencia']) ? $_POST['precio_referencia'] : '0.00'; ?>" 
                               required min="0" step="0.01">
                        <div class="invalid-feedback">
                            Por favor ingrese el precio de referencia.
                        </div>
                    </div>
                    <div class="form-text">Precio unitario de referencia para cálculos</div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Material
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden ajustar stock
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Ajustar Stock';

$database = new Database();
$conn = $database->getConnection();

$material_id = (int)($_GET['id'] ?? 0);

if ($material_id <= 0) {
    redirect(SITE_URL . '/modules/materiales/list.php');
}

$errors = [];
$success_message = '';

try {
    // Obtener datos del material
    $query = "SELECT * FROM materiales WHERE id_material = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();

    if (!$material) {
        redirect(SITE_URL . '/modules/materiales/list.php');
    }
} catch (Exception $e) {
    error_log("Error al obtener material: " . $e->getMessage());
    redirect(SITE_URL . '/modules/materiales/list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        $tipo_ajuste = $_POST['tipo_ajuste'];
        $cantidad = (int)$_POST['cantidad'];
        $motivo = sanitize_input($_POST['motivo']);

        // Validaciones
        if (!in_array($tipo_ajuste, ['entrada', 'salida', 'ajuste'])) {
            $errors[] = 'Tipo de ajuste inválido';
        }
        if ($cantidad <= 0) {
            $errors[] = 'La cantidad debe ser mayor a cero';
        }
        if (empty($motivo)) {
            $errors[] = 'El motivo es obligatorio';
        }

        // Calcular nuevo stock
        $nuevo_stock = $material['stock_actual'];
        switch ($tipo_ajuste) {
            case 'entrada':
                $nuevo_stock += $cantidad;
                break;
            case 'salida':
                $nuevo_stock -= $cantidad;
                if ($nuevo_stock < 0) {
                    $errors[] = 'No hay suficiente stock para realizar esta salida';
                }
                break;
            case 'ajuste':
                $nuevo_stock = $cantidad;
                break;
        }

        // Si no hay errores, actualizar en la base de datos
        if (empty($errors)) {
            try {
                $query = "UPDATE materiales SET stock_actual = ? WHERE id_material = ?";
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([$nuevo_stock, $material_id]);

                if ($result) {
                    $success_message = 'Stock ajustado exitosamente';
                    // Actualizar datos locales
                    $material['stock_actual'] = $nuevo_stock;
                    // Limpiar formulario
                    $_POST = [];
                } else {
                    $errors[] = 'Error al ajustar el stock';
                }
            } catch (Exception $e) {
                error_log("Error al ajustar stock: " . $e->getMessage());
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
                <i class="bi bi-arrow-up-down"></i> Ajustar Stock
            </h1>
            <div>
                <a href="view.php?id=<?php echo $material['id_material']; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Ver Material
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
        <a href="view.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-success">Ver Material</a>
        <a href="list.php" class="btn btn-sm btn-outline-success">Ver Todos los Materiales</a>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-box-seam"></i> Material: <?php echo htmlspecialchars($material['nombre_material']); ?>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Stock Actual</h6>
                        <p class="fs-3 <?php echo $material['stock_actual'] <= $material['stock_minimo'] ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($material['stock_actual']); ?>
                            <small class="text-muted"><?php echo htmlspecialchars($material['unidad_medida']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Stock Mínimo</h6>
                        <p class="fs-4 text-warning">
                            <?php echo number_format($material['stock_minimo']); ?>
                            <small class="text-muted"><?php echo htmlspecialchars($material['unidad_medida']); ?></small>
                        </p>
                    </div>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_ajuste" class="form-label">Tipo de Ajuste *</label>
                            <select class="form-select" id="tipo_ajuste" name="tipo_ajuste" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="entrada" <?php echo (isset($_POST['tipo_ajuste']) && $_POST['tipo_ajuste'] === 'entrada') ? 'selected' : ''; ?>>
                                    ➕ Entrada (Agregar stock)
                                </option>
                                <option value="salida" <?php echo (isset($_POST['tipo_ajuste']) && $_POST['tipo_ajuste'] === 'salida') ? 'selected' : ''; ?>>
                                    ➖ Salida (Reducir stock)
                                </option>
                                <option value="ajuste" <?php echo (isset($_POST['tipo_ajuste']) && $_POST['tipo_ajuste'] === 'ajuste') ? 'selected' : ''; ?>>
                                    🔄 Ajuste (Establecer cantidad exacta)
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione un tipo de ajuste.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cantidad" class="form-label">Cantidad *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="cantidad" name="cantidad" 
                                       value="<?php echo isset($_POST['cantidad']) ? $_POST['cantidad'] : ''; ?>" 
                                       required min="1" step="1">
                                <span class="input-group-text"><?php echo htmlspecialchars($material['unidad_medida']); ?></span>
                                <div class="invalid-feedback">
                                    Por favor ingrese la cantidad.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo del Ajuste *</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" 
                                  required maxlength="500"><?php echo isset($_POST['motivo']) ? htmlspecialchars($_POST['motivo']) : ''; ?></textarea>
                        <div class="invalid-feedback">
                            Por favor ingrese el motivo del ajuste.
                        </div>
                        <div class="form-text">Explique la razón del ajuste de stock</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?php echo $material['id_material']; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-circle"></i> Ajustar Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Guía de Ajustes
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-success">➕ Entrada</h6>
                    <p class="small text-muted">Suma la cantidad al stock actual. Usar para:</p>
                    <ul class="small text-muted">
                        <li>Compras nuevas</li>
                        <li>Devoluciones de obra</li>
                        <li>Correcciones por faltante</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-danger">➖ Salida</h6>
                    <p class="small text-muted">Resta la cantidad del stock actual. Usar para:</p>
                    <ul class="small text-muted">
                        <li>Consumo en obra</li>
                        <li>Material dañado</li>
                        <li>Pérdidas</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-info">🔄 Ajuste</h6>
                    <p class="small text-muted">Establece la cantidad exacta. Usar para:</p>
                    <ul class="small text-muted">
                        <li>Inventario físico</li>
                        <li>Corrección de errores</li>
                        <li>Recuento general</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning" role="alert">
                    <small>
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Importante:</strong> Los ajustes de stock son permanentes y afectan los cálculos de inventario.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('tipo_ajuste').addEventListener('change', function() {
    const cantidad = document.getElementById('cantidad');
    const stockActual = <?php echo $material['stock_actual']; ?>;
    
    if (this.value === 'ajuste') {
        cantidad.placeholder = 'Cantidad final deseada';
        cantidad.value = stockActual;
    } else {
        cantidad.placeholder = 'Cantidad a ' + (this.value === 'entrada' ? 'agregar' : 'reducir');
        cantidad.value = '';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

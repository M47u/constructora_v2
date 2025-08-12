<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Editar Pedido';

$database = new Database();
$conn = $database->getConnection();

$id_pedido = $_GET['id'] ?? 0;
$errors = [];
$success = false;

if (!$id_pedido) {
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_obra = sanitize_input($_POST['id_obra']);
    $observaciones = sanitize_input($_POST['observaciones']);
    $materiales = $_POST['materiales'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    
    // Validaciones
    if (empty($id_obra)) {
        $errors[] = "Debe seleccionar una obra.";
    }
    
    if (empty($materiales) || empty(array_filter($cantidades))) {
        $errors[] = "Debe agregar al menos un material al pedido.";
    }
    
    // Validar cantidades
    foreach ($cantidades as $key => $cantidad) {
        if (!empty($cantidad) && (!is_numeric($cantidad) || $cantidad <= 0)) {
            $errors[] = "Las cantidades deben ser números positivos.";
            break;
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Actualizar pedido
            $stmt = $conn->prepare("UPDATE pedidos_materiales SET id_obra = ?, observaciones = ? WHERE id_pedido = ?");
            $stmt->execute([$id_obra, $observaciones, $id_pedido]);
            
            // Eliminar detalles existentes
            $stmt_delete = $conn->prepare("DELETE FROM detalle_pedido WHERE id_pedido = ?");
            $stmt_delete->execute([$id_pedido]);
            
            // Insertar nuevos detalles
            $stmt_detalle = $conn->prepare("INSERT INTO detalle_pedido (id_pedido, id_material, cantidad) VALUES (?, ?, ?)");
            
            foreach ($materiales as $key => $id_material) {
                $cantidad = $cantidades[$key];
                if (!empty($cantidad) && $cantidad > 0) {
                    $stmt_detalle->execute([$id_pedido, $id_material, $cantidad]);
                }
            }
            
            // Registrar en seguimiento
            $stmt_seguimiento = $conn->prepare("INSERT INTO seguimiento_pedidos (id_pedido, estado, observaciones, id_usuario_cambio) VALUES (?, 'pendiente', 'Pedido modificado', ?)");
            $stmt_seguimiento->execute([$id_pedido, $_SESSION['user_id']]);
            
            $conn->commit();
            $success = true;
            
            // Redireccionar después de editar
            header("Location: view.php?id=" . $id_pedido . "&updated=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Error al actualizar el pedido: " . $e->getMessage();
        }
    }
}

try {
    // Obtener información del pedido
    $stmt = $conn->prepare("SELECT p.*, o.nombre_obra
                            FROM pedidos_materiales p
                            LEFT JOIN obras o ON p.id_obra = o.id_obra
                            WHERE p.id_pedido = ? AND p.estado = 'pendiente'");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        redirect(SITE_URL . '/modules/pedidos/list.php');
    }
    
    // Obtener detalles del pedido
    $stmt_detalles = $conn->prepare("SELECT dp.*, m.nombre_material
                                     FROM detalle_pedido dp
                                     LEFT JOIN materiales m ON dp.id_material = m.id_material
                                     WHERE dp.id_pedido = ?
                                     ORDER BY m.nombre_material");
    $stmt_detalles->execute([$id_pedido]);
    $detalles_existentes = $stmt_detalles->fetchAll();
    
    // Obtener obras
    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras WHERE estado != 'cancelada' ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();
    
    // Obtener materiales con stock
    $stmt_materiales = $conn->query("SELECT id_material, nombre_material, stock_actual, stock_minimo, precio_referencia, unidad_medida FROM materiales ORDER BY nombre_material");
    $materiales = $stmt_materiales->fetchAll();
    
} catch (Exception $e) {
    $errors[] = "Error al cargar datos: " . $e->getMessage();
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-pencil"></i> Editar Pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
            </h1>
            <div class="btn-group">
                <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Pedido
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list"></i> Lista de Pedidos
                </a>
            </div>
        </div>
    </div>
</div>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-data"></i> Información del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_obra" class="form-label">Obra <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_obra" name="id_obra" required>
                                    <option value="">Seleccionar obra...</option>
                                    <?php foreach ($obras as $obra): ?>
                                        <option value="<?php echo $obra['id_obra']; ?>" 
                                                <?php echo $pedido['id_obra'] == $obra['id_obra'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor seleccione una obra.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Solicitante</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($_SESSION['user_name'] . ' ' . $_SESSION['user_lastname']); ?>" 
                                       readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                  placeholder="Observaciones adicionales sobre el pedido..."><?php echo htmlspecialchars($pedido['observaciones']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Materiales del pedido -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box-seam"></i> Materiales del Pedido
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarMaterial()">
                        <i class="bi bi-plus"></i> Agregar Material
                    </button>
                </div>
                <div class="card-body">
                    <div id="materiales-container">
                        <!-- Los materiales se cargarán aquí -->
                    </div>
                    
                    <div class="text-muted mt-3" id="empty-message" style="display: none;">
                        <i class="bi bi-info-circle"></i> Haga clic en "Agregar Material" para agregar materiales al pedido.
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Resumen del pedido -->
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-calculator"></i> Resumen del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Items:</span>
                        <span id="total-items">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Valor Estimado:</span>
                        <span id="valor-total">$0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-success">Disponible:</span>
                        <span id="items-disponibles" class="text-success">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-warning">Stock Parcial:</span>
                        <span id="items-parciales" class="text-warning">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-danger">Sin Stock:</span>
                        <span id="items-sin-stock" class="text-danger">0</span>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Pedido
                        </button>
                        <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let contadorMateriales = 0;
const materialesData = <?php echo json_encode($materiales); ?>;
const detallesExistentes = <?php echo json_encode($detalles_existentes); ?>;

function agregarMaterial(materialId = '', cantidad = '') {
    contadorMateriales++;
    const container = document.getElementById('materiales-container');
    const emptyMessage = document.getElementById('empty-message');
    
    const materialRow = document.createElement('div');
    materialRow.className = 'material-row border rounded p-3 mb-3';
    materialRow.id = `material-${contadorMateriales}`;
    
    materialRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-5">
                <label class="form-label">Material</label>
                <select class="form-select material-select" name="materiales[]" onchange="actualizarInfoMaterial(${contadorMateriales})" required>
                    <option value="">Seleccionar material...</option>
                    ${materialesData.map(m => `<option value="${m.id_material}" 
                        data-stock="${m.stock_actual}" 
                        data-precio="${m.precio_referencia}"
                        data-unidad="${m.unidad_medida}"
                        data-minimo="${m.stock_minimo}"
                        ${materialId == m.id_material ? 'selected' : ''}>
                        ${m.nombre_material}
                    </option>`).join('')}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cantidad</label>
                <input type="number" class="form-control cantidad-input" name="cantidades[]" 
                       min="1" step="1" value="${cantidad}" onchange="actualizarResumen()" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado Stock</label>
                <div id="stock-status-${contadorMateriales}" class="form-control-plaintext">
                    <span class="badge bg-secondary">Sin seleccionar</span>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMaterial(${contadorMateriales})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div id="info-material-${contadorMateriales}" class="mt-2" style="display: none;">
            <small class="text-muted">
                <strong>Stock disponible:</strong> <span class="stock-disponible">0</span> 
                <span class="unidad-medida"></span> | 
                <strong>Precio:</strong> $<span class="precio-referencia">0.00</span>
            </small>
        </div>
        <div id="alert-material-${contadorMateriales}" class="mt-2" style="display: none;"></div>
    `;
    
    container.appendChild(materialRow);
    emptyMessage.style.display = 'none';
    
    // Si se especificó un material, actualizar la información
    if (materialId) {
        actualizarInfoMaterial(contadorMateriales);
    }
    
    actualizarResumen();
}

function eliminarMaterial(id) {
    const materialRow = document.getElementById(`material-${id}`);
    materialRow.remove();
    
    const container = document.getElementById('materiales-container');
    const emptyMessage = document.getElementById('empty-message');
    
    if (container.children.length === 0) {
        emptyMessage.style.display = 'block';
    }
    
    actualizarResumen();
}

function actualizarInfoMaterial(id) {
    const select = document.querySelector(`#material-${id} .material-select`);
    const selectedOption = select.options[select.selectedIndex];
    const infoDiv = document.getElementById(`info-material-${id}`);
    const alertDiv = document.getElementById(`alert-material-${id}`);
    
    if (selectedOption.value) {
        const stock = parseInt(selectedOption.dataset.stock);
        const precio = parseFloat(selectedOption.dataset.precio);
        const unidad = selectedOption.dataset.unidad;
        const minimo = parseInt(selectedOption.dataset.minimo);
        
        // Mostrar información del material
        infoDiv.style.display = 'block';
        infoDiv.querySelector('.stock-disponible').textContent = stock;
        infoDiv.querySelector('.unidad-medida').textContent = unidad;
        infoDiv.querySelector('.precio-referencia').textContent = precio.toFixed(2);
        
        // Mostrar alerta si stock bajo
        if (stock <= minimo && stock > 0) {
            alertDiv.innerHTML = '<div class="alert alert-warning alert-sm mb-0"><i class="bi bi-exclamation-triangle"></i> Este material tiene stock bajo.</div>';
            alertDiv.style.display = 'block';
        } else if (stock === 0) {
            alertDiv.innerHTML = '<div class="alert alert-danger alert-sm mb-0"><i class="bi bi-x-circle"></i> Este material no tiene stock disponible.</div>';
            alertDiv.style.display = 'block';
        } else {
            alertDiv.style.display = 'none';
        }
    } else {
        infoDiv.style.display = 'none';
        alertDiv.style.display = 'none';
    }
    
    actualizarEstadoStock(id);
    actualizarResumen();
}

function actualizarEstadoStock(id) {
    const select = document.querySelector(`#material-${id} .material-select`);
    const cantidadInput = document.querySelector(`#material-${id} .cantidad-input`);
    const statusDiv = document.getElementById(`stock-status-${id}`);
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value && cantidadInput.value) {
        const stock = parseInt(selectedOption.dataset.stock);
        const cantidad = parseInt(cantidadInput.value);
        
        let statusHtml = '';
        
        if (stock === 0) {
            statusHtml = '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin Stock</span>';
        } else if (stock < cantidad) {
            const faltante = cantidad - stock;
            statusHtml = `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Parcial</span>
                         <small class="d-block text-danger">Faltan: ${faltante}</small>`;
        } else {
            statusHtml = '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Disponible</span>';
        }
        
        statusDiv.innerHTML = statusHtml;
    } else {
        statusDiv.innerHTML = '<span class="badge bg-secondary">Sin seleccionar</span>';
    }
}

function actualizarResumen() {
    let totalItems = 0;
    let valorTotal = 0;
    let disponibles = 0;
    let parciales = 0;
    let sinStock = 0;
    
    document.querySelectorAll('.material-row').forEach(row => {
        const select = row.querySelector('.material-select');
        const cantidadInput = row.querySelector('.cantidad-input');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value && cantidadInput.value) {
            const stock = parseInt(selectedOption.dataset.stock);
            const precio = parseFloat(selectedOption.dataset.precio);
            const cantidad = parseInt(cantidadInput.value);
            
            totalItems++;
            valorTotal += cantidad * precio;
            
            if (stock === 0) {
                sinStock++;
            } else if (stock < cantidad) {
                parciales++;
            } else {
                disponibles++;
            }
        }
    });
    
    document.getElementById('total-items').textContent = totalItems;
    document.getElementById('valor-total').textContent = '$' + valorTotal.toFixed(2);
    document.getElementById('items-disponibles').textContent = disponibles;
    document.getElementById('items-parciales').textContent = parciales;
    document.getElementById('items-sin-stock').textContent = sinStock;
}

// Event listeners para actualizar cuando se cambie la cantidad
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('cantidad-input')) {
        const materialRow = e.target.closest('.material-row');
        const id = materialRow.id.split('-')[1];
        actualizarEstadoStock(id);
        actualizarResumen();
    }
});

// Cargar materiales existentes al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    detallesExistentes.forEach(detalle => {
        agregarMaterial(detalle.id_material, detalle.cantidad);
    });
    
    // Si no hay materiales, agregar uno vacío
    if (detallesExistentes.length === 0) {
        agregarMaterial();
    }
});
</script>

<style>
.material-row {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.material-row:hover {
    background-color: #e9ecef;
}

.alert-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.sticky-top {
    position: sticky;
    top: 20px;
    z-index: 1020;
}
</style>

<?php include '../../includes/footer.php'; ?>

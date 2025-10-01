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
    
    // Validar que no haya materiales duplicados
    $materiales_filtrados = array_filter($materiales);
    if (count($materiales_filtrados) !== count(array_unique($materiales_filtrados))) {
        $errors[] = "No puede seleccionar el mismo material más de una vez.";
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
            
            // Actualizar pedido principal
            $stmt = $conn->prepare("UPDATE pedidos_materiales SET id_obra = ?, observaciones = ? WHERE id_pedido = ?");
            $stmt->execute([$id_obra, $observaciones, $id_pedido]);
            
            // Eliminar detalles existentes
            $stmt_delete = $conn->prepare("DELETE FROM detalle_pedidos_materiales WHERE id_pedido = ?");
            $stmt_delete->execute([$id_pedido]);
            
            // Insertar nuevos detalles
            $stmt_detalle = $conn->prepare("INSERT INTO detalle_pedidos_materiales (id_pedido, id_material, cantidad_solicitada, precio_unitario) VALUES (?, ?, ?, ?)");
            
            foreach ($materiales as $key => $id_material) {
                if (!empty($id_material)) {
                    $cantidad = intval($cantidades[$key]);
                    if ($cantidad > 0) {
                        // Obtener precio del material
                        $stmt_material = $conn->prepare("SELECT precio_referencia FROM materiales WHERE id_material = ?");
                        $stmt_material->execute([$id_material]);
                        $material = $stmt_material->fetch();
                        
                        $precio_unitario = floatval($material['precio_referencia']);
                        
                        // Insertar detalle (los triggers calcularán automáticamente disponibilidad, subtotales, etc.)
                        $stmt_detalle->execute([$id_pedido, $id_material, $cantidad, $precio_unitario]);
                    }
                }
            }
            
            // Registrar en seguimiento
            $stmt_seguimiento = $conn->prepare("INSERT INTO seguimiento_pedidos (id_pedido, estado_nuevo, observaciones, id_usuario_cambio) VALUES (?, 'pendiente', 'Pedido modificado', ?)");
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
    // Obtener información del pedido con datos del solicitante
    $stmt = $conn->prepare("SELECT p.*, o.nombre_obra, o.direccion, o.cliente,
                                   u.nombre, u.apellido, u.email
                            FROM pedidos_materiales p
                            LEFT JOIN obras o ON p.id_obra = o.id_obra
                            LEFT JOIN usuarios u ON p.id_solicitante = u.id_usuario
                            WHERE p.id_pedido = ?");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        redirect(SITE_URL . '/modules/pedidos/list.php');
    }
    
    // Obtener detalles del pedido
    $stmt_detalles = $conn->prepare("SELECT d.*, m.nombre_material
                                     FROM detalle_pedidos_materiales d
                                     LEFT JOIN materiales m ON d.id_material = m.id_material
                                     WHERE d.id_pedido = ?
                                     ORDER BY m.nombre_material");
    $stmt_detalles->execute([$id_pedido]);
    $detalles_existentes = $stmt_detalles->fetchAll();
    
    // Obtener obras
    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras WHERE estado != 'cancelada' ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();
    
    // Obtener materiales con stock
    $stmt_materiales = $conn->query("SELECT id_material, nombre_material, stock_actual, stock_minimo, precio_referencia, unidad_medida FROM materiales ORDER BY nombre_material");
    $materiales = $stmt_materiales->fetchAll();
    
    // Obtener información del solicitante
    // Verificar si el campo 'id_usuario_solicitante' existe antes de usarlo
    if (isset($pedido['id_usuario_solicitante'])) {
        $stmt_solicitante = $conn->prepare("SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?");
        $stmt_solicitante->execute([$pedido['id_usuario_solicitante']]);
        $solicitante = $stmt_solicitante->fetch();

        if (!$solicitante) {
            $solicitante_nombre = 'Usuario desconocido';
        } else {
            $solicitante_nombre = htmlspecialchars($solicitante['nombre'] . ' ' . $solicitante['apellido']);
        }
    } else {
        $solicitante_nombre = 'Usuario desconocido';
    }
    
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
                <li><?php echo htmlspecialchars($error); ?></li>
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
                                       value="<?php echo isset($_SESSION['user_name'], $_SESSION['user_lastname']) ? htmlspecialchars($_SESSION['user_name'] . ' ' . $_SESSION['user_lastname']) : 'Usuario desconocido'; ?>" 
                                       readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                  placeholder="Observaciones adicionales sobre el pedido..."><?php echo htmlspecialchars($pedido['observaciones']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prioridad" class="form-label">Prioridad</label>
                                <select class="form-select" id="prioridad" name="prioridad" required>
                                    <option value="baja" <?php echo $pedido['prioridad'] === 'baja' ? 'selected' : ''; ?>>Baja</option>
                                    <option value="media" <?php echo $pedido['prioridad'] === 'media' ? 'selected' : ''; ?>>Media</option>
                                    <option value="alta" <?php echo $pedido['prioridad'] === 'alta' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="urgente" <?php echo $pedido['prioridad'] === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_necesaria" class="form-label">Fecha Necesaria</label>
                                <input type="date" class="form-control" id="fecha_necesaria" name="fecha_necesaria" value="<?php echo htmlspecialchars($pedido['fecha_necesaria']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Materiales del pedido -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box-seam"></i> Materiales del Pedido
                    </h5>
                    <button type="button" class="btn btn-sm custom-btn" onclick="agregarMaterial()">
                        <i class="bi bi-plus"></i> Agregar Material
                    </button>
                </div>
                <div class="card-body">
                    <div id="materiales-container">
                        <!-- Los materiales se cargarán aquí -->
                        <?php foreach ($detalles_existentes as $detalle): ?>
                            <div class="material-row border rounded p-3 mb-3" id="material-<?php echo $detalle['id_material']; ?>">
                                <div class="row align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label">Material <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control material-search-input" 
                                               value="<?php echo htmlspecialchars($detalle['nombre_material']); ?>" readonly>
                                        <input type="hidden" class="material-select" name="materiales[]" 
                                               value="<?php echo $detalle['id_material']; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control cantidad-input" name="cantidades[]" 
                                               value="<?php echo $detalle['cantidad_solicitada']; ?>" min="1" step="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Estado Stock</label>
                                        <div class="form-control-plaintext">
                                            <span class="badge bg-success">Disponible</span>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMaterial(<?php echo $detalle['id_material']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

function agregarMaterial() {
    contadorMateriales++;
    const container = document.getElementById('materiales-container');
    const emptyMessage = document.getElementById('empty-message');
    
    const materialRow = document.createElement('div');
    materialRow.className = 'material-row border rounded p-3 mb-3';
    materialRow.id = `material-${contadorMateriales}`;
    
    materialRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-5">
                <label class="form-label">Material <span class="text-danger">*</span></label>
                <div class="material-search-container position-relative">
                    <input type="text" 
                           class="form-control material-search-input" 
                           id="material-search-${contadorMateriales}"
                           placeholder="Escriba al menos 3 caracteres para buscar..."
                           autocomplete="off"
                           oninput="filtrarMateriales(${contadorMateriales})"
                           onfocus="mostrarListaMateriales(${contadorMateriales})"
                           onblur="setTimeout(() => ocultarListaMateriales(${contadorMateriales}), 200)"
                           required>
                    <input type="hidden" class="material-select" name="materiales[]" id="material-hidden-${contadorMateriales}">
                    <div class="material-dropdown" id="material-dropdown-${contadorMateriales}">
                        <div class="material-list" id="material-list-${contadorMateriales}">
                            <!-- Los materiales se cargarán dinámicamente -->
                        </div>
                    </div>
                </div>
                <div class="invalid-feedback">
                    Por favor seleccione un material.
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                <input type="number" class="form-control cantidad-input" name="cantidades[]" 
                       min="1" step="1" onchange="actualizarResumen()" required>
                <div class="invalid-feedback">
                    Ingrese una cantidad válida.
                </div>
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
    validarMaterialesDuplicados();
}

function actualizarInfoMaterial(id, materialData) {
    const infoDiv = document.getElementById(`info-material-${id}`);
    const alertDiv = document.getElementById(`alert-material-${id}`);
    
    if (materialData) {
        const stock = parseInt(materialData.stock_actual);
        const precio = parseFloat(materialData.precio_referencia);
        const unidad = materialData.unidad_medida;
        const minimo = parseInt(materialData.stock_minimo);
        
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
    validarMaterialesDuplicados();
}

function actualizarEstadoStock(id) {
    const hiddenInput = document.getElementById(`material-hidden-${id}`);
    const cantidadInput = document.querySelector(`#material-${id} .cantidad-input`);
    const statusDiv = document.getElementById(`stock-status-${id}`);
    
    if (hiddenInput.value && cantidadInput.value) {
        // Buscar el material en los datos
        const materialData = materialesData.find(m => m.id_material == hiddenInput.value);
        
        if (materialData) {
            const stock = parseInt(materialData.stock_actual);
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
        }
    } else {
        statusDiv.innerHTML = '<span class="badge bg-secondary">Sin seleccionar</span>';
    }
}

function validarMaterialesDuplicados() {
    const selects = document.querySelectorAll('.material-select');
    const materialesSeleccionados = [];
    let hayDuplicados = false;
    
    // Limpiar estilos previos
    selects.forEach(select => {
        const searchInput = select.parentNode.querySelector('.material-search-input');
        searchInput.classList.remove('is-invalid');
        const feedback = select.parentNode.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = 'Por favor seleccione un material.';
        }
    });
    
    // Verificar duplicados
    selects.forEach(select => {
        const valor = select.value;
        if (valor) {
            if (materialesSeleccionados.includes(valor)) {
                // Material duplicado encontrado
                const searchInput = select.parentNode.querySelector('.material-search-input');
                searchInput.classList.add('is-invalid');
                const feedback = select.parentNode.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = 'Este material ya fue seleccionado.';
                }
                hayDuplicados = true;
            } else {
                materialesSeleccionados.push(valor);
            }
        }
    });
    
    // Mostrar alerta general si hay duplicados
    const alertContainer = document.getElementById('alert-container');
    if (hayDuplicados) {
        alertContainer.innerHTML = `
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Atención:</strong> No puede seleccionar el mismo material más de una vez.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    } else {
        alertContainer.innerHTML = '';
    }
    
    return !hayDuplicados;
}

function actualizarResumen() {
    let totalItems = 0;
    let valorTotal = 0;
    let disponibles = 0;
    let parciales = 0;
    let sinStock = 0;
    
    document.querySelectorAll('.material-row').forEach(row => {
        const hiddenInput = row.querySelector('.material-select');
        const cantidadInput = row.querySelector('.cantidad-input');
        
        if (hiddenInput.value && cantidadInput.value) {
            const materialData = materialesData.find(m => m.id_material == hiddenInput.value);
            
            if (materialData) {
                const stock = parseInt(materialData.stock_actual);
                const precio = parseFloat(materialData.precio_referencia);
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
        }
    });
    
    document.getElementById('total-items').textContent = totalItems;
    document.getElementById('valor-total').textContent = '$' + valorTotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('items-disponibles').textContent = disponibles;
    document.getElementById('items-parciales').textContent = parciales;
    document.getElementById('items-sin-stock').textContent = sinStock;
}

// Función mejorada para búsqueda inteligente de materiales
function buscarMaterialesInteligente(searchTerm) {
    const term = searchTerm.toLowerCase().trim();
    
    if (term.length < 3) {
        return [];
    }
    
    // Función para calcular relevancia de coincidencia
    function calcularRelevancia(material, searchTerm) {
        const nombre = material.nombre_material.toLowerCase();
        const palabras = nombre.split(/\s+/);
        let score = 0;
        
        // Coincidencia exacta al inicio del nombre (máxima prioridad)
        if (nombre.startsWith(searchTerm)) {
            score += 100;
        }
        
        // Coincidencia al inicio de cualquier palabra (alta prioridad)
        for (let palabra of palabras) {
            if (palabra.startsWith(searchTerm)) {
                score += 50;
                break;
            }
        }
        
        // Coincidencia de palabra completa (media prioridad)
        if (palabras.includes(searchTerm)) {
            score += 30;
        }
        
        // Coincidencia parcial en cualquier parte (baja prioridad)
        if (nombre.includes(searchTerm) && score === 0) {
            score += 10;
        }
        
        // Bonus por longitud de coincidencia
        const coincidenceRatio = searchTerm.length / nombre.length;
        score += coincidenceRatio * 5;
        
        return score;
    }
    
    // Filtrar y ordenar materiales por relevancia
    const materialesConScore = materialesData
        .map(material => ({
            ...material,
            relevancia: calcularRelevancia(material, term)
        }))
        .filter(material => material.relevancia > 0)
        .sort((a, b) => b.relevancia - a.relevancia)
        .slice(0, 20); // Limitar a 20 resultados
    
    return materialesConScore;
}

// Funciones para el buscador de materiales
function filtrarMateriales(id) {
    const searchInput = document.getElementById(`material-search-${id}`);
    const materialList = document.getElementById(`material-list-${id}`);
    const dropdown = document.getElementById(`material-dropdown-${id}`);
    const searchTerm = searchInput.value.toLowerCase().trim();
    
    // Limpiar lista anterior
    materialList.innerHTML = '';
    
    // Solo buscar si hay al menos 3 caracteres
    if (searchTerm.length < 3) {
        dropdown.style.display = 'none';
        return;
    }
    
    // Usar búsqueda inteligente
    const materialesFiltrados = buscarMaterialesInteligente(searchTerm);
    
    if (materialesFiltrados.length > 0) {
        materialesFiltrados.forEach(material => {
            const option = document.createElement('div');
            option.className = 'material-option';
            option.onclick = () => seleccionarMaterial(id, material.id_material, material.nombre_material, material);
            
            // Determinar clase de stock
            let stockClass = 'text-success';
            let stockText = 'Disponible';
            let stockIcon = 'bi-check-circle';
            
            if (material.stock_actual === 0) {
                stockClass = 'text-danger';
                stockText = 'Sin stock';
                stockIcon = 'bi-x-circle';
            } else if (material.stock_actual <= material.stock_minimo) {
                stockClass = 'text-warning';
                stockText = 'Stock bajo';
                stockIcon = 'bi-exclamation-triangle';
            }
            
            // Resaltar término de búsqueda en el nombre | linea 666 <i class="bi bi-currency-dollar"></i> $${parseFloat(material.precio_referencia).toFixed(2)}
            const nombreResaltado = material.nombre_material.replace(
                new RegExp(`(${searchTerm})`, 'gi'),
                '<mark>$1</mark>'
            );
            
            option.innerHTML = `
                <div class="material-name">${nombreResaltado}</div>
                <div class="material-info">
                    <small class="text-muted">
                        <span class="${stockClass}">
                            <i class="bi ${stockIcon}"></i> ${material.stock_actual} ${material.unidad_medida} (${stockText})
                        </span> 
                        
                    </small>
                </div>
            `;
            
            materialList.appendChild(option);
        });
        
        dropdown.style.display = 'block';
    } else {
        // Mostrar mensaje de no resultados
        const noResults = document.createElement('div');
        noResults.className = 'no-results text-muted p-3 text-center';
        noResults.innerHTML = `
            <i class="bi bi-search"></i> 
            No se encontraron materiales que coincidan con "<strong>${searchTerm}</strong>"
            <br><small>Intente con términos más específicos o diferentes palabras clave</small>
        `;
        materialList.appendChild(noResults);
        dropdown.style.display = 'block';
    }
}

function mostrarListaMateriales(id) {
    const searchInput = document.getElementById(`material-search-${id}`);
    if (searchInput.value.length >= 3) {
        filtrarMateriales(id);
    }
}

function ocultarListaMateriales(id) {
    const dropdown = document.getElementById(`material-dropdown-${id}`);
    dropdown.style.display = 'none';
}

function seleccionarMaterial(id, materialId, materialName, materialData) {
    const searchInput = document.getElementById(`material-search-${id}`);
    const hiddenInput = document.getElementById(`material-hidden-${id}`);
    const dropdown = document.getElementById(`material-dropdown-${id}`);
    
    // Actualizar inputs
    searchInput.value = materialName;
    hiddenInput.value = materialId;
    
    // Ocultar dropdown
    dropdown.style.display = 'none';
    
    // Actualizar información del material
    actualizarInfoMaterial(id, materialData);
    
    // Validar duplicados
    validarMaterialesDuplicados();
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

// Event listener para validar duplicados al cambiar material
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('material-select')) {
        validarMaterialesDuplicados();
    }
});

// Agregar un material por defecto al cargar
document.addEventListener('DOMContentLoaded', function() {
    agregarMaterial();
});

// Validación del formulario
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                // Validar materiales duplicados antes de enviar
                if (!validarMaterialesDuplicados()) {
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
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

.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}
</style>

<?php include '../../includes/footer.php'; ?>

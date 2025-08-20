<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden editar materiales
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Editar Material';

$database = new Database();
$conn = $database->getConnection();

$material_id = (int)($_GET['id'] ?? 0);
$errors = [];
$success = '';

if ($material_id <= 0) {
    redirect(SITE_URL . '/modules/materiales/list.php');
}

// Obtener datos del material
try {
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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar datos
    $nombre_material = sanitize_input($_POST['nombre_material'] ?? '');
    $descripcion = sanitize_input($_POST['descripcion'] ?? '');
    $categoria = sanitize_input($_POST['categoria'] ?? '');
    $stock_actual = (int)($_POST['stock_actual'] ?? 0);
    $stock_minimo = (int)($_POST['stock_minimo'] ?? 0);
    $stock_maximo = (int)($_POST['stock_maximo'] ?? 0);
    $precio_referencia = (float)($_POST['precio_referencia'] ?? 0);
    $precio_compra = (float)($_POST['precio_compra'] ?? 0);
    $unidad_medida = sanitize_input($_POST['unidad_medida'] ?? '');
    $proveedor = sanitize_input($_POST['proveedor'] ?? '');
    $codigo_interno = sanitize_input($_POST['codigo_interno'] ?? '');
    $ubicacion_deposito = sanitize_input($_POST['ubicacion_deposito'] ?? '');
    $estado = sanitize_input($_POST['estado'] ?? 'activo');

    // Validaciones
    if (empty($nombre_material)) {
        $errors[] = "El nombre del material es obligatorio.";
    }

    if ($stock_actual < 0) {
        $errors[] = "El stock actual no puede ser negativo.";
    }

    if ($stock_minimo < 0) {
        $errors[] = "El stock mínimo no puede ser negativo.";
    }

    if ($stock_maximo > 0 && $stock_maximo < $stock_minimo) {
        $errors[] = "El stock máximo debe ser mayor al stock mínimo.";
    }

    if ($precio_referencia < 0) {
        $errors[] = "El precio de referencia no puede ser negativo.";
    }

    if ($precio_compra < 0) {
        $errors[] = "El precio de compra no puede ser negativo.";
    }

    if (empty($unidad_medida)) {
        $errors[] = "La unidad de medida es obligatoria.";
    }

    if (!in_array($estado, ['activo', 'inactivo', 'descontinuado'])) {
        $errors[] = "Estado inválido.";
    }

    // Verificar si el código interno ya existe (si se proporcionó)
    if (!empty($codigo_interno)) {
        try {
            $query_check = "SELECT id_material FROM materiales WHERE codigo_interno = ? AND id_material != ?";
            $stmt_check = $conn->prepare($query_check);
            $stmt_check->execute([$codigo_interno, $material_id]);
            if ($stmt_check->fetch()) {
                $errors[] = "El código interno ya está en uso por otro material.";
            }
        } catch (Exception $e) {
            $errors[] = "Error al verificar código interno.";
        }
    }

    // Si no hay errores, actualizar
    if (empty($errors)) {
        try {
            $query_update = "UPDATE materiales SET 
                           nombre_material = ?, 
                           descripcion = ?, 
                           categoria = ?, 
                           stock_actual = ?, 
                           stock_minimo = ?, 
                           stock_maximo = ?, 
                           precio_referencia = ?, 
                           precio_compra = ?, 
                           unidad_medida = ?, 
                           proveedor = ?, 
                           codigo_interno = ?, 
                           ubicacion_deposito = ?, 
                           estado = ?,
                           fecha_actualizacion = CURRENT_TIMESTAMP
                           WHERE id_material = ?";

            $stmt_update = $conn->prepare($query_update);
            $result = $stmt_update->execute([
                $nombre_material,
                $descripcion,
                $categoria,
                $stock_actual,
                $stock_minimo,
                $stock_maximo,
                $precio_referencia,
                $precio_compra,
                $unidad_medida,
                $proveedor,
                $codigo_interno ?: null,
                $ubicacion_deposito,
                $estado,
                $material_id
            ]);

            if ($result) {
                // Registrar en logs
                try {
                    $log_query = "INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, ip_usuario) 
                                VALUES (?, 'actualizar', 'materiales', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->execute([
                        $_SESSION['user_id'],
                        "Material actualizado: {$nombre_material}",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    // Log error but don't stop execution
                    error_log("Error al registrar log: " . $e->getMessage());
                }

                $success = "Material actualizado correctamente.";
                
                // Actualizar datos del material para mostrar los nuevos valores
                $material['nombre_material'] = $nombre_material;
                $material['descripcion'] = $descripcion;
                $material['categoria'] = $categoria;
                $material['stock_actual'] = $stock_actual;
                $material['stock_minimo'] = $stock_minimo;
                $material['stock_maximo'] = $stock_maximo;
                $material['precio_referencia'] = $precio_referencia;
                $material['precio_compra'] = $precio_compra;
                $material['unidad_medida'] = $unidad_medida;
                $material['proveedor'] = $proveedor;
                $material['codigo_interno'] = $codigo_interno;
                $material['ubicacion_deposito'] = $ubicacion_deposito;
                $material['estado'] = $estado;
            } else {
                $errors[] = "Error al actualizar el material.";
            }
        } catch (Exception $e) {
            error_log("Error al actualizar material: " . $e->getMessage());
            $errors[] = "Error interno del servidor.";
        }
    }
}

// Obtener categorías existentes para el select
try {
    $query_categorias = "SELECT DISTINCT categoria FROM materiales WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
    $stmt_categorias = $conn->query($query_categorias);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categorias = [];
}

// Obtener proveedores existentes para el select
try {
    $query_proveedores = "SELECT DISTINCT proveedor FROM materiales WHERE proveedor IS NOT NULL AND proveedor != '' ORDER BY proveedor";
    $stmt_proveedores = $conn->query($query_proveedores);
    $proveedores = $stmt_proveedores->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $proveedores = [];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-pencil-square"></i> Editar Material
            </h1>
            <div>
                <a href="view.php?id=<?php echo $material['id_material']; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Ver Detalles
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información del Material
            </div>
            <div class="card-body">
                <form method="POST" id="editMaterialForm">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="nombre_material" class="form-label">
                                Nombre del Material <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nombre_material" name="nombre_material" 
                                   value="<?php echo htmlspecialchars($material['nombre_material']); ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="codigo_interno" class="form-label">Código Interno</label>
                            <input type="text" class="form-control" id="codigo_interno" name="codigo_interno" 
                                   value="<?php echo htmlspecialchars($material['codigo_interno'] ?? ''); ?>"
                                   placeholder="Ej: MAT-001">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                  placeholder="Descripción detallada del material..."><?php echo htmlspecialchars($material['descripcion'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="categoria" class="form-label">Categoría</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="categoria" name="categoria" 
                                       value="<?php echo htmlspecialchars($material['categoria'] ?? ''); ?>"
                                       list="categorias-list" placeholder="Seleccionar o escribir nueva...">
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php foreach ($categorias as $cat): ?>
                                        <li><a class="dropdown-item" href="#" onclick="document.getElementById('categoria').value='<?php echo htmlspecialchars($cat); ?>'"><?php echo htmlspecialchars($cat); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <datalist id="categorias-list">
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="proveedor" class="form-label">Proveedor</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="proveedor" name="proveedor" 
                                       value="<?php echo htmlspecialchars($material['proveedor'] ?? ''); ?>"
                                       list="proveedores-list" placeholder="Seleccionar o escribir nuevo...">
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php foreach ($proveedores as $prov): ?>
                                        <li><a class="dropdown-item" href="#" onclick="document.getElementById('proveedor').value='<?php echo htmlspecialchars($prov); ?>'"><?php echo htmlspecialchars($prov); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <datalist id="proveedores-list">
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="unidad_medida" class="form-label">
                                Unidad de Medida <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="unidad_medida" name="unidad_medida" required>
                                <option value="">Seleccionar...</option>
                                <option value="unidad" <?php echo $material['unidad_medida'] === 'unidad' ? 'selected' : ''; ?>>Unidad</option>
                                <option value="kg" <?php echo $material['unidad_medida'] === 'kg' ? 'selected' : ''; ?>>Kilogramo (kg)</option>
                                <option value="m" <?php echo $material['unidad_medida'] === 'm' ? 'selected' : ''; ?>>Metro (m)</option>
                                <option value="m2" <?php echo $material['unidad_medida'] === 'm2' ? 'selected' : ''; ?>>Metro cuadrado (m²)</option>
                                <option value="m3" <?php echo $material['unidad_medida'] === 'm3' ? 'selected' : ''; ?>>Metro cúbico (m³)</option>
                                <option value="litro" <?php echo $material['unidad_medida'] === 'litro' ? 'selected' : ''; ?>>Litro</option>
                                <option value="bolsa" <?php echo $material['unidad_medida'] === 'bolsa' ? 'selected' : ''; ?>>Bolsa</option>
                                <option value="barra" <?php echo $material['unidad_medida'] === 'barra' ? 'selected' : ''; ?>>Barra</option>
                                <option value="rollo" <?php echo $material['unidad_medida'] === 'rollo' ? 'selected' : ''; ?>>Rollo</option>
                                <option value="caja" <?php echo $material['unidad_medida'] === 'caja' ? 'selected' : ''; ?>>Caja</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ubicacion_deposito" class="form-label">Ubicación en Depósito</label>
                            <input type="text" class="form-control" id="ubicacion_deposito" name="ubicacion_deposito" 
                                   value="<?php echo htmlspecialchars($material['ubicacion_deposito'] ?? ''); ?>"
                                   placeholder="Ej: Estante A-1, Sector B">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="activo" <?php echo $material['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo $material['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="descontinuado" <?php echo $material['estado'] === 'descontinuado' ? 'selected' : ''; ?>>Descontinuado</option>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-muted mb-3">
                        <i class="bi bi-box-seam"></i> Control de Stock
                    </h6>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="stock_actual" class="form-label">Stock Actual</label>
                            <input type="number" class="form-control" id="stock_actual" name="stock_actual" 
                                   value="<?php echo $material['stock_actual']; ?>" min="0" step="1">
                            <div class="form-text">Cantidad actual en depósito</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="stock_minimo" class="form-label">Stock Mínimo</label>
                            <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" 
                                   value="<?php echo $material['stock_minimo']; ?>" min="0" step="1">
                            <div class="form-text">Cantidad mínima requerida</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="stock_maximo" class="form-label">Stock Máximo</label>
                            <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" 
                                   value="<?php echo $material['stock_maximo']; ?>" min="0" step="1">
                            <div class="form-text">Cantidad máxima a almacenar (opcional)</div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-muted mb-3">
                        <i class="bi bi-currency-dollar"></i> Información de Precios
                    </h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="precio_compra" class="form-label">Precio de Compra</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="precio_compra" name="precio_compra" 
                                       value="<?php echo $material['precio_compra']; ?>" min="0" step="0.01">
                            </div>
                            <div class="form-text">Precio al que se compra el material</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="precio_referencia" class="form-label">Precio de Referencia</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="precio_referencia" name="precio_referencia" 
                                       value="<?php echo $material['precio_referencia']; ?>" min="0" step="0.01">
                            </div>
                            <div class="form-text">Precio de referencia para cálculos</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Actualizar Material
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel lateral con información -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información del Sistema
            </div>
            <div class="card-body">
                <h6 class="text-muted">Estado Actual del Stock</h6>
                <?php 
                $stock_status = '';
                $stock_class = '';
                if ($material['stock_actual'] <= 0) {
                    $stock_status = 'Sin Stock';
                    $stock_class = 'text-danger';
                } elseif ($material['stock_actual'] <= $material['stock_minimo']) {
                    $stock_status = 'Stock Bajo';
                    $stock_class = 'text-warning';
                } else {
                    $stock_status = 'Stock Disponible';
                    $stock_class = 'text-success';
                }
                ?>
                <p class="<?php echo $stock_class; ?> mb-3">
                    <i class="bi bi-circle-fill"></i> <?php echo $stock_status; ?>
                </p>

                <h6 class="text-muted">Valor Total en Stock</h6>
                <p class="fs-5 text-primary mb-3">
                    $<?php echo number_format($material['stock_actual'] * $material['precio_referencia'], 2); ?>
                </p>

                <h6 class="text-muted">Información de Registro</h6>
                <p class="mb-2">
                    <small class="text-muted">Creado:</small><br>
                    <?php echo date('d/m/Y H:i', strtotime($material['fecha_creacion'])); ?>
                </p>
                <p class="mb-3">
                    <small class="text-muted">Última actualización:</small><br>
                    <?php echo date('d/m/Y H:i', strtotime($material['fecha_actualizacion'])); ?>
                </p>

                <hr>

                <h6 class="text-muted">Acciones Rápidas</h6>
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-eye"></i> Ver Detalles
                    </a>
                    <a href="adjust_stock.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-arrow-up-down"></i> Ajustar Stock
                    </a>
                    <a href="../pedidos/create.php?material_id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-cart-plus"></i> Crear Pedido
                    </a>
                </div>
            </div>
        </div>

        <!-- Ayuda -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-question-circle"></i> Ayuda
            </div>
            <div class="card-body">
                <h6>Consejos para editar materiales:</h6>
                <ul class="small">
                    <li><strong>Stock Mínimo:</strong> Establece un nivel que permita reposición a tiempo.</li>
                    <li><strong>Código Interno:</strong> Usa un sistema consistente (ej: MAT-001).</li>
                    <li><strong>Categorías:</strong> Agrupa materiales similares para mejor organización.</li>
                    <li><strong>Ubicación:</strong> Especifica la ubicación física en el depósito.</li>
                    <li><strong>Precios:</strong> Mantén actualizados los precios de compra y referencia.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editMaterialForm');
    
    // Validación en tiempo real
    form.addEventListener('input', function(e) {
        validateField(e.target);
    });
    
    // Validación al enviar
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        switch(field.name) {
            case 'nombre_material':
                if (value === '') {
                    isValid = false;
                    message = 'El nombre del material es obligatorio.';
                }
                break;
                
            case 'stock_actual':
            case 'stock_minimo':
            case 'stock_maximo':
                if (value !== '' && parseInt(value) < 0) {
                    isValid = false;
                    message = 'El valor no puede ser negativo.';
                }
                break;
                
            case 'precio_referencia':
            case 'precio_compra':
                if (value !== '' && parseFloat(value) < 0) {
                    isValid = false;
                    message = 'El precio no puede ser negativo.';
                }
                break;
                
            case 'unidad_medida':
                if (value === '') {
                    isValid = false;
                    message = 'La unidad de medida es obligatoria.';
                }
                break;
        }
        
        // Validación especial para stock máximo
        if (field.name === 'stock_maximo') {
            const stockMinimo = parseInt(document.getElementById('stock_minimo').value) || 0;
            const stockMaximo = parseInt(value) || 0;
            
            if (stockMaximo > 0 && stockMaximo < stockMinimo) {
                isValid = false;
                message = 'El stock máximo debe ser mayor al stock mínimo.';
            }
        }
        
        // Aplicar estilos de validación
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            field.nextElementSibling.textContent = message;
        }
        
        return isValid;
    }
    
    function validateForm() {
        const requiredFields = ['nombre_material', 'unidad_medida'];
        let isValid = true;
        
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    // Calcular valor total automáticamente
    const stockActual = document.getElementById('stock_actual');
    const precioReferencia = document.getElementById('precio_referencia');
    
    function updateTotalValue() {
        const stock = parseInt(stockActual.value) || 0;
        const precio = parseFloat(precioReferencia.value) || 0;
        const total = stock * precio;
        
        // Actualizar en el panel lateral si existe
        const valorTotalElement = document.querySelector('.fs-5.text-primary');
        if (valorTotalElement) {
            valorTotalElement.textContent = '$' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
        }
    }
    
    stockActual.addEventListener('input', updateTotalValue);
    precioReferencia.addEventListener('input', updateTotalValue);
});
</script>

<?php include '../../includes/footer.php'; ?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden procesar pedidos
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Procesar Pedido';

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
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Token de seguridad inválido. Por favor, recargue la página.";
    } else {
        $accion = $_POST['accion'] ?? '';
        $observaciones = sanitize_input($_POST['observaciones']);
        $cantidades_entregadas = $_POST['cantidades_entregadas'] ?? [];
        
        if (empty($accion)) {
            $errors[] = "Debe seleccionar una acción.";
        }
        
        // Validar que el pedido esté en un estado procesable
        $stmt_check = $conn->prepare("SELECT estado FROM pedidos_materiales WHERE id_pedido = ?");
        $stmt_check->execute([$id_pedido]);
        $pedido_actual = $stmt_check->fetch();
        
        if (!$pedido_actual) {
            $errors[] = "El pedido no existe.";
        } else {
            // Validar transiciones de estado permitidas
            $estado_actual = $pedido_actual['estado'];
            
            if ($estado_actual === 'cancelado' || $estado_actual === 'recibido') {
                $errors[] = "Este pedido ya está finalizado y no puede ser procesado.";
            }
            
            // Validar flujo correcto de etapas
            if ($estado_actual === 'pendiente' && !in_array($accion, ['aprobado', 'cancelado'])) {
                $errors[] = "Un pedido pendiente solo puede ser aprobado o cancelado.";
            }
            
            if ($estado_actual === 'aprobado' && !in_array($accion, ['retirado', 'cancelado'])) {
                $errors[] = "Un pedido aprobado solo puede ser marcado como retirado o cancelado.";
            }
            
            if ($estado_actual === 'retirado' && !in_array($accion, ['recibido', 'cancelado'])) {
                $errors[] = "Un pedido retirado solo puede ser marcado como recibido o cancelado.";
            }
        }
        
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                $fecha_actual = date('Y-m-d H:i:s');
                $id_usuario = $_SESSION['user_id'];
                
                // Actualizar estado del pedido según la acción
                if ($accion == 'aprobado') {
                    $stmt = $conn->prepare("UPDATE pedidos_materiales SET 
                        estado = ?, 
                        id_aprobado_por = ?, 
                        fecha_aprobacion = ? 
                        WHERE id_pedido = ?");
                    $stmt->execute([$accion, $id_usuario, $fecha_actual, $id_pedido]);
                    
                } elseif ($accion == 'retirado') {
                    $stmt = $conn->prepare("UPDATE pedidos_materiales SET 
                        estado = ?, 
                        id_retirado_por = ?, 
                        fecha_retiro = ? 
                        WHERE id_pedido = ?");
                    $stmt->execute([$accion, $id_usuario, $fecha_actual, $id_pedido]);
                    
                    // Al retirar, descontar del stock
                    $stmt_det = $conn->prepare("SELECT id_material, cantidad_solicitada FROM detalle_pedidos_materiales WHERE id_pedido = ?");
                    $stmt_det->execute([$id_pedido]);
                    $detalles = $stmt_det->fetchAll();
                    
                    $stmt_update_stock = $conn->prepare("UPDATE materiales SET stock_actual = stock_actual - ? WHERE id_material = ?");
                    $stmt_log = $conn->prepare("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion) VALUES (?, ?, ?, ?, ?)");
                    
                    foreach ($detalles as $d) {
                        $cantidad = intval($d['cantidad_solicitada']);
                        if ($cantidad > 0) {
                            $stmt_update_stock->execute([$cantidad, $d['id_material']]);
                            $stmt_log->execute([
                                $id_usuario,
                                'stock_salida',
                                'materiales',
                                "Retiro pedido #" . str_pad($id_pedido, 4, '0', STR_PAD_LEFT) . " - Material ID: " . $d['id_material'] . " - Cantidad: " . $cantidad,
                                $fecha_actual
                            ]);
                        }
                    }
                    
                } elseif ($accion == 'recibido') {
                    $stmt = $conn->prepare("UPDATE pedidos_materiales SET 
                        estado = ?, 
                        id_recibido_por = ?, 
                        fecha_recibido = ? 
                        WHERE id_pedido = ?");
                    $stmt->execute([$accion, $id_usuario, $fecha_actual, $id_pedido]);
                    
                } elseif ($accion == 'entregado') {
                    // Mantener compatibilidad con entregado (legacy)
                    $stmt = $conn->prepare("UPDATE pedidos_materiales SET 
                        estado = ?, 
                        id_entregado_por = ?, 
                        fecha_entrega = ? 
                        WHERE id_pedido = ?");
                    $stmt->execute([$accion, $id_usuario, $fecha_actual, $id_pedido]);
                    
                } else {
                    // Para cancelado y otros estados
                    $stmt = $conn->prepare("UPDATE pedidos_materiales SET estado = ? WHERE id_pedido = ?");
                    $stmt->execute([$accion, $id_pedido]);

                    // Si se cancela, devolver stock de los materiales solicitados
                    if ($accion == 'cancelado' && $pedido_actual['estado'] == 'pendiente') {
                        $stmt_det = $conn->prepare("SELECT id_material, cantidad_solicitada FROM detalle_pedidos_materiales WHERE id_pedido = ?");
                        $stmt_det->execute([$id_pedido]);
                        $detalles_cancel = $stmt_det->fetchAll();

                        $stmt_add_stock = $conn->prepare("UPDATE materiales SET stock_actual = stock_actual + ? WHERE id_material = ?");
                        $stmt_log = $conn->prepare("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion) VALUES (?, ?, ?, ?, ?)");

                        foreach ($detalles_cancel as $d) {
                            $cantidad_devuelta = intval($d['cantidad_solicitada']);
                            if ($cantidad_devuelta > 0) {
                                $stmt_add_stock->execute([$cantidad_devuelta, $d['id_material']]);
                                $stmt_log->execute([
                                    $id_usuario,
                                    'stock_entrada',
                                    'materiales',
                                    "Devolución por cancelación pedido #" . str_pad($id_pedido, 4, '0', STR_PAD_LEFT) . " - Material ID: " . $d['id_material'] . " - Cantidad: " . $cantidad_devuelta,
                                    $fecha_actual
                                ]);
                            }
                        }
                    }
                }
                
                // Si se aprueba o entrega, actualizar cantidades entregadas
                if ($accion == 'aprobado' || $accion == 'entregado') {
                    $stmt_update_detalle = $conn->prepare("UPDATE detalle_pedidos_materiales SET 
                        cantidad_entregada = ? 
                        WHERE id_pedido = ? AND id_material = ?");
                    
                    foreach ($cantidades_entregadas as $id_material => $cantidad_entregada) {
                        $cantidad_entregada = intval($cantidad_entregada);
                        if ($cantidad_entregada > 0) {
                            // Actualizar cantidad entregada
                            $stmt_update_detalle->execute([$cantidad_entregada, $id_pedido, $id_material]);
                            
                            // En entrega no se descuenta stock aquí, ya fue descontado al crear el pedido
                        }
                    }
                }
                
                // Registrar en seguimiento
                $stmt_seguimiento = $conn->prepare("INSERT INTO seguimiento_pedidos 
                    (id_pedido, estado_anterior, estado_nuevo, observaciones, id_usuario_cambio, fecha_cambio) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_seguimiento->execute([$id_pedido, $pedido_actual['estado'], $accion, $observaciones, $id_usuario, $fecha_actual]);
                
                $conn->commit();
                $success = true;
                
                // Redireccionar después de procesar
                header("Location: view.php?id=" . $id_pedido . "&processed=1");
                exit();
                
            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = "Error al procesar el pedido: " . $e->getMessage();
                error_log("Error procesando pedido ID " . $id_pedido . ": " . $e->getMessage());
            }
        }
    }
}

try {
    // Obtener información del pedido
    $stmt = $conn->prepare("SELECT p.*, o.nombre_obra, u.nombre, u.apellido
                            FROM pedidos_materiales p
                            LEFT JOIN obras o ON p.id_obra = o.id_obra
                            LEFT JOIN usuarios u ON p.id_solicitante = u.id_usuario
                            WHERE p.id_pedido = ? AND p.estado NOT IN ('cancelado', 'recibido')");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        redirect(SITE_URL . '/modules/pedidos/list.php');
    }
    
    // Obtener detalles del pedido con análisis de stock
    $stmt_detalles = $conn->prepare("SELECT dp.*, m.nombre_material, m.stock_actual, m.stock_minimo, 
                                            m.precio_referencia, m.unidad_medida,
                                            CASE 
                                                WHEN m.stock_actual = 0 THEN 'sin_stock'
                                                WHEN m.stock_actual < dp.cantidad_solicitada THEN 'stock_parcial'
                                                ELSE 'disponible'
                                            END as estado_stock,
                                            CASE 
                                                WHEN m.stock_actual < dp.cantidad_solicitada THEN dp.cantidad_solicitada - m.stock_actual
                                                ELSE 0
                                            END as cantidad_faltante,
                                            CASE 
                                                WHEN m.stock_actual >= dp.cantidad_solicitada THEN dp.cantidad_solicitada
                                                ELSE m.stock_actual
                                            END as cantidad_disponible
                                     FROM detalle_pedidos_materiales dp
                                     LEFT JOIN materiales m ON dp.id_material = m.id_material
                                     WHERE dp.id_pedido = ?
                                     ORDER BY m.nombre_material");
    $stmt_detalles->execute([$id_pedido]);
    $detalles = $stmt_detalles->fetchAll();
    
} catch (Exception $e) {
    error_log("Error al obtener pedido: " . $e->getMessage());
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
                <i class="bi bi-gear"></i> Procesar Pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
            </h1>
            <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Pedido
            </a>
        </div>
    </div>
</div>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <div class="row">
        <div class="col-md-8">
            <!-- Información del pedido -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i> Información del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Obra:</strong> <?php echo htmlspecialchars($pedido['nombre_obra']); ?></p>
                            <p><strong>Solicitante:</strong> <?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></p>
                            <p><strong>Estado Actual:</strong> 
                                <?php 
                                switch($pedido['estado']) {
                                    case 'pendiente':
                                        echo '<span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pendiente</span>';
                                        break;
                                    case 'aprobado':
                                        echo '<span class="badge bg-info"><i class="bi bi-check-circle"></i> Aprobado</span>';
                                        break;
                                    case 'picking':
                                        echo '<span class="badge bg-warning"><i class="bi bi-box-seam"></i> En Picking</span>';
                                        break;
                                    case 'retirado':
                                        echo '<span class="badge bg-primary"><i class="bi bi-box-arrow-right"></i> Retirado</span>';
                                        break;
                                    case 'recibido':
                                        echo '<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Recibido</span>';
                                        break;
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Materiales y cantidades -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box-seam"></i> Materiales y Cantidades a Entregar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Solicitado</th>
                                    <th>Stock Disponible</th>
                                    <th>Estado</th>
                                    <th>Cantidad a Entregar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $detalle): ?>
                                <tr class="<?php echo $detalle['estado_stock'] == 'sin_stock' ? 'table-danger' : ($detalle['estado_stock'] == 'stock_parcial' ? 'table-warning' : ''); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($detalle['nombre_material']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($detalle['unidad_medida']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo number_format($detalle['cantidad_solicitada']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $detalle['stock_actual'] <= $detalle['stock_minimo'] ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                            <?php echo number_format($detalle['stock_actual']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        switch ($detalle['estado_stock']) {
                                            case 'disponible':
                                                echo '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Disponible</span>';
                                                break;
                                            case 'stock_parcial':
                                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Parcial</span>';
                                                echo '<br><small class="text-danger">Faltan: ' . number_format($detalle['cantidad_faltante']) . '</small>';
                                                break;
                                            case 'sin_stock':
                                                echo '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin Stock</span>';
                                                echo '<br><small class="text-danger">Requiere compra</small>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm" style="width: 120px;">
                                            <input type="number" 
                                                   class="form-control cantidad-entrega" 
                                                   name="cantidades_entregadas[<?php echo $detalle['id_material']; ?>]"
                                                   min="0" 
                                                   max="<?php echo $detalle['cantidad_disponible']; ?>"
                                                   value="<?php echo $detalle['cantidad_disponible']; ?>"
                                                   data-solicitado="<?php echo $detalle['cantidad_solicitada']; ?>"
                                                   data-disponible="<?php echo $detalle['cantidad_disponible']; ?>"
                                                   data-estado="<?php echo $detalle['estado_stock']; ?>">
                                            <span class="input-group-text"><?php echo htmlspecialchars($detalle['unidad_medida']); ?></span>
                                        </div>
                                        <?php if ($detalle['estado_stock'] == 'stock_parcial'): ?>
                                            <small class="text-muted">Máximo: <?php echo number_format($detalle['cantidad_disponible']); ?></small>
                                        <?php elseif ($detalle['estado_stock'] == 'sin_stock'): ?>
                                            <small class="text-danger">Sin stock disponible</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Nota:</strong> Las cantidades mostradas son las máximas disponibles en stock. 
                        Puede ajustar las cantidades según sea necesario.
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Acciones del pedido -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear"></i> Acción a Realizar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Acción <span class="text-danger">*</span></label>
                        
                        <?php if ($pedido['estado'] == 'pendiente'): ?>
                            <!-- Opciones para pedidos pendientes -->
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="accion" id="aprobar" value="aprobado" required checked>
                                <label class="form-check-label" for="aprobar">
                                    <i class="bi bi-check-circle text-info"></i> Aprobar Pedido
                                </label>
                                <small class="form-text text-muted d-block">El pedido queda aprobado para preparación</small>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="accion" id="cancelar" value="cancelado">
                                <label class="form-check-label" for="cancelar">
                                    <i class="bi bi-x-circle text-danger"></i> Cancelar Pedido
                                </label>
                                <small class="form-text text-muted d-block">El pedido será cancelado</small>
                            </div>
                            
                        <?php elseif ($pedido['estado'] == 'aprobado'): ?>
                            <!-- Opciones para pedidos aprobados -->
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="accion" id="retirar" value="retirado" required checked>
                                <label class="form-check-label" for="retirar">
                                    <i class="bi bi-box-arrow-right text-primary"></i> Marcar como Retirado
                                </label>
                                <small class="form-text text-muted d-block">El pedido será retirado y se descontará del stock</small>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="accion" id="cancelar" value="cancelado">
                                <label class="form-check-label" for="cancelar">
                                    <i class="bi bi-x-circle text-danger"></i> Cancelar Pedido
                                </label>
                                <small class="form-text text-muted d-block">El pedido será cancelado</small>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Nota:</strong> Al retirar, se descontará del stock automáticamente.
                            </div>
                            
                        <?php elseif ($pedido['estado'] == 'retirado'): ?>
                            <!-- Opciones para pedidos retirados -->
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="accion" id="recibir" value="recibido" required checked>
                                <label class="form-check-label" for="recibir">
                                    <i class="bi bi-check-circle-fill text-success"></i> Marcar como Recibido
                                </label>
                                <small class="form-text text-muted d-block">El pedido será marcado como recibido (etapa final)</small>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="accion" id="cancelar" value="cancelado">
                                <label class="form-check-label" for="cancelar">
                                    <i class="bi bi-x-circle text-danger"></i> Cancelar Pedido
                                </label>
                                <small class="form-text text-muted d-block">El pedido será cancelado</small>
                            </div>
                            
                            <div class="alert alert-success mt-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Nota:</strong> Al marcar como recibido, el pedido se dará por finalizado.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                  placeholder="Observaciones sobre el procesamiento del pedido..."></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Procesar Pedido
                        </button>
                        <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Resumen de entrega -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-check"></i> Resumen de Entrega
                    </h5>
                </div>
                <div class="card-body">
                    <div id="resumen-entrega">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Items a Entregar:</span>
                            <span id="items-entregar" class="fw-bold">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Entrega Completa:</span>
                            <span id="entrega-completa" class="fw-bold">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Entrega Parcial:</span>
                            <span id="entrega-parcial" class="fw-bold">0</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Sin Entregar:</span>
                            <span id="sin-entregar" class="fw-bold">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function actualizarResumen() {
    let itemsEntregar = 0;
    let entregaCompleta = 0;
    let entregaParcial = 0;
    let sinEntregar = 0;
    
    document.querySelectorAll('.cantidad-entrega').forEach(input => {
        const cantidad = parseInt(input.value) || 0;
        const solicitado = parseInt(input.dataset.solicitado);
        const disponible = parseInt(input.dataset.disponible);
        const estado = input.dataset.estado;
        
        if (cantidad > 0) {
            itemsEntregar++;
            
            if (cantidad === solicitado) {
                entregaCompleta++;
            } else {
                entregaParcial++;
            }
        } else {
            sinEntregar++;
        }
    });
    
    document.getElementById('items-entregar').textContent = itemsEntregar;
    document.getElementById('entrega-completa').textContent = entregaCompleta;
    document.getElementById('entrega-parcial').textContent = entregaParcial;
    document.getElementById('sin-entregar').textContent = sinEntregar;
}

// Event listeners
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('cantidad-entrega')) {
        actualizarResumen();
    }
});

document.addEventListener('change', function(e) {
    if (e.target.name === 'accion') {
        const accion = e.target.value;
        const inputs = document.querySelectorAll('.cantidad-entrega');
        
        if (accion === 'cancelado') {
            // Si se cancela, poner todas las cantidades en 0
            inputs.forEach(input => {
                input.value = 0;
                input.disabled = true;
            });
        } else {
            // Habilitar inputs y restaurar valores por defecto
            inputs.forEach(input => {
                input.disabled = false;
                if (input.value == 0) {
                    input.value = input.dataset.disponible;
                }
            });
        }
        
        actualizarResumen();
    }
});

// Si el pedido está aprobado, deshabilitar la opción de cancelar
document.addEventListener('DOMContentLoaded', function() {
    const pedidoEstado = '<?php echo $pedido['estado']; ?>';
    if (pedidoEstado === 'aprobado') {
        // Solo mostrar la opción de entregar para pedidos aprobados
        const radioEntregar = document.getElementById('entregar');
        if (radioEntregar) {
            radioEntregar.checked = true;
        }
    }
});

// Inicializar resumen
document.addEventListener('DOMContentLoaded', function() {
    actualizarResumen();
});
</script>

<?php include '../../includes/footer.php'; ?>

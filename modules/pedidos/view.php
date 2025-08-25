<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Detalle del Pedido';

$database = new Database();
$conn = $database->getConnection();

$id_pedido = $_GET['id'] ?? 0;
$created = $_GET['created'] ?? false;

if (!$id_pedido) {
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

try {
    // Obtener información del pedido
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
    
    // Obtener detalles del pedido usando la nueva tabla
    $stmt_detalles = $conn->prepare("SELECT d.*, m.nombre_material, m.stock_actual, m.stock_minimo, 
                                            m.unidad_medida
                                     FROM detalle_pedidos_materiales d
                                     LEFT JOIN materiales m ON d.id_material = m.id_material
                                     WHERE d.id_pedido = ?
                                     ORDER BY m.nombre_material");
    $stmt_detalles->execute([$id_pedido]);
    $detalles = $stmt_detalles->fetchAll();
    
    // Obtener seguimiento del pedido
    $stmt_seguimiento = $conn->prepare("SELECT s.*, u.nombre, u.apellido
                                       FROM seguimiento_pedidos s
                                       LEFT JOIN usuarios u ON s.id_usuario_cambio = u.id_usuario
                                       WHERE s.id_pedido = ?
                                       ORDER BY s.fecha_cambio DESC");
    $stmt_seguimiento->execute([$id_pedido]);
    $seguimiento = $stmt_seguimiento->fetchAll();
    
    // Calcular estadísticas adicionales
    $items_disponibles = 0;
    $items_parciales = 0;
    $items_sin_stock = 0;
    
    foreach ($detalles as $detalle) {
        switch ($detalle['estado_item']) {
            case 'disponible':
                $items_disponibles++;
                break;
            case 'parcial':
                $items_parciales++;
                break;
            case 'sin_stock':
                $items_sin_stock++;
                break;
        }
    }
    
} catch (Exception $e) {
    error_log("Error al obtener pedido: " . $e->getMessage());
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

include '../../includes/header.php';
?>

<!-- Print-only header with logo -->
<div class="print-only text-center mb-4">
    <img src="<?php echo SITE_URL; ?>/assets/img/logo_san_simon.png" alt="SAN SIMON SRL" style="max-width: 300px; height: auto;">
</div>

<div id="alert-container"></div>

<?php if ($created): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> El pedido ha sido creado exitosamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 no-print">
                <i class="bi bi-clipboard-check"></i> Pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
                <small class="text-muted">(<?php echo htmlspecialchars($pedido['numero_pedido']); ?>)</small>
            </h1>
            <div class="btn-group no-print">
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Lista
                </a>
                <?php if ($pedido['estado'] == 'pendiente'): ?>
                    <a href="edit.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                <?php endif; ?>
                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) && ($pedido['estado'] == 'pendiente' || $pedido['estado'] == 'aprobado')): ?>
                    <a href="process.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-success">
                        <i class="bi bi-gear"></i> Procesar Pedido
                    </a>
                <?php endif; ?>
                <button class="btn btn-outline-info" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

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
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Número:</strong></td>
                                <td><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Obra:</strong></td>
                                <td><?php echo htmlspecialchars($pedido['nombre_obra']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cliente:</strong></td>
                                <td><?php echo htmlspecialchars($pedido['cliente']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Dirección:</strong></td>
                                <td><?php echo htmlspecialchars($pedido['direccion']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Solicitante:</strong></td>
                                <td><?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo htmlspecialchars($pedido['email']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Fecha Pedido:</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Prioridad:</strong></td>
                                <td>
                                    <?php
                                    $prioridad_class = [
                                        'baja' => 'bg-secondary',
                                        'media' => 'bg-primary',
                                        'alta' => 'bg-warning text-dark',
                                        'urgente' => 'bg-danger'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $prioridad_class[$pedido['prioridad']] ?? 'bg-secondary'; ?>">
                                        <?php echo ucfirst($pedido['prioridad']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($pedido['observaciones'])): ?>
                <div class="mt-3">
                    <strong>Observaciones:</strong>
                    <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($pedido['observaciones'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Materiales del pedido -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-box-seam"></i> Materiales Solicitados
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Cantidad Solicitada</th>
                                <th>Stock Disponible</th>
                                <th>Estado</th>
                                <th class="print-hide">Precio Unit.</th>
                                <th class="print-hide">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total_cantidad_solicitada = 0; ?>
                            <?php foreach ($detalles as $detalle): ?>
                            <tr class="<?php echo $detalle['estado_item'] == 'sin_stock' ? 'table-danger' : ($detalle['estado_item'] == 'parcial' ? 'table-warning' : ''); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($detalle['nombre_material']); ?></strong>
                                    <br>
                                    <small class="text-muted">Unidad: <?php echo htmlspecialchars($detalle['unidad_medida']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary fs-6"><?php echo number_format($detalle['cantidad_solicitada']); ?></span>
                                </td>
                                <?php $total_cantidad_solicitada += $detalle['cantidad_solicitada']; ?>
                                <td>
                                    <span class="badge <?php echo $detalle['stock_actual'] <= $detalle['stock_minimo'] ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                        <?php echo number_format($detalle['stock_actual']); ?>
                                    </span>
                                    <?php if ($detalle['stock_actual'] <= $detalle['stock_minimo'] && $detalle['stock_actual'] > 0): ?>
                                        <br><small class="text-warning">Stock bajo</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    switch ($detalle['estado_item']) {
                                        case 'disponible':
                                            echo '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Disponible</span>';
                                            break;
                                        case 'parcial':
                                            echo '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Parcial</span>';
                                            echo '<br><small class="text-danger">Faltan: ' . number_format($detalle['cantidad_faltante']) . '</small>';
                                            break;
                                        case 'sin_stock':
                                            echo '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin Stock</span>';
                                            echo '<br><small class="text-danger">Requiere compra</small>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">Pendiente</span>';
                                    }
                                    ?>
                                </td>
                                <td class="print-hide">$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                <td class="print-hide">
                                    <strong>$<?php echo number_format($detalle['subtotal'], 2); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light print-hide">
                                <th colspan="5">Total del Pedido:</th>
                                <th>$<?php echo number_format($pedido['valor_total'], 2); ?></th>
                            </tr>
                            <tr class="table-light print-only">
                                <th colspan="3">Total Cantidad Solicitada:</th>
                                <th><?php echo number_format($total_cantidad_solicitada); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Estado del pedido -->
        <div class="card mb-4 no-print">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-flag"></i> Estado del Pedido
                </h5>
            </div>
            <div class="card-body text-center">
                <?php
                $estado_class = [
                    'pendiente' => 'bg-warning text-dark',
                    'aprobado' => 'bg-info',
                    'en_camino' => 'bg-primary',
                    'entregado' => 'bg-success',
                    'devuelto' => 'bg-secondary',
                    'cancelado' => 'bg-danger'
                ];
                $estado_icons = [
                    'pendiente' => 'clock',
                    'aprobado' => 'check-circle',
                    'en_camino' => 'truck',
                    'entregado' => 'check-square',
                    'devuelto' => 'arrow-return-left',
                    'cancelado' => 'x-circle'
                ];
                ?>
                <span class="badge <?php echo $estado_class[$pedido['estado']] ?? 'bg-secondary'; ?> fs-6 p-3">
                    <i class="bi bi-<?php echo $estado_icons[$pedido['estado']] ?? 'question'; ?>"></i>
                    <?php echo ucfirst($pedido['estado']); ?>
                </span>
            </div>
        </div>
        
        <!-- Resumen de stock -->
        <div class="card mb-4 no-print">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart"></i> Análisis de Stock
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Items:</span>
                    <span class="fw-bold"><?php echo $pedido['total_items']; ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-success">Disponibles:</span>
                    <span class="text-success fw-bold"><?php echo $items_disponibles; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-warning">Stock Parcial:</span>
                    <span class="text-warning fw-bold"><?php echo $items_parciales; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-danger">Sin Stock:</span>
                    <span class="text-danger fw-bold"><?php echo $items_sin_stock; ?></span>
                </div>
                
                <hr>
                <h6>Análisis Financiero:</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span>Valor Total:</span>
                    <span class="fw-bold">$<?php echo number_format($pedido['valor_total'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-success">Disponible:</span>
                    <span class="text-success">$<?php echo number_format($pedido['valor_disponible'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-danger">A Comprar:</span>
                    <span class="text-danger fw-bold">$<?php echo number_format($pedido['valor_a_comprar'], 2); ?></span>
                </div>
                
                <?php if ($pedido['valor_a_comprar'] > 0): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Atención:</strong> Este pedido requiere compras por $<?php echo number_format($pedido['valor_a_comprar'], 2); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Seguimiento -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history"></i> Seguimiento
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($seguimiento)): ?>
                    <div class="timeline">
                        <?php foreach ($seguimiento as $evento): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1"><?php echo ucfirst($evento['estado_nuevo']); ?></h6>
                                <p class="mb-1 text-muted small">
                                    <?php echo date('d/m/Y H:i', strtotime($evento['fecha_cambio'])); ?>
                                </p>
                                <p class="mb-1 small">
                                    Por: <?php echo htmlspecialchars($evento['nombre'] . ' ' . $evento['apellido']); ?>
                                </p>
                                <?php if (!empty($evento['observaciones'])): ?>
                                <p class="mb-0 small text-muted">
                                    <?php echo htmlspecialchars($evento['observaciones']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No hay seguimiento disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Print-only footer -->
<div class="print-only mt-5">
    <div class="border-top pt-4">
        <p class="text-center mb-3">
            <strong>Nota:</strong> Por favor controlar al momento de la descarga, dejar firma por triplicado con fecha y lugar.
        </p>
        <div class="row">
            <div class="col-6">
                <p class="mb-1"><strong>Entregado a:</strong> .......................</p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1">Formosa, ..... de ............................. de 20.....</p>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

/* Print helpers */
@media print {
    .print-hide { display: none !important; }
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { margin: 0; padding: 20px; }
    .container-fluid { max-width: none; }
}
@media screen {
    .print-only { display: none !important; }
}
</style>

<?php include '../../includes/footer.php'; ?>

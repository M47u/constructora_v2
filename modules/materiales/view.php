<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden ver materiales
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Detalles del Material';

$database = new Database();
$conn = $database->getConnection();

$material_id = (int)($_GET['id'] ?? 0);
$errors = [];
$success = ''; 

if ($material_id <= 0) {
    redirect(SITE_URL . '/modules/materiales/list.php');
}
 
try {
    // Obtener datos del material
    $query = "SELECT * FROM materiales WHERE id_material = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();

    if (!$material) {
        redirect(SITE_URL . '/modules/materiales/list.php');
    }

    // Obtener historial de pedidos de este material
    $query_pedidos = "SELECT pm.*, o.nombre_obra, u.nombre, u.apellido, dp.cantidad, dp.cantidad_entregada
                      FROM detalle_pedido dp
                      JOIN pedidos_materiales pm ON dp.id_pedido = pm.id_pedido
                      JOIN obras o ON pm.id_obra = o.id_obra
                      JOIN usuarios u ON pm.id_solicitante = u.id_usuario
                      WHERE dp.id_material = ?
                      ORDER BY pm.fecha_pedido DESC
                      LIMIT 10";
    
    $stmt_pedidos = $conn->prepare($query_pedidos);
    $stmt_pedidos->execute([$material_id]);
    $pedidos = $stmt_pedidos->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener material: " . $e->getMessage());
    redirect(SITE_URL . '/modules/materiales/list.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($material['nombre_material']); ?>
            </h1>
            <div>
                <a href="adjust_stock.php?id=<?php echo $material['id_material']; ?>" class="btn btn-warning">
                    <i class="bi bi-arrow-up-down"></i> Ajustar Stock
                </a>
                <a href="edit.php?id=<?php echo $material['id_material']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información principal -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información del Material
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Nombre del Material</h6>
                        <p class="mb-3 fs-5"><?php echo htmlspecialchars($material['nombre_material']); ?></p>
                        
                        <h6 class="text-muted">Unidad de Medida</h6>
                        <p class="mb-3">
                            <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($material['unidad_medida']); ?></span>
                        </p>
                        
                        <h6 class="text-muted">Precio de Referencia</h6>
                        <p class="mb-3">
                            <span class="fs-4 text-success">$<?php echo number_format($material['precio_referencia'], 2); ?></span>
                            <small class="text-muted">por <?php echo htmlspecialchars($material['unidad_medida']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Stock Actual</h6>
                        <p class="mb-3">
                            <span class="fs-3 <?php echo $material['stock_actual'] <= $material['stock_minimo'] ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($material['stock_actual']); ?>
                            </span>
                            <small class="text-muted"><?php echo htmlspecialchars($material['unidad_medida']); ?></small>
                        </p>
                        
                        <h6 class="text-muted">Stock Mínimo</h6>
                        <p class="mb-3">
                            <span class="fs-5 text-warning"><?php echo number_format($material['stock_minimo']); ?></span>
                            <small class="text-muted"><?php echo htmlspecialchars($material['unidad_medida']); ?></small>
                        </p>
                        
                        <h6 class="text-muted">Valor Total en Stock</h6>
                        <p class="mb-3">
                            <span class="fs-4 text-primary">$<?php echo number_format($material['stock_actual'] * $material['precio_referencia'], 2); ?></span>
                        </p>
                    </div>
                </div>
                
                <!-- Barra de progreso del stock -->
                <div class="mt-4">
                    <h6 class="text-muted">Estado del Stock</h6>
                    <?php 
                    $porcentaje_stock = $material['stock_minimo'] > 0 ? min(100, ($material['stock_actual'] / ($material['stock_minimo'] * 2)) * 100) : 100;
                    $color_barra = $material['stock_actual'] <= $material['stock_minimo'] ? 'bg-danger' : ($porcentaje_stock < 50 ? 'bg-warning' : 'bg-success');
                    ?>
                    <div class="progress mb-2">
                        <div class="progress-bar <?php echo $color_barra; ?>" role="progressbar" 
                             style="width: <?php echo $porcentaje_stock; ?>%" 
                             aria-valuenow="<?php echo $porcentaje_stock; ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">0</small>
                        <small class="text-muted">Stock Mínimo: <?php echo $material['stock_minimo']; ?></small>
                        <small class="text-muted">Óptimo: <?php echo $material['stock_minimo'] * 2; ?></small>
                    </div>
                </div>
                
                <?php if ($material['stock_actual'] <= $material['stock_minimo']): ?>
                <div class="alert alert-warning mt-3" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>¡Atención!</strong> El stock actual está por debajo del mínimo requerido.
                    Se recomienda realizar un pedido urgente.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel lateral con estadísticas -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> Estadísticas
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if ($material['stock_actual'] <= $material['stock_minimo']): ?>
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="text-danger mt-2">Stock Bajo</h5>
                    <?php elseif ($material['stock_actual'] == 0): ?>
                        <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="text-danger mt-2">Sin Stock</h5>
                    <?php else: ?>
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="text-success mt-2">Stock Disponible</h5>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <h6 class="text-muted">Información Adicional</h6>
                <p class="mb-2">
                    <small class="text-muted">Fecha de creación:</small><br>
                    <?php echo date('d/m/Y H:i', strtotime($material['fecha_creacion'])); ?>
                </p>
                <p class="mb-2">
                    <small class="text-muted">Última actualización:</small><br>
                    <?php echo date('d/m/Y H:i', strtotime($material['fecha_actualizacion'])); ?>
                </p>
                
                <hr>
                
                <h6 class="text-muted">Acciones Rápidas</h6>
                <div class="d-grid gap-2">
                    <a href="adjust_stock.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-arrow-up-down"></i> Ajustar Stock
                    </a>
                    <a href="edit.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil"></i> Editar Material
                    </a>
                    <a href="../pedidos/create.php?material_id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-cart-plus"></i> Crear Pedido
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historial de pedidos -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Historial de Pedidos
            </div>
            <div class="card-body">
                <?php if (!empty($pedidos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Obra</th>
                                <th>Solicitante</th>
                                <th>Cantidad</th>
                                <th>Entregado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($pedido['fecha_pedido'])); ?></td>
                                <td><?php echo htmlspecialchars($pedido['nombre_obra']); ?></td>
                                <td><?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo number_format($pedido['cantidad']); ?> <?php echo htmlspecialchars($material['unidad_medida']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo number_format($pedido['cantidad_entregada']); ?> <?php echo htmlspecialchars($material['unidad_medida']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    switch ($pedido['estado']) {
                                        case 'pendiente':
                                            $estado_class = 'bg-warning text-dark';
                                            break;
                                        case 'aprobado':
                                            $estado_class = 'bg-info';
                                            break;
                                        case 'en_camino':
                                            $estado_class = 'bg-primary';
                                            break;
                                        case 'entregado':
                                            $estado_class = 'bg-success';
                                            break;
                                        case 'devuelto':
                                            $estado_class = 'bg-secondary';
                                            break;
                                        case 'cancelado':
                                            $estado_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $estado_class; ?>">
                                        <?php echo ucfirst($pedido['estado']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-cart text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">Sin historial de pedidos</h5>
                    <p class="text-muted">Este material aún no ha sido solicitado en ningún pedido.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

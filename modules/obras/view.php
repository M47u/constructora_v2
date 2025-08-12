<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Detalles de Obra';

$database = new Database();
$conn = $database->getConnection();

$obra_id = (int)($_GET['id'] ?? 0);

if ($obra_id <= 0) {
    redirect(SITE_URL . '/modules/obras/list.php');
}

try {
    // Obtener datos de la obra
    $query = "SELECT o.*, u.nombre, u.apellido, u.email 
              FROM obras o 
              JOIN usuarios u ON o.id_responsable = u.id_usuario 
              WHERE o.id_obra = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$obra_id]);
    $obra = $stmt->fetch();

    if (!$obra) {
        redirect(SITE_URL . '/modules/obras/list.php');
    }

    // Obtener pedidos de materiales de esta obra
    $query_pedidos = "SELECT pm.*, COUNT(dp.id_detalle) as total_items,
                      SUM(dp.cantidad * m.precio_referencia) as valor_estimado
                      FROM pedidos_materiales pm
                      LEFT JOIN detalle_pedido dp ON pm.id_pedido = dp.id_pedido
                      LEFT JOIN materiales m ON dp.id_material = m.id_material
                      WHERE pm.id_obra = ?
                      GROUP BY pm.id_pedido
                      ORDER BY pm.fecha_pedido DESC
                      LIMIT 10";
    
    $stmt_pedidos = $conn->prepare($query_pedidos);
    $stmt_pedidos->execute([$obra_id]);
    $pedidos = $stmt_pedidos->fetchAll();

    // Obtener préstamos de herramientas de esta obra
    $query_prestamos = "SELECT p.*, u.nombre, u.apellido, COUNT(dp.id_detalle) as total_herramientas
                        FROM prestamos p
                        JOIN usuarios u ON p.id_empleado = u.id_usuario
                        LEFT JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
                        WHERE p.id_obra = ?
                        GROUP BY p.id_prestamo
                        ORDER BY p.fecha_retiro DESC
                        LIMIT 10";
    
    $stmt_prestamos = $conn->prepare($query_prestamos);
    $stmt_prestamos->execute([$obra_id]);
    $prestamos = $stmt_prestamos->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener obra: " . $e->getMessage());
    redirect(SITE_URL . '/modules/obras/list.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-building"></i> <?php echo htmlspecialchars($obra['nombre_obra']); ?>
            </h1>
            <div>
                <?php if (has_permission(ROLE_ADMIN) || ($_SESSION['user_id'] == $obra['id_responsable'])): ?>
                <a href="edit.php?id=<?php echo $obra['id_obra']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <?php endif; ?>
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
                <i class="bi bi-info-circle"></i> Información General
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Cliente</h6>
                        <p class="mb-3"><?php echo htmlspecialchars($obra['cliente']); ?></p>
                        
                        <h6 class="text-muted">Responsable</h6>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($obra['nombre'] . ' ' . $obra['apellido']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($obra['email']); ?></small>
                        </p>
                        
                        <h6 class="text-muted">Estado</h6>
                        <p class="mb-3">
                            <?php
                            $badge_class = '';
                            switch ($obra['estado']) {
                                case 'planificada':
                                    $badge_class = 'bg-info';
                                    break;
                                case 'en_progreso':
                                    $badge_class = 'bg-warning text-dark';
                                    break;
                                case 'finalizada':
                                    $badge_class = 'bg-success';
                                    break;
                                case 'cancelada':
                                    $badge_class = 'bg-danger';
                                    break;
                            }
                            ?>
                            <span class="badge <?php echo $badge_class; ?> fs-6">
                                <?php echo ucfirst(str_replace('_', ' ', $obra['estado'])); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Ubicación</h6>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($obra['direccion']); ?><br>
                            <?php echo htmlspecialchars($obra['localidad'] . ', ' . $obra['provincia']); ?>
                        </p>
                        
                        <h6 class="text-muted">Fecha de Inicio</h6>
                        <p class="mb-3"><?php echo date('d/m/Y', strtotime($obra['fecha_inicio'])); ?></p>
                        
                        <h6 class="text-muted">Fecha de Fin</h6>
                        <p class="mb-3">
                            <?php if ($obra['fecha_fin']): ?>
                                <?php echo date('d/m/Y', strtotime($obra['fecha_fin'])); ?>
                            <?php else: ?>
                                <span class="text-muted">No definida</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> Estadísticas
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo count($pedidos); ?></h4>
                            <small class="text-muted">Pedidos</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-info"><?php echo count($prestamos); ?></h4>
                        <small class="text-muted">Préstamos</small>
                    </div>
                </div>
                
                <?php
                $dias_transcurridos = floor((time() - strtotime($obra['fecha_inicio'])) / (60 * 60 * 24));
                if ($obra['fecha_fin']) {
                    $dias_totales = floor((strtotime($obra['fecha_fin']) - strtotime($obra['fecha_inicio'])) / (60 * 60 * 24));
                    $progreso = min(100, max(0, ($dias_transcurridos / $dias_totales) * 100));
                } else {
                    $progreso = null;
                }
                ?>
                
                <h6 class="text-muted mt-3">Duración</h6>
                <p class="mb-2">
                    <strong><?php echo max(0, $dias_transcurridos); ?></strong> días transcurridos
                </p>
                
                <?php if ($progreso !== null): ?>
                <div class="progress mb-2">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progreso; ?>%" 
                         aria-valuenow="<?php echo $progreso; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($progreso, 1); ?>%
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Pedidos de materiales -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart"></i> Pedidos de Materiales</span>
                <a href="<?php echo SITE_URL; ?>/modules/pedidos/create.php?obra_id=<?php echo $obra['id_obra']; ?>" 
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Nuevo Pedido
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($pedidos)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pedidos as $pedido): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Pedido #<?php echo $pedido['id_pedido']; ?></h6>
                            <small><?php echo date('d/m/Y', strtotime($pedido['fecha_pedido'])); ?></small>
                        </div>
                        <p class="mb-1">
                            <span class="badge bg-<?php echo $pedido['estado'] === 'entregado' ? 'success' : ($pedido['estado'] === 'pendiente' ? 'warning' : 'info'); ?>">
                                <?php echo ucfirst($pedido['estado']); ?>
                            </span>
                            - <?php echo $pedido['total_items']; ?> items
                        </p>
                        <?php if ($pedido['valor_estimado']): ?>
                        <small class="text-muted">
                            Valor estimado: $<?php echo number_format($pedido['valor_estimado'], 2); ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/modules/pedidos/list.php?obra_id=<?php echo $obra['id_obra']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        Ver Todos los Pedidos
                    </a>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-cart text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No hay pedidos registrados</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Préstamos de herramientas -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tools"></i> Préstamos de Herramientas</span>
                <a href="<?php echo SITE_URL; ?>/modules/herramientas/prestamos.php?obra_id=<?php echo $obra['id_obra']; ?>" 
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Nuevo Préstamo
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($prestamos)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($prestamos as $prestamo): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($prestamo['nombre'] . ' ' . $prestamo['apellido']); ?></h6>
                            <small><?php echo date('d/m/Y', strtotime($prestamo['fecha_retiro'])); ?></small>
                        </div>
                        <p class="mb-1">
                            <?php echo $prestamo['total_herramientas']; ?> herramientas retiradas
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/prestamos.php?obra_id=<?php echo $obra['id_obra']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        Ver Todos los Préstamos
                    </a>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-tools text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No hay préstamos registrados</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

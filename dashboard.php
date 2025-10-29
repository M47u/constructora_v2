<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Dashboard';

// Obtener estadísticas básicas
$database = new Database();
$conn = $database->getConnection();

try {
    // Contar obras activas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM obras WHERE estado IN ('planificada', 'en_progreso')");
    $obras_activas = $stmt->fetch()['total'];

    // Contar materiales con stock bajo
    $stmt = $conn->query("SELECT COUNT(*) as total FROM materiales WHERE stock_actual <= stock_minimo");
    $materiales_stock_bajo = $stmt->fetch()['total'];

    // Contar pedidos pendientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM pedidos_materiales WHERE estado = 'pendiente'");
    $pedidos_pendientes = $stmt->fetch()['total'];

    // Contar tareas pendientes del usuario
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tareas WHERE id_empleado = ? AND estado IN ('pendiente', 'en_proceso')");
    $stmt->execute([$_SESSION['user_id']]);
    $tareas_pendientes = $stmt->fetch()['total'];

    // Obtener últimas actividades (obras recientes)
    $stmt = $conn->query("SELECT o.nombre_obra, o.fecha_inicio, u.nombre, u.apellido 
                         FROM obras o 
                         JOIN usuarios u ON o.id_responsable = u.id_usuario 
                         ORDER BY o.fecha_creacion DESC LIMIT 5");
    $ultimas_obras = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $obras_activas = $materiales_stock_bajo = $pedidos_pendientes = $tareas_pendientes = 0;
    $ultimas_obras = [];
}

include 'includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-speedometer2"></i> Tablero de Control
            <small class="text-muted">- Bienvenido, <?php echo $_SESSION['user_name']; ?></small>
        </h1>
    </div>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row mb-4">
    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Obras Activas
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $obras_activas; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-building-gear dashboard-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission(ROLE_ADMIN)): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Stock Bajo
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $materiales_stock_bajo; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-exclamation-triangle dashboard-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Pedidos Pendientes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $pedidos_pendientes; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-cart dashboard-icon text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Mis Tareas Pendientes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $tareas_pendientes; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check dashboard-icon text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Accesos rápidos -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning"></i> Accesos Rápidos
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/obras/create.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-plus-circle"></i><br>
                            Nueva Obra
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/pedidos/create.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-cart-plus"></i><br>
                            Nuevo Pedido
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (has_permission(ROLE_ADMIN)): ?>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/usuarios/create.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-person-plus"></i><br>
                            Nuevo Usuario
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/tareas/list.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-list-task"></i><br>
                            Ver Tareas
                        </a>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/herramientas/list.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-tools"></i><br>
                            Herramientas
                        </a>
                    </div>
                    
                    <?php if (has_permission(ROLE_ADMIN)): ?>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/materiales/stock_bajo.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-exclamation-triangle"></i><br>
                            Stock Bajo
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!--<div class="col-md-4 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/migraciones/interfaz_migracion.php" class="btn btn-outline-dark w-100">
                            <i class="bi bi-arrow-repeat"></i><br>
                            Migración de Herramientas
                        </a>
                    </div>-->
                </div>
            </div>
        </div>
    </div>

    <!-- Actividad reciente -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Obras Recientes
            </div>
            <div class="card-body">
                <?php if (!empty($ultimas_obras)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ultimas_obras as $obra): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($obra['nombre_obra']); ?></h6>
                                <small><?php echo date('d/m/Y', strtotime($obra['fecha_inicio'])); ?></small>
                            </div>
                            <p class="mb-1">
                                <small class="text-muted">
                                    Responsable: <?php echo htmlspecialchars($obra['nombre'] . ' ' . $obra['apellido']); ?>
                                </small>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No hay obras registradas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Dashboard de Tareas';

$database = new Database();
$conn = $database->getConnection();

$es_empleado = get_user_role() === ROLE_EMPLEADO;
$user_id = $_SESSION['user_id'];

try {
    // Obtener tareas del usuario (si es empleado) o todas (si es admin/responsable)
    if ($es_empleado) {
        // Tareas del empleado logueado
        $query_mis_tareas = "SELECT t.*, 
                            asig.nombre as asignador_nombre, asig.apellido as asignador_apellido
                            FROM tareas t 
                            JOIN usuarios asig ON t.id_asignador = asig.id_usuario
                            WHERE t.id_empleado = ? AND t.estado != 'finalizada'
                            ORDER BY 
                                CASE t.prioridad 
                                    WHEN 'urgente' THEN 1 
                                    WHEN 'alta' THEN 2 
                                    WHEN 'media' THEN 3 
                                    WHEN 'baja' THEN 4 
                                END,
                                t.fecha_vencimiento ASC";
        
        $stmt = $conn->prepare($query_mis_tareas);
        $stmt->execute([$user_id]);
        $mis_tareas = $stmt->fetchAll();

        // Estadísticas del empleado
        $query_stats = "SELECT 
                       COUNT(*) as total,
                       COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                       COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                       COUNT(CASE WHEN estado = 'finalizada' THEN 1 END) as finalizadas,
                       COUNT(CASE WHEN fecha_vencimiento < CURDATE() AND estado != 'finalizada' THEN 1 END) as vencidas
                       FROM tareas WHERE id_empleado = ?";
        
        $stmt_stats = $conn->prepare($query_stats);
        $stmt_stats->execute([$user_id]);
        $stats = $stmt_stats->fetch();

        // Tareas completadas recientemente
        $query_completadas = "SELECT t.*, 
                             asig.nombre as asignador_nombre, asig.apellido as asignador_apellido
                             FROM tareas t 
                             JOIN usuarios asig ON t.id_asignador = asig.id_usuario
                             WHERE t.id_empleado = ? AND t.estado = 'finalizada'
                             ORDER BY t.fecha_finalizacion DESC LIMIT 5";
        
        $stmt_completadas = $conn->prepare($query_completadas);
        $stmt_completadas->execute([$user_id]);
        $tareas_completadas = $stmt_completadas->fetchAll();

    } else {
        // Vista para administradores y responsables
        $query_resumen = "SELECT 
                         COUNT(*) as total_tareas,
                         COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                         COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                         COUNT(CASE WHEN estado = 'finalizada' THEN 1 END) as finalizadas,
                         COUNT(CASE WHEN fecha_vencimiento < CURDATE() AND estado != 'finalizada' THEN 1 END) as vencidas
                         FROM tareas";
        
        $stmt_resumen = $conn->query($query_resumen);
        $stats = $stmt_resumen->fetch();

        // Tareas urgentes y vencidas
        $query_urgentes = "SELECT t.*, 
                          emp.nombre as empleado_nombre, emp.apellido as empleado_apellido
                          FROM tareas t 
                          JOIN usuarios emp ON t.id_empleado = emp.id_usuario
                          WHERE (t.prioridad = 'urgente' OR (t.fecha_vencimiento < CURDATE() AND t.estado != 'finalizada'))
                          AND t.estado != 'finalizada'
                          ORDER BY t.prioridad = 'urgente' DESC, t.fecha_vencimiento ASC
                          LIMIT 10";
        
        $stmt_urgentes = $conn->query($query_urgentes);
        $tareas_urgentes = $stmt_urgentes->fetchAll();

        // Empleados con más tareas pendientes
        $query_empleados = "SELECT u.nombre, u.apellido, 
                           COUNT(t.id_tarea) as tareas_pendientes
                           FROM usuarios u
                           LEFT JOIN tareas t ON u.id_usuario = t.id_empleado AND t.estado IN ('pendiente', 'en_proceso')
                           WHERE u.estado = 'activo'
                           GROUP BY u.id_usuario, u.nombre, u.apellido
                           ORDER BY tareas_pendientes DESC
                           LIMIT 5";
        
        $stmt_empleados = $conn->query($query_empleados);
        $empleados_stats = $stmt_empleados->fetchAll();

        $mis_tareas = [];
        $tareas_completadas = [];
    }

} catch (Exception $e) {
    error_log("Error en dashboard de tareas: " . $e->getMessage());
    $mis_tareas = [];
    $tareas_completadas = [];
    $tareas_urgentes = [];
    $empleados_stats = [];
    $stats = ['total' => 0, 'pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'vencidas' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-speedometer2"></i> 
            <?php echo $es_empleado ? 'Mi Dashboard de Tareas' : 'Dashboard de Tareas del Equipo'; ?>
        </h1>
    </div>
</div>

<!-- Estadísticas generales -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h3 class="text-primary"><?php echo $stats['total'] ?? $stats['total_tareas']; ?></h3>
                <small class="text-muted">Total Tareas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card warning">
            <div class="card-body text-center">
                <h3 class="text-warning"><?php echo $stats['pendientes']; ?></h3>
                <small class="text-muted">Pendientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card info">
            <div class="card-body text-center">
                <h3 class="text-info"><?php echo $stats['en_proceso']; ?></h3>
                <small class="text-muted">En Proceso</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card success">
            <div class="card-body text-center">
                <h3 class="text-success"><?php echo $stats['finalizadas']; ?></h3>
                <small class="text-muted">Finalizadas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card danger">
            <div class="card-body text-center">
                <h3 class="text-danger"><?php echo $stats['vencidas']; ?></h3>
                <small class="text-muted">Vencidas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <?php 
                $total = $stats['total'] ?? $stats['total_tareas'];
                $porcentaje = $total > 0 ? round(($stats['finalizadas'] / $total) * 100) : 0;
                ?>
                <h3 class="text-secondary"><?php echo $porcentaje; ?>%</h3>
                <small class="text-muted">Completado</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php if ($es_empleado): ?>
    <!-- Vista para empleados -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-task"></i> Mis Tareas Activas</span>
                <a href="list.php" class="btn btn-sm btn-outline-primary">Ver Todas</a>
            </div>
            <div class="card-body">
                <?php if (!empty($mis_tareas)): ?>
                <div class="row">
                    <?php foreach ($mis_tareas as $tarea): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 <?php echo $tarea['fecha_vencimiento'] < date('Y-m-d') ? 'border-danger' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($tarea['titulo']); ?></h6>
                                    <?php
                                    $prioridad_class = '';
                                    switch ($tarea['prioridad']) {
                                        case 'urgente': $prioridad_class = 'text-danger'; break;
                                        case 'alta': $prioridad_class = 'text-warning'; break;
                                        case 'media': $prioridad_class = 'text-info'; break;
                                        case 'baja': $prioridad_class = 'text-secondary'; break;
                                    }
                                    ?>
                                    <small class="<?php echo $prioridad_class; ?>">
                                        <?php echo ucfirst($tarea['prioridad']); ?>
                                    </small>
                                </div>
                                
                                <p class="card-text small text-muted">
                                    <?php echo htmlspecialchars(substr($tarea['descripcion'], 0, 80)); ?>...
                                </p>
                                
                                <?php if ($tarea['fecha_vencimiento']): ?>
                                <p class="card-text">
                                    <small class="<?php echo $tarea['fecha_vencimiento'] < date('Y-m-d') ? 'text-danger' : 'text-muted'; ?>">
                                        <i class="bi bi-clock"></i> 
                                        <?php echo date('d/m/Y', strtotime($tarea['fecha_vencimiento'])); ?>
                                    </small>
                                </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $tarea['estado'] === 'pendiente' ? 'warning text-dark' : 'info'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?>
                                    </span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-outline-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">¡Excelente trabajo!</h5>
                    <p class="text-muted">No tienes tareas pendientes en este momento.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-check-circle"></i> Tareas Completadas Recientemente
            </div>
            <div class="card-body">
                <?php if (!empty($tareas_completadas)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($tareas_completadas as $tarea): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($tarea['titulo']); ?></h6>
                            <small><?php echo date('d/m', strtotime($tarea['fecha_finalizacion'])); ?></small>
                        </div>
                        <small class="text-muted">
                            Asignada por: <?php echo htmlspecialchars($tarea['asignador_nombre'] . ' ' . $tarea['asignador_apellido']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No hay tareas completadas recientemente</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Vista para administradores y responsables -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle"></i> Tareas Urgentes y Vencidas</span>
                <a href="list.php?vencimiento=vencidas" class="btn btn-sm btn-outline-danger">Ver Todas</a>
            </div>
            <div class="card-body">
                <?php if (!empty($tareas_urgentes)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tarea</th>
                                <th>Empleado</th>
                                <th>Prioridad</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tareas_urgentes as $tarea): ?>
                            <tr class="<?php echo $tarea['fecha_vencimiento'] < date('Y-m-d') ? 'table-danger' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($tarea['titulo']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($tarea['empleado_nombre'] . ' ' . $tarea['empleado_apellido']); ?></td>
                                <td>
                                    <?php
                                    $prioridad_class = '';
                                    switch ($tarea['prioridad']) {
                                        case 'urgente': $prioridad_class = 'bg-danger'; break;
                                        case 'alta': $prioridad_class = 'bg-warning text-dark'; break;
                                        case 'media': $prioridad_class = 'bg-info'; break;
                                        case 'baja': $prioridad_class = 'bg-secondary'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $prioridad_class; ?>">
                                        <?php echo ucfirst($tarea['prioridad']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($tarea['fecha_vencimiento']): ?>
                                        <span class="<?php echo $tarea['fecha_vencimiento'] < date('Y-m-d') ? 'text-danger' : ''; ?>">
                                            <?php echo date('d/m/Y', strtotime($tarea['fecha_vencimiento'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $tarea['estado'] === 'pendiente' ? 'warning text-dark' : 'info'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">¡Todo bajo control!</h5>
                    <p class="text-muted">No hay tareas urgentes o vencidas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people"></i> Carga de Trabajo por Empleado
            </div>
            <div class="card-body">
                <?php if (!empty($empleados_stats)): ?>
                <?php foreach ($empleados_stats as $empleado): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-0"><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']); ?></h6>
                    </div>
                    <div>
                        <span class="badge <?php echo $empleado['tareas_pendientes'] > 5 ? 'bg-danger' : ($empleado['tareas_pendientes'] > 2 ? 'bg-warning text-dark' : 'bg-success'); ?>">
                            <?php echo $empleado['tareas_pendientes']; ?> tareas
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-3">
                    <a href="create.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus"></i> Asignar Nueva Tarea
                    </a>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No hay datos de empleados</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Accesos rápidos -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning"></i> Accesos Rápidos
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($es_empleado): ?>
                    <div class="col-md-3 mb-3">
                        <a href="list.php?estado=pendiente" class="btn btn-outline-warning w-100">
                            <i class="bi bi-clock"></i><br>
                            Mis Tareas Pendientes
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="list.php?estado=en_proceso" class="btn btn-outline-info w-100">
                            <i class="bi bi-play-circle"></i><br>
                            En Proceso
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="list.php?estado=finalizada" class="btn btn-outline-success w-100">
                            <i class="bi bi-check-circle"></i><br>
                            Completadas
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="list.php?vencimiento=semana" class="btn btn-outline-primary w-100">
                            <i class="bi bi-calendar-week"></i><br>
                            Esta Semana
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="col-md-2 mb-3">
                        <a href="create.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-plus-circle"></i><br>
                            Nueva Tarea
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="list.php?estado=pendiente" class="btn btn-outline-warning w-100">
                            <i class="bi bi-clock"></i><br>
                            Pendientes
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="list.php?vencimiento=vencidas" class="btn btn-outline-danger w-100">
                            <i class="bi bi-exclamation-triangle"></i><br>
                            Vencidas
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="list.php?prioridad=urgente" class="btn btn-outline-danger w-100">
                            <i class="bi bi-fire"></i><br>
                            Urgentes
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="list.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-list"></i><br>
                            Todas
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="<?php echo SITE_URL; ?>/modules/usuarios/list.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-people"></i><br>
                            Empleados
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

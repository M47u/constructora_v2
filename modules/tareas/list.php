<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Gestión de Tareas';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_vencimiento = $_GET['vencimiento'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Si es empleado, solo ve sus propias tareas
$es_empleado = get_user_role() === ROLE_EMPLEADO;
if ($es_empleado) {
    $filtro_empleado = $_SESSION['user_id'];
}

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_empleado)) {
    $where_conditions[] = "t.id_empleado = ?";
    $params[] = $filtro_empleado;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "t.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_prioridad)) {
    $where_conditions[] = "t.prioridad = ?";
    $params[] = $filtro_prioridad;
}

if (!empty($filtro_vencimiento)) {
    switch ($filtro_vencimiento) {
        case 'vencidas':
            $where_conditions[] = "t.fecha_vencimiento < CURDATE() AND t.estado != 'finalizada'";
            break;
        case 'hoy':
            $where_conditions[] = "t.fecha_vencimiento = CURDATE()";
            break;
        case 'semana':
            $where_conditions[] = "t.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
    }
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(t.titulo LIKE ? OR t.descripcion LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    // Contar total de tareas para los filtros actuales
    $count_query = "SELECT COUNT(*) FROM tareas t 
        JOIN usuarios emp ON t.id_empleado = emp.id_usuario
        JOIN usuarios asig ON t.id_asignador = asig.id_usuario
        $where_clause";
    $stmt_count = $conn->prepare($count_query);
    $stmt_count->execute($params);
    $total_tareas = $stmt_count->fetchColumn();

    // Obtener tareas con información del empleado y asignador (limitadas)
    $query = "SELECT t.*, 
              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido,
              asig.nombre as asignador_nombre, asig.apellido as asignador_apellido
              FROM tareas t 
              JOIN usuarios emp ON t.id_empleado = emp.id_usuario
              JOIN usuarios asig ON t.id_asignador = asig.id_usuario
              $where_clause 
              ORDER BY t.fecha_asignacion DESC
              LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $tareas = $stmt->fetchAll();
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Contar total de tareas para los filtros actuales
$count_query = "SELECT COUNT(*) FROM tareas t 
    JOIN usuarios emp ON t.id_empleado = emp.id_usuario
    JOIN usuarios asig ON t.id_asignador = asig.id_usuario
    $where_clause";
$stmt_count = $conn->prepare($count_query);
$stmt_count->execute($params);
$total_tareas = $stmt_count->fetchColumn();

// Obtener tareas con información del empleado y asignador (limitadas)
$query = "SELECT t.*, 
          emp.nombre as empleado_nombre, emp.apellido as empleado_apellido,
          asig.nombre as asignador_nombre, asig.apellido as asignador_apellido
          FROM tareas t 
          JOIN usuarios emp ON t.id_empleado = emp.id_usuario
          JOIN usuarios asig ON t.id_asignador = asig.id_usuario
          $where_clause 
          ORDER BY t.fecha_asignacion DESC
          LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$tareas = $stmt->fetchAll();

    // Obtener empleados para el filtro (solo si no es empleado)
    if (!$es_empleado) {
        $stmt_empleados = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
        $empleados = $stmt_empleados->fetchAll();
    } else {
        $empleados = [];
    }

    // Obtener estadísticas
    $stats_query = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
        COUNT(CASE WHEN estado = 'finalizada' THEN 1 END) as finalizadas,
        COUNT(CASE WHEN fecha_vencimiento < CURDATE() AND estado != 'finalizada' THEN 1 END) as vencidas
        FROM tareas t";
    
    if ($es_empleado) {
        $stats_query .= " WHERE t.id_empleado = ?";
        $stmt_stats = $conn->prepare($stats_query);
        $stmt_stats->execute([$_SESSION['user_id']]);
    } else {
        $stmt_stats = $conn->query($stats_query);
    }
    
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener tareas: " . $e->getMessage());
    $tareas = [];
    $empleados = [];
    $stats = ['total' => 0, 'pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'vencidas' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-calendar-check"></i> 
                <?php echo $es_empleado ? 'Mis Tareas' : 'Gestión de Tareas'; ?>
            </h1>
            <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Tarea
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card warning">
            <div class="card-body text-center">
                <h4 class="text-warning"><?php echo $stats['pendientes']; ?></h4>
                <small class="text-muted">Pendientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card info">
            <div class="card-body text-center">
                <h4 class="text-info"><?php echo $stats['en_proceso']; ?></h4>
                <small class="text-muted">En Proceso</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card success">
            <div class="card-body text-center">
                <h4 class="text-success"><?php echo $stats['finalizadas']; ?></h4>
                <small class="text-muted">Finalizadas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card danger">
            <div class="card-body text-center">
                <h4 class="text-danger"><?php echo $stats['vencidas']; ?></h4>
                <small class="text-muted">Vencidas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <?php 
                $porcentaje_completado = $stats['total'] > 0 ? round(($stats['finalizadas'] / $stats['total']) * 100) : 0;
                ?>
                <h4 class="text-secondary"><?php echo $porcentaje_completado; ?>%</h4>
                <small class="text-muted">Completado</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <?php if (!$es_empleado): ?>
        <div class="col-md-3">
            <label for="empleado" class="form-label">Empleado</label>
            <select class="form-select" id="empleado" name="empleado">
                <option value="">Todos los empleados</option>
                <?php foreach ($empleados as $emp): ?>
                <option value="<?php echo $emp['id_usuario']; ?>" <?php echo $filtro_empleado == $emp['id_usuario'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="en_proceso" <?php echo $filtro_estado === 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                <option value="finalizada" <?php echo $filtro_estado === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                <option value="cancelada" <?php echo $filtro_estado === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="prioridad" class="form-label">Prioridad</label>
            <select class="form-select" id="prioridad" name="prioridad">
                <option value="">Todas</option>
                <option value="urgente" <?php echo $filtro_prioridad === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                <option value="alta" <?php echo $filtro_prioridad === 'alta' ? 'selected' : ''; ?>>Alta</option>
                <option value="media" <?php echo $filtro_prioridad === 'media' ? 'selected' : ''; ?>>Media</option>
                <option value="baja" <?php echo $filtro_prioridad === 'baja' ? 'selected' : ''; ?>>Baja</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="vencimiento" class="form-label">Vencimiento</label>
            <select class="form-select" id="vencimiento" name="vencimiento">
                <option value="">Todas</option>
                <option value="vencidas" <?php echo $filtro_vencimiento === 'vencidas' ? 'selected' : ''; ?>>Vencidas</option>
                <option value="hoy" <?php echo $filtro_vencimiento === 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                <option value="semana" <?php echo $filtro_vencimiento === 'semana' ? 'selected' : ''; ?>>Esta Semana</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="busqueda" class="form-label">Búsqueda</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar..." 
                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
        </div>
        
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary w-100">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </form>
</div>


<!-- Lista de tareas en tabla -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($tareas)): ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Nombre de tarea</th>
                        <th>Asignado a</th>
                        <th>Prioridad</th>
                        <th>Fecha de asignación</th>
                        <th>Asignado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tareas as $tarea): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                        <td><?php echo htmlspecialchars($tarea['empleado_nombre'] . ' ' . $tarea['empleado_apellido']); ?></td>
                        <td>
                            <?php
                            $prioridad_text = '';
                            $prioridad_class = '';
                            switch ($tarea['prioridad']) {
                                case 'urgente':
                                    $prioridad_text = 'Urgente';
                                    $prioridad_class = 'text-danger';
                                    break;
                                case 'alta':
                                    $prioridad_text = 'Alta';
                                    $prioridad_class = 'text-warning';
                                    break;
                                case 'media':
                                    $prioridad_text = 'Media';
                                    $prioridad_class = 'text-info';
                                    break;
                                case 'baja':
                                    $prioridad_text = 'Baja';
                                    $prioridad_class = 'text-secondary';
                                    break;
                            }
                            ?>
                            <span class="fw-bold <?php echo $prioridad_class; ?>"><?php echo $prioridad_text; ?></span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])); ?></td>
                        <td><?php echo htmlspecialchars($tarea['asignador_nombre'] . ' ' . $tarea['asignador_apellido']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-outline-info" title="Ver"><i class="bi bi-eye"></i></a>
                                <?php if ($es_empleado && $tarea['estado'] != 'finalizada'): ?>
                                <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-outline-primary" title="Actualizar Estado"><i class="bi bi-arrow-clockwise"></i></a>
                                <?php endif; ?>
                                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) || ($_SESSION['user_id'] == $tarea['id_asignador'])): ?>
                                <a href="edit.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Paginación -->
        <nav aria-label="Paginación de tareas">
            <ul class="pagination justify-content-center">
                <?php $total_pages = ceil($total_tareas / $limit); ?>
                <li class="page-item<?php if ($page <= 1) echo ' disabled'; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page-1)])); ?>">Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item<?php if ($i == $page) echo ' active'; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item<?php if ($page >= $total_pages) echo ' disabled'; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page+1)])); ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-check text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron tareas</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || !empty($filtro_estado) || !empty($filtro_empleado)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    <?php if ($es_empleado): ?>
                        No tienes tareas asignadas en este momento.
                    <?php else: ?>
                        Comience asignando tareas a los empleados.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Primera Tarea
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

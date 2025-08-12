<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden gestionar transportes
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Gestión de Transportes';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_encargado = $_GET['encargado'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_estado)) {
    $where_conditions[] = "t.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_encargado)) {
    $where_conditions[] = "t.id_encargado = ?";
    $params[] = $filtro_encargado;
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(t.marca LIKE ? OR t.modelo LIKE ? OR t.matricula LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Obtener transportes con información del encargado
    $query = "SELECT t.*, u.nombre, u.apellido 
              FROM transportes t 
              LEFT JOIN usuarios u ON t.id_encargado = u.id_usuario 
              $where_clause 
              ORDER BY t.estado, t.marca, t.modelo";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transportes = $stmt->fetchAll();

    // Obtener encargados para el filtro
    $stmt_encargados = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $encargados = $stmt_encargados->fetchAll();

    // Obtener estadísticas
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_transportes,
        COUNT(CASE WHEN estado = 'disponible' THEN 1 END) as disponibles,
        COUNT(CASE WHEN estado = 'en_uso' THEN 1 END) as en_uso,
        COUNT(CASE WHEN estado = 'mantenimiento' THEN 1 END) as mantenimiento,
        COUNT(CASE WHEN estado = 'fuera_servicio' THEN 1 END) as fuera_servicio
        FROM transportes");
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener transportes: " . $e->getMessage());
    $transportes = [];
    $encargados = [];
    $stats = ['total_transportes' => 0, 'disponibles' => 0, 'en_uso' => 0, 'mantenimiento' => 0, 'fuera_servicio' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-truck"></i> Gestión de Transportes
            </h1>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo Transporte
            </a>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo $stats['total_transportes']; ?></h4>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card success">
            <div class="card-body text-center">
                <h4 class="text-success"><?php echo $stats['disponibles']; ?></h4>
                <small class="text-muted">Disponibles</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card warning">
            <div class="card-body text-center">
                <h4 class="text-warning"><?php echo $stats['en_uso']; ?></h4>
                <small class="text-muted">En Uso</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card info">
            <div class="card-body text-center">
                <h4 class="text-info"><?php echo $stats['mantenimiento']; ?></h4>
                <small class="text-muted">Mantenimiento</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card danger">
            <div class="card-body text-center">
                <h4 class="text-danger"><?php echo $stats['fuera_servicio']; ?></h4>
                <small class="text-muted">Fuera Servicio</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <?php 
                $porcentaje_disponible = $stats['total_transportes'] > 0 ? round(($stats['disponibles'] / $stats['total_transportes']) * 100) : 0;
                ?>
                <h4 class="text-secondary"><?php echo $porcentaje_disponible; ?>%</h4>
                <small class="text-muted">Disponibilidad</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos los estados</option>
                <option value="disponible" <?php echo $filtro_estado === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                <option value="en_uso" <?php echo $filtro_estado === 'en_uso' ? 'selected' : ''; ?>>En Uso</option>
                <option value="mantenimiento" <?php echo $filtro_estado === 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                <option value="fuera_servicio" <?php echo $filtro_estado === 'fuera_servicio' ? 'selected' : ''; ?>>Fuera de Servicio</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label for="encargado" class="form-label">Encargado</label>
            <select class="form-select" id="encargado" name="encargado">
                <option value="">Todos los encargados</option>
                <?php foreach ($encargados as $enc): ?>
                <option value="<?php echo $enc['id_usuario']; ?>" <?php echo $filtro_encargado == $enc['id_usuario'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($enc['nombre'] . ' ' . $enc['apellido']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="busqueda" class="form-label">Búsqueda</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por marca, modelo o matrícula..." 
                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary me-2">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
    </form>
</div>

<!-- Lista de transportes -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($transportes)): ?>
        <div class="row">
            <?php foreach ($transportes as $transporte): ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-truck"></i>
                            <strong><?php echo htmlspecialchars($transporte['marca'] . ' ' . $transporte['modelo']); ?></strong>
                        </div>
                        <?php
                        $estado_class = '';
                        $estado_icon = '';
                        switch ($transporte['estado']) {
                            case 'disponible':
                                $estado_class = 'bg-success';
                                $estado_icon = 'bi-check-circle';
                                break;
                            case 'en_uso':
                                $estado_class = 'bg-warning text-dark';
                                $estado_icon = 'bi-gear';
                                break;
                            case 'mantenimiento':
                                $estado_class = 'bg-info';
                                $estado_icon = 'bi-tools';
                                break;
                            case 'fuera_servicio':
                                $estado_class = 'bg-danger';
                                $estado_icon = 'bi-x-circle';
                                break;
                        }
                        ?>
                        <span class="badge <?php echo $estado_class; ?>">
                            <i class="bi <?php echo $estado_icon; ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $transporte['estado'])); ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-credit-card"></i> 
                            <?php echo htmlspecialchars($transporte['matricula']); ?>
                        </h6>
                        
                        <?php if ($transporte['nombre']): ?>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="bi bi-person"></i> 
                                Encargado: <?php echo htmlspecialchars($transporte['nombre'] . ' ' . $transporte['apellido']); ?>
                            </small>
                        </p>
                        <?php else: ?>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="bi bi-person-x"></i> Sin encargado asignado
                            </small>
                        </p>
                        <?php endif; ?>
                        
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i> 
                                Registrado: <?php echo date('d/m/Y', strtotime($transporte['fecha_creacion'])); ?>
                            </small>
                        </p>
                    </div>
                    
                    <div class="card-footer">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <a href="view.php?id=<?php echo $transporte['id_transporte']; ?>" 
                               class="btn btn-outline-info">
                                <i class="bi bi-eye"></i> Ver
                            </a>
                            <a href="edit.php?id=<?php echo $transporte['id_transporte']; ?>" 
                               class="btn btn-outline-primary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <a href="delete.php?id=<?php echo $transporte['id_transporte']; ?>" 
                               class="btn btn-outline-danger btn-delete" 
                               data-item-name="el transporte '<?php echo htmlspecialchars($transporte['marca'] . ' ' . $transporte['modelo'] . ' - ' . $transporte['matricula']); ?>'"
                               title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-truck text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron transportes</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || !empty($filtro_estado) || !empty($filtro_encargado)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience agregando vehículos a la flota.
                <?php endif; ?>
            </p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Agregar Primer Transporte
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

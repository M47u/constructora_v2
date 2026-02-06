<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Permisos: Admin y Responsable pueden gestionar, Empleado solo puede ver
$can_manage = has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]);

$page_title = 'Gestión de Herramientas';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_stock_bajo = isset($_GET['stock_bajo']) ? (bool)$_GET['stock_bajo'] : false;

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(h.marca LIKE ? OR h.modelo LIKE ? OR h.descripcion LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

if ($filtro_stock_bajo) {
    $where_conditions[] = "h.stock_total <= 0"; // Consideramos stock bajo si es 0 o menos
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Obtener herramientas con el conteo de unidades disponibles
    $query = "SELECT h.*, 
                     COUNT(CASE WHEN hu.estado_actual = 'disponible' THEN 1 ELSE NULL END) as unidades_disponibles
              FROM herramientas h 
              LEFT JOIN herramientas_unidades hu ON h.id_herramienta = hu.id_herramienta
              $where_clause 
              GROUP BY h.id_herramienta
              ORDER BY h.marca, h.modelo";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $herramientas = $stmt->fetchAll();

    // Obtener estadísticas
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_tipos_herramientas,
        SUM(stock_total) as total_unidades,
        COUNT(CASE WHEN stock_total <= 0 THEN 1 END) as sin_stock_tipos
        FROM herramientas");
    $stats = $stmt_stats->fetch();

    $stmt_unidades_disponibles = $conn->query("SELECT COUNT(*) as total FROM herramientas_unidades WHERE estado_actual = 'disponible'");
    $unidades_disponibles = $stmt_unidades_disponibles->fetch()['total'];

} catch (Exception $e) {
    error_log("Error al obtener herramientas: " . $e->getMessage());
    $herramientas = [];
    $stats = ['total_tipos_herramientas' => 0, 'total_unidades' => 0, 'sin_stock_tipos' => 0];
    $unidades_disponibles = 0;
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-tools"></i> Gestión de Herramientas
            </h1>
            <?php if ($can_manage): ?>
            <div>
                <a href="create.php" class="btn btn-primary me-2">
                    <i class="bi bi-plus-circle"></i> Nuevo Tipo
                </a>
                <a href="exportar_herramientas.php" class="btn btn-success me-2">
                    <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                </a>
                <a href="prestamos.php" class="btn btn-info">
                    <i class="bi bi-box-arrow-up"></i> Préstamos
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo $stats['total_tipos_herramientas']; ?></h4>
                <small class="text-muted">Tipos de Herramientas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card success">
            <div class="card-body text-center">
                <h4 class="text-success"><?php echo $unidades_disponibles; ?></h4>
                <small class="text-muted">Unidades Disponibles</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card warning">
            <div class="card-body text-center">
                <h4 class="text-warning"><?php echo $stats['total_unidades'] - $unidades_disponibles; ?></h4>
                <small class="text-muted">Unidades en Uso/Mantenimiento</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card danger">
            <div class="card-body text-center">
                <h4 class="text-danger"><?php echo $stats['sin_stock_tipos']; ?></h4>
                <small class="text-muted">Tipos Sin Stock</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-5">
            <label for="busqueda" class="form-label">Búsqueda</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por marca, modelo o descripción..." 
                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
        </div>
        
        <div class="col-md-3">
            <label class="form-label">Filtros</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="stock_bajo" name="stock_bajo" value="1"
                       <?php echo $filtro_stock_bajo ? 'checked' : ''; ?>>
                <label class="form-check-label" for="stock_bajo">
                    Solo sin stock disponible
                </label>
            </div>
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

<!-- Tabla de herramientas -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($herramientas)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Herramienta</th>
                        <th>Descripción</th>
                        <th>Stock Total</th>
                        <th>Disponibles</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($herramientas as $herramienta): ?>
                    <tr class="<?php echo $herramienta['unidades_disponibles'] <= 0 ? 'table-danger' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($herramienta['marca'] . ' ' . $herramienta['modelo']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars(substr($herramienta['descripcion'], 0, 70)); ?>...</td>
                        <td>
                            <span class="badge bg-primary fs-6">
                                <?php echo number_format($herramienta['stock_total']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $herramienta['unidades_disponibles'] <= 0 ? 'bg-danger' : 'bg-success'; ?> fs-6">
                                <?php echo number_format($herramienta['unidades_disponibles']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($can_manage): ?>
                                <a href="add_unit.php?id=<?php echo $herramienta['id_herramienta']; ?>" 
                                   class="btn btn-outline-success" title="Agregar unidad">
                                    <i class="bi bi-plus-square"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $herramienta['id_herramienta']; ?>" 
                                   class="btn btn-outline-primary" title="Editar tipo">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $herramienta['id_herramienta']; ?>" 
                                   class="btn btn-outline-danger btn-delete" 
                                   data-item-name="la herramienta '<?php echo htmlspecialchars($herramienta['marca'] . ' ' . $herramienta['modelo']); ?>'"
                                   title="Eliminar tipo">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-tools text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron herramientas</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || $filtro_stock_bajo): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    <?php if ($can_manage): ?>
                        Comience agregando tipos de herramientas al inventario.
                    <?php else: ?>
                        No hay herramientas registradas en el sistema.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <?php if ($can_manage): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Agregar Primer Tipo de Herramienta
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

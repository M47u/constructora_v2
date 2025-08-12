<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden gestionar materiales
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Gestión de Materiales';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_stock_bajo = isset($_GET['stock_bajo']) ? (bool)$_GET['stock_bajo'] : false;
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if ($filtro_stock_bajo) {
    $where_conditions[] = "stock_actual <= stock_minimo";
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "nombre_material LIKE ?";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Obtener materiales
    $query = "SELECT * FROM materiales $where_clause ORDER BY nombre_material";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $materiales = $stmt->fetchAll();

    // Obtener estadísticas
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_materiales,
        COUNT(CASE WHEN stock_actual <= stock_minimo THEN 1 END) as stock_bajo,
        SUM(stock_actual * precio_referencia) as valor_total_stock
        FROM materiales");
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener materiales: " . $e->getMessage());
    $materiales = [];
    $stats = ['total_materiales' => 0, 'stock_bajo' => 0, 'valor_total_stock' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-box-seam"></i> Gestión de Materiales
            </h1>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo Material
            </a>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Total Materiales</h6>
                        <h3 class="mb-0"><?php echo $stats['total_materiales']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-box dashboard-icon text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Stock Bajo</h6>
                        <h3 class="mb-0 text-warning"><?php echo $stats['stock_bajo']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle dashboard-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Valor Total Stock</h6>
                        <h3 class="mb-0 text-success">$<?php echo number_format($stats['valor_total_stock'], 2); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar dashboard-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <label for="busqueda" class="form-label">Buscar Material</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por nombre..." 
                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
        </div>
        
        <div class="col-md-3">
            <label class="form-label">Filtros</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="stock_bajo" name="stock_bajo" value="1"
                       <?php echo $filtro_stock_bajo ? 'checked' : ''; ?>>
                <label class="form-check-label" for="stock_bajo">
                    Solo stock bajo
                </label>
            </div>
        </div>
        
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary me-2">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <a href="stock_bajo.php" class="btn btn-warning w-100">
                <i class="bi bi-exclamation-triangle"></i> Stock Bajo
            </a>
        </div>
    </form>
</div>

<!-- Tabla de materiales -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($materiales)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Precio Referencia</th>
                        <th>Unidad</th>
                        <th>Valor Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiales as $material): ?>
                    <tr class="<?php echo $material['stock_actual'] <= $material['stock_minimo'] ? 'table-warning' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($material['nombre_material']); ?></strong>
                        </td>
                        <td>
                            <span class="badge <?php echo $material['stock_actual'] <= $material['stock_minimo'] ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                <?php echo number_format($material['stock_actual']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($material['stock_minimo']); ?></td>
                        <td>$<?php echo number_format($material['precio_referencia'], 2); ?></td>
                        <td><?php echo htmlspecialchars($material['unidad_medida']); ?></td>
                        <td>
                            <strong>$<?php echo number_format($material['stock_actual'] * $material['precio_referencia'], 2); ?></strong>
                        </td>
                        <td>
                            <?php if ($material['stock_actual'] <= $material['stock_minimo']): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle"></i> Stock Bajo
                                </span>
                            <?php elseif ($material['stock_actual'] == 0): ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-x-circle"></i> Sin Stock
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Disponible
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="adjust_stock.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-warning" title="Ajustar stock">
                                    <i class="bi bi-arrow-up-down"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-danger btn-delete" 
                                   data-item-name="el material '<?php echo htmlspecialchars($material['nombre_material']); ?>'"
                                   title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-box-seam text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron materiales</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || $filtro_stock_bajo): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience agregando materiales al inventario.
                <?php endif; ?>
            </p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Agregar Primer Material
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

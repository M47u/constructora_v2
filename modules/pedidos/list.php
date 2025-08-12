<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Gestión de Pedidos';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_obra = $_GET['obra'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$where_conditions = ['1=1'];
$params = [];

if (!empty($filtro_obra)) {
    $where_conditions[] = "p.id_obra = ?";
    $params[] = $filtro_obra;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "p.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "DATE(p.fecha_pedido) >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "DATE(p.fecha_pedido) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Obtener pedidos con información de obra y solicitante
    $query = "SELECT p.*, o.nombre_obra, u.nombre, u.apellido,
                     COUNT(dp.id_detalle) as total_items,
                     SUM(dp.cantidad * m.precio_referencia) as valor_total
              FROM pedidos_materiales p
              LEFT JOIN obras o ON p.id_obra = o.id_obra
              LEFT JOIN usuarios u ON p.id_solicitante = u.id_usuario
              LEFT JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
              LEFT JOIN materiales m ON dp.id_material = m.id_material
              $where_clause
              GROUP BY p.id_pedido
              ORDER BY p.fecha_pedido DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    // Obtener obras para el filtro
    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();

    // Obtener estadísticas
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_pedidos,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'aprobado' THEN 1 END) as aprobados,
        COUNT(CASE WHEN estado = 'entregado' THEN 1 END) as entregados
        FROM pedidos_materiales");
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener pedidos: " . $e->getMessage());
    $pedidos = [];
    $obras = [];
    $stats = ['total_pedidos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'entregados' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-clipboard-check"></i> Gestión de Pedidos
            </h1>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo Pedido
            </a>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Total Pedidos</h6>
                        <h3 class="mb-0"><?php echo $stats['total_pedidos']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clipboard dashboard-icon text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Pendientes</h6>
                        <h3 class="mb-0 text-warning"><?php echo $stats['pendientes']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock dashboard-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Aprobados</h6>
                        <h3 class="mb-0 text-info"><?php echo $stats['aprobados']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle dashboard-icon text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Entregados</h6>
                        <h3 class="mb-0 text-success"><?php echo $stats['entregados']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-truck dashboard-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="obra" class="form-label">Obra</label>
            <select class="form-select" id="obra" name="obra">
                <option value="">Todas las obras</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?php echo $obra['id_obra']; ?>" 
                            <?php echo $filtro_obra == $obra['id_obra'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="aprobado" <?php echo $filtro_estado == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                <option value="en_camino" <?php echo $filtro_estado == 'en_camino' ? 'selected' : ''; ?>>En Camino</option>
                <option value="entregado" <?php echo $filtro_estado == 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                <option value="cancelado" <?php echo $filtro_estado == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="fecha_desde" class="form-label">Desde</label>
            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                   value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
        </div>
        
        <div class="col-md-2">
            <label for="fecha_hasta" class="form-label">Hasta</label>
            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                   value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
        </div>
        
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary me-2">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        </div>
    </form>
</div>

<!-- Tabla de pedidos -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($pedidos)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Obra</th>
                        <th>Solicitante</th>
                        <th>Fecha</th>
                        <th>Items</th>
                        <th>Valor Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($pedido['nombre_obra']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $pedido['total_items']; ?> items</span>
                        </td>
                        <td>
                            <strong>$<?php echo number_format($pedido['valor_total'] ?? 0, 2); ?></strong>
                        </td>
                        <td>
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
                            <span class="badge <?php echo $estado_class[$pedido['estado']] ?? 'bg-secondary'; ?>">
                                <i class="bi bi-<?php echo $estado_icons[$pedido['estado']] ?? 'question'; ?>"></i>
                                <?php echo ucfirst($pedido['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($pedido['estado'] == 'pendiente'): ?>
                                <a href="edit.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) && $pedido['estado'] == 'pendiente'): ?>
                                <a href="process.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-success" title="Procesar">
                                    <i class="bi bi-gear"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($pedido['estado'] == 'pendiente'): ?>
                                <a href="delete.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-danger btn-delete" 
                                   data-item-name="el pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>"
                                   title="Eliminar">
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
            <i class="bi bi-clipboard-x text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron pedidos</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_obra) || !empty($filtro_estado)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience creando el primer pedido de materiales.
                <?php endif; ?>
            </p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Primer Pedido
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

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

$page_title = 'Gestión de Obras';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_responsable = $_GET['responsable'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_estado)) {
    $where_conditions[] = "o.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_responsable)) {
    $where_conditions[] = "o.id_responsable = ?";
    $params[] = $filtro_responsable;
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(o.nombre_obra LIKE ? OR o.cliente LIKE ? OR o.localidad LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Obtener obras con información del responsable
    $query = "SELECT o.*, u.nombre, u.apellido 
              FROM obras o 
              JOIN usuarios u ON o.id_responsable = u.id_usuario 
              $where_clause 
              ORDER BY o.fecha_creacion DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $obras = $stmt->fetchAll();

    // Obtener responsables para el filtro
    $stmt_responsables = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE rol IN ('administrador', 'responsable_obra') AND estado = 'activo' ORDER BY nombre, apellido");
    $responsables = $stmt_responsables->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener obras: " . $e->getMessage());
    $obras = [];
    $responsables = [];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-building-gear"></i> Gestión de Obras
            </h1>
            <?php if (has_permission(ROLE_ADMIN)): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Obra
            </a>
            <?php endif; ?>
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
                <option value="planificada" <?php echo $filtro_estado === 'planificada' ? 'selected' : ''; ?>>Planificada</option>
                <option value="en_progreso" <?php echo $filtro_estado === 'en_progreso' ? 'selected' : ''; ?>>En Progreso</option>
                <option value="finalizada" <?php echo $filtro_estado === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                <option value="cancelada" <?php echo $filtro_estado === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label for="responsable" class="form-label">Responsable</label>
            <select class="form-select" id="responsable" name="responsable">
                <option value="">Todos los responsables</option>
                <?php foreach ($responsables as $resp): ?>
                <option value="<?php echo $resp['id_usuario']; ?>" <?php echo $filtro_responsable == $resp['id_usuario'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($resp['nombre'] . ' ' . $resp['apellido']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="busqueda" class="form-label">Búsqueda</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por nombre, cliente o localidad..." 
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

<!-- Tabla de obras -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($obras)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Obra</th>
                        <th>Cliente</th>
                        <th>Ubicación</th>
                        <th>Responsable</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($obras as $obra): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($obra['nombre_obra']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($obra['cliente']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($obra['localidad'] . ', ' . $obra['provincia']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($obra['direccion']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($obra['nombre'] . ' ' . $obra['apellido']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($obra['fecha_inicio'])); ?></td>
                        <td>
                            <?php if ($obra['fecha_fin']): ?>
                                <?php echo date('d/m/Y', strtotime($obra['fecha_fin'])); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
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
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $obra['estado'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $obra['id_obra']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (has_permission(ROLE_ADMIN) || ($_SESSION['user_id'] == $obra['id_responsable'])): ?>
                                <a href="edit.php?id=<?php echo $obra['id_obra']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (has_permission(ROLE_ADMIN)): ?>
                                <a href="delete.php?id=<?php echo $obra['id_obra']; ?>" 
                                   class="btn btn-outline-danger btn-delete" 
                                   data-item-name="la obra '<?php echo htmlspecialchars($obra['nombre_obra']); ?>'"
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
            <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron obras</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_estado) || !empty($filtro_responsable) || !empty($filtro_busqueda)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience creando una nueva obra.
                <?php endif; ?>
            </p>
            <?php if (has_permission(ROLE_ADMIN)): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Primera Obra
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

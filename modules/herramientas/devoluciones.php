<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden gestionar devoluciones
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Gestión de Devoluciones de Herramientas';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_obra = $_GET['obra'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? ''; // Para buscar por QR o nombre de herramienta

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_empleado)) {
    $where_conditions[] = "p.id_empleado = ?";
    $params[] = $filtro_empleado;
}

if (!empty($filtro_obra)) {
    $where_conditions[] = "p.id_obra = ?";
    $params[] = $filtro_obra;
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(hu.qr_code LIKE ? OR h.marca LIKE ? OR h.modelo LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Obtener devoluciones con detalles de préstamo, empleado, obra y herramientas
    $query = "SELECT d.*, 
                     p.id_empleado, emp.nombre as empleado_nombre, emp.apellido as empleado_apellido,
                     obra.nombre_obra,
                     GROUP_CONCAT(CONCAT(h.marca, ' ', h.modelo, ' (', hu.qr_code, ')') SEPARATOR '; ') as herramientas_devueltas
              FROM devoluciones d 
              JOIN prestamos p ON d.id_prestamo = p.id_prestamo
              JOIN usuarios emp ON p.id_empleado = emp.id_usuario
              JOIN obras obra ON p.id_obra = obra.id_obra
              LEFT JOIN detalle_devolucion dd ON d.id_devolucion = dd.id_devolucion
              LEFT JOIN herramientas_unidades hu ON dd.id_unidad = hu.id_unidad
              LEFT JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
              $where_clause 
              GROUP BY d.id_devolucion
              ORDER BY d.fecha_devolucion DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $devoluciones = $stmt->fetchAll();

    // Obtener empleados y obras para los filtros
    $stmt_empleados = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $empleados = $stmt_empleados->fetchAll();

    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras WHERE estado IN ('planificada', 'en_progreso') ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener devoluciones: " . $e->getMessage());
    $devoluciones = [];
    $empleados = [];
    $obras = [];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-box-arrow-down"></i> Gestión de Devoluciones de Herramientas
            </h1>
            <a href="create_devolucion.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Devolución
            </a>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
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
        
        <div class="col-md-4">
            <label for="obra" class="form-label">Obra</label>
            <select class="form-select" id="obra" name="obra">
                <option value="">Todas las obras</option>
                <?php foreach ($obras as $obr): ?>
                <option value="<?php echo $obr['id_obra']; ?>" <?php echo $filtro_obra == $obr['id_obra'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($obr['nombre_obra']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="busqueda" class="form-label">Búsqueda</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por QR, marca o modelo..." 
                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
        </div>
        
        <div class="col-12 d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="devoluciones.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpiar Filtros
            </a>
        </div>
    </form>
</div>

<!-- Tabla de devoluciones -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($devoluciones)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID Devolución</th>
                        <th>Préstamo #</th>
                        <th>Empleado</th>
                        <th>Obra</th>
                        <th>Fecha Devolución</th>
                        <th>Herramientas Devueltas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devoluciones as $devolucion): ?>
                    <tr>
                        <td>#<?php echo $devolucion['id_devolucion']; ?></td>
                        <td>
                            <a href="view_prestamo.php?id=<?php echo $devolucion['id_prestamo']; ?>">
                                #<?php echo $devolucion['id_prestamo']; ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($devolucion['empleado_nombre'] . ' ' . $devolucion['empleado_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($devolucion['nombre_obra']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($devolucion['fecha_devolucion'])); ?></td>
                        <td>
                            <?php 
                            $herramientas_list = explode('; ', $devolucion['herramientas_devueltas']);
                            foreach ($herramientas_list as $h_item) {
                                echo '<span class="badge bg-light text-dark me-1 mb-1">' . htmlspecialchars($h_item) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view_devolucion.php?id=<?php echo $devolucion['id_devolucion']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
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
            <i class="bi bi-box-arrow-down text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron devoluciones de herramientas</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || !empty($filtro_empleado) || !empty($filtro_obra)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Las devoluciones se registran una vez que las herramientas son retornadas de un préstamo.
                <?php endif; ?>
            </p>
            <a href="create_prestamo.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Registrar Nuevo Préstamo
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

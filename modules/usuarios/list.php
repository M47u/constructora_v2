<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden gestionar usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Gestión de Usuarios';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_rol = $_GET['rol'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_rol)) {
    $where_conditions[] = "rol = ?";
    $params[] = $filtro_rol;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Obtener usuarios
    $query = "SELECT * FROM usuarios $where_clause ORDER BY estado, rol, nombre, apellido";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();

    // Obtener estadísticas
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_usuarios,
        COUNT(CASE WHEN rol = 'administrador' THEN 1 END) as administradores,
        COUNT(CASE WHEN rol = 'responsable_obra' THEN 1 END) as responsables,
        COUNT(CASE WHEN rol = 'empleado' THEN 1 END) as empleados,
        COUNT(CASE WHEN estado = 'activo' THEN 1 END) as activos,
        COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as inactivos
        FROM usuarios");
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener usuarios: " . $e->getMessage());
    $usuarios = [];
    $stats = ['total_usuarios' => 0, 'administradores' => 0, 'responsables' => 0, 'empleados' => 0, 'activos' => 0, 'inactivos' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-people"></i> Gestión de Usuarios
            </h1>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Nuevo Usuario
            </a>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo $stats['total_usuarios']; ?></h4>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card danger">
            <div class="card-body text-center">
                <h4 class="text-danger"><?php echo $stats['administradores']; ?></h4>
                <small class="text-muted">Administradores</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card warning">
            <div class="card-body text-center">
                <h4 class="text-warning"><?php echo $stats['responsables']; ?></h4>
                <small class="text-muted">Responsables</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card info">
            <div class="card-body text-center">
                <h4 class="text-info"><?php echo $stats['empleados']; ?></h4>
                <small class="text-muted">Empleados</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card success">
            <div class="card-body text-center">
                <h4 class="text-success"><?php echo $stats['activos']; ?></h4>
                <small class="text-muted">Activos</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h4 class="text-secondary"><?php echo $stats['inactivos']; ?></h4>
                <small class="text-muted">Inactivos</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="rol" class="form-label">Rol</label>
            <select class="form-select" id="rol" name="rol">
                <option value="">Todos los roles</option>
                <option value="administrador" <?php echo $filtro_rol === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                <option value="responsable_obra" <?php echo $filtro_rol === 'responsable_obra' ? 'selected' : ''; ?>>Responsable de Obra</option>
                <option value="empleado" <?php echo $filtro_rol === 'empleado' ? 'selected' : ''; ?>>Empleado</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
            </select>
        </div>
        
        <div class="col-md-5">
            <label for="busqueda" class="form-label">Búsqueda</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por nombre, apellido o email..." 
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

<!-- Tabla de usuarios -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($usuarios)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr class="<?php echo $usuario['estado'] === 'inactivo' ? 'table-secondary' : ''; ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-2">
                                    <?php echo strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['apellido'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                    <?php if ($usuario['id_usuario'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info ms-1">Tú</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td>
                            <?php
                            $rol_class = '';
                            $rol_text = '';
                            switch ($usuario['rol']) {
                                case 'administrador':
                                    $rol_class = 'bg-danger';
                                    $rol_text = 'Administrador';
                                    break;
                                case 'responsable_obra':
                                    $rol_class = 'bg-warning text-dark';
                                    $rol_text = 'Responsable';
                                    break;
                                case 'empleado':
                                    $rol_class = 'bg-info';
                                    $rol_text = 'Empleado';
                                    break;
                            }
                            ?>
                            <span class="badge <?php echo $rol_class; ?>">
                                <?php echo $rol_text; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $usuario['estado'] === 'activo' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($usuario['estado']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $usuario['id_usuario']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $usuario['id_usuario']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                                <a href="toggle_status.php?id=<?php echo $usuario['id_usuario']; ?>" 
                                   class="btn btn-outline-<?php echo $usuario['estado'] === 'activo' ? 'warning' : 'success'; ?>" 
                                   title="<?php echo $usuario['estado'] === 'activo' ? 'Desactivar' : 'Activar'; ?>">
                                    <i class="bi bi-<?php echo $usuario['estado'] === 'activo' ? 'pause' : 'play'; ?>"></i>
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
            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron usuarios</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || !empty($filtro_rol) || !empty($filtro_estado)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience agregando usuarios al sistema.
                <?php endif; ?>
            </p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Agregar Primer Usuario
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.8rem;
}
</style>

<?php include '../../includes/footer.php'; ?>

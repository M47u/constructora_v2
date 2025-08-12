<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden ver usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Detalles del Usuario';

$database = new Database();
$conn = $database->getConnection();

$id_usuario = $_GET['id'] ?? 0;

if (!$id_usuario) {
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

try {
    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        redirect(SITE_URL . '/modules/usuarios/list.php');
    }

    // Obtener estadísticas del usuario
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(DISTINCT t.id_tarea) as tareas_asignadas,
            COUNT(DISTINCT CASE WHEN t.estado = 'completada' THEN t.id_tarea END) as tareas_completadas,
            COUNT(DISTINCT p.id_prestamo) as prestamos_realizados,
            COUNT(DISTINCT CASE WHEN p.estado = 'activo' THEN p.id_prestamo END) as prestamos_activos
        FROM usuarios u
        LEFT JOIN tareas t ON u.id_usuario = t.id_responsable
        LEFT JOIN prestamos_herramientas p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?
    ");
    $stmt_stats->execute([$id_usuario]);
    $stats = $stmt_stats->fetch();

    // Obtener últimas actividades
    $stmt_actividades = $conn->prepare("
        (SELECT 'tarea' as tipo, t.nombre as descripcion, t.fecha_creacion as fecha
         FROM tareas t WHERE t.id_responsable = ? ORDER BY t.fecha_creacion DESC LIMIT 5)
        UNION ALL
        (SELECT 'prestamo' as tipo, CONCAT('Préstamo de ', h.nombre) as descripcion, p.fecha_prestamo as fecha
         FROM prestamos_herramientas p 
         JOIN unidades_herramientas uh ON p.id_unidad = uh.id_unidad
         JOIN herramientas h ON uh.id_herramienta = h.id_herramienta
         WHERE p.id_usuario = ? ORDER BY p.fecha_prestamo DESC LIMIT 5)
        ORDER BY fecha DESC LIMIT 10
    ");
    $stmt_actividades->execute([$id_usuario, $id_usuario]);
    $actividades = $stmt_actividades->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener usuario: " . $e->getMessage());
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-person"></i> Detalles del Usuario
            </h1>
            <div class="btn-group">
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <a href="edit.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información del Usuario -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="avatar-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 1.5rem;">
                    <?php echo strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['apellido'], 0, 1)); ?>
                </div>
                
                <h4><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h4>
                
                <div class="mb-3">
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
                            $rol_text = 'Responsable de Obra';
                            break;
                        case 'empleado':
                            $rol_class = 'bg-info';
                            $rol_text = 'Empleado';
                            break;
                    }
                    ?>
                    <span class="badge <?php echo $rol_class; ?> fs-6">
                        <?php echo $rol_text; ?>
                    </span>
                </div>

                <div class="mb-3">
                    <span class="badge <?php echo $usuario['estado'] === 'activo' ? 'bg-success' : 'bg-secondary'; ?> fs-6">
                        <?php echo ucfirst($usuario['estado']); ?>
                    </span>
                </div>

                <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                <div class="d-grid gap-2">
                    <a href="toggle_status.php?id=<?php echo $usuario['id_usuario']; ?>" 
                       class="btn btn-outline-<?php echo $usuario['estado'] === 'activo' ? 'warning' : 'success'; ?>">
                        <i class="bi bi-<?php echo $usuario['estado'] === 'activo' ? 'pause' : 'play'; ?>"></i>
                        <?php echo $usuario['estado'] === 'activo' ? 'Desactivar' : 'Activar'; ?>
                    </a>
                    <a href="change_password.php?id=<?php echo $usuario['id_usuario']; ?>" 
                       class="btn btn-outline-primary">
                        <i class="bi bi-key"></i> Cambiar Contraseña
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Información Detallada -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información Personal</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Email:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Teléfono:</strong><br>
                        <span class="text-muted">
                            <?php echo $usuario['telefono'] ? htmlspecialchars($usuario['telefono']) : 'No especificado'; ?>
                        </span>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-12">
                        <strong>Dirección:</strong><br>
                        <span class="text-muted">
                            <?php echo $usuario['direccion'] ? htmlspecialchars($usuario['direccion']) : 'No especificada'; ?>
                        </span>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <strong>Fecha de Registro:</strong><br>
                        <span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Último Acceso:</strong><br>
                        <span class="text-muted">
                            <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Estadísticas de Actividad</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h4 class="text-primary"><?php echo $stats['tareas_asignadas']; ?></h4>
                            <small class="text-muted">Tareas Asignadas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h4 class="text-success"><?php echo $stats['tareas_completadas']; ?></h4>
                            <small class="text-muted">Tareas Completadas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h4 class="text-info"><?php echo $stats['prestamos_realizados']; ?></h4>
                            <small class="text-muted">Préstamos Realizados</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h4 class="text-warning"><?php echo $stats['prestamos_activos']; ?></h4>
                            <small class="text-muted">Préstamos Activos</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actividad Reciente -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Actividad Reciente</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($actividades)): ?>
                <div class="timeline">
                    <?php foreach ($actividades as $actividad): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker">
                            <i class="bi bi-<?php echo $actividad['tipo'] === 'tarea' ? 'check-circle' : 'tools'; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <p class="mb-1"><?php echo htmlspecialchars($actividad['descripcion']); ?></p>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-clock-history text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No hay actividad reciente</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #fff;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #6c757d;
}

.timeline-content {
    background-color: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}
</style>

<?php include '../../includes/footer.php'; ?>

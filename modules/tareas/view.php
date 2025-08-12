<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Detalles de Tarea';

$database = new Database();
$conn = $database->getConnection();

$tarea_id = (int)($_GET['id'] ?? 0);

if ($tarea_id <= 0) {
    redirect(SITE_URL . '/modules/tareas/list.php');
}

try {
    // Obtener datos de la tarea
    $query = "SELECT t.*, 
              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido, emp.email as empleado_email,
              asig.nombre as asignador_nombre, asig.apellido as asignador_apellido
              FROM tareas t 
              JOIN usuarios emp ON t.id_empleado = emp.id_usuario
              JOIN usuarios asig ON t.id_asignador = asig.id_usuario
              WHERE t.id_tarea = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$tarea_id]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        redirect(SITE_URL . '/modules/tareas/list.php');
    }

    // Verificar permisos - empleados solo pueden ver sus propias tareas
    $es_empleado = get_user_role() === ROLE_EMPLEADO;
    if ($es_empleado && $tarea['id_empleado'] != $_SESSION['user_id']) {
        redirect(SITE_URL . '/modules/tareas/list.php');
    }

} catch (Exception $e) {
    error_log("Error al obtener tarea: " . $e->getMessage());
    redirect(SITE_URL . '/modules/tareas/list.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-clipboard-check"></i> Tarea #<?php echo $tarea['id_tarea']; ?>
            </h1>
            <div>
                <?php if ($es_empleado && $tarea['estado'] != 'finalizada'): ?>
                <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar Estado
                </a>
                <?php endif; ?>
                
                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) || ($_SESSION['user_id'] == $tarea['id_asignador'])): ?>
                <a href="edit.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <?php endif; ?>
                
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información principal -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle"></i> Información de la Tarea</span>
                
                <?php
                $estado_class = '';
                switch ($tarea['estado']) {
                    case 'pendiente':
                        $estado_class = 'bg-warning text-dark';
                        break;
                    case 'en_proceso':
                        $estado_class = 'bg-info';
                        break;
                    case 'finalizada':
                        $estado_class = 'bg-success';
                        break;
                    case 'cancelada':
                        $estado_class = 'bg-danger';
                        break;
                }
                ?>
                <span class="badge <?php echo $estado_class; ?> fs-6">
                    <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?>
                </span>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?php echo htmlspecialchars($tarea['titulo']); ?></h4>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Prioridad</h6>
                        <p class="mb-3">
                            <?php
                            $prioridad_class = '';
                            $prioridad_icon = '';
                            switch ($tarea['prioridad']) {
                                case 'urgente':
                                    $prioridad_class = 'text-danger';
                                    $prioridad_icon = 'bi-exclamation-triangle-fill';
                                    $prioridad_text = '🔴 Urgente';
                                    break;
                                case 'alta':
                                    $prioridad_class = 'text-warning';
                                    $prioridad_icon = 'bi-arrow-up-circle-fill';
                                    $prioridad_text = '🟠 Alta';
                                    break;
                                case 'media':
                                    $prioridad_class = 'text-info';
                                    $prioridad_icon = 'bi-dash-circle-fill';
                                    $prioridad_text = '🟡 Media';
                                    break;
                                case 'baja':
                                    $prioridad_class = 'text-secondary';
                                    $prioridad_icon = 'bi-arrow-down-circle-fill';
                                    $prioridad_text = '🔵 Baja';
                                    break;
                            }
                            ?>
                            <span class="<?php echo $prioridad_class; ?>">
                                <i class="bi <?php echo $prioridad_icon; ?>"></i>
                                <?php echo $prioridad_text; ?>
                            </span>
                        </p>
                        
                        <h6 class="text-muted">Asignado a</h6>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($tarea['empleado_nombre'] . ' ' . $tarea['empleado_apellido']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($tarea['empleado_email']); ?></small>
                        </p>
                        
                        <h6 class="text-muted">Asignado por</h6>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($tarea['asignador_nombre'] . ' ' . $tarea['asignador_apellido']); ?>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Fecha de Asignación</h6>
                        <p class="mb-3"><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])); ?></p>
                        
                        <h6 class="text-muted">Fecha de Vencimiento</h6>
                        <p class="mb-3">
                            <?php if ($tarea['fecha_vencimiento']): ?>
                                <?php 
                                $vencida = $tarea['fecha_vencimiento'] < date('Y-m-d') && $tarea['estado'] != 'finalizada';
                                $class = $vencida ? 'text-danger' : '';
                                ?>
                                <span class="<?php echo $class; ?>">
                                    <?php echo date('d/m/Y', strtotime($tarea['fecha_vencimiento'])); ?>
                                    <?php if ($vencida): ?>
                                        <i class="bi bi-exclamation-triangle"></i> Vencida
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Sin fecha límite</span>
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($tarea['fecha_finalizacion']): ?>
                        <h6 class="text-muted">Fecha de Finalización</h6>
                        <p class="mb-3"><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_finalizacion'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h6 class="text-muted">Descripción</h6>
                <div class="bg-light p-3 rounded mb-3">
                    <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                </div>
                
                <?php if (!empty($tarea['observaciones'])): ?>
                <h6 class="text-muted">Observaciones</h6>
                <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($tarea['observaciones'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel lateral con estadísticas -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> Información Adicional
            </div>
            <div class="card-body">
                <?php
                // Calcular días transcurridos
                $dias_asignacion = floor((time() - strtotime($tarea['fecha_asignacion'])) / (60 * 60 * 24));
                
                // Calcular progreso si hay fecha de vencimiento
                $progreso = null;
                if ($tarea['fecha_vencimiento']) {
                    $dias_totales = floor((strtotime($tarea['fecha_vencimiento']) - strtotime($tarea['fecha_asignacion'])) / (60 * 60 * 24));
                    if ($dias_totales > 0) {
                        $progreso = min(100, max(0, ($dias_asignacion / $dias_totales) * 100));
                    }
                }
                ?>
                
                <h6 class="text-muted">Tiempo Transcurrido</h6>
                <p class="mb-3">
                    <strong><?php echo max(0, $dias_asignacion); ?></strong> días desde la asignación
                </p>
                
                <?php if ($progreso !== null): ?>
                <h6 class="text-muted">Progreso de Tiempo</h6>
                <div class="progress mb-3">
                    <div class="progress-bar <?php echo $progreso > 100 ? 'bg-danger' : ($progreso > 80 ? 'bg-warning' : 'bg-info'); ?>" 
                         role="progressbar" style="width: <?php echo min(100, $progreso); ?>%" 
                         aria-valuenow="<?php echo $progreso; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($progreso, 1); ?>%
                    </div>
                </div>
                <?php if ($progreso > 100): ?>
                <small class="text-danger">
                    <i class="bi bi-exclamation-triangle"></i> Tarea vencida
                </small>
                <?php endif; ?>
                <?php endif; ?>
                
                <hr>
                
                <h6 class="text-muted">Acciones Rápidas</h6>
                <div class="d-grid gap-2">
                    <?php if ($es_empleado && $tarea['estado'] != 'finalizada'): ?>
                    <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Cambiar Estado
                    </a>
                    <?php endif; ?>
                    
                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                    <a href="create.php?empleado_id=<?php echo $tarea['id_empleado']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus"></i> Nueva Tarea para este Empleado
                    </a>
                    <?php endif; ?>
                    
                    <a href="list.php?empleado=<?php echo $tarea['id_empleado']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-list"></i> Ver Todas las Tareas del Empleado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

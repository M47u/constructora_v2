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

    // Determinar si la tarea está habilitada (columna puede no existir en versiones antiguas)
    $tarea_habilitada = ((int)($tarea['habilitada'] ?? 1)) === 1;

    // Si es una tarea de pedido, cargar info del pedido
    $pedido_info = null;
    if (($tarea['tipo'] ?? '') === 'pedido' && !empty($tarea['id_pedido'])) {
        $stmt_p = $conn->prepare("
            SELECT p.id_pedido, p.estado, p.prioridad,
                   p.fecha_pedido, p.fecha_necesaria,
                   o.nombre_obra
            FROM pedidos_materiales p
            JOIN obras o ON o.id_obra = p.id_obra
            WHERE p.id_pedido = ?
        ");
        $stmt_p->execute([$tarea['id_pedido']]);
        $pedido_info = $stmt_p->fetch();
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
                <?php if ($es_empleado && $tarea['estado'] != 'finalizada' && $tarea_habilitada): ?>
                <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar Estado
                </a>
                <?php elseif (!$tarea_habilitada && ($tarea['tipo'] ?? '') === 'pedido'): ?>
                <span class="btn btn-secondary disabled" title="Pendiente de habilitación por etapa anterior">
                    <i class="bi bi-lock"></i> Bloqueada
                </span>
                <?php endif; ?>
                
                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) || ($_SESSION['user_id'] == $tarea['id_asignador'])): ?>
                <a href="edit.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <?php endif; ?>
                
                <a href="print.php?id=<?php echo $tarea['id_tarea']; ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-printer"></i> Imprimir
                </a>
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
                        <p class="mb-3">
                            <?php if (!empty($tarea['fecha_asignacion'])): ?>
                                <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])); ?>
                            <?php else: ?>
                                <em class="text-muted">Pendiente de habilitación</em>
                            <?php endif; ?>
                        </p>
                        
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

        <?php if (!$tarea_habilitada && ($tarea['tipo'] ?? '') === 'pedido'): ?>
        <div class="card mb-4 border-warning">
            <div class="card-body d-flex align-items-center gap-3 text-warning-emphasis">
                <i class="bi bi-lock-fill fs-4 text-warning"></i>
                <div>
                    <strong>Tarea pendiente de habilitación</strong><br>
                    <small class="text-muted">Esta etapa se activará automáticamente cuando se complete la etapa anterior del pedido.</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($pedido_info): ?>
        <?php
            $etapa_labels = [
                'creacion'   => ['label' => 'Creación',        'color' => 'secondary'],
                'aprobacion' => ['label' => 'Aprobación',      'color' => 'info'],
                'picking'    => ['label' => 'Picking',         'color' => 'warning'],
                'retiro'     => ['label' => 'Retiro',          'color' => 'primary'],
                'recibido'   => ['label' => 'Recepción en obra','color' => 'success'],
            ];
            $etapa_cfg = $etapa_labels[$tarea['etapa_pedido']] ?? ['label' => $tarea['etapa_pedido'], 'color' => 'secondary'];
            $estado_pedido_labels = [
                'pendiente' => ['label' => 'Pendiente', 'color' => 'warning text-dark'],
                'aprobado'  => ['label' => 'Aprobado',  'color' => 'info'],
                'picking'   => ['label' => 'En Picking','color' => 'warning'],
                'retirado'  => ['label' => 'Retirado',  'color' => 'primary'],
                'recibido'  => ['label' => 'Recibido',  'color' => 'success'],
                'cancelado' => ['label' => 'Cancelado', 'color' => 'danger'],
            ];
            $estado_cfg = $estado_pedido_labels[$pedido_info['estado']] ?? ['label' => $pedido_info['estado'], 'color' => 'secondary'];
        ?>
        <div class="card mb-4 border-<?php echo $etapa_cfg['color']; ?>">
            <div class="card-header bg-<?php echo $etapa_cfg['color']; ?> bg-opacity-10">
                <i class="bi bi-box-seam"></i>
                <strong>Pedido vinculado</strong>
            </div>
            <div class="card-body">
                <h6 class="fw-bold mb-1">#<?php echo str_pad($pedido_info['id_pedido'], 4, '0', STR_PAD_LEFT); ?></h6>
                <p class="text-muted small mb-2"><?php echo htmlspecialchars($pedido_info['nombre_obra']); ?></p>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Estado del pedido</small>
                    <span class="badge bg-<?php echo $estado_cfg['color']; ?>">
                        <?php echo $estado_cfg['label']; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted">Etapa de esta tarea</small>
                    <span class="badge bg-<?php echo $etapa_cfg['color']; ?> bg-opacity-75 text-dark">
                        <?php echo $etapa_cfg['label']; ?>
                    </span>
                </div>

                <?php if ($pedido_info['fecha_necesaria']): ?>
                <div class="mb-3">
                    <small class="text-muted">Fecha necesaria</small><br>
                    <?php
                    $vence = $pedido_info['fecha_necesaria'] < date('Y-m-d') && $tarea['estado'] !== 'finalizada';
                    ?>
                    <small class="<?php echo $vence ? 'text-danger fw-bold' : ''; ?>">
                        <?php echo date('d/m/Y', strtotime($pedido_info['fecha_necesaria'])); ?>
                        <?php if ($vence): ?><i class="bi bi-exclamation-triangle"></i><?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>

                <a href="<?php echo SITE_URL; ?>/modules/pedidos/view.php?id=<?php echo $pedido_info['id_pedido']; ?>"
                   class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-eye"></i> Ver pedido completo
                </a>
            </div>
        </div>

        <?php if ($tarea_habilitada && $tarea['estado'] !== 'finalizada' && $tarea['estado'] !== 'cancelada'
                  && ($tarea['id_empleado'] == $_SESSION['user_id'] || has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]))): ?>
        <div class="card mb-4 border-success">
            <div class="card-body text-center">
                <p class="mb-2 small text-muted">¿Terminaste esta etapa?</p>
                <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>"
                   class="btn btn-success w-100">
                    <i class="bi bi-check-circle"></i> Completar tarea y avanzar pedido
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

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
                    <?php if ($tarea_habilitada && $tarea['estado'] !== 'finalizada' && $tarea['estado'] !== 'cancelada'
                              && ($tarea['id_empleado'] == $_SESSION['user_id'] || has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]))): ?>
                    <a href="update_status.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar Estado
                    </a>
                    <?php endif; ?>

                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) && ($tarea['tipo'] ?? '') !== 'pedido'): ?>
                    <a href="create.php?empleado_id=<?php echo $tarea['id_empleado']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus"></i> Nueva Tarea para este Empleado
                    </a>
                    <?php endif; ?>

                    <a href="list.php?empleado=<?php echo $tarea['id_empleado']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-list"></i> Ver Tareas del Empleado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

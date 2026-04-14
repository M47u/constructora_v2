<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/PedidoTareasHelper.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Actualizar Estado de Tarea';

$database = new Database();
$conn = $database->getConnection();

$tarea_id = (int)($_GET['id'] ?? 0);

if ($tarea_id <= 0) {
    redirect(SITE_URL . '/modules/tareas/list.php');
}

$errors = [];
$success_message = '';

try {
    // Obtener datos de la tarea
    $query = "SELECT t.*, 
              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido
              FROM tareas t 
              JOIN usuarios emp ON t.id_empleado = emp.id_usuario
              WHERE t.id_tarea = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$tarea_id]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        redirect(SITE_URL . '/modules/tareas/list.php');
    }

    // Verificar permisos
    $es_empleado = get_user_role() === ROLE_EMPLEADO;
    $puede_editar = has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) ||
                   ($es_empleado && $tarea['id_empleado'] == $_SESSION['user_id']);

    if (!$puede_editar) {
        redirect(SITE_URL . '/modules/tareas/list.php');
    }

} catch (Exception $e) {
    error_log("Error al obtener tarea: " . $e->getMessage());
    redirect(SITE_URL . '/modules/tareas/list.php');
}

// Determinar si la tarea está habilitada para ejecución
// habilitada=0 significa que es una tarea pre-creada pendiente de que la etapa anterior sea completada
$tarea_habilitada      = ((int)($tarea['habilitada'] ?? 1)) === 1;
$es_tarea_pedido_bloq  = !$tarea_habilitada && ($tarea['tipo'] ?? '') === 'pedido';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } elseif ($es_tarea_pedido_bloq) {
        // Bloqueo de backend: impedir avance de etapas fuera de secuencia
        $errors[] = 'Esta tarea aún no está habilitada. Debe completarse la etapa anterior del pedido primero.';
    } else {
        $nuevo_estado = $_POST['estado'];
        $observaciones = sanitize_input($_POST['observaciones']);

        // Validaciones
        if (!in_array($nuevo_estado, ['pendiente', 'en_proceso', 'finalizada', 'cancelada'])) {
            $errors[] = 'Estado inválido';
        }

        // Si no hay errores, actualizar en la base de datos
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                $fecha_finalizacion = ($nuevo_estado === 'finalizada') ? date('Y-m-d H:i:s') : null;
                $fecha_inicio_set   = ($nuevo_estado === 'en_proceso')  ? 'COALESCE(fecha_inicio, NOW())' : 'fecha_inicio';

                $stmt = $conn->prepare("UPDATE tareas SET
                    estado             = ?,
                    observaciones      = ?,
                    fecha_finalizacion = ?,
                    fecha_inicio       = {$fecha_inicio_set}
                    WHERE id_tarea = ?");
                $result = $stmt->execute([
                    $nuevo_estado,
                    $observaciones,
                    $fecha_finalizacion,
                    $tarea_id,
                ]);

                if (!$result) {
                    throw new Exception('Error al actualizar el estado de la tarea.');
                }

                // Si la tarea finalizada está vinculada a un pedido, avanzar el pedido
                $pedido_avanzado = null;
                if ($nuevo_estado === 'finalizada' && ($tarea['tipo'] ?? '') === 'pedido') {
                    // Recargar tarea con el id_empleado actualizado (puede ser el usuario actual)
                    $tarea['estado'] = $nuevo_estado;
                    PedidoTareasHelper::onTareaEtapaFinalizada($conn, $tarea);

                    // Obtener el nuevo estado del pedido para mostrarlo en el mensaje
                    if (!empty($tarea['id_pedido'])) {
                        $stmt_p = $conn->prepare("SELECT estado FROM pedidos_materiales WHERE id_pedido = ?");
                        $stmt_p->execute([$tarea['id_pedido']]);
                        $pedido_avanzado = $stmt_p->fetch(PDO::FETCH_ASSOC);
                    }
                }

                $conn->commit();

                // Actualizar datos locales
                $tarea['estado']             = $nuevo_estado;
                $tarea['observaciones']      = $observaciones;
                $tarea['fecha_finalizacion'] = $fecha_finalizacion;

                if ($pedido_avanzado) {
                    $etiquetas_estado = [
                        'aprobado' => 'Aprobado',
                        'picking'  => 'En Picking',
                        'retirado' => 'Retirado',
                        'recibido' => 'Recibido',
                    ];
                    $etiqueta   = $etiquetas_estado[$pedido_avanzado['estado']] ?? $pedido_avanzado['estado'];
                    $id_ped_fmt = '#' . str_pad($tarea['id_pedido'], 4, '0', STR_PAD_LEFT);
                    $success_message = "Tarea finalizada. El pedido <strong>{$id_ped_fmt}</strong> avanzó a <strong>{$etiqueta}</strong>.";
                } else {
                    $success_message = 'Estado de la tarea actualizado exitosamente.';
                }

            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error al actualizar tarea {$tarea_id}: " . $e->getMessage());
                $errors[] = 'Error interno del servidor: ' . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-arrow-clockwise"></i> Actualizar Estado de Tarea
            </h1>
            <div>
                <a href="view.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Ver Detalles
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Error:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
    <div class="mt-2">
        <a href="view.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-sm btn-success">Ver Tarea</a>
        <a href="list.php" class="btn btn-sm btn-outline-success">Ver Todas las Tareas</a>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clipboard-check"></i> Tarea: <?php echo htmlspecialchars($tarea['titulo']); ?>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Empleado Asignado</h6>
                        <p><?php echo htmlspecialchars($tarea['empleado_nombre'] . ' ' . $tarea['empleado_apellido']); ?></p>
                        
                        <h6 class="text-muted">Estado Actual</h6>
                        <p>
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
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Fecha de Asignación</h6>
                        <p><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])); ?></p>
                        
                        <?php if ($tarea['fecha_vencimiento']): ?>
                        <h6 class="text-muted">Fecha de Vencimiento</h6>
                        <p>
                            <?php 
                            $vencida = $tarea['fecha_vencimiento'] < date('Y-m-d') && $tarea['estado'] != 'finalizada';
                            $class = $vencida ? 'text-danger' : '';
                            ?>
                            <span class="<?php echo $class; ?>">
                                <?php echo date('d/m/Y', strtotime($tarea['fecha_vencimiento'])); ?>
                                <?php if ($vencida): ?>
                                    <i class="bi bi-exclamation-triangle"></i>
                                <?php endif; ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h6 class="text-muted">Descripción</h6>
                <div class="bg-light p-3 rounded mb-4">
                    <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                </div>

                <?php if ($es_tarea_pedido_bloq): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-lock fs-5"></i>
                    <div>
                        <strong>Tarea bloqueada</strong> &mdash;
                        Esta etapa todavía no está habilitada. Debe completarse la etapa anterior del pedido antes de poder avanzar esta tarea.
                        <?php if (!empty($tarea['id_pedido'])): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/pedidos/view.php?id=<?php echo $tarea['id_pedido']; ?>" class="alert-link ms-1" target="_blank">
                            Ver pedido <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif (($tarea['tipo'] ?? '') === 'pedido' && !empty($tarea['id_pedido'])): ?>
                <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-box-seam fs-5"></i>
                    <div>
                        <strong>Tarea de pedido</strong> &mdash;
                        Al marcarla como <strong>Finalizada</strong>, el pedido avanzará automáticamente a la siguiente etapa.
                        <a href="<?php echo SITE_URL; ?>/modules/pedidos/view.php?id=<?php echo $tarea['id_pedido']; ?>" class="alert-link ms-1" target="_blank">
                            Ver pedido <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$es_tarea_pedido_bloq): ?>
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Nuevo Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="pendiente" <?php echo $tarea['estado'] === 'pendiente' ? 'selected' : ''; ?>>
                                    🟡 Pendiente
                                </option>
                                <option value="en_proceso" <?php echo $tarea['estado'] === 'en_proceso' ? 'selected' : ''; ?>>
                                    🔵 En Proceso
                                </option>
                                <option value="finalizada" <?php echo $tarea['estado'] === 'finalizada' ? 'selected' : ''; ?>>
                                    ✅ Finalizada
                                </option>
                                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                                <option value="cancelada" <?php echo $tarea['estado'] === 'cancelada' ? 'selected' : ''; ?>>
                                    ❌ Cancelada
                                </option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione un estado.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" 
                                  maxlength="500"><?php echo htmlspecialchars($tarea['observaciones']); ?></textarea>
                        <div class="form-text">Agregue comentarios sobre el progreso o cambios en la tarea</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Estado
                        </button>
                    </div>
                </form>
                <?php endif; /* !$es_tarea_pedido_bloq */ ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Guía de Estados
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-warning text-dark">🟡 Pendiente</span>
                    <p class="small text-muted mt-1">La tarea está asignada pero aún no se ha comenzado.</p>
                </div>
                
                <div class="mb-3">
                    <span class="badge bg-info">🔵 En Proceso</span>
                    <p class="small text-muted mt-1">La tarea está siendo trabajada actualmente.</p>
                </div>
                
                <div class="mb-3">
                    <span class="badge bg-success">✅ Finalizada</span>
                    <p class="small text-muted mt-1">La tarea ha sido completada exitosamente.</p>
                </div>
                
                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                <div class="mb-3">
                    <span class="badge bg-danger">❌ Cancelada</span>
                    <p class="small text-muted mt-1">La tarea ha sido cancelada y no se completará.</p>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <h6 class="text-muted">Consejos</h6>
                <ul class="small text-muted">
                    <li>Actualice el estado regularmente</li>
                    <li>Use las observaciones para comunicar el progreso</li>
                    <li>Marque como finalizada solo cuando esté 100% completa</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
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
                $fecha_finalizacion = ($nuevo_estado === 'finalizada') ? date('Y-m-d H:i:s') : null;
                
                $query = "UPDATE tareas SET 
                         estado = ?, 
                         observaciones = ?, 
                         fecha_finalizacion = ?
                         WHERE id_tarea = ?";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $nuevo_estado, 
                    $observaciones, 
                    $fecha_finalizacion,
                    $tarea_id
                ]);

                if ($result) {
                    $success_message = 'Estado de la tarea actualizado exitosamente';
                    // Actualizar datos locales
                    $tarea['estado'] = $nuevo_estado;
                    $tarea['observaciones'] = $observaciones;
                    $tarea['fecha_finalizacion'] = $fecha_finalizacion;
                } else {
                    $errors[] = 'Error al actualizar el estado';
                }
            } catch (Exception $e) {
                error_log("Error al actualizar tarea: " . $e->getMessage());
                $errors[] = 'Error interno del servidor';
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

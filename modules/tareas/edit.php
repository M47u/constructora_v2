<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden editar tareas
if (!has_permission(ROLE_ADMIN) && !has_permission(ROLE_RESPONSABLE)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Editar Tarea';

$database = new Database();
$conn = $database->getConnection();

$tarea_id = (int)($_GET['id'] ?? 0);
$errors = [];
$success = '';

if ($tarea_id <= 0) {
    redirect(SITE_URL . '/modules/tareas/list.php');
}

// Obtener datos de la tarea
try {
    $query = "SELECT t.*, 
                     u.nombre as empleado_nombre, u.apellido as empleado_apellido,
                     o.nombre_obra, 
                     a.nombre as asignador_nombre, a.apellido as asignador_apellido
              FROM tareas t
              LEFT JOIN usuarios u ON t.id_empleado = u.id_usuario
              LEFT JOIN obras o ON t.id_obra = o.id_obra
              LEFT JOIN usuarios a ON t.id_asignador = a.id_usuario
              WHERE t.id_tarea = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tarea_id]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        redirect(SITE_URL . '/modules/tareas/list.php');
    }
} catch (Exception $e) {
    error_log("Error al obtener tarea: " . $e->getMessage());
    redirect(SITE_URL . '/modules/tareas/list.php');
}

// Obtener empleados activos
try {
    $query_empleados = "SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido";
    $stmt_empleados = $conn->prepare($query_empleados);
    $stmt_empleados->execute();
    $empleados = $stmt_empleados->fetchAll();
} catch (Exception $e) {
    $empleados = [];
}

// Obtener obras activas
try {
    $query_obras = "SELECT id_obra, nombre_obra FROM obras WHERE estado IN ('planificada', 'en_progreso') ORDER BY nombre_obra";
    $stmt_obras = $conn->prepare($query_obras);
    $stmt_obras->execute();
    $obras = $stmt_obras->fetchAll();
} catch (Exception $e) {
    $obras = [];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar datos
    $id_empleado = (int)($_POST['id_empleado'] ?? 0);
    $id_obra = !empty($_POST['id_obra']) ? (int)$_POST['id_obra'] : null;
    $titulo = sanitize_input($_POST['titulo'] ?? '');
    $descripcion = sanitize_input($_POST['descripcion'] ?? '');
    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
    $estado = sanitize_input($_POST['estado'] ?? 'pendiente');
    $prioridad = sanitize_input($_POST['prioridad'] ?? 'media');
    $progreso = (int)($_POST['progreso'] ?? 0);
    $observaciones = sanitize_input($_POST['observaciones'] ?? '');
    $tiempo_estimado = !empty($_POST['tiempo_estimado']) ? (int)$_POST['tiempo_estimado'] : null;
    $tiempo_real = !empty($_POST['tiempo_real']) ? (int)$_POST['tiempo_real'] : null;

    // Validaciones
    if (empty($titulo)) {
        $errors[] = "El título es obligatorio.";
    }

    if ($id_empleado <= 0) {
        $errors[] = "Debe seleccionar un empleado.";
    }

    if ($progreso < 0 || $progreso > 100) {
        $errors[] = "El progreso debe estar entre 0 y 100.";
    }

    if ($fecha_vencimiento && $fecha_vencimiento < date('Y-m-d')) {
        $errors[] = "La fecha de vencimiento no puede ser anterior a hoy.";
    }

    if ($tiempo_estimado && $tiempo_estimado <= 0) {
        $errors[] = "El tiempo estimado debe ser mayor a 0.";
    }

    if ($tiempo_real && $tiempo_real <= 0) {
        $errors[] = "El tiempo real debe ser mayor a 0.";
    }

    if (!in_array($estado, ['pendiente', 'en_proceso', 'finalizada', 'cancelada'])) {
        $errors[] = "Estado inválido.";
    }

    if (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'])) {
        $errors[] = "Prioridad inválida.";
    }

    // Verificar que el empleado existe y está activo
    if ($id_empleado > 0) {
        try {
            $query_check = "SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND estado = 'activo'";
            $stmt_check = $conn->prepare($query_check);
            $stmt_check->execute([$id_empleado]);
            if (!$stmt_check->fetch()) {
                $errors[] = "El empleado seleccionado no es válido.";
            }
        } catch (Exception $e) {
            $errors[] = "Error al verificar empleado.";
        }
    }

    // Verificar obra si se seleccionó
    if ($id_obra) {
        try {
            $query_check_obra = "SELECT id_obra FROM obras WHERE id_obra = ? AND estado IN ('planificada', 'en_progreso')";
            $stmt_check_obra = $conn->prepare($query_check_obra);
            $stmt_check_obra->execute([$id_obra]);
            if (!$stmt_check_obra->fetch()) {
                $errors[] = "La obra seleccionada no es válida.";
            }
        } catch (Exception $e) {
            $errors[] = "Error al verificar obra.";
        }
    }

    // Si no hay errores, actualizar
    if (empty($errors)) {
        try {
            // Manejar fechas según el estado
            $fecha_inicio = $tarea['fecha_inicio'];
            $fecha_finalizacion = $tarea['fecha_finalizacion'];

            if ($estado === 'en_proceso' && !$fecha_inicio) {
                $fecha_inicio = date('Y-m-d H:i:s');
            }

            if ($estado === 'finalizada' && !$fecha_finalizacion) {
                $fecha_finalizacion = date('Y-m-d H:i:s');
                $progreso = 100; // Automáticamente 100% si está finalizada
            }

            if ($estado !== 'finalizada') {
                $fecha_finalizacion = null;
            }

            $query_update = "UPDATE tareas SET 
                           id_empleado = ?, 
                           id_obra = ?, 
                           titulo = ?, 
                           descripcion = ?, 
                           fecha_vencimiento = ?, 
                           fecha_inicio = ?, 
                           fecha_finalizacion = ?, 
                           estado = ?, 
                           prioridad = ?, 
                           progreso = ?, 
                           observaciones = ?, 
                           tiempo_estimado = ?, 
                           tiempo_real = ?
                           WHERE id_tarea = ?";

            $stmt_update = $conn->prepare($query_update);
            $result = $stmt_update->execute([
                $id_empleado, $id_obra, $titulo, $descripcion, $fecha_vencimiento,
                $fecha_inicio, $fecha_finalizacion, $estado, $prioridad, $progreso,
                $observaciones, $tiempo_estimado, $tiempo_real, $tarea_id
            ]);

            if ($result) {
                // Registrar en logs
                try {
                    $log_query = "INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, ip_usuario) 
                                VALUES (?, 'actualizar', 'tareas', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->execute([
                        $_SESSION['user_id'],
                        "Tarea actualizada: {$titulo}",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    error_log("Error al registrar log: " . $e->getMessage());
                }

                $success = "Tarea actualizada correctamente.";
                
                // Actualizar datos de la tarea para mostrar los nuevos valores
                $stmt->execute([$tarea_id]);
                $tarea = $stmt->fetch();
            } else {
                $errors[] = "Error al actualizar la tarea.";
            }
        } catch (Exception $e) {
            error_log("Error al actualizar tarea: " . $e->getMessage());
            $errors[] = "Error interno del servidor.";
        }
    }
}

// Calcular estadísticas de la tarea
$dias_restantes = null;
$estado_vencimiento = '';
if ($tarea['fecha_vencimiento']) {
    $fecha_venc = new DateTime($tarea['fecha_vencimiento']);
    $hoy = new DateTime();
    $diferencia = $hoy->diff($fecha_venc);
    
    if ($fecha_venc < $hoy) {
        $dias_restantes = -$diferencia->days;
        $estado_vencimiento = 'vencida';
    } else {
        $dias_restantes = $diferencia->days;
        if ($dias_restantes <= 3) {
            $estado_vencimiento = 'proxima';
        } else {
            $estado_vencimiento = 'normal';
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
                <i class="bi bi-pencil-square"></i> Editar Tarea
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

<?php if (!empty($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información de la Tarea
            </div>
            <div class="card-body">
                <form method="POST" id="editTareaForm">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="titulo" class="form-label">
                                Título <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="titulo" name="titulo" 
                                   value="<?php echo htmlspecialchars($tarea['titulo']); ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="prioridad" class="form-label">Prioridad</label>
                            <select class="form-select" id="prioridad" name="prioridad">
                                <option value="baja" <?php echo ($tarea['prioridad'] == 'baja') ? 'selected' : ''; ?>>Baja</option>
                                <option value="media" <?php echo ($tarea['prioridad'] == 'media') ? 'selected' : ''; ?>>Media</option>
                                <option value="alta" <?php echo ($tarea['prioridad'] == 'alta') ? 'selected' : ''; ?>>Alta</option>
                                <option value="urgente" <?php echo ($tarea['prioridad'] == 'urgente') ? 'selected' : ''; ?>>Urgente</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="4"
                                  placeholder="Descripción detallada de la tarea..."><?php echo htmlspecialchars($tarea['descripcion'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_empleado" class="form-label">
                                Empleado Asignado <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="id_empleado" name="id_empleado" required>
                                <option value="">Seleccionar empleado...</option>
                                <?php foreach ($empleados as $empleado): ?>
                                    <option value="<?php echo $empleado['id_usuario']; ?>" 
                                            <?php echo ($empleado['id_usuario'] == $tarea['id_empleado']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="id_obra" class="form-label">Obra</label>
                            <select class="form-select" id="id_obra" name="id_obra">
                                <option value="">Sin obra específica</option>
                                <?php foreach ($obras as $obra): ?>
                                    <option value="<?php echo $obra['id_obra']; ?>" 
                                            <?php echo ($obra['id_obra'] == $tarea['id_obra']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" 
                                   value="<?php echo $tarea['fecha_vencimiento']; ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="pendiente" <?php echo ($tarea['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="en_proceso" <?php echo ($tarea['estado'] == 'en_proceso') ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="finalizada" <?php echo ($tarea['estado'] == 'finalizada') ? 'selected' : ''; ?>>Finalizada</option>
                                <option value="cancelada" <?php echo ($tarea['estado'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="progreso" class="form-label">
                                Progreso (<span id="progreso-valor"><?php echo $tarea['progreso']; ?></span>%)
                            </label>
                            <input type="range" class="form-range" id="progreso" name="progreso" 
                                   min="0" max="100" value="<?php echo $tarea['progreso']; ?>">
                            <div class="progress mt-2" style="height: 20px;">
                                <div class="progress-bar" id="barra-progreso" role="progressbar" 
                                     style="width: <?php echo $tarea['progreso']; ?>%" 
                                     aria-valuenow="<?php echo $tarea['progreso']; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $tarea['progreso']; ?>%
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="tiempo_estimado" class="form-label">Tiempo Estimado (horas)</label>
                                    <input type="number" class="form-control" id="tiempo_estimado" name="tiempo_estimado" 
                                           min="1" value="<?php echo $tarea['tiempo_estimado']; ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="tiempo_real" class="form-label">Tiempo Real (horas)</label>
                                    <input type="number" class="form-control" id="tiempo_real" name="tiempo_real" 
                                           min="1" value="<?php echo $tarea['tiempo_real']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                  placeholder="Observaciones adicionales..."><?php echo htmlspecialchars($tarea['observaciones'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Actualizar Tarea
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel lateral con información -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Estado de la Tarea
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-muted">Estado Actual</h6>
                    <span class="badge bg-<?php 
                        echo match($tarea['estado']) {
                            'pendiente' => 'warning',
                            'en_proceso' => 'primary',
                            'finalizada' => 'success',
                            'cancelada' => 'danger',
                            default => 'secondary'
                        };
                    ?> fs-6">
                        <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?>
                    </span>
                </div>

                <div class="mb-3">
                    <h6 class="text-muted">Prioridad</h6>
                    <span class="badge bg-<?php 
                        echo match($tarea['prioridad']) {
                            'baja' => 'success',
                            'media' => 'warning',
                            'alta' => 'danger',
                            'urgente' => 'dark',
                            default => 'secondary'
                        };
                    ?> fs-6">
                        <?php echo ucfirst($tarea['prioridad']); ?>
                    </span>
                </div>

                <?php if ($dias_restantes !== null): ?>
                    <div class="mb-3">
                        <h6 class="text-muted">Vencimiento</h6>
                        <div class="text-<?php 
                            echo match($estado_vencimiento) {
                                'vencida' => 'danger',
                                'proxima' => 'warning',
                                default => 'success'
                            };
                        ?>">
                            <?php if ($dias_restantes < 0): ?>
                                <i class="bi bi-exclamation-triangle"></i>
                                Vencida hace <?php echo abs($dias_restantes); ?> días
                            <?php elseif ($dias_restantes == 0): ?>
                                <i class="bi bi-clock"></i>
                                Vence hoy
                            <?php else: ?>
                                <i class="bi bi-calendar-check"></i>
                                <?php echo $dias_restantes; ?> días restantes
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <h6 class="text-muted">Progreso</h6>
                    <div class="progress">
                        <div class="progress-bar bg-<?php echo ($tarea['progreso'] == 100) ? 'success' : 'primary'; ?>" 
                             style="width: <?php echo $tarea['progreso']; ?>%">
                            <?php echo $tarea['progreso']; ?>%
                        </div>
                    </div>
                </div>

                <?php if ($tarea['tiempo_estimado'] && $tarea['tiempo_real']): ?>
                    <div class="mb-3">
                        <h6 class="text-muted">Tiempo</h6>
                        <div class="small">
                            <strong>Estimado:</strong> <?php echo $tarea['tiempo_estimado']; ?>h<br>
                            <strong>Real:</strong> <?php echo $tarea['tiempo_real']; ?>h
                            <?php 
                            $diferencia = $tarea['tiempo_real'] - $tarea['tiempo_estimado'];
                            if ($diferencia > 0): ?>
                                <span class="text-warning">(+<?php echo $diferencia; ?>h)</span>
                            <?php elseif ($diferencia < 0): ?>
                                <span class="text-success">(<?php echo $diferencia; ?>h)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <hr>

                <div class="mb-2">
                    <small class="text-muted">Asignado por:</small><br>
                    <?php echo htmlspecialchars($tarea['asignador_nombre'] . ' ' . $tarea['asignador_apellido']); ?>
                </div>

                <div class="mb-2">
                    <small class="text-muted">Fecha de Asignación:</small><br>
                    <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])); ?>
                </div>

                <?php if ($tarea['fecha_inicio']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Fecha de Inicio:</small><br>
                        <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_inicio'])); ?>
                    </div>
                <?php endif; ?>

                <?php if ($tarea['fecha_finalizacion']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Fecha de Finalización:</small><br>
                        <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_finalizacion'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ayuda -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-question-circle"></i> Ayuda
            </div>
            <div class="card-body">
                <h6>Estados de Tareas:</h6>
                <ul class="small">
                    <li><span class="badge bg-warning">Pendiente</span> - Tarea sin iniciar</li>
                    <li><span class="badge bg-primary">En Proceso</span> - Tarea en desarrollo</li>
                    <li><span class="badge bg-success">Finalizada</span> - Tarea completada</li>
                    <li><span class="badge bg-danger">Cancelada</span> - Tarea cancelada</li>
                </ul>
                
                <h6 class="mt-3">Consejos:</h6>
                <ul class="small">
                    <li>El progreso se actualiza automáticamente al 100% cuando se marca como finalizada</li>
                    <li>La fecha de inicio se establece automáticamente al cambiar a "En Proceso"</li>
                    <li>Use el tiempo estimado para planificación y el tiempo real para control</li>
                    <li>Las tareas vencidas aparecen marcadas en rojo</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editTareaForm');
    const progresoSlider = document.getElementById('progreso');
    const progresoValor = document.getElementById('progreso-valor');
    const barraProgreso = document.getElementById('barra-progreso');
    const estadoSelect = document.getElementById('estado');
    
    // Actualizar barra de progreso en tiempo real
    progresoSlider.addEventListener('input', function() {
        const valor = this.value;
        progresoValor.textContent = valor;
        barraProgreso.style.width = valor + '%';
        barraProgreso.setAttribute('aria-valuenow', valor);
        barraProgreso.textContent = valor + '%';
        
        // Cambiar color según el progreso
        barraProgreso.className = 'progress-bar';
        if (valor == 100) {
            barraProgreso.classList.add('bg-success');
        } else if (valor >= 75) {
            barraProgreso.classList.add('bg-info');
        } else if (valor >= 50) {
            barraProgreso.classList.add('bg-primary');
        } else if (valor >= 25) {
            barraProgreso.classList.add('bg-warning');
        } else {
            barraProgreso.classList.add('bg-danger');
        }
    });

    // Auto-completar progreso al 100% si se marca como finalizada
    estadoSelect.addEventListener('change', function() {
        if (this.value === 'finalizada') {
            progresoSlider.value = 100;
            progresoValor.textContent = 100;
            
            // Actualizar barra visual
            barraProgreso.style.width = '100%';
            barraProgreso.setAttribute('aria-valuenow', 100);
            barraProgreso.textContent = '100%';
            barraProgreso.className = 'progress-bar bg-success';
        }
    });

    // Validación del formulario
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    // Validación en tiempo real
    form.addEventListener('input', function(e) {
        validateField(e.target);
    });
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        switch(field.name) {
            case 'titulo':
                if (value === '') {
                    isValid = false;
                    message = 'El título es obligatorio.';
                }
                break;
                
            case 'id_empleado':
                if (value === '') {
                    isValid = false;
                    message = 'Debe seleccionar un empleado.';
                }
                break;
                
            case 'tiempo_estimado':
            case 'tiempo_real':
                if (value !== '' && parseInt(value) <= 0) {
                    isValid = false;
                    message = 'El tiempo debe ser mayor a 0.';
                }
                break;
        }
        
        // Aplicar estilos de validación
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            const feedback = field.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = message;
            }
        }
        
        return isValid;
    }
    
    function validateForm() {
        const requiredFields = ['titulo', 'id_empleado'];
        let isValid = true;
        
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

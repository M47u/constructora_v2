<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

//require_once 'config/config.php';
//require_once 'config/database.php';
//require_once 'includes/auth.php';

// Verificar que el usuario esté logueado
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Inicializar correctamente la conexión PDO
$database = new Database();
$pdo = $database->getConnection();

// Obtener información del usuario actual
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM tareas WHERE id_empleado = u.id_usuario) as total_tareas,
               (SELECT COUNT(*) FROM tareas WHERE id_empleado = u.id_usuario AND estado = 'finalizada') as tareas_completadas,
               (SELECT COUNT(*) FROM prestamos WHERE id_empleado = u.id_usuario AND estado = 'activo') as prestamos_activos,
               (SELECT COUNT(*) FROM pedidos_materiales WHERE id_solicitante = u.id_usuario) as pedidos_realizados
        FROM usuarios u 
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        redirect('logout.php');
    }
} catch (PDOException $e) {
    $error_message = "Error al cargar el perfil: " . $e->getMessage();
}

// Procesar formulario de actualización de información personal
if (isset($_POST['action']) && $_POST['action'] === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "Token de seguridad inválido.";
    } else {
        $nombre = sanitize_input($_POST['nombre']);
        $apellido = sanitize_input($_POST['apellido']);
        $email = sanitize_input($_POST['email']);
        $telefono = sanitize_input($_POST['telefono']);
        $direccion = sanitize_input($_POST['direccion']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
        $documento = sanitize_input($_POST['documento']);
        
        // Validaciones
        if (empty($nombre) || empty($apellido) || empty($email)) {
            $error_message = "Los campos nombre, apellido y email son obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "El formato del email no es válido.";
        } else {
            // Verificar que el email no esté en uso por otro usuario
            try {
                $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
                $stmt->execute([$email, $user_id]);
                
                if ($stmt->fetch()) {
                    $error_message = "El email ya está en uso por otro usuario.";
                } else {
                    // Actualizar información
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, apellido = ?, email = ?, telefono = ?, 
                            direccion = ?, fecha_nacimiento = ?, documento = ?
                        WHERE id_usuario = ?
                    ");
                    
                    if ($stmt->execute([$nombre, $apellido, $email, $telefono, $direccion, $fecha_nacimiento, $documento, $user_id])) {
                        // Actualizar sesión
                        $_SESSION['user_name'] = $nombre . ' ' . $apellido;
                        $_SESSION['user_email'] = $email;
                        
                        $success_message = "Información actualizada correctamente.";
                        
                        // Recargar datos del usuario
                        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                        $stmt->execute([$user_id]);
                        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error_message = "Error al actualizar la información.";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error de base de datos: " . $e->getMessage();
            }
        }
    }
}

// Procesar formulario de cambio de contraseña
if (isset($_POST['action']) && $_POST['action'] === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "Token de seguridad inválido.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validaciones
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "Todos los campos de contraseña son obligatorios.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Las contraseñas nuevas no coinciden.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "La nueva contraseña debe tener al menos 6 caracteres.";
        } else {
            // Verificar contraseña actual
            if (password_verify($current_password, $usuario['contraseña'])) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
                    
                    if ($stmt->execute([$hashed_password, $user_id])) {
                        $success_message = "Contraseña actualizada correctamente.";
                    } else {
                        $error_message = "Error al actualizar la contraseña.";
                    }
                } catch (PDOException $e) {
                    $error_message = "Error de base de datos: " . $e->getMessage();
                }
            } else {
                $error_message = "La contraseña actual es incorrecta.";
            }
        }
    }
}

// Obtener actividad reciente del usuario
try {
    $actividad_reciente = [];
    
    // Tareas recientes
    $stmt = $pdo->prepare("
        SELECT 'tarea' as tipo, t.titulo as descripcion, t.fecha_asignacion as fecha,
               o.nombre_obra, t.estado, t.prioridad
        FROM tareas t
        LEFT JOIN obras o ON t.id_obra = o.id_obra
        WHERE t.id_empleado = ?
        ORDER BY t.fecha_asignacion DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $actividad_reciente = array_merge($actividad_reciente, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Préstamos recientes
    $stmt = $pdo->prepare("
        SELECT 'prestamo' as tipo, 
               CONCAT('Préstamo #', p.numero_prestamo) as descripcion,
               p.fecha_retiro as fecha, o.nombre_obra, p.estado, 'media' as prioridad
        FROM prestamos p
        LEFT JOIN obras o ON p.id_obra = o.id_obra
        WHERE p.id_empleado = ?
        ORDER BY p.fecha_retiro DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $actividad_reciente = array_merge($actividad_reciente, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Ordenar por fecha
    usort($actividad_reciente, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });
    
    $actividad_reciente = array_slice($actividad_reciente, 0, 10);
    
} catch (PDOException $e) {
    $actividad_reciente = [];
}

// Calcular estadísticas
$progreso_tareas = $usuario['total_tareas'] > 0 ? 
    round(($usuario['tareas_completadas'] / $usuario['total_tareas']) * 100) : 0;

include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Mi Perfil</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Mi Perfil</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Información del Perfil -->
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">
                        <div class="avatar-lg mx-auto mb-4">
                            <?php if ($usuario['avatar']): ?>
                                <img src="<?php echo htmlspecialchars($usuario['avatar']); ?>" 
                                     alt="Avatar" class="avatar-img rounded-circle">
                            <?php else: ?>
                                <div class="avatar-title rounded-circle bg-primary text-white fs-1">
                                    <?php echo strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['apellido'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="mb-1"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h5>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($usuario['email']); ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <?php
                            $rol_colors = [
                                'administrador' => 'danger',
                                'responsable_obra' => 'warning',
                                'empleado' => 'info'
                            ];
                            $rol_color = $rol_colors[$usuario['rol']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $rol_color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?>
                            </span>
                            <span class="badge bg-<?php echo $usuario['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($usuario['estado']); ?>
                            </span>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="mt-3">
                                    <p class="text-muted mb-1">Tareas Asignadas</p>
                                    <h5 class="mb-0"><?php echo isset($usuario['total_tareas']) ? $usuario['total_tareas'] : '0'; ?></h5>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mt-3">
                                    <p class="text-muted mb-1">Préstamos Activos</p>
                                    <h5 class="mb-0"><?php echo $usuario['prestamos_activos']; ?></h5>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="mb-2">Progreso de Tareas</h6>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $progreso_tareas; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $progreso_tareas; ?>% completado</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Adicional -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información Personal</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="ps-0" scope="row">Teléfono:</td>
                                    <td class="text-muted"><?php echo $usuario['telefono'] ?: 'No especificado'; ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0" scope="row">Documento:</td>
                                    <td class="text-muted"><?php echo $usuario['documento'] ?: 'No especificado'; ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0" scope="row">Fecha de Nacimiento:</td>
                                    <td class="text-muted">
                                        <?php echo $usuario['fecha_nacimiento'] ? 
                                            date('d/m/Y', strtotime($usuario['fecha_nacimiento'])) : 'No especificado'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-0" scope="row">Miembro desde:</td>
                                    <td class="text-muted"><?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0" scope="row">Último acceso:</td>
                                    <td class="text-muted">
                                        <?php echo $usuario['fecha_ultimo_acceso'] ? 
                                            date('d/m/Y H:i', strtotime($usuario['fecha_ultimo_acceso'])) : 'Nunca'; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formularios de Edición -->
        <div class="col-xl-8">
            <!-- Editar Información Personal -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Editar Información Personal</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" 
                                           value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($usuario['telefono']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="documento" class="form-label">Documento</label>
                                    <input type="text" class="form-control" id="documento" name="documento" 
                                           value="<?php echo htmlspecialchars($usuario['documento']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?php echo $usuario['fecha_nacimiento']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="3"><?php echo isset($usuario['direccion']) && $usuario['direccion'] !== null ? htmlspecialchars($usuario['direccion']) : ''; ?></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Recomendaciones de seguridad:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Use al menos 8 caracteres</li>
                            <li>Combine letras mayúsculas y minúsculas</li>
                            <li>Incluya números y símbolos</li>
                            <li>No use información personal</li>
                        </ul>
                    </div>
                    
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-1"></i>Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actividad Reciente</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($actividad_reciente)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay actividad reciente</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($actividad_reciente as $actividad): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <?php if ($actividad['tipo'] === 'tarea'): ?>
                                            <i class="fas fa-tasks text-primary"></i>
                                        <?php else: ?>
                                            <i class="fas fa-tools text-info"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($actividad['descripcion']); ?></h6>
                                        <?php if ($actividad['nombre_obra']): ?>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($actividad['nombre_obra']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (isset($actividad['estado'])): ?>
                                                <?php
                                                $estado_colors = [
                                                    'pendiente' => 'warning',
                                                    'en_proceso' => 'info',
                                                    'finalizada' => 'success',
                                                    'cancelada' => 'danger',
                                                    'activo' => 'success',
                                                    'devuelto' => 'secondary'
                                                ];
                                                $estado_color = $estado_colors[$actividad['estado']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $estado_color; ?>">
                                                    <?php echo ucfirst($actividad['estado']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-lg {
    width: 5rem;
    height: 5rem;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 0;
    width: 2rem;
    height: 2rem;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    border-left: 3px solid #007bff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del formulario de perfil
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const apellido = document.getElementById('apellido').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!nombre || !apellido || !email) {
                e.preventDefault();
                alert('Los campos nombre, apellido y email son obligatorios.');
                return false;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Por favor ingrese un email válido.');
                return false;
            }
        });
    }
    
    // Validación del formulario de contraseña
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Todos los campos de contraseña son obligatorios.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas nuevas no coinciden.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('La nueva contraseña debe tener al menos 6 caracteres.');
                return false;
            }
        });
        
        // Validación en tiempo real de contraseñas
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (newPassword.value && confirmPassword.value) {
                if (newPassword.value === confirmPassword.value) {
                    confirmPassword.classList.remove('is-invalid');
                    confirmPassword.classList.add('is-valid');
                } else {
                    confirmPassword.classList.remove('is-valid');
                    confirmPassword.classList.add('is-invalid');
                }
            }
        }
        
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>

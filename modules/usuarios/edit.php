<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden editar usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Editar Usuario';

$database = new Database();
$conn = $database->getConnection();

$id_usuario = $_GET['id'] ?? 0;
$errors = [];
$success = '';

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
} catch (Exception $e) {
    error_log("Error al obtener usuario: " . $e->getMessage());
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $estado = $_POST['estado'] ?? 'activo';

    // Validaciones
    if (empty($nombre)) {
        $errors[] = 'El nombre es obligatorio';
    }
    
    if (empty($apellido)) {
        $errors[] = 'El apellido es obligatorio';
    }
    
    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no tiene un formato válido';
    }
    
    if (empty($rol) || !in_array($rol, ['administrador', 'responsable_obra', 'empleado'])) {
        $errors[] = 'Debe seleccionar un rol válido';
    }

    // Verificar si el email ya existe (excepto el usuario actual)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
            $stmt->execute([$email, $id_usuario]);
            if ($stmt->fetch()) {
                $errors[] = 'Ya existe otro usuario con este email';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al verificar el email';
        }
    }

    // Si no hay errores, actualizar el usuario
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET nombre = ?, apellido = ?, email = ?, rol = ?, telefono = ?, direccion = ?, estado = ?
                WHERE id_usuario = ?
            ");
            
            $stmt->execute([
                $nombre, $apellido, $email, $rol, $telefono, $direccion, $estado, $id_usuario
            ]);
            
            $success = 'Usuario actualizado exitosamente';
            
            // Actualizar datos para mostrar en el formulario
            $usuario['nombre'] = $nombre;
            $usuario['apellido'] = $apellido;
            $usuario['email'] = $email;
            $usuario['rol'] = $rol;
            $usuario['telefono'] = $telefono;
            $usuario['direccion'] = $direccion;
            $usuario['estado'] = $estado;
            
        } catch (Exception $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            $errors[] = 'Error al actualizar el usuario. Intente nuevamente.';
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
                <i class="bi bi-pencil"></i> Editar Usuario
            </h1>
            <div class="btn-group">
                <a href="view.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-outline-info">
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
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            Información del Usuario
            <?php if ($usuario['id_usuario'] == $_SESSION['user_id']): ?>
                <span class="badge bg-info ms-2">Tu perfil</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese el nombre.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" 
                               value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese el apellido.
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese un email válido.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                               value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección</label>
                <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="rol" name="rol" required 
                                <?php echo $usuario['id_usuario'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                            <option value="">Seleccionar rol...</option>
                            <option value="administrador" <?php echo $usuario['rol'] === 'administrador' ? 'selected' : ''; ?>>
                                Administrador
                            </option>
                            <option value="responsable_obra" <?php echo $usuario['rol'] === 'responsable_obra' ? 'selected' : ''; ?>>
                                Responsable de Obra
                            </option>
                            <option value="empleado" <?php echo $usuario['rol'] === 'empleado' ? 'selected' : ''; ?>>
                                Empleado
                            </option>
                        </select>
                        <?php if ($usuario['id_usuario'] == $_SESSION['user_id']): ?>
                            <input type="hidden" name="rol" value="<?php echo $usuario['rol']; ?>">
                            <div class="form-text">No puedes cambiar tu propio rol</div>
                        <?php endif; ?>
                        <div class="invalid-feedback">
                            Por favor seleccione un rol.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado"
                                <?php echo $usuario['id_usuario'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                            <option value="activo" <?php echo $usuario['estado'] === 'activo' ? 'selected' : ''; ?>>
                                Activo
                            </option>
                            <option value="inactivo" <?php echo $usuario['estado'] === 'inactivo' ? 'selected' : ''; ?>>
                                Inactivo
                            </option>
                        </select>
                        <?php if ($usuario['id_usuario'] == $_SESSION['user_id']): ?>
                            <input type="hidden" name="estado" value="<?php echo $usuario['estado']; ?>">
                            <div class="form-text">No puedes cambiar tu propio estado</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Fecha de Registro</label>
                        <input type="text" class="form-control" 
                               value="<?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?>" 
                               readonly>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Último Acceso</label>
                        <input type="text" class="form-control" 
                            value="<?php echo (isset($usuario['ultimo_acceso']) && $usuario['ultimo_acceso']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>" 
                            readonly>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <div>
                    <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                    <a href="change_password.php?id=<?php echo $usuario['id_usuario']; ?>" 
                       class="btn btn-outline-warning">
                        <i class="bi bi-key"></i> Cambiar Contraseña
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="view.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../includes/footer.php'; ?>

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden crear usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Crear Usuario';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
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
    
    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (empty($rol) || !in_array($rol, ['administrador', 'responsable_obra', 'empleado'])) {
        $errors[] = 'Debe seleccionar un rol válido';
    }

    // Verificar si el email ya existe
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Ya existe un usuario con este email';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al verificar el email';
        }
    }

    // Si no hay errores, crear el usuario
    if (empty($errors)) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO usuarios (nombre, apellido, email, contraseña, rol, telefono, direccion, estado, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $nombre, $apellido, $email, $password_hash, 
                $rol, $telefono, $direccion, $estado
            ]);
            
            $success = 'Usuario creado exitosamente';
            
            // Limpiar formulario
            $nombre = $apellido = $email = $telefono = $direccion = '';
            $rol = $estado = '';
            
        } catch (Exception $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            $errors[] = 'Error al crear el usuario. Intente nuevamente.';
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
                <i class="bi bi-person-plus"></i> Crear Usuario
            </h1>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a la lista
            </a>
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
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese el nombre.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" 
                               value="<?php echo htmlspecialchars($apellido ?? ''); ?>" required>
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
                               value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese un email válido.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                               value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección</label>
                <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($direccion ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Mínimo 6 caracteres</div>
                        <div class="invalid-feedback">
                            Por favor ingrese una contraseña.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">
                            Por favor confirme la contraseña.
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="rol" name="rol" required>
                            <option value="">Seleccionar rol...</option>
                            <option value="administrador" <?php echo ($rol ?? '') === 'administrador' ? 'selected' : ''; ?>>
                                Administrador
                            </option>
                            <option value="responsable_obra" <?php echo ($rol ?? '') === 'responsable_obra' ? 'selected' : ''; ?>>
                                Responsable de Obra
                            </option>
                            <option value="empleado" <?php echo ($rol ?? '') === 'empleado' ? 'selected' : ''; ?>>
                                Empleado
                            </option>
                        </select>
                        <div class="invalid-feedback">
                            Por favor seleccione un rol.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="activo" <?php echo ($estado ?? 'activo') === 'activo' ? 'selected' : ''; ?>>
                                Activo
                            </option>
                            <option value="inactivo" <?php echo ($estado ?? '') === 'inactivo' ? 'selected' : ''; ?>>
                                Inactivo
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Crear Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Validación de contraseñas en tiempo real
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Las contraseñas no coinciden');
    } else {
        this.setCustomValidity('');
    }
});

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

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden cambiar contraseñas de otros usuarios
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Cambiar Contraseña';

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
    $stmt = $conn->prepare("SELECT nombre, apellido, email FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        redirect(SITE_URL . '/modules/usuarios/list.php');
    }
} catch (Exception $e) {
    error_log("Error al obtener usuario: " . $e->getMessage());
    redirect(SITE_URL . '/modules/usuarios/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    // Validaciones
    if (empty($nueva_password)) {
        $errors[] = 'La nueva contraseña es obligatoria';
    } elseif (strlen($nueva_password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    }
    
    if ($nueva_password !== $confirmar_password) {
        $errors[] = 'Las contraseñas no coinciden';
    }

    // Si no hay errores, actualizar la contraseña
    if (empty($errors)) {
        try {
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
            $stmt->execute([$password_hash, $id_usuario]);
            
            $success = 'Contraseña actualizada exitosamente';
            
        } catch (Exception $e) {
            error_log("Error al cambiar contraseña: " . $e->getMessage());
            $errors[] = 'Error al cambiar la contraseña. Intente nuevamente.';
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
                <i class="bi bi-key"></i> Cambiar Contraseña
            </h1>
            <div class="btn-group">
                <a href="view.php?id=<?php echo $id_usuario; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Ver Usuario
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

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-person"></i> 
                    <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                </h5>
                <small class="text-muted"><?php echo htmlspecialchars($usuario['email']); ?></small>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Importante:</strong> Esta acción cambiará la contraseña del usuario. 
                    Asegúrese de comunicar la nueva contraseña al usuario de forma segura.
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="nueva_password" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="nueva_password" name="nueva_password" required>
                        <div class="form-text">Mínimo 6 caracteres</div>
                        <div class="invalid-feedback">
                            Por favor ingrese la nueva contraseña.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmar_password" class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                        <div class="invalid-feedback">
                            Por favor confirme la nueva contraseña.
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="view.php?id=<?php echo $id_usuario; ?>" class="btn btn-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Validación de contraseñas en tiempo real
document.getElementById('confirmar_password').addEventListener('input', function() {
    const password = document.getElementById('nueva_password').value;
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

<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden crear transportes
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nuevo Transporte';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

// Obtener usuarios disponibles para asignar como encargados
try {
    $stmt = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $usuarios = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error al obtener usuarios: " . $e->getMessage());
    $usuarios = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $marca = sanitize_input($_POST['marca']);
        $modelo = sanitize_input($_POST['modelo']);
        $matricula = sanitize_input($_POST['matricula']);
        $id_encargado = !empty($_POST['id_encargado']) ? (int)$_POST['id_encargado'] : null;
        $estado = $_POST['estado'];

        // Validaciones
        if (empty($marca)) {
            $errors[] = 'La marca es obligatoria';
        }
        if (empty($modelo)) {
            $errors[] = 'El modelo es obligatorio';
        }
        if (empty($matricula)) {
            $errors[] = 'La matrícula es obligatoria';
        }
        if (!in_array($estado, ['disponible', 'en_uso', 'mantenimiento', 'fuera_servicio'])) {
            $errors[] = 'Estado inválido';
        }

        // Verificar si la matrícula ya existe
        if (empty($errors)) {
            try {
                $check_query = "SELECT id_transporte FROM transportes WHERE matricula = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$matricula]);
                
                if ($check_stmt->rowCount() > 0) {
                    $errors[] = 'Ya existe un transporte con esa matrícula';
                }
            } catch (Exception $e) {
                error_log("Error al verificar matrícula: " . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $query = "INSERT INTO transportes (marca, modelo, matricula, id_encargado, estado) 
                         VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $marca, $modelo, $matricula, $id_encargado, $estado
                ]);

                if ($result) {
                    $success_message = 'Transporte creado exitosamente';
                    // Limpiar formulario
                    $_POST = [];
                } else {
                    $errors[] = 'Error al crear el transporte';
                }
            } catch (Exception $e) {
                error_log("Error al crear transporte: " . $e->getMessage());
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
                <i class="bi bi-plus-circle"></i> Nuevo Transporte
            </h1>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Listado
            </a>
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
        <a href="list.php" class="btn btn-sm btn-success">Ver Todos los Transportes</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Crear Otro Transporte</button>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-truck-front-fill"></i> Información del Transporte
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="marca" class="form-label">Marca *</label>
                    <input type="text" class="form-control" id="marca" name="marca" 
                           value="<?php echo isset($_POST['marca']) ? htmlspecialchars($_POST['marca']) : ''; ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese la marca del vehículo.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="modelo" class="form-label">Modelo *</label>
                    <input type="text" class="form-control" id="modelo" name="modelo" 
                           value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>" 
                           required maxlength="100">
                    <div class="invalid-feedback">
                        Por favor ingrese el modelo del vehículo.
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="matricula" class="form-label">Matrícula *</label>
                    <input type="text" class="form-control" id="matricula" name="matricula" 
                           value="<?php echo isset($_POST['matricula']) ? htmlspecialchars($_POST['matricula']) : ''; ?>" 
                           required maxlength="20" style="text-transform: uppercase;">
                    <div class="invalid-feedback">
                        Por favor ingrese la matrícula del vehículo.
                    </div>
                    <div class="form-text">Ejemplo: ABC123, AB123CD</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_encargado" class="form-label">Encargado</label>
                    <select class="form-select" id="id_encargado" name="id_encargado">
                        <option value="">Sin encargado asignado</option>
                        <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?php echo $usuario['id_usuario']; ?>" 
                                <?php echo (isset($_POST['id_encargado']) && $_POST['id_encargado'] == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Opcional - puede asignarse más tarde</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="estado" class="form-label">Estado *</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="">Seleccionar estado</option>
                        <option value="disponible" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'disponible') ? 'selected' : 'selected'; ?>>
                            🟢 Disponible
                        </option>
                        <option value="en_uso" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'en_uso') ? 'selected' : ''; ?>>
                            🟡 En Uso
                        </option>
                        <option value="mantenimiento" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'mantenimiento') ? 'selected' : ''; ?>>
                            🔵 Mantenimiento
                        </option>
                        <option value="fuera_servicio" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'fuera_servicio') ? 'selected' : ''; ?>>
                            🔴 Fuera de Servicio
                        </option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione un estado.
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Transporte
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Convertir matrícula a mayúsculas automáticamente
document.getElementById('matricula').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

<?php include '../../includes/footer.php'; ?>

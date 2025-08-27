<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session(); 

// Solo administradores y responsables pueden agregar unidades
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Agregar Unidad de Herramienta';

$database = new Database();
$conn = $database->getConnection();

$herramienta_id = (int)($_GET['id'] ?? 0);

if ($herramienta_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

$errors = [];
$success_message = '';

try {
    // Obtener datos del tipo de herramienta
    $query = "SELECT * FROM herramientas WHERE id_herramienta = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$herramienta_id]);
    $herramienta = $stmt->fetch();

    if (!$herramienta) {
        redirect(SITE_URL . '/modules/herramientas/list.php');
    }
} catch (Exception $e) {
    error_log("Error al obtener herramienta para agregar unidad: " . $e->getMessage());
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $cantidad = (int)($_POST['cantidad'] ?? 1);
        $estado_actual = $_POST['estado_actual'];

        if ($cantidad <= 0) {
            $errors[] = 'La cantidad debe ser mayor a cero.';
        }
        if (!in_array($estado_actual, ['disponible', 'prestada', 'mantenimiento', 'perdida', 'dañada'])) {
            $errors[] = 'Estado actual inválido';
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // Buscar el último QR incremental para esta herramienta
                $query_last_qr = "SELECT qr_code FROM herramientas_unidades WHERE id_herramienta = ? ORDER BY qr_code DESC LIMIT 1";
                $stmt_last_qr = $conn->prepare($query_last_qr);
                $stmt_last_qr->execute([$herramienta_id]);
                $last_qr = $stmt_last_qr->fetchColumn();

                // Extraer el número incremental
                $last_num = 0;
                if ($last_qr && preg_match('/^' . $herramienta_id . '-(\d{3,})$/', $last_qr, $matches)) {
                    $last_num = (int)$matches[1];
                }

                $agregadas = 0;
                $qrs_generados = [];
                for ($i = 1; $i <= $cantidad; $i++) {
                    $num = $last_num + $i;
                    $qr_code = $herramienta_id . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);

                    // Verificar que no exista
                    $check_query = "SELECT id_unidad FROM herramientas_unidades WHERE qr_code = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->execute([$qr_code]);
                    if ($check_stmt->rowCount() > 0) {
                        continue; // Saltar si ya existe
                    }

                    // Insertar nueva unidad
                    $query_unit = "INSERT INTO herramientas_unidades (id_herramienta, qr_code, estado_actual) VALUES (?, ?, ?)";
                    $stmt_unit = $conn->prepare($query_unit);
                    $result_unit = $stmt_unit->execute([$herramienta_id, $qr_code, $estado_actual]);
                    if ($result_unit) {
                        $agregadas++;
                        $qrs_generados[] = $qr_code;
                    }
                }

                // Actualizar stock_total en la tabla herramientas
                $query_update_stock = "UPDATE herramientas SET stock_total = stock_total + ? WHERE id_herramienta = ?";
                $stmt_update_stock = $conn->prepare($query_update_stock);
                $result_update_stock = $stmt_update_stock->execute([$agregadas, $herramienta_id]);

                if ($agregadas > 0 && $result_update_stock) {
                    $conn->commit();
                    $success_message = 'Unidades agregadas exitosamente. Cantidad agregada: ' . $agregadas . '.';
                    $pdf_url = 'qr_pdf.php?id=' . $herramienta_id . '&estado=' . urlencode($estado_actual) . '&qrs=' . urlencode(implode(',', $qrs_generados));
                    $pdf_button = '<a href="' . $pdf_url . '" target="_blank" class="btn btn-outline-dark mt-2"><i class="bi bi-printer"></i> Descargar PDF de etiquetas QR</a>';
                    $_POST = [];
                } else {
                    $conn->rollBack();
                    $errors[] = 'No se pudo agregar ninguna unidad nueva (puede que los QR generados ya existan).';
                }
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error al agregar unidad de herramienta: " . $e->getMessage());
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
                <i class="bi bi-plus-square"></i> Agregar Unidad a "<?php echo htmlspecialchars($herramienta['marca'] . ' ' . $herramienta['modelo']); ?>"
            </h1>
            <div>
                <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-eye"></i> Ver Herramienta
                </a>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Listado
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
        <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-sm btn-success">Ver Herramienta</a>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">Agregar Otra Unidad</button>
        <?php if (isset($pdf_button)) echo $pdf_button; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-qr-code"></i> Datos de la Nueva Unidad
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            

            <div class="mb-3">
                <label for="cantidad" class="form-label">Cantidad de unidades a agregar *</label>
                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" value="<?php echo isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1; ?>" required>
                <div class="invalid-feedback">
                    Por favor ingrese la cantidad de unidades a agregar.
                </div>
                <div class="form-text">Los códigos QR se generarán automáticamente y serán únicos e incrementales.</div>
            </div>

            <div class="mb-3">
                <label for="estado_actual" class="form-label">Estado Inicial *</label>
                <select class="form-select" id="estado_actual" name="estado_actual" required>
                    <option value="">Seleccionar estado</option>
                    <option value="disponible" <?php echo (isset($_POST['estado_actual']) && $_POST['estado_actual'] === 'disponible') ? 'selected' : 'selected'; ?>>
                        🟢 Disponible
                    </option>
                    <option value="mantenimiento" <?php echo (isset($_POST['estado_actual']) && $_POST['estado_actual'] === 'mantenimiento') ? 'selected' : ''; ?>>
                        🔵 Mantenimiento
                    </option>
                    <option value="dañada" <?php echo (isset($_POST['estado_actual']) && $_POST['estado_actual'] === 'dañada') ? 'selected' : ''; ?>>
                        🔴 Dañada
                    </option>
                    <option value="perdida" <?php echo (isset($_POST['estado_actual']) && $_POST['estado_actual'] === 'perdida') ? 'selected' : ''; ?>>
                        ⚫ Perdida
                    </option>
                </select>
                <div class="invalid-feedback">
                    Por favor seleccione el estado inicial de la unidad.
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="view.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Agregar Unidad
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Script para QRCodeJS (debe ir antes de tu main.js) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

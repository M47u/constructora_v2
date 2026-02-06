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
        $precio_compra = !empty($_POST['precio_compra']) ? (float)$_POST['precio_compra'] : null;
        $proveedor = !empty($_POST['proveedor']) ? trim($_POST['proveedor']) : null;
        $fecha_compra = !empty($_POST['fecha_compra']) ? $_POST['fecha_compra'] : null;

        if ($cantidad <= 0) {
            $errors[] = 'La cantidad debe ser mayor a cero.';
        }
        if (!es_estado_valido($estado_actual)) {
            $errors[] = 'Estado actual inválido';
        }
        if ($precio_compra !== null && $precio_compra < 0) {
            $errors[] = 'El precio de compra no puede ser negativo.';
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

                    // Insertar nueva unidad con condición inicial 'nueva' y datos de compra
                    $query_unit = "INSERT INTO herramientas_unidades (id_herramienta, qr_code, estado_actual, condicion_actual, precio_compra, proveedor, fecha_compra) VALUES (?, ?, ?, 'nueva', ?, ?, ?)";
                    $stmt_unit = $conn->prepare($query_unit);
                    $result_unit = $stmt_unit->execute([$herramienta_id, $qr_code, $estado_actual, $precio_compra, $proveedor, $fecha_compra]);
                    if ($result_unit) {
                        $agregadas++;
                        $qrs_generados[] = $qr_code;
                    }
                }

                // El trigger tr_herramientas_stock_insert se encarga automáticamente de actualizar el stock_total

                if ($agregadas > 0) {
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
                    <?php foreach (ESTADOS_HERRAMIENTAS as $codigo => $nombre): ?>
                        <option value="<?php echo $codigo; ?>" 
                            <?php echo (isset($_POST['estado_actual']) && $_POST['estado_actual'] === $codigo) ? 'selected' : 
                                       ($codigo === 'disponible' && !isset($_POST['estado_actual']) ? 'selected' : ''); ?>>
                            <?php echo $nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">
                    Por favor seleccione el estado inicial de la unidad.
                </div>
            </div>

            <hr>
            <h5 class="mb-3"><i class="bi bi-receipt"></i> Información de Compra (Opcional)</h5>
            <p class="text-muted small">Los siguientes campos son opcionales y se aplicarán a todas las unidades que se agreguen.</p>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="precio_compra" class="form-label">Precio de Compra</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="precio_compra" name="precio_compra" 
                               min="0" step="0.01" placeholder="0.00"
                               value="<?php echo isset($_POST['precio_compra']) ? htmlspecialchars($_POST['precio_compra']) : ''; ?>">
                    </div>
                    <div class="form-text">Precio unitario de compra</div>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="proveedor" class="form-label">Proveedor</label>
                    <input type="text" class="form-control" id="proveedor" name="proveedor" 
                           maxlength="100" placeholder="Nombre del proveedor"
                           value="<?php echo isset($_POST['proveedor']) ? htmlspecialchars($_POST['proveedor']) : ''; ?>">
                    <div class="form-text">Nombre del proveedor</div>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="fecha_compra" class="form-label">Fecha de Compra</label>
                    <input type="date" class="form-control" id="fecha_compra" name="fecha_compra"
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo isset($_POST['fecha_compra']) ? htmlspecialchars($_POST['fecha_compra']) : ''; ?>">
                    <div class="form-text">Fecha de adquisición</div>
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

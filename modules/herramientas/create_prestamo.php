<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden crear préstamos
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Nuevo Préstamo de Herramientas';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';
$prestamo_creado = null;

// Obtener empleados y obras activas
try {
    $stmt_empleados = $conn->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $empleados = $stmt_empleados->fetchAll();

    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras WHERE estado IN ('planificada', 'en_progreso') ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();

    // Obtener TODAS las herramientas (no solo disponibles) con información del préstamo actual
    $query_unidades = "SELECT 
                        hu.id_unidad, 
                        hu.qr_code, 
                        h.modelo, 
                        h.marca, 
                        h.descripcion, 
                        hu.estado_actual,
                        pa.id_prestamo,
                        pa.fecha_retiro,
                        pa.empleado_prestamo,
                        pa.obra_prestamo,
                        pa.condicion_prestamo
                        FROM herramientas_unidades hu
                        JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                        LEFT JOIN (
                            SELECT 
                                dp.id_unidad,
                                p.id_prestamo,
                                p.fecha_retiro,
                                CONCAT(u.nombre, ' ', u.apellido) as empleado_prestamo,
                                o.nombre_obra as obra_prestamo,
                                dp.condicion_retiro as condicion_prestamo
                            FROM detalle_prestamo dp
                            JOIN prestamos p ON dp.id_prestamo = p.id_prestamo
                            LEFT JOIN devoluciones d ON p.id_prestamo = d.id_prestamo
                            LEFT JOIN usuarios u ON p.id_empleado = u.id_usuario
                            LEFT JOIN obras o ON p.id_obra = o.id_obra
                            WHERE d.id_devolucion IS NULL
                        ) pa ON hu.id_unidad = pa.id_unidad
                        ORDER BY h.marca, h.modelo, hu.qr_code";
    
    $stmt_unidades = $conn->query($query_unidades);
    
    if (!$stmt_unidades) {
        error_log("Error en consulta de herramientas: " . print_r($conn->errorInfo(), true));
        throw new Exception("Error al obtener herramientas");
    }
    
    $unidades_disponibles = $stmt_unidades->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Ver primera herramienta
    if (!empty($unidades_disponibles)) {
        error_log("Primera herramienta ejemplo: " . print_r($unidades_disponibles[0], true));
    }

} catch (Exception $e) {
    error_log("Error al cargar datos para préstamo: " . $e->getMessage());
    $empleados = [];
    $obras = [];
    $unidades_disponibles = [];
    $errors[] = 'Error al cargar datos necesarios para el formulario.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $id_empleado = (int)$_POST['id_empleado'];
        $id_obra = (int)$_POST['id_obra'];
        $fecha_devolucion_programada = $_POST['fecha_devolucion_programada'] ?? null;
        $observaciones_retiro = sanitize_input($_POST['observaciones_retiro']);
        $unidades_seleccionadas = $_POST['unidades_seleccionadas'] ?? [];
        $condicion_retiro = $_POST['condicion_retiro'] ?? [];

        // Validaciones
        if (empty($id_empleado)) {
            $errors[] = 'Debe seleccionar un empleado';
        }
        if (empty($id_obra)) {
            $errors[] = 'Debe seleccionar una obra';
        }
        if (empty($unidades_seleccionadas)) {
            $errors[] = 'Debe seleccionar al menos una herramienta para prestar';
        } else {
            foreach ($unidades_seleccionadas as $unidad_id) {
                if (!isset($condicion_retiro[$unidad_id]) || empty($condicion_retiro[$unidad_id])) {
                    $errors[] = 'Debe especificar la condición de retiro para todas las herramientas seleccionadas.';
                    break;
                }
                if (!in_array($condicion_retiro[$unidad_id], ['excelente', 'buena', 'regular', 'mala'])) {
                    $errors[] = 'Condición de retiro inválida para alguna herramienta.';
                    break;
                }
            }
        }

        // Si no hay errores, insertar en la base de datos
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // 1. Insertar el préstamo principal
                $query_prestamo = "INSERT INTO prestamos (id_empleado, id_obra, fecha_devolucion_programada, id_autorizado_por, observaciones_retiro) 
                                   VALUES (?, ?, ?, ?, ?)";
                $stmt_prestamo = $conn->prepare($query_prestamo);
                $result_prestamo = $stmt_prestamo->execute([
                    $id_empleado, $id_obra, $fecha_devolucion_programada, $_SESSION['user_id'], $observaciones_retiro
                ]);

                if (!$result_prestamo) {
                    throw new Exception('Error al crear el registro de préstamo.');
                }
                $id_prestamo = $conn->lastInsertId();

                // 2. Insertar los detalles del préstamo y actualizar el estado de las unidades
                foreach ($unidades_seleccionadas as $unidad_id) {
                    $condicion = $condicion_retiro[$unidad_id];
                    
                    // Insertar detalle
                    $query_detalle = "INSERT INTO detalle_prestamo (id_prestamo, id_unidad, condicion_retiro) 
                                      VALUES (?, ?, ?)";
                    $stmt_detalle = $conn->prepare($query_detalle);
                    $result_detalle = $stmt_detalle->execute([$id_prestamo, $unidad_id, $condicion]);

                    if (!$result_detalle) {
                        throw new Exception('Error al insertar detalle de préstamo para unidad ' . $unidad_id);
                    }

                    // Actualizar estado de la unidad a 'prestada'
                    $query_update_unit = "UPDATE herramientas_unidades SET estado_actual = 'prestada' WHERE id_unidad = ?";
                    $stmt_update_unit = $conn->prepare($query_update_unit);
                    $result_update_unit = $stmt_update_unit->execute([$unidad_id]);

                    if (!$result_update_unit) {
                        throw new Exception('Error al actualizar estado de unidad ' . $unidad_id);
                    }
                }

                $conn->commit();
                
                // Obtener datos del préstamo creado para el comprobante
                $query_prestamo_info = "SELECT p.*, u.nombre, u.apellido, o.nombre_obra, ua.nombre as autorizado_nombre, ua.apellido as autorizado_apellido
                                        FROM prestamos p
                                        JOIN usuarios u ON p.id_empleado = u.id_usuario
                                        JOIN obras o ON p.id_obra = o.id_obra
                                        JOIN usuarios ua ON p.id_autorizado_por = ua.id_usuario
                                        WHERE p.id_prestamo = ?";
                $stmt_info = $conn->prepare($query_prestamo_info);
                $stmt_info->execute([$id_prestamo]);
                $prestamo_creado = $stmt_info->fetch();
                
                // Obtener detalles de herramientas
                $query_detalles = "SELECT dp.*, hu.qr_code, h.tipo, h.marca, h.modelo, h.descripcion
                                   FROM detalle_prestamo dp
                                   JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
                                   JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                                   WHERE dp.id_prestamo = ?";
                $stmt_detalles = $conn->prepare($query_detalles);
                $stmt_detalles->execute([$id_prestamo]);
                $detalles_prestamo = $stmt_detalles->fetchAll();
                
                $success_message = 'Préstamo de herramientas registrado exitosamente.';
                
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error al crear préstamo: " . $e->getMessage());
                $errors[] = 'Error interno del servidor: ' . $e->getMessage();
            }
        }
    }
}

// Si no hay préstamo creado, incluir header normal
if (!$prestamo_creado) {
    include '../../includes/header.php';
}
?>

<?php if ($prestamo_creado): ?>
<!-- Comprobante de Préstamo -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Préstamo - <?php echo $prestamo_creado['id_prestamo']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-break { page-break-after: always; }
            body { font-size: 12px; }
        }
        .comprobante-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo-empresa {
            max-height: 80px;
            width: auto;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin: 15px 0;
        }
        .herramienta-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 5px 0;
            background-color: #fff;
        }
        .qr-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header del Comprobante -->
        <div class="comprobante-header">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <img src="../../assets/img/logo_san_simon.png" alt="Logo San Simón" class="logo-empresa">
                </div>
                <div class="col-md-6 text-center">
                    <h2 class="mb-1">COMPROBANTE DE PRÉSTAMO</h2>
                    <h4 class="text-muted">Herramientas y Equipos</h4>
                </div>
                <div class="col-md-3 text-end">
                    <div class="info-box">
                        <strong>Préstamo N°:</strong><br>
                        <span class="fs-4 text-primary"><?php echo str_pad($prestamo_creado['id_prestamo'], 6, '0', STR_PAD_LEFT); ?></span><br>
                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($prestamo_creado['fecha_retiro'])); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Préstamo -->
        <div class="row">
            <div class="col-md-6">
                <div class="info-box">
                    <h5><i class="bi bi-person-fill"></i> Datos del Responsable</h5>
                    <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars(($prestamo_creado['nombre'] ?? '') . ' ' . ($prestamo_creado['apellido'] ?? '')); ?></p>
                    <p class="mb-1"><strong>Obra:</strong> <?php echo htmlspecialchars($prestamo_creado['nombre_obra'] ?? ''); ?></p>
                    <p class="mb-1"><strong>Fecha de Retiro:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo_creado['fecha_retiro'])); ?></p>
                    <?php if (!empty($prestamo_creado['fecha_devolucion_programada'])): ?>
                    <p class="mb-0"><strong>Devolución Programada:</strong> <?php echo date('d/m/Y', strtotime($prestamo_creado['fecha_devolucion_programada'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box">
                    <h5><i class="bi bi-person-check"></i> Autorizado por</h5>
                    <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars(($prestamo_creado['autorizado_nombre'] ?? '') . ' ' . ($prestamo_creado['autorizado_apellido'] ?? '')); ?></p>
                    <p class="mb-0"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                </div>
            </div>
        </div>

        <!-- Herramientas Prestadas -->
        <div class="mt-4">
            <h5><i class="bi bi-tools"></i> Herramientas Prestadas</h5>
            <div class="row">
                <?php foreach ($detalles_prestamo as $detalle): ?>
                <div class="col-md-6 mb-3">
                    <div class="herramienta-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($detalle['tipo'] ?? ''); ?></h6>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars(($detalle['marca'] ?? '') . ' ' . ($detalle['modelo'] ?? '')); ?></p>
                                <?php if (!empty($detalle['descripcion'])): ?>
                                <p class="mb-1"><small><?php echo htmlspecialchars($detalle['descripcion']); ?></small></p>
                                <?php endif; ?>
                                <p class="mb-1"><strong>QR:</strong> <span class="qr-code"><?php echo htmlspecialchars($detalle['qr_code'] ?? ''); ?></span></p>
                            </div>
                            <div class="text-end">
                                <?php
                                $condicion_class = '';
                                switch($detalle['condicion_retiro']) {
                                    case 'excelente': $condicion_class = 'bg-success'; break;
                                    case 'buena': $condicion_class = 'bg-info'; break;
                                    case 'regular': $condicion_class = 'bg-warning'; break;
                                    case 'mala': $condicion_class = 'bg-danger'; break;
                                }
                                ?>
                                <span class="badge <?php echo $condicion_class; ?> text-white">
                                    <?php echo ucfirst($detalle['condicion_retiro']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($prestamo_creado['observaciones_retiro'])): ?>
        <div class="mt-4">
            <div class="info-box">
                <h5><i class="bi bi-chat-text"></i> Observaciones</h5>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($prestamo_creado['observaciones_retiro'] ?? '')); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Firmas -->
        <div class="row mt-5">
            <div class="col-md-6 text-center">
                <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 10px;">
                    <strong>Firma del Responsable</strong><br>
                    <small><?php echo htmlspecialchars(($prestamo_creado['nombre'] ?? '') . ' ' . ($prestamo_creado['apellido'] ?? '')); ?></small>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 10px;">
                    <strong>Firma del Autorizante</strong><br>
                    <small><?php echo htmlspecialchars(($prestamo_creado['autorizado_nombre'] ?? '') . ' ' . ($prestamo_creado['autorizado_apellido'] ?? '')); ?></small>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary btn-lg me-3">
                <i class="bi bi-printer"></i> Imprimir Comprobante
            </button>
            <a href="prestamos.php" class="btn btn-success btn-lg me-3">
                <i class="bi bi-list"></i> Ver Todos los Préstamos
            </a>
            <a href="create_prestamo.php" class="btn btn-outline-primary btn-lg">
                <i class="bi bi-plus-circle"></i> Nuevo Préstamo
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php else: ?>
<!-- Formulario de Creación de Préstamo -->

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-plus-circle"></i> Nuevo Préstamo de Herramientas
            </h1>
            <a href="prestamos.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Préstamos
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

<!-- Debug: Mostrar cantidad de herramientas cargadas -->
<div class="alert alert-info" id="debug-info">
    <i class="bi bi-info-circle"></i>
    <strong>Debug:</strong> Se cargaron <?php echo count($unidades_disponibles); ?> herramientas disponibles.
    <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="console.log(herramientasData)">Ver en Consola</button>
</div>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-data"></i> Información del Préstamo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_empleado" class="form-label">Empleado que Retira <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_empleado" name="id_empleado" required>
                                <option value="">Seleccionar empleado</option>
                                <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo $empleado['id_usuario']; ?>" 
                                        <?php echo (isset($_POST['id_empleado']) && $_POST['id_empleado'] == $empleado['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione el empleado que retira.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="id_obra" class="form-label">Obra Destino <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_obra" name="id_obra" required>
                                <option value="">Seleccionar obra</option>
                                <?php foreach ($obras as $obra): ?>
                                <option value="<?php echo $obra['id_obra']; ?>" 
                                        <?php echo (isset($_POST['id_obra']) && $_POST['id_obra'] == $obra['id_obra']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione la obra destino.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="fecha_devolucion_programada" class="form-label">Fecha de Devolución Programada</label>
                        <input type="date" class="form-control" id="fecha_devolucion_programada" name="fecha_devolucion_programada" 
                               value="<?php echo isset($_POST['fecha_devolucion_programada']) ? htmlspecialchars($_POST['fecha_devolucion_programada']) : ''; ?>">
                        <div class="form-text">Fecha estimada para la devolución de las herramientas (opcional).</div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones_retiro" class="form-label">Observaciones de Retiro</label>
                        <textarea class="form-control" id="observaciones_retiro" name="observaciones_retiro" rows="3" 
                                  maxlength="500"><?php echo isset($_POST['observaciones_retiro']) ? htmlspecialchars($_POST['observaciones_retiro']) : ''; ?></textarea>
                        <div class="form-text">Cualquier detalle adicional sobre el retiro de las herramientas.</div>
                    </div>
                </div>
            </div>

            <!-- Herramientas del préstamo -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-tools"></i> Herramientas del Préstamo
                    </h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="mostrarBuscadorQR()">
                            <i class="bi bi-qr-code-scan"></i> Escanear QR
                        </button>
                    </div>
                </div>
                <div class="card-body">                    <!-- Buscador QR/Manual -->
                    <div id="buscador-container" class="mb-4" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6><i class="bi bi-search"></i> Buscar Herramienta</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="qr-input" class="form-label">Código QR o Búsqueda Manual</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="qr-input" 
                                                   placeholder="Ingrese código QR o busque por nombre (mín. 3 caracteres)...">
                                            <button class="btn btn-outline-secondary" type="button" onclick="buscarHerramienta()">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cámara QR</label>
                                        <div>
                                            <button type="button" class="btn btn-success" onclick="iniciarCamara()">
                                                <i class="bi bi-camera"></i> Activar Cámara
                                            </button>
                                            <button type="button" class="btn btn-secondary ms-2" onclick="cerrarBuscador()">
                                                <i class="bi bi-x"></i> Cerrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Área de la cámara -->
                                <div id="camera-container" class="mt-3" style="display: none;">
                                    <div id="qr-reader" style="width: 100%; max-width: 400px;"></div>
                                </div>
                                
                                <!-- Resultados de búsqueda -->
                                <div id="search-results" class="mt-3"></div>
                            </div>
                        </div>
                    </div>

                    <div id="herramientas-container">
                        <!-- Las herramientas se agregarán aquí dinámicamente -->
                    </div>
                    
                    <div class="alert alert-info mt-3" id="empty-message">
                        <i class="bi bi-info-circle"></i> <strong>¿Cómo agregar herramientas?</strong><br>
                        <small>Use el buscador de arriba para encontrar herramientas por QR, marca, modelo o escanear con cámara. Las herramientas disponibles se pueden agregar, las prestadas se pueden devolver.</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Resumen del préstamo -->
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-check"></i> Resumen del Préstamo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Herramientas:</span>
                        <span id="total-herramientas" class="fw-bold">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-success">Excelente:</span>
                        <span id="condicion-excelente" class="text-success">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-info">Buena:</span>
                        <span id="condicion-buena" class="text-info">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-warning">Regular:</span>
                        <span id="condicion-regular" class="text-warning">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-danger">Mala:</span>
                        <span id="condicion-mala" class="text-danger">0</span>
                    </div>
                    
                    <div class="alert alert-info alert-sm">
                        <i class="bi bi-info-circle"></i>
                        <small>Registre la condición de cada herramienta al momento del retiro.</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Registrar Préstamo
                        </button>
                        <a href="prestamos.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal para Devolución Rápida -->
<div class="modal fade" id="modalDevolucion" tabindex="-1" aria-labelledby="modalDevolucionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalDevolucionLabel">
                    <i class="bi bi-box-arrow-in-down"></i> Registrar Devolución
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Esta herramienta está actualmente prestada. Complete la devolución para poder prestarla nuevamente.
                </div>
                
                <form id="formDevolucion">
                    <input type="hidden" name="id_prestamo" id="devolucion_id_prestamo">
                    <input type="hidden" name="id_unidad" id="devolucion_id_unidad">
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Herramienta:</strong></label>
                        <div id="devolucion_herramienta_info"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="devolucion_condicion" class="form-label">Condición de Devolución <span class="text-danger">*</span></label>
                        <select class="form-select" id="devolucion_condicion" name="condicion_devolucion" required>
                            <option value="">Seleccionar condición</option>
                            <option value="excelente">Excelente</option>
                            <option value="buena">Buena</option>
                            <option value="regular">Regular</option>
                            <option value="mala">Mala</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="devolucion_observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="devolucion_observaciones" name="observaciones_devolucion" rows="3" placeholder="Detalles sobre el estado de la herramienta al ser devuelta..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="devolucion_requiere_mantenimiento" class="form-label">¿Requiere mantenimiento?</label>
                        <select class="form-select" id="devolucion_requiere_mantenimiento" name="requiere_mantenimiento">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnGuardarDevolucion" onclick="guardarDevolucion()">
                    <i class="bi bi-check-circle"></i> Guardar Devolución
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Incluir librería QR -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
let contadorHerramientas = 0;
let html5QrCode = null;

// CORREGIDO: Asegurar que los datos se carguen correctamente
const herramientasData = <?php echo json_encode($unidades_disponibles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

// Debug: Verificar que los datos se cargaron
console.log('Herramientas cargadas:', herramientasData);
console.log('Total herramientas:', herramientasData.length);
// Debug adicional: Ver una herramienta de ejemplo
if (herramientasData.length > 0) {
    console.log('Ejemplo de herramienta:', herramientasData[0]);
}

function mostrarBuscadorQR() {
    const buscador = document.getElementById('buscador-container');
    buscador.style.display = buscador.style.display === 'none' ? 'block' : 'none';
    
    if (buscador.style.display === 'block') {
        document.getElementById('qr-input').focus();
    }
}

function cerrarBuscador() {
    document.getElementById('buscador-container').style.display = 'none';
    if (html5QrCode) {
        html5QrCode.stop();
        document.getElementById('camera-container').style.display = 'none';
    }
}

function iniciarCamara() {
    const cameraContainer = document.getElementById('camera-container');
    cameraContainer.style.display = 'block';
    
    html5QrCode = new Html5Qrcode("qr-reader");
    
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            const cameraId = devices[0].id;
            
            html5QrCode.start(
                cameraId,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText, decodedResult) => {
                    // QR Code detectado
                    document.getElementById('qr-input').value = decodedText;
                    buscarHerramienta();
                    html5QrCode.stop();
                    cameraContainer.style.display = 'none';
                },
                (errorMessage) => {
                    // Error de lectura (normal)
                }
            ).catch(err => {
                console.error('Error al iniciar cámara:', err);
                alert('Error al acceder a la cámara. Verifique los permisos.');
            });
        }
    }).catch(err => {
        console.error('Error al obtener cámaras:', err);
        alert('No se pudieron detectar cámaras disponibles.');
    });
}

// CORREGIDO: Cambiar a 3 caracteres mínimos y mejorar la búsqueda
function buscarHerramienta() {
    const searchTerm = document.getElementById('qr-input').value.toLowerCase().trim();
    const resultsContainer = document.getElementById('search-results');
    
    console.log('Buscando:', searchTerm);
    
    if (searchTerm.length < 3) {
        resultsContainer.innerHTML = '<div class="alert alert-warning">Ingrese al menos 3 caracteres para buscar.</div>';
        return;
    }
    
    // Verificar que tenemos datos
    if (!herramientasData || herramientasData.length === 0) {
        resultsContainer.innerHTML = '<div class="alert alert-danger">No hay herramientas disponibles cargadas.</div>';
        return;
    }
    
    // CORREGIDO: Mejorar el filtro de búsqueda
    const herramientasEncontradas = herramientasData.filter(herramienta => {
        if (!herramienta) return false;
        
        const qrCode = (herramienta.qr_code || '').toLowerCase();
        //const tipo = (herramienta.tipo || '').toLowerCase();
        const marca = (herramienta.marca || '').toLowerCase();
        const modelo = (herramienta.modelo || '').toLowerCase();
        const descripcion = (herramienta.descripcion || '').toLowerCase();
        
        return qrCode.includes(searchTerm) ||
               //tipo.includes(searchTerm) ||
               marca.includes(searchTerm) ||
               modelo.includes(searchTerm) ||
               descripcion.includes(searchTerm);
    }).slice(0, 10);
    
    console.log('Herramientas encontradas:', herramientasEncontradas);
    
    if (herramientasEncontradas.length > 0) {
        let html = '<div class="mt-3"><h6>Herramientas encontradas (' + herramientasEncontradas.length + '):</h6>';
        herramientasEncontradas.forEach(herramienta => {
            // Debug: ver los datos de cada herramienta
            console.log('Procesando herramienta:', herramienta);
            console.log('Estado actual:', herramienta.estado_actual);
            console.log('ID Préstamo:', herramienta.id_prestamo);
            
            const estadoActual = herramienta.estado_actual || 'desconocido';
            const estaPrestada = estadoActual === 'prestada';
            const esDisponible = estadoActual === 'disponible';
            
            console.log('¿Está prestada?', estaPrestada);
            console.log('¿Es disponible?', esDisponible);
            
            let estadoBadge = '';
            let estadoClass = '';
            let botonAccion = '';
            
            if (estaPrestada) {
                estadoBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> PRESTADA</span>';
                estadoClass = 'border-warning';
                botonAccion = `
                    <button type="button" class="btn btn-sm btn-warning" 
                            onclick="mostrarDevolucion('${herramienta.id_unidad}', '${herramienta.id_prestamo}', '${herramienta.qr_code}', '${herramienta.marca}', '${herramienta.modelo}')">
                        <i class="bi bi-box-arrow-in-down"></i> Devolver
                    </button>
                `;
            } else if (esDisponible) {
                estadoBadge = '<span class="badge bg-success">DISPONIBLE</span>';
                estadoClass = 'border-success';
                botonAccion = `
                    <button type="button" class="btn btn-sm btn-primary" 
                            onclick="seleccionarHerramientaBuscada('${herramienta.id_unidad}', '${herramienta.qr_code}', '', '${herramienta.marca}', '${herramienta.modelo}')">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                `;
            } else {
                estadoBadge = `<span class="badge bg-secondary">${estadoActual.toUpperCase()}</span>`;
                estadoClass = '';
                botonAccion = '<small class="text-muted">No disponible</small>';
            }
            
            html += `
                <div class="card mb-2 ${estadoClass}">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <strong>${herramienta.marca || ''} ${herramienta.modelo || 'Sin modelo'}</strong><br>
                                <span class="badge bg-secondary">QR: ${herramienta.qr_code || 'Sin QR'}</span>
                                ${estadoBadge}
                                ${estaPrestada ? `
                                    <br><small class="text-muted">
                                        <i class="bi bi-person"></i> ${herramienta.empleado_prestamo || 'N/A'} | 
                                        <i class="bi bi-building"></i> ${herramienta.obra_prestamo || 'N/A'}
                                    </small>
                                ` : ''}
                            </div>
                            <div>
                                ${botonAccion}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        resultsContainer.innerHTML = html;
    } else {
        resultsContainer.innerHTML = '<div class="alert alert-info">No se encontraron herramientas que coincidan con la búsqueda "<strong>' + searchTerm + '</strong>".</div>';
    }
}

function seleccionarHerramientaBuscada(idUnidad, qrCode, tipo, marca, modelo) {
    // Verificar si ya está agregada
    const existente = document.querySelector(`input[name="unidades_seleccionadas[]"][value="${idUnidad}"]`);
    if (existente) {
        alert('Esta herramienta ya está agregada al préstamo.');
        return;
    }
    
    // Agregar herramienta
    agregarHerramientaEspecifica(idUnidad, qrCode, tipo, marca, modelo);
    
    // Limpiar buscador
    document.getElementById('qr-input').value = '';
    document.getElementById('search-results').innerHTML = '';
    cerrarBuscador();
}

function agregarHerramienta() {
    contadorHerramientas++;
    const container = document.getElementById('herramientas-container');
    const emptyMessage = document.getElementById('empty-message');
    
    const herramientaRow = document.createElement('div');
    herramientaRow.className = 'herramienta-row border rounded p-3 mb-3';
    herramientaRow.id = `herramienta-${contadorHerramientas}`;
    
    herramientaRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-5">
                <label class="form-label">Herramienta <span class="text-danger">*</span></label>
                <div class="herramienta-search-container position-relative">
                    <input type="text" 
                           class="form-control herramienta-search-input" 
                           id="herramienta-search-${contadorHerramientas}"
                           placeholder="Escriba para buscar herramienta (mín. 3 caracteres)..."
                           autocomplete="off"
                           oninput="filtrarHerramientas(${contadorHerramientas})"
                           onfocus="mostrarListaHerramientas(${contadorHerramientas})"
                           onblur="setTimeout(() => ocultarListaHerramientas(${contadorHerramientas}), 200)"
                           required>
                    <input type="hidden" class="herramienta-select" name="unidades_seleccionadas[]" id="herramienta-hidden-${contadorHerramientas}">
                    <div class="herramienta-dropdown" id="herramienta-dropdown-${contadorHerramientas}">
                        <div class="herramienta-list" id="herramienta-list-${contadorHerramientas}">
                            <!-- Las herramientas se cargarán dinámicamente -->
                        </div>
                    </div>
                </div>
                <div class="invalid-feedback">
                    Por favor seleccione una herramienta.
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Condición de Retiro <span class="text-danger">*</span></label>
                <select class="form-select condicion-select" name="condicion_retiro[${contadorHerramientas}]" onchange="actualizarResumen()" required>
                    <option value="">Seleccionar condición</option>
                    <option value="excelente">Excelente</option>
                    <option value="buena">Buena</option>
                    <option value="regular">Regular</option>
                    <option value="mala">Mala</option>
                </select>
                <div class="invalid-feedback">
                    Seleccione la condición de la herramienta.
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">QR Code</label>
                <div id="qr-display-${contadorHerramientas}" class="form-control-plaintext">
                    <span class="badge bg-secondary">Sin seleccionar</span>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarHerramienta(${contadorHerramientas})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div id="info-herramienta-${contadorHerramientas}" class="mt-2" style="display: none;">
            <small class="text-muted">
                <strong>Marca:</strong> <span class="tipo-herramienta"></span> | 
                <strong>Modelo:</strong> <span class="marca-herramienta"></span> | 
                
            </small>
        </div>
    `;
    
    container.appendChild(herramientaRow);
    emptyMessage.style.display = 'none';
    actualizarResumen();
}

function agregarHerramientaEspecifica(idUnidad, qrCode, tipo, marca, modelo) {
    contadorHerramientas++;
    const container = document.getElementById('herramientas-container');
    const emptyMessage = document.getElementById('empty-message');
    
    const herramientaRow = document.createElement('div');
    herramientaRow.className = 'herramienta-row border rounded p-3 mb-3 bg-light';
    herramientaRow.id = `herramienta-${contadorHerramientas}`;
    
    herramientaRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-5">
                <label class="form-label">Herramienta</label>
                <input type="text" class="form-control" value="${tipo} - ${marca} ${modelo}" readonly>
                <input type="hidden" name="unidades_seleccionadas[]" value="${idUnidad}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Condición de Retiro <span class="text-danger">*</span></label>
                <select class="form-select condicion-select" name="condicion_retiro[${idUnidad}]" onchange="actualizarResumen()" required>
                    <option value="">Seleccionar condición</option>
                    <option value="excelente">Excelente</option>
                    <option value="buena">Buena</option>
                    <option value="regular">Regular</option>
                    <option value="mala">Mala</option>
                </select>
                <div class="invalid-feedback">
                    Seleccione la condición de la herramienta.
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">QR Code</label>
                <div class="form-control-plaintext">
                    <span class="badge bg-primary">${qrCode}</span>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarHerramienta(${contadorHerramientas})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="mt-2">
            <small class="text-muted">
                <strong>Tipo:</strong> ${tipo} | 
                <strong>Marca:</strong> ${marca} | 
                <strong>Modelo:</strong> ${modelo}
            </small>
        </div>
    `;
    
    container.appendChild(herramientaRow);
    emptyMessage.style.display = 'none';
    actualizarResumen();
}

function eliminarHerramienta(id) {
    const herramientaRow = document.getElementById(`herramienta-${id}`);
    herramientaRow.remove();
    
    const container = document.getElementById('herramientas-container');
    const emptyMessage = document.getElementById('empty-message');
    
    if (container.children.length === 0) {
        emptyMessage.style.display = 'block';
    }
    
    actualizarResumen();
}

// CORREGIDO: Cambiar a 3 caracteres mínimos
function filtrarHerramientas(id) {
    const searchInput = document.getElementById(`herramienta-search-${id}`);
    const herramientaList = document.getElementById(`herramienta-list-${id}`);
    const dropdown = document.getElementById(`herramienta-dropdown-${id}`);
    const searchTerm = searchInput.value.toLowerCase().trim();
    
    console.log('Filtrando herramientas para ID:', id, 'Término:', searchTerm);
    
    // Limpiar lista anterior
    herramientaList.innerHTML = '';
    
    // CORREGIDO: Cambiar a 3 caracteres mínimos
    if (searchTerm.length < 3) {
        dropdown.style.display = 'none';
        return;
    }
    
    // Verificar que tenemos datos
    if (!herramientasData || herramientasData.length === 0) {
        const noData = document.createElement('div');
        noData.className = 'no-results text-danger p-3 text-center';
        noData.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No hay herramientas disponibles';
        herramientaList.appendChild(noData);
        dropdown.style.display = 'block';
        return;
    }
    
    // Filtrar herramientas disponibles
    const herramientasFiltradas = herramientasData.filter(herramienta => {
        if (!herramienta) return false;
        
        // Verificar que no esté ya seleccionada
        const yaSeleccionada = document.querySelector(`input[name="unidades_seleccionadas[]"][value="${herramienta.id_unidad}"]`);
        if (yaSeleccionada) return false;
        
        const qrCode = (herramienta.qr_code || '').toLowerCase();
        //const tipo = (herramienta.tipo || '').toLowerCase();
        const marca = (herramienta.marca || '').toLowerCase();
        const modelo = (herramienta.modelo || '').toLowerCase();
        const descripcion = (herramienta.descripcion || '').toLowerCase();
        
        return qrCode.includes(searchTerm) ||
               //tipo.includes(searchTerm) ||
               marca.includes(searchTerm) ||
               modelo.includes(searchTerm) ||
               descripcion.includes(searchTerm);
    }).slice(0, 10);
    
    console.log('Herramientas filtradas:', herramientasFiltradas);
    
    if (herramientasFiltradas.length > 0) {
        herramientasFiltradas.forEach(herramienta => {
            const option = document.createElement('div');
            option.className = 'herramienta-option';
            option.onclick = () => seleccionarHerramienta(id, herramienta.id_unidad, herramienta.qr_code, herramienta.marca, herramienta.modelo);
            
            option.innerHTML = `
                <div class="herramienta-name">${herramienta.modelo || 'Sin modelo'}</div>
                <div class="herramienta-info">
                    <small class="text-muted">
                        <span class="badge bg-secondary">QR: ${herramienta.qr_code || 'Sin QR'}</span>
                    </small>
                </div>
            `;
            
            herramientaList.appendChild(option);
        });
        
        dropdown.style.display = 'block';
    } else {
        const noResults = document.createElement('div');
        noResults.className = 'no-results text-muted p-3 text-center';
        noResults.innerHTML = '<i class="bi bi-search"></i> No se encontraron herramientas disponibles para "' + searchTerm + '"';
        herramientaList.appendChild(noResults);
        dropdown.style.display = 'block';
    }
}

function mostrarListaHerramientas(id) {
    const searchInput = document.getElementById(`herramienta-search-${id}`);
    if (searchInput.value.length >= 3) {
        filtrarHerramientas(id);
    }
}

function ocultarListaHerramientas(id) {
    const dropdown = document.getElementById(`herramienta-dropdown-${id}`);
    dropdown.style.display = 'none';
}

function seleccionarHerramienta(id, idUnidad, qrCode, tipo, marca, modelo) {
    const searchInput = document.getElementById(`herramienta-search-${id}`);
    const hiddenInput = document.getElementById(`herramienta-hidden-${id}`);
    const dropdown = document.getElementById(`herramienta-dropdown-${id}`);
    const qrDisplay = document.getElementById(`qr-display-${id}`);
    const infoDiv = document.getElementById(`info-herramienta-${id}`);
    const condicionSelect = document.querySelector(`#herramienta-${id} .condicion-select`);
    
    // Actualizar inputs
    searchInput.value = `${tipo} - ${marca} ${modelo}`;
    hiddenInput.value = idUnidad;
    
    // Actualizar nombre del select de condición
    condicionSelect.name = `condicion_retiro[${idUnidad}]`;
    
    // Mostrar QR
    qrDisplay.innerHTML = `<span class="badge bg-primary">${qrCode}</span>`;
    
    // Mostrar información
    infoDiv.style.display = 'block';
    infoDiv.querySelector('.tipo-herramienta').textContent = tipo;
    infoDiv.querySelector('.marca-herramienta').textContent = marca;
    infoDiv.querySelector('.modelo-herramienta').textContent = modelo;
    
    // Ocultar dropdown
    dropdown.style.display = 'none';
    
    actualizarResumen();
}

function actualizarResumen() {
    let totalHerramientas = 0;
    let excelente = 0, buena = 0, regular = 0, mala = 0;
    
    document.querySelectorAll('.herramienta-row').forEach(row => {
        const hiddenInput = row.querySelector('input[name="unidades_seleccionadas[]"]');
        const condicionSelect = row.querySelector('.condicion-select');
        
        if (hiddenInput && hiddenInput.value) {
            totalHerramientas++;
            
            if (condicionSelect && condicionSelect.value) {
                switch(condicionSelect.value) {
                    case 'excelente': excelente++; break;
                    case 'buena': buena++; break;
                    case 'regular': regular++; break;
                    case 'mala': mala++; break;
                }
            }
        }
    });
    
    document.getElementById('total-herramientas').textContent = totalHerramientas;
    document.getElementById('condicion-excelente').textContent = excelente;
    document.getElementById('condicion-buena').textContent = buena;
    document.getElementById('condicion-regular').textContent = regular;
    document.getElementById('condicion-mala').textContent = mala;
}

// Event listeners
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('condicion-select')) {
        actualizarResumen();
    }
});

// Función para mostrar modal de devolución
function mostrarDevolucion(idUnidad, idPrestamo, qrCode, marca, modelo) {
    if (!idPrestamo) {
        alert('No se pudo obtener el ID del préstamo');
        return;
    }
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalDevolucion'));
    
    // Llenar datos del modal
    document.getElementById('devolucion_id_prestamo').value = idPrestamo;
    document.getElementById('devolucion_id_unidad').value = idUnidad;
    document.getElementById('devolucion_herramienta_info').innerHTML = `
        <strong>${marca} ${modelo}</strong><br>
        <span class="badge bg-secondary">QR: ${qrCode}</span>
    `;
    
    modal.show();
}

// Función para guardar devolución
function guardarDevolucion() {
    const form = document.getElementById('formDevolucion');
    const formData = new FormData(form);
    
    // Validar campos
    if (!formData.get('condicion_devolucion')) {
        alert('Debe seleccionar la condición de devolución');
        return;
    }
    
    const idUnidad = formData.get('id_unidad');
    
    // Mostrar loading
    const btnGuardar = document.getElementById('btnGuardarDevolucion');
    const originalText = btnGuardar.innerHTML;
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
    
    // Enviar datos
    fetch('ajax_devolucion_rapida.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar herramientasData con el nuevo estado
            const herramienta = herramientasData.find(h => h.id_unidad == idUnidad);
            if (herramienta) {
                herramienta.estado_actual = data.nuevo_estado;
                herramienta.id_prestamo = null;
                herramienta.empleado_prestamo = null;
                herramienta.obra_prestamo = null;
                herramienta.condicion_prestamo = null;
            }
            
            alert('Devolución registrada exitosamente');
            // Cerrar modal
            bootstrap.Modal.getInstance(document.getElementById('modalDevolucion')).hide();
            // Limpiar formulario
            form.reset();
            // Actualizar búsqueda para mostrar el nuevo estado
            buscarHerramienta();
        } else {
            alert('Error: ' + (data.error || 'No se pudo registrar la devolución'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al registrar la devolución');
    })
    .finally(() => {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = originalText;
    });
}

// Búsqueda con Enter
document.getElementById('qr-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        buscarHerramienta();
    }
});

// Validación del formulario
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

// Ocultar mensaje de debug después de 10 segundos
setTimeout(function() {
    const debugInfo = document.getElementById('debug-info');
    if (debugInfo) {
        debugInfo.style.display = 'none';
    }
}, 10000);
</script>

<style>
.herramienta-row {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.herramienta-row:hover {
    background-color: #e9ecef;
}

.alert-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.sticky-top {
    position: sticky;
    top: 20px;
    z-index: 1020;
}

/* Estilos para el buscador de herramientas */
.herramienta-search-container {
    position: relative;
}

.herramienta-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
    display: none;
}

.herramienta-list {
    padding: 0;
}

.herramienta-option {
    padding: 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.15s ease-in-out;
}

.herramienta-option:hover {
    background-color: #f8f9fa;
}

.herramienta-option:last-child {
    border-bottom: none;
}

.herramienta-name {
    font-weight: 500;
    color: #212529;
    margin-bottom: 0.25rem;
}

.herramienta-info {
    font-size: 0.875rem;
}

.no-results {
    padding: 1rem;
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

/* QR Reader */
#qr-reader {
    margin: 0 auto;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .herramienta-dropdown {
        max-height: 200px;
    }
    
    .herramienta-option {
        padding: 0.5rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>

<?php endif; ?>

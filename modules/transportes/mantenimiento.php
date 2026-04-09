<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$id_transporte = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_transporte <= 0) {
    redirect(SITE_URL . '/modules/transportes/list.php');
}

$page_title = 'Mantenimiento de Transporte';

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success_message = '';

// Criterios de próximo service (en días y km)
define('DIAS_PROXIMO_SERVICE', 180); // 6 meses
define('KM_PROXIMO_SERVICE', 10000); // 10.000 km
define('DIAS_INSPECCION', 90);       // 3 meses

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad invalido';
    } else {
        $tipo_evento = sanitize_input($_POST['tipo_evento'] ?? '');
        $fecha_evento = sanitize_input($_POST['fecha_evento'] ?? '');
        $kilometraje = isset($_POST['kilometraje']) && $_POST['kilometraje'] !== '' ? (int)$_POST['kilometraje'] : null;
        $proveedor_taller = sanitize_input($_POST['proveedor_taller'] ?? '');
        $descripcion_problema = sanitize_input($_POST['descripcion_problema'] ?? '');
        $trabajo_realizado = sanitize_input($_POST['trabajo_realizado'] ?? '');
        $observaciones = sanitize_input($_POST['observaciones'] ?? '');

        $costo_mano_obra = isset($_POST['costo_mano_obra']) && $_POST['costo_mano_obra'] !== '' ? (float)$_POST['costo_mano_obra'] : 0.00;
        $costo_repuestos = isset($_POST['costo_repuestos']) && $_POST['costo_repuestos'] !== '' ? (float)$_POST['costo_repuestos'] : 0.00;
        $costo_total = round($costo_mano_obra + $costo_repuestos, 2);

        $tipos_validos = ['preventivo', 'correctivo', 'service', 'inspeccion', 'otro'];

        if (!in_array($tipo_evento, $tipos_validos, true)) {
            $errors[] = 'Tipo de evento invalido';
        }
        if (empty($fecha_evento)) {
            $errors[] = 'La fecha del evento es obligatoria';
        }
        if ($kilometraje !== null && $kilometraje < 0) {
            $errors[] = 'El kilometraje no puede ser negativo';
        }
        if ($costo_mano_obra < 0 || $costo_repuestos < 0) {
            $errors[] = 'Los costos no pueden ser negativos';
        }

        if (empty($errors)) {
            try {
                $insert_query = "INSERT INTO transportes_mantenimientos (
                    id_transporte, tipo_evento, fecha_evento, kilometraje, proveedor_taller,
                    descripcion_problema, trabajo_realizado, costo_mano_obra, costo_repuestos,
                    costo_total, observaciones, id_usuario_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $insert_stmt = $conn->prepare($insert_query);
                $ok = $insert_stmt->execute([
                    $id_transporte,
                    $tipo_evento,
                    $fecha_evento,
                    $kilometraje,
                    $proveedor_taller !== '' ? $proveedor_taller : null,
                    $descripcion_problema !== '' ? $descripcion_problema : null,
                    $trabajo_realizado !== '' ? $trabajo_realizado : null,
                    $costo_mano_obra,
                    $costo_repuestos,
                    $costo_total,
                    $observaciones !== '' ? $observaciones : null,
                    (int)$_SESSION['user_id']
                ]);

                if ($ok) {
                    // Calcular próximo service según tipo de evento
                    $proximo_km = null;
                    $proximo_fecha = null;

                    if (in_array($tipo_evento, ['service', 'preventivo'])) {
                        if ($kilometraje !== null) {
                            $proximo_km = $kilometraje + KM_PROXIMO_SERVICE;
                        }
                        $proximo_fecha = date('Y-m-d', strtotime('+' . DIAS_PROXIMO_SERVICE . ' days', strtotime($fecha_evento)));
                    } elseif ($tipo_evento === 'inspeccion') {
                        if ($kilometraje !== null) {
                            $proximo_km = $kilometraje + KM_PROXIMO_SERVICE;
                        }
                        $proximo_fecha = date('Y-m-d', strtotime('+' . DIAS_INSPECCION . ' days', strtotime($fecha_evento)));
                    }

                    // Actualizar tabla transportes con últimos y próximos servicios
                    try {
                        $update_transporte = "UPDATE transportes SET 
                            ultimo_service_km = ?, 
                            ultimo_service_fecha = ?";
                        $params_update = [$kilometraje, $fecha_evento];

                        if ($proximo_km !== null || $proximo_fecha !== null) {
                            $update_transporte .= ", proximo_service_km = ?, proximo_service_fecha = ?";
                            $params_update[] = $proximo_km;
                            $params_update[] = $proximo_fecha;
                        }

                        $update_transporte .= " WHERE id_transporte = ?";
                        $params_update[] = $id_transporte;

                        $stmt_update = $conn->prepare($update_transporte);
                        $stmt_update->execute($params_update);

                        $success_message = 'Mantenimiento registrado exitosamente';
                        $_POST = [];
                    } catch (Exception $e) {
                        error_log('Error al actualizar transportes con próximo service: ' . $e->getMessage());
                        $success_message = 'Mantenimiento registrado, pero hubo un error al calcular próximo service';
                    }
                } else {
                    $errors[] = 'No se pudo registrar el mantenimiento';
                }
            } catch (Exception $e) {
                error_log('Error al registrar mantenimiento de transporte: ' . $e->getMessage());
                $errors[] = 'Error interno del servidor';
            }
        }
    }
}

try {
    $stmt_transporte = $conn->prepare("SELECT t.*, u.nombre, u.apellido
        FROM transportes t
        LEFT JOIN usuarios u ON t.id_encargado = u.id_usuario
        WHERE t.id_transporte = ?");
    $stmt_transporte->execute([$id_transporte]);
    $transporte = $stmt_transporte->fetch();

    if (!$transporte) {
        redirect(SITE_URL . '/modules/transportes/list.php');
    }

    $stmt_historial = $conn->prepare("SELECT tm.*, ur.nombre AS nombre_usuario, ur.apellido AS apellido_usuario
        FROM transportes_mantenimientos tm
        INNER JOIN usuarios ur ON ur.id_usuario = tm.id_usuario_registro
        WHERE tm.id_transporte = ?
        ORDER BY tm.fecha_evento DESC, tm.fecha_creacion DESC");
    $stmt_historial->execute([$id_transporte]);
    $historial = $stmt_historial->fetchAll();

    $stmt_stats = $conn->prepare("SELECT
        COUNT(*) AS total_eventos,
        SUM(costo_total) AS costo_total_acumulado
        FROM transportes_mantenimientos
        WHERE id_transporte = ?");
    $stmt_stats->execute([$id_transporte]);
    $stats = $stmt_stats->fetch();

    if (!$stats) {
        $stats = ['total_eventos' => 0, 'costo_total_acumulado' => 0];
    }
} catch (Exception $e) {
    error_log('Error al cargar datos de mantenimiento de transporte: ' . $e->getMessage());
    $errors[] = 'No se pudieron cargar los datos del transporte';
    $transporte = null;
    $historial = [];
    $stats = ['total_eventos' => 0, 'finalizados' => 0, 'costo_total_acumulado' => 0];
}

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="bi bi-tools"></i> Mantenimiento de Transporte
        </h1>
        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
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
</div>
<?php endif; ?>

<?php if ($transporte): ?>
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Vehiculo</small>
                <strong><?php echo htmlspecialchars($transporte['marca'] . ' ' . $transporte['modelo']); ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Matricula</small>
                <strong><?php echo htmlspecialchars($transporte['matricula']); ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Km Actual</small>
                <strong><?php echo (int)$transporte['kilometraje']; ?> km</strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Eventos</small>
                <strong><?php echo (int)$stats['total_eventos']; ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Costo total</small>
                <strong>$<?php echo number_format((float)$stats['costo_total_acumulado'], 2, ',', '.'); ?></strong>
            </div>
        </div>

        <?php if ($transporte['proximo_service_fecha'] || $transporte['proximo_service_km']): ?>
        <hr class="my-2" />
        <div class="row">
            <?php 
            $hoy = new DateTime(date('Y-m-d'));
            $proximo_fecha = $transporte['proximo_service_fecha'] ? new DateTime($transporte['proximo_service_fecha']) : null;
            $dias_faltantes = $proximo_fecha ? $proximo_fecha->diff($hoy)->days : null;
            $vencido = $proximo_fecha && $proximo_fecha < $hoy;
            $pronto = !$vencido && $proximo_fecha && $dias_faltantes <= 30;
            ?>
            <div class="col-md-6">
                <small class="text-muted d-block"><i class="bi bi-calendar"></i> Próximo service</small>
                <strong>
                    <?php echo $transporte['proximo_service_fecha'] ? date('d/m/Y', strtotime($transporte['proximo_service_fecha'])) : 'No registrado'; ?>
                    <?php if ($vencido): ?>
                    <span class="badge bg-danger ms-2">VENCIDO</span>
                    <?php elseif ($pronto): ?>
                    <span class="badge bg-warning text-dark ms-2">PRÓXIMO (<?php echo $dias_faltantes; ?> días)</span>
                    <?php endif; ?>
                </strong>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block"><i class="bi bi-speedometer"></i> Próximo km</small>
                <strong>
                    <?php 
                    if ($transporte['proximo_service_km']):
                        $km_faltantes = $transporte['proximo_service_km'] - (int)$transporte['kilometraje'];
                        $km_vencido = $km_faltantes <= 0;
                    ?>
                        <?php echo (int)$transporte['proximo_service_km']; ?> km
                        <?php if ($km_vencido): ?>
                        <span class="badge bg-danger ms-2">VENCIDO</span>
                        <?php elseif ($km_faltantes <= 1000): ?>
                        <span class="badge bg-warning text-dark ms-2">PRÓXIMO (<?php echo $km_faltantes; ?> km)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        No registrado
                    <?php endif; ?>
                </strong>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-plus-circle"></i> Registrar Mantenimiento
                <small class="text-muted d-block mt-1">Service, preventivo, correctivo o inspección</small>
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="mb-3">
                        <label for="tipo_evento" class="form-label">Tipo de evento *</label>
                        <select class="form-select" id="tipo_evento" name="tipo_evento" required>
                            <option value="">Seleccionar</option>
                            <option value="service" <?php echo (($_POST['tipo_evento'] ?? '') === 'service') ? 'selected' : ''; ?>>Service</option>
                            <option value="preventivo" <?php echo (($_POST['tipo_evento'] ?? '') === 'preventivo') ? 'selected' : ''; ?>>Preventivo</option>
                            <option value="correctivo" <?php echo (($_POST['tipo_evento'] ?? '') === 'correctivo') ? 'selected' : ''; ?>>Correctivo</option>
                            <option value="inspeccion" <?php echo (($_POST['tipo_evento'] ?? '') === 'inspeccion') ? 'selected' : ''; ?>>Inspeccion</option>
                            <option value="otro" <?php echo (($_POST['tipo_evento'] ?? '') === 'otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_evento" class="form-label">Fecha *</label>
                            <input type="date" class="form-control" id="fecha_evento" name="fecha_evento"
                                   value="<?php echo htmlspecialchars($_POST['fecha_evento'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="kilometraje" class="form-label">Kilometraje</label>
                            <input type="number" min="0" class="form-control" id="kilometraje" name="kilometraje"
                                   value="<?php echo htmlspecialchars($_POST['kilometraje'] ?? ''); ?>" placeholder="Ej: 85000">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="proveedor_taller" class="form-label">Proveedor/Taller</label>
                        <input type="text" maxlength="150" class="form-control" id="proveedor_taller" name="proveedor_taller"
                               value="<?php echo htmlspecialchars($_POST['proveedor_taller'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="descripcion_problema" class="form-label">Descripcion del problema</label>
                        <textarea class="form-control" id="descripcion_problema" name="descripcion_problema" rows="2"><?php echo htmlspecialchars($_POST['descripcion_problema'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="trabajo_realizado" class="form-label">Trabajo realizado</label>
                        <textarea class="form-control" id="trabajo_realizado" name="trabajo_realizado" rows="2"><?php echo htmlspecialchars($_POST['trabajo_realizado'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="costo_mano_obra" class="form-label">Costo mano de obra</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="costo_mano_obra" name="costo_mano_obra"
                                   value="<?php echo htmlspecialchars($_POST['costo_mano_obra'] ?? '0.00'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="costo_repuestos" class="form-label">Costo repuestos</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="costo_repuestos" name="costo_repuestos"
                                   value="<?php echo htmlspecialchars($_POST['costo_repuestos'] ?? '0.00'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Guardar registro
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Historial
            </div>
            <div class="card-body">
                <?php if (!empty($historial)): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Km</th>
                                <th>Costo</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $item): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($item['fecha_evento'])); ?></td>
                                <td><?php echo ucfirst($item['tipo_evento']); ?></td>
                                <td><?php echo $item['kilometraje'] !== null ? (int)$item['kilometraje'] : '-'; ?></td>
                                <td>$<?php echo number_format((float)$item['costo_total'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($item['nombre_usuario'] . ' ' . $item['apellido_usuario']); ?></td>
                            </tr>
                            <?php if (!empty($item['descripcion_problema']) || !empty($item['trabajo_realizado']) || !empty($item['observaciones'])): ?>
                            <tr>
                                <td colspan="6">
                                    <?php if (!empty($item['descripcion_problema'])): ?>
                                        <small class="d-block"><strong>Problema:</strong> <?php echo htmlspecialchars($item['descripcion_problema']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($item['trabajo_realizado'])): ?>
                                        <small class="d-block"><strong>Trabajo:</strong> <?php echo htmlspecialchars($item['trabajo_realizado']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($item['observaciones'])): ?>
                                        <small class="d-block"><strong>Obs:</strong> <?php echo htmlspecialchars($item['observaciones']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-tools" style="font-size:2rem;"></i>
                    <p class="mb-0 mt-2">Sin mantenimientos registrados para este vehiculo.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>

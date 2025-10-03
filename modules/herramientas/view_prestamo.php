<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Permisos: Admin y Responsable pueden ver préstamos
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Detalles de Préstamo';

$database = new Database();
$conn = $database->getConnection();

$prestamo_id = (int)($_GET['id'] ?? 0);

if ($prestamo_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/prestamos.php');
}

try {
    // Obtener datos del préstamo principal
    $query_prestamo = "SELECT p.*, 
                              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido, emp.email as empleado_email,
                              obra.nombre_obra, obra.direccion as obra_direccion, obra.localidad as obra_localidad,
                              aut.nombre as autorizado_nombre, aut.apellido as autorizado_apellido
                       FROM prestamos p 
                       JOIN usuarios emp ON p.id_empleado = emp.id_usuario
                       JOIN obras obra ON p.id_obra = obra.id_obra
                       JOIN usuarios aut ON p.id_autorizado_por = aut.id_usuario
                       WHERE p.id_prestamo = ?";
    
    $stmt_prestamo = $conn->prepare($query_prestamo);
    $stmt_prestamo->execute([$prestamo_id]);
    $prestamo = $stmt_prestamo->fetch();

    if (!$prestamo) {
        redirect(SITE_URL . '/modules/herramientas/prestamos.php');
    }

    // Obtener detalles de las herramientas prestadas
    $query_detalle = "SELECT dp.*, hu.qr_code, hu.estado_actual as estado_unidad_actual, h.marca, h.modelo
                      FROM detalle_prestamo dp
                      JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
                      JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                      WHERE dp.id_prestamo = ?";
    
    $stmt_detalle = $conn->prepare($query_detalle);
    $stmt_detalle->execute([$prestamo_id]);
    $herramientas_prestadas = $stmt_detalle->fetchAll();

    // Verificar si ya hay una devolución registrada para este préstamo
    $query_devolucion = "SELECT id_devolucion FROM devoluciones WHERE id_prestamo = ?";
    $stmt_devolucion = $conn->prepare($query_devolucion);
    $stmt_devolucion->execute([$prestamo_id]);
    $devolucion_existente = $stmt_devolucion->fetch();

} catch (Exception $e) {
    error_log("Error al obtener detalles de préstamo: " . $e->getMessage());
    redirect(SITE_URL . '/modules/herramientas/prestamos.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-box-arrow-up"></i> Detalles del Préstamo #<?php echo $prestamo['id_prestamo']; ?>
            </h1>
            <div>
                <?php if (!$devolucion_existente): ?>
                <a href="create_devolucion.php?prestamo_id=<?php echo $prestamo['id_prestamo']; ?>" class="btn btn-success me-2">
                    <i class="bi bi-box-arrow-down"></i> Registrar Devolución
                </a>
                <?php else: ?>
                <a href="view_devolucion.php?id=<?php echo $devolucion_existente['id_devolucion']; ?>" class="btn btn-outline-success me-2">
                    <i class="bi bi-check-circle"></i> Ver Devolución Registrada
                </a>
                <?php endif; ?>
                <a href="prestamos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Préstamos
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información principal del préstamo -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información General del Préstamo
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Empleado que Retira</h6>
                        <p class="mb-3">
                            <strong><?php echo htmlspecialchars($prestamo['empleado_nombre'] . ' ' . $prestamo['empleado_apellido']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($prestamo['empleado_email']); ?></small>
                        </p>
                        
                        <h6 class="text-muted">Obra Destino</h6>
                        <p class="mb-3">
                            <strong><?php echo htmlspecialchars($prestamo['nombre_obra']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($prestamo['obra_direccion'] . ', ' . $prestamo['obra_localidad']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Fecha y Hora de Retiro</h6>
                        <p class="mb-3 fs-5"><?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_retiro'])); ?></p>
                        
                        <h6 class="text-muted">Autorizado Por</h6>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($prestamo['autorizado_nombre'] . ' ' . $prestamo['autorizado_apellido']); ?>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($prestamo['observaciones_retiro'])): ?>
                <h6 class="text-muted">Observaciones de Retiro</h6>
                <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($prestamo['observaciones_retiro'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resumen de estado -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clipboard-check"></i> Estado del Préstamo
            </div>
            <div class="card-body text-center">
                <?php if ($devolucion_existente): ?>
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="text-success mt-2">Completado</h5>
                    <p class="text-muted">Todas las herramientas han sido devueltas.</p>
                    <a href="view_devolucion.php?id=<?php echo $devolucion_existente['id_devolucion']; ?>" class="btn btn-sm btn-outline-success">
                        Ver Detalles de Devolución
                    </a>
                <?php else: ?>
                    <i class="bi bi-hourglass-split text-warning" style="font-size: 3rem;"></i>
                    <h5 class="text-warning mt-2">Pendiente de Devolución</h5>
                    <p class="text-muted">Las herramientas aún no han sido devueltas.</p>
                    <a href="create_devolucion.php?prestamo_id=<?php echo $prestamo['id_prestamo']; ?>" class="btn btn-sm btn-primary">
                        Registrar Devolución
                    </a>
                <?php endif; ?>
                
                <hr>
                
                <h6 class="text-muted">Herramientas Prestadas</h6>
                <h3 class="text-primary"><?php echo count($herramientas_prestadas); ?></h3>
                <small class="text-muted">unidades</small>
            </div>
        </div>
    </div>
</div>

<!-- Detalles de Herramientas Prestadas -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-tools"></i> Herramientas Prestadas
            </div>
            <div class="card-body">
                <?php if (!empty($herramientas_prestadas)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Herramienta</th>
                                <th>Código QR</th>
                                <th>Condición al Retiro</th>
                                <th>Estado Actual de Unidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($herramientas_prestadas as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['marca'] . ' ' . $item['modelo']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['qr_code']); ?></strong>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($item['qr_code']); ?>&amp;size=50x50" alt="QR Code" class="qr-code ms-2" style="width:50px;height:50px;">
                                </td>
                                <td>
                                    <?php
                                    $condicion_class = '';
                                    switch ($item['condicion_retiro']) {
                                        case 'excelente': $condicion_class = 'text-success'; break;
                                        case 'buena': $condicion_class = 'text-primary'; break;
                                        case 'regular': $condicion_class = 'text-warning'; break;
                                        case 'mala': $condicion_class = 'text-danger'; break;
                                    }
                                    ?>
                                    <span class="fw-bold <?php echo $condicion_class; ?>">
                                        <?php echo ucfirst($item['condicion_retiro']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    switch ($item['estado_unidad_actual']) {
                                        case 'disponible': $estado_class = 'bg-success'; break;
                                        case 'prestada': $estado_class = 'bg-warning text-dark'; break;
                                        case 'mantenimiento': $estado_class = 'bg-info'; break;
                                        case 'perdida': $estado_class = 'bg-danger'; break;
                                        case 'dañada': $estado_class = 'bg-danger'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $estado_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['estado_unidad_actual'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="view.php?id=<?php echo $item['id_herramienta']; ?>" 
                                           class="btn btn-outline-info" title="Ver tipo de herramienta">
                                            <i class="bi bi-tools"></i>
                                        </a>
                                        <a href="update_unit_status.php?id=<?php echo $item['id_unidad']; ?>" 
                                           class="btn btn-outline-primary" title="Actualizar estado de unidad">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-tools text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No se encontraron herramientas asociadas a este préstamo.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

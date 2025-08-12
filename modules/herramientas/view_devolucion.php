<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Permisos: Admin y Responsable pueden ver devoluciones
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Detalles de Devolución';

$database = new Database();
$conn = $database->getConnection();

$devolucion_id = (int)($_GET['id'] ?? 0);

if ($devolucion_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/devoluciones.php');
}

try {
    // Obtener datos de la devolución principal
    $query_devolucion = "SELECT d.*, 
                                p.id_empleado, emp.nombre as empleado_nombre, emp.apellido as empleado_apellido,
                                obra.nombre_obra, obra.direccion as obra_direccion, obra.localidad as obra_localidad,
                                rec.nombre as recibido_nombre, rec.apellido as recibido_apellido,
                                p.fecha_retiro
                         FROM devoluciones d 
                         JOIN prestamos p ON d.id_prestamo = p.id_prestamo
                         JOIN usuarios emp ON p.id_empleado = emp.id_usuario
                         JOIN obras obra ON p.id_obra = obra.id_obra
                         JOIN usuarios rec ON d.id_recibido_por = rec.id_usuario
                         WHERE d.id_devolucion = ?";
    
    $stmt_devolucion = $conn->prepare($query_devolucion);
    $stmt_devolucion->execute([$devolucion_id]);
    $devolucion = $stmt_devolucion->fetch();

    if (!$devolucion) {
        redirect(SITE_URL . '/modules/herramientas/devoluciones.php');
    }

    // Obtener detalles de las herramientas devueltas
    $query_detalle = "SELECT dd.*, hu.qr_code, hu.estado_actual as estado_unidad_actual, h.marca, h.modelo
                      FROM detalle_devolucion dd
                      JOIN herramientas_unidades hu ON dd.id_unidad = hu.id_unidad
                      JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                      WHERE dd.id_devolucion = ?";
    
    $stmt_detalle = $conn->prepare($query_detalle);
    $stmt_detalle->execute([$devolucion_id]);
    $herramientas_devueltas = $stmt_detalle->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener detalles de devolución: " . $e->getMessage());
    redirect(SITE_URL . '/modules/herramientas/devoluciones.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-box-arrow-down"></i> Detalles de Devolución #<?php echo $devolucion['id_devolucion']; ?>
            </h1>
            <div>
                <a href="view_prestamo.php?id=<?php echo $devolucion['id_prestamo']; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-box-arrow-up"></i> Ver Préstamo Asociado
                </a>
                <a href="devoluciones.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Devoluciones
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información principal de la devolución -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información General de la Devolución
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Préstamo Asociado</h6>
                        <p class="mb-3">
                            <a href="view_prestamo.php?id=<?php echo $devolucion['id_prestamo']; ?>">
                                <strong>#<?php echo $devolucion['id_prestamo']; ?></strong>
                            </a>
                            <br>
                            <small class="text-muted">Retirado por: <?php echo htmlspecialchars($devolucion['empleado_nombre'] . ' ' . $devolucion['empleado_apellido']); ?></small>
                        </p>
                        
                        <h6 class="text-muted">Obra de Origen</h6>
                        <p class="mb-3">
                            <strong><?php echo htmlspecialchars($devolucion['nombre_obra']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($devolucion['obra_direccion'] . ', ' . $devolucion['obra_localidad']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Fecha y Hora de Devolución</h6>
                        <p class="mb-3 fs-5"><?php echo date('d/m/Y H:i', strtotime($devolucion['fecha_devolucion'])); ?></p>
                        
                        <h6 class="text-muted">Recibido Por</h6>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($devolucion['recibido_nombre'] . ' ' . $devolucion['recibido_apellido']); ?>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($devolucion['observaciones_devolucion'])): ?>
                <h6 class="text-muted">Observaciones Generales de Devolución</h6>
                <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($devolucion['observaciones_devolucion'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resumen de estado -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-check-circle"></i> Resumen
            </div>
            <div class="card-body text-center">
                <i class="bi bi-box-arrow-down text-success" style="font-size: 3rem;"></i>
                <h5 class="text-success mt-2">Devolución Registrada</h5>
                <p class="text-muted">Este registro documenta la devolución de herramientas.</p>
                
                <hr>
                
                <h6 class="text-muted">Herramientas Devueltas</h6>
                <h3 class="text-primary"><?php echo count($herramientas_devueltas); ?></h3>
                <small class="text-muted">unidades</small>
            </div>
        </div>
    </div>
</div>

<!-- Detalles de Herramientas Devueltas -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-tools"></i> Herramientas Devueltas
            </div>
            <div class="card-body">
                <?php if (!empty($herramientas_devueltas)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Herramienta</th>
                                <th>Código QR</th>
                                <th>Condición al Devolver</th>
                                <th>Estado Actual de Unidad</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($herramientas_devueltas as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['marca'] . ' ' . $item['modelo']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['qr_code']); ?></strong>
                                    <img src="/placeholder.svg?height=40&width=40" alt="QR Code" class="qr-code ms-2" style="max-width: 40px;">
                                </td>
                                <td>
                                    <?php
                                    $condicion_class = '';
                                    switch ($item['condicion_devolucion']) {
                                        case 'excelente': $condicion_class = 'text-success'; break;
                                        case 'buena': $condicion_class = 'text-primary'; break;
                                        case 'regular': $condicion_class = 'text-warning'; break;
                                        case 'mala': $condicion_class = 'text-danger'; break;
                                        case 'dañada': $condicion_class = 'text-danger'; break;
                                        case 'perdida': $condicion_class = 'text-dark'; break;
                                    }
                                    ?>
                                    <span class="fw-bold <?php echo $condicion_class; ?>">
                                        <?php echo ucfirst($item['condicion_devolucion']); ?>
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
                                    <?php echo !empty($item['observaciones_devolucion']) ? htmlspecialchars($item['observaciones_devolucion']) : '-'; ?>
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
                    <p class="text-muted mt-2">No se encontraron herramientas asociadas a esta devolución.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

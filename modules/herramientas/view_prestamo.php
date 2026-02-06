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

    // Obtener historial de extensiones de fecha
    $query_historial = "SELECT h.*, u.nombre, u.apellido 
                        FROM historial_extensiones_prestamo h
                        JOIN usuarios u ON h.id_usuario_modifico = u.id_usuario
                        WHERE h.id_prestamo = ?
                        ORDER BY h.fecha_modificacion DESC";
    $stmt_historial = $conn->prepare($query_historial);
    $stmt_historial->execute([$prestamo_id]);
    $historial_extensiones = $stmt_historial->fetchAll();

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
                        
                        <h6 class="text-muted">Fecha Devolución Programada</h6>
                        <div class="mb-3">
                            <?php if (!empty($prestamo['fecha_devolucion_programada'])): ?>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="date" 
                                           class="form-control form-control-sm" 
                                           id="fecha_devolucion_programada" 
                                           value="<?php echo $prestamo['fecha_devolucion_programada']; ?>" 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           style="max-width: 200px;"
                                           disabled>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExtenderFecha" onclick="toggleEditarFecha()">
                                        <i class="bi bi-calendar-plus"></i> Extender fecha
                                    </button>
                                </div>
                                <div id="motivoContainer" style="display: none;">
                                    <label for="motivo_extension" class="form-label"><small>Motivo de la extensión:</small></label>
                                    <textarea class="form-control form-control-sm" 
                                              id="motivo_extension" 
                                              rows="2" 
                                              placeholder="Ingrese el motivo de la extensión..."
                                              disabled></textarea>
                                </div>
                            <?php else: ?>
                                <p class="text-muted"><em>No definida</em></p>
                            <?php endif; ?>
                        </div>
                        
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
                
                <?php if (!empty($historial_extensiones)): ?>
                <hr>
                <h6 class="text-muted"><i class="bi bi-clock-history"></i> Historial de Extensiones de Fecha</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha Anterior</th>
                                <th>Fecha Nueva</th>
                                <th>Motivo</th>
                                <th>Extendido Por</th>
                                <th>Fecha de Creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial_extensiones as $extension): ?>
                            <tr>
                                <td>
                                    <?php echo $extension['fecha_anterior'] ? date('d/m/Y', strtotime($extension['fecha_anterior'])) : '<em class="text-muted">No definida</em>'; ?>
                                </td>
                                <td>
                                    <strong class="text-primary"><?php echo date('d/m/Y', strtotime($extension['fecha_nueva'])); ?></strong>
                                </td>
                                <td>
                                    <?php echo !empty($extension['motivo']) ? nl2br(htmlspecialchars($extension['motivo'])) : '<em class="text-muted">Sin motivo</em>'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($extension['nombre'] . ' ' . $extension['apellido']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($extension['fecha_modificacion'])); ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                                <th>Condición al Retirar</th>
                                <th>Estado Actual de Unidad</th>
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
                                    <span class="badge <?php echo get_clase_condicion($item['condicion_retiro']); ?>">
                                        <i class="<?php echo get_icono_condicion($item['condicion_retiro']); ?>"></i>
                                        <?php echo get_nombre_condicion($item['condicion_retiro']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo get_clase_estado($item['estado_unidad_actual']); ?>">
                                        <i class="<?php echo get_icono_estado($item['estado_unidad_actual']); ?>"></i>
                                        <?php echo get_nombre_estado($item['estado_unidad_actual']); ?>
                                    </span>
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

<script>
let modoEdicion = false;

function toggleEditarFecha() {
    const inputFecha = document.getElementById('fecha_devolucion_programada');
    const motivoContainer = document.getElementById('motivoContainer');
    const motivoTextarea = document.getElementById('motivo_extension');
    const btnExtender = document.getElementById('btnExtenderFecha');
    
    if (!modoEdicion) {
        // Habilitar edición
        inputFecha.disabled = false;
        motivoContainer.style.display = 'block';
        motivoTextarea.disabled = false;
        inputFecha.focus();
        btnExtender.innerHTML = '<i class="bi bi-check-circle"></i> Guardar';
        btnExtender.classList.remove('btn-outline-primary');
        btnExtender.classList.add('btn-success');
        modoEdicion = true;
    } else {
        // Guardar cambios
        guardarFechaDevolucion();
    }
}

function guardarFechaDevolucion() {
    const inputFecha = document.getElementById('fecha_devolucion_programada');
    const motivoContainer = document.getElementById('motivoContainer');
    const motivoTextarea = document.getElementById('motivo_extension');
    const btnExtender = document.getElementById('btnExtenderFecha');
    const nuevaFecha = inputFecha.value;
    const motivo = motivoTextarea.value.trim();
    const fechaActual = new Date().toISOString().split('T')[0];
    
    // Validar que la fecha no sea anterior a la actual
    if (nuevaFecha < fechaActual) {
        alert('⚠️ La fecha de devolución no puede ser anterior a la fecha actual.');
        return;
    }
    
    // Validar que se haya ingresado un motivo
    if (motivo === '') {
        alert('⚠️ Debe ingresar un motivo para la extensión de fecha.');
        motivoTextarea.focus();
        return;
    }
    
    // Confirmar el guardado
    if (!confirm('¿Está seguro de actualizar la fecha de devolución programada?')) {
        // Cancelar y volver al estado anterior
        inputFecha.disabled = true;
        motivoContainer.style.display = 'none';
        motivoTextarea.disabled = true;
        motivoTextarea.value = '';
        btnExtender.innerHTML = '<i class="bi bi-calendar-plus"></i> Extender fecha';
        btnExtender.classList.remove('btn-success');
        btnExtender.classList.add('btn-outline-primary');
        modoEdicion = false;
        return;
    }
    
    // Deshabilitar botón mientras se guarda
    btnExtender.disabled = true;
    btnExtender.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
    
    // Enviar solicitud AJAX
    fetch('ajax_actualizar_fecha_devolucion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_prestamo=<?php echo $prestamo_id; ?>&fecha_devolucion_programada=${encodeURIComponent(nuevaFecha)}&motivo=${encodeURIComponent(motivo)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.getElementById('alert-container').appendChild(alertDiv);
            
            // Volver al modo visualización
            inputFecha.disabled = true;
            motivoContainer.style.display = 'none';
            motivoTextarea.disabled = true;
            motivoTextarea.value = '';
            btnExtender.innerHTML = '<i class="bi bi-calendar-plus"></i> Extender fecha';
            btnExtender.classList.remove('btn-success');
            btnExtender.classList.add('btn-outline-primary');
            btnExtender.disabled = false;
            modoEdicion = false;
            
            // Recargar página después de 2 segundos para mostrar el nuevo registro en el historial
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            // Mostrar mensaje de error
            alert('❌ Error: ' + data.message);
            btnExtender.innerHTML = '<i class="bi bi-check-circle"></i> Guardar';
            btnExtender.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al actualizar la fecha. Por favor, inténtelo nuevamente.');
        btnExtender.innerHTML = '<i class="bi bi-check-circle"></i> Guardar';
        btnExtender.disabled = false;
    });
}
</script>

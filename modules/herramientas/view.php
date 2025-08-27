<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Permisos: Admin y Responsable pueden gestionar, Empleado solo puede ver
$can_manage = has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]);

$page_title = 'Detalles de Herramienta';

$database = new Database();
$conn = $database->getConnection();

$herramienta_id = (int)($_GET['id'] ?? 0);

if ($herramienta_id <= 0) {
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

try {
    // Obtener datos del tipo de herramienta
    $query = "SELECT h.*, 
                     COUNT(CASE WHEN hu.estado_actual = 'disponible' THEN 1 ELSE NULL END) as unidades_disponibles
              FROM herramientas h 
              LEFT JOIN herramientas_unidades hu ON h.id_herramienta = hu.id_herramienta
              WHERE h.id_herramienta = ?
              GROUP BY h.id_herramienta";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$herramienta_id]);
    $herramienta = $stmt->fetch();

    if (!$herramienta) {
        redirect(SITE_URL . '/modules/herramientas/list.php');
    }

    // Obtener unidades individuales de esta herramienta
    $query_unidades = "SELECT * FROM herramientas_unidades WHERE id_herramienta = ? ORDER BY qr_code";
    $stmt_unidades = $conn->prepare($query_unidades);
    $stmt_unidades->execute([$herramienta_id]);
    $unidades = $stmt_unidades->fetchAll();

    // Obtener préstamos recientes de esta herramienta (cualquier unidad)
    $query_prestamos = "SELECT p.*, u.nombre, u.apellido, o.nombre_obra, dp.id_unidad, hu.qr_code
                        FROM prestamos p
                        JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
                        JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
                        JOIN usuarios u ON p.id_empleado = u.id_usuario
                        JOIN obras o ON p.id_obra = o.id_obra
                        WHERE hu.id_herramienta = ?
                        ORDER BY p.fecha_retiro DESC
                        LIMIT 5";
    
    $stmt_prestamos = $conn->prepare($query_prestamos);
    $stmt_prestamos->execute([$herramienta_id]);
    $prestamos_recientes = $stmt_prestamos->fetchAll();

} catch (Exception $e) {
    error_log("Error al obtener herramienta: " . $e->getMessage());
    redirect(SITE_URL . '/modules/herramientas/list.php');
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-tools"></i> <?php echo htmlspecialchars($herramienta['marca'] . ' ' . $herramienta['modelo']); ?>
            </h1>
            <div>
                <?php if ($can_manage): ?>
                <a href="add_unit.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-success me-2">
                    <i class="bi bi-plus-square"></i> Agregar Unidad
                </a>
                <a href="edit.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil"></i> Editar Tipo
                </a>
                <?php endif; ?>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información principal del tipo de herramienta -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información General
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Marca</h6>
                        <p class="mb-3 fs-5"><?php echo htmlspecialchars($herramienta['marca']); ?></p>
                        
                        <h6 class="text-muted">Modelo</h6>
                        <p class="mb-3 fs-5"><?php echo htmlspecialchars($herramienta['modelo']); ?></p>
                        
                        <h6 class="text-muted">Condición General</h6>
                        <p class="mb-3">
                            <?php
                            $condicion_class = '';
                            switch ($herramienta['condicion_general']) {
                                case 'excelente': $condicion_class = 'text-success'; break;
                                case 'buena': $condicion_class = 'text-primary'; break;
                                case 'regular': $condicion_class = 'text-warning'; break;
                                case 'mala': $condicion_class = 'text-danger'; break;
                            }
                            ?>
                            <span class="fw-bold <?php echo $condicion_class; ?> fs-6">
                                <?php echo ucfirst($herramienta['condicion_general']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted">Stock Total</h6>
                        <p class="mb-3">
                            <span class="fs-3 text-primary">
                                <?php echo number_format($herramienta['stock_total']); ?>
                            </span>
                            <small class="text-muted">unidades</small>
                        </p>
                        
                        <h6 class="text-muted">Unidades Disponibles</h6>
                        <p class="mb-3">
                            <span class="fs-3 <?php echo $herramienta['unidades_disponibles'] <= 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($herramienta['unidades_disponibles']); ?>
                            </span>
                            <small class="text-muted">para préstamo</small>
                        </p>
                    </div>
                </div>
                
                <h6 class="text-muted">Descripción</h6>
                <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($herramienta['descripcion'])); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas rápidas y acciones -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> Resumen de Unidades
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if ($herramienta['unidades_disponibles'] <= 0): ?>
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="text-danger mt-2">Sin Unidades Disponibles</h5>
                    <?php else: ?>
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="text-success mt-2">Unidades Disponibles</h5>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <h6 class="text-muted">Distribución de Unidades</h6>
                <ul class="list-group list-group-flush">
                    <?php
                    $estados_unidades = [
                        'disponible' => ['count' => 0, 'class' => 'success', 'icon' => 'bi-check-circle'],
                        'prestada' => ['count' => 0, 'class' => 'warning text-dark', 'icon' => 'bi-box-arrow-up'],
                        'mantenimiento' => ['count' => 0, 'class' => 'info', 'icon' => 'bi-tools'],
                        'perdida' => ['count' => 0, 'class' => 'danger', 'icon' => 'bi-question-circle'],
                        'dañada' => ['count' => 0, 'class' => 'danger', 'icon' => 'bi-x-circle'],
                    ];
                    foreach ($unidades as $unidad) {
                        $estados_unidades[$unidad['estado_actual']]['count']++;
                    }
                    ?>
                    <?php foreach ($estados_unidades as $estado => $data): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="bi <?php echo $data['icon']; ?> me-2 text-<?php echo str_replace(' text-dark', '', $data['class']); ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $estado)); ?>
                        </div>
                        <span class="badge bg-<?php echo $data['class']; ?> rounded-pill">
                            <?php echo $data['count']; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($can_manage): ?>
                <hr>
                <h6 class="text-muted">Acciones Rápidas</h6>
                <div class="d-grid gap-2">
                    <a href="add_unit.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-square"></i> Agregar Nueva Unidad
                    </a>
                    <a href="prestamos.php?herramienta_id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-box-arrow-up"></i> Ver Préstamos
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Unidades Individuales -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ol"></i> Unidades Individuales (<?php echo count($unidades); ?>)</span>
                <?php if ($can_manage): ?>
                <a href="add_unit.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-square"></i> Agregar Unidad
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($unidades)): ?>
                <form method="POST" action="qr_pdf.php" target="_blank" id="qr-form">
                    <input type="hidden" name="id" value="<?php echo $herramienta_id; ?>">
                    <input type="hidden" name="estado" value="disponible">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select-all" title="Seleccionar todos">
                                    </th>
                                    <th>ID Unidad</th>
                                    <th>Código QR</th>
                                    <th>Estado Actual</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unidades as $unidad): ?>
                                <tr>
                                    <td><input type="checkbox" name="qrs[]" value="<?php echo htmlspecialchars($unidad['qr_code']); ?>"></td>
                                    <td>#<?php echo $unidad['id_unidad']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($unidad['qr_code']); ?></strong>
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($unidad['qr_code']); ?>&amp;size=50x50" alt="QR Code" class="qr-code ms-2" style="width:50px;height:50px;">
                                    </td>
                                    <td>
                                        <?php
                                        $estado_class = '';
                                        switch ($unidad['estado_actual']) {
                                            case 'disponible': $estado_class = 'bg-success'; break;
                                            case 'prestada': $estado_class = 'bg-warning text-dark'; break;
                                            case 'mantenimiento': $estado_class = 'bg-info'; break;
                                            case 'perdida': $estado_class = 'bg-danger'; break;
                                            case 'dañada': $estado_class = 'bg-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $estado_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $unidad['estado_actual'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($can_manage): ?>
                                            <a href="update_unit_status.php?id=<?php echo $unidad['id_unidad']; ?>" 
                                               class="btn btn-outline-primary" title="Actualizar estado">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </a>
                                            <a href="delete_unit.php?id=<?php echo $unidad['id_unidad']; ?>" 
                                               class="btn btn-outline-danger btn-delete" 
                                               data-item-name="la unidad '<?php echo htmlspecialchars($unidad['qr_code']); ?>'"
                                               title="Eliminar unidad">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Seleccione las unidades que desea reimprimir. Use "Seleccionar todos" para marcar todas las unidades.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-outline-dark mt-2" id="submit-qr"><i class="bi bi-printer"></i> Reimprimir QR seleccionados</button>
                </form>
                
                <script>
                // Validación del formulario
                document.getElementById('qr-form').addEventListener('submit', function(e) {
                    const checkboxes = document.querySelectorAll('input[name="qrs[]"]:checked');
                    if (checkboxes.length === 0) {
                        e.preventDefault();
                        alert('Por favor, seleccione al menos una unidad para generar el PDF.');
                        return false;
                    }
                });
                
                // Funcionalidad "Seleccionar todos"
                document.getElementById('select-all').addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="qrs[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
                
                // Actualizar estado del checkbox "Seleccionar todos"
                document.querySelectorAll('input[name="qrs[]"]').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allCheckboxes = document.querySelectorAll('input[name="qrs[]"]');
                        const checkedCheckboxes = document.querySelectorAll('input[name="qrs[]"]:checked');
                        const selectAllCheckbox = document.getElementById('select-all');
                        
                        if (checkedCheckboxes.length === 0) {
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.checked = false;
                        } else if (checkedCheckboxes.length === allCheckboxes.length) {
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.checked = true;
                        } else {
                            selectAllCheckbox.indeterminate = true;
                        }
                    });
                });
                </script>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-qr-code text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No hay unidades individuales registradas para este tipo de herramienta.</p>
                    <?php if ($can_manage): ?>
                    <a href="add_unit.php?id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-square"></i> Agregar Primera Unidad
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Préstamos Recientes -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> Préstamos Recientes de esta Herramienta</span>
                <?php if ($can_manage): ?>
                <a href="prestamos.php?herramienta_id=<?php echo $herramienta['id_herramienta']; ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-list"></i> Ver Todos
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($prestamos_recientes)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($prestamos_recientes as $prestamo): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                Préstamo a <?php echo htmlspecialchars($prestamo['nombre'] . ' ' . $prestamo['apellido']); ?>
                            </h6>
                            <small><?php echo date('d/m/Y', strtotime($prestamo['fecha_retiro'])); ?></small>
                        </div>
                        <p class="mb-1">
                            <small class="text-muted">
                                Unidad: <strong><?php echo htmlspecialchars($prestamo['qr_code']); ?></strong>
                                en Obra: <?php echo htmlspecialchars($prestamo['nombre_obra']); ?>
                            </small>
                        </p>
                        <a href="view_prestamo.php?id=<?php echo $prestamo['id_prestamo']; ?>" class="btn btn-sm btn-link p-0">Ver detalles</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-box-arrow-up text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No hay préstamos recientes registrados para este tipo de herramienta.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

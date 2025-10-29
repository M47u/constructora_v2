<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden gestionar materiales
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

// Permisos: Admin y Responsable pueden gestionar, Empleado solo puede ver
$can_manage = has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]);

$page_title = 'Gestión de Materiales';

$database = new Database();
$conn = $database->getConnection();

// Filtros
$filtro_stock_bajo = isset($_GET['stock_bajo']) ? (bool)$_GET['stock_bajo'] : false;
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

// Siempre mostrar solo materiales activos
$where_conditions[] = "estado = 'activo'";

if ($filtro_stock_bajo) {
    $where_conditions[] = "stock_actual <= stock_minimo";
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "nombre_material LIKE ?";
    $params[] = "%$filtro_busqueda%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Paginación
$allowed_page_sizes = [20, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, $allowed_page_sizes, true)) { $per_page = 20; }
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

try {
    // Obtener total para paginación
    $query_count = "SELECT COUNT(*) FROM materiales $where_clause";
    $stmt_count = $conn->prepare($query_count);
    $stmt_count->execute($params);
    $total_items = (int)$stmt_count->fetchColumn();
    $total_pages = max(1, (int)ceil($total_items / $per_page));
    if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

    // Obtener materiales paginados
    $query = "SELECT * FROM materiales $where_clause ORDER BY nombre_material LIMIT $per_page OFFSET $offset";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $materiales = $stmt->fetchAll();

    // Obtener estadísticas (solo materiales activos)
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_materiales,
        COUNT(CASE WHEN stock_actual <= stock_minimo THEN 1 END) as stock_bajo,
        SUM(stock_actual * precio_referencia) as valor_total_stock
        FROM materiales 
        WHERE estado = 'activo'");
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener materiales: " . $e->getMessage());
    $materiales = [];
    $stats = ['total_materiales' => 0, 'stock_bajo' => 0, 'valor_total_stock' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-box-seam"></i> Gestión de Materiales
            </h1>
            <?php if ($can_manage): ?>
            <div>
                <a href="create.php" class="btn btn-primary me-2">
                    <i class="bi bi-plus-circle"></i> Nuevo Material
                </a>
                <a href="exportar_materiales.php" class="btn btn-info me-2">
                    <i class="bi bi-box-arrow-up"></i> Exportar a Excel
                </a>
                <!-- Botón para eliminar seleccionados -->
                <button id="btn-delete-selected" class="btn btn-danger" disabled>
                    <i class="bi bi-trash"></i> Eliminar Seleccionados
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Total Materiales</h6>
                        <h3 class="mb-0"><?php echo $stats['total_materiales']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-box dashboard-icon text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Stock Bajo</h6>
                        <h3 class="mb-0 text-warning"><?php echo $stats['stock_bajo']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle dashboard-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Valor Total Stock</h6>
                        <h3 class="mb-0 text-success">$<?php echo number_format((float)($stats['valor_total_stock'] ?? 0), 2); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar dashboard-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <label for="busqueda" class="form-label">Buscar Material</label>
            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                   placeholder="Buscar por nombre..." 
                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
        </div>
        
        <div class="col-md-3">
            <label class="form-label">Filtros</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="stock_bajo" name="stock_bajo" value="1"
                       <?php echo $filtro_stock_bajo ? 'checked' : ''; ?>>
                <label class="form-check-label" for="stock_bajo">
                    Solo stock bajo
                </label>
            </div>
        </div>
        
        <div class="col-md-3">
            <label for="per_page" class="form-label">Resultados por página</label>
            <select class="form-select" id="per_page" name="per_page">
                <?php foreach ($allowed_page_sizes as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo $per_page === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary me-2">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <a href="stock_bajo.php" class="btn btn-warning w-100">
                <i class="bi bi-exclamation-triangle"></i> Stock Bajo
            </a>
        </div>
    </form>
</div>

<!-- Tabla de materiales (envuelta en formulario para eliminación masiva) -->
<form id="bulk-delete-form" method="POST" action="delete_selected.php">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="card">
    <div class="card-body">
        <?php if (!empty($materiales)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <!-- Checkbox seleccionar todos -->
                        <th style="width:40px;">
                            <input type="checkbox" id="select_all" title="Seleccionar todos">
                        </th>
                        <th>Material</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Precio Referencia</th>
                        <th>Unidad</th>
                        <th>Valor Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiales as $material): ?>
                    <tr class="<?php echo $material['stock_actual'] <= $material['stock_minimo'] ? 'table-warning' : ''; ?>">
                        <!-- Checkbox por fila -->
                        <td>
                            <input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?php echo (int)$material['id_material']; ?>">
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($material['nombre_material']); ?></strong>
                        </td>
                        <td>
                            <span class="badge <?php echo $material['stock_actual'] <= $material['stock_minimo'] ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                <?php echo number_format((float)($material['stock_actual'] ?? 0)); ?>
                            </span>
                        </td>
                        <td><?php echo number_format((float)($material['stock_minimo'] ?? 0)); ?></td>
                        <td>$<?php echo number_format((float)($material['precio_referencia'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars($material['unidad_medida']); ?></td>
                        <td>
                            <strong>$<?php echo number_format((float)(($material['stock_actual'] ?? 0) * ($material['precio_referencia'] ?? 0)), 2); ?></strong>
                        </td>
                        <td>
                            <?php if ($material['stock_actual'] <= $material['stock_minimo']): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle"></i> Stock Bajo
                                </span>
                            <?php elseif ($material['stock_actual'] == 0): ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-x-circle"></i> Sin Stock
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Disponible
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="adjust_stock.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-warning" title="Ajustar stock">
                                    <i class="bi bi-arrow-up-down"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-danger btn-delete" 
                                   data-item-name="el material '<?php echo htmlspecialchars($material['nombre_material']); ?>'"
                                   title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Paginación materiales">
            <ul class="pagination justify-content-center mt-3">
                <?php
                // Construir query string preservando filtros
                $qs = $_GET;
                $qs['page'] = 1;
                $qs['per_page'] = $per_page;
                $first_url = 'list.php?' . http_build_query($qs);
                $qs['page'] = max(1, $page - 1);
                $prev_url = 'list.php?' . http_build_query($qs);
                $qs['page'] = min($total_pages, $page + 1);
                $next_url = 'list.php?' . http_build_query($qs);
                $qs['page'] = $total_pages;
                $last_url = 'list.php?' . http_build_query($qs);
                ?>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $first_url; ?>" aria-label="Primera">
                        <span aria-hidden="true">&laquo;&laquo;</span>
                    </a>
                </li>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $prev_url; ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">Página <?php echo $page; ?> de <?php echo $total_pages; ?></span>
                </li>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $next_url; ?>" aria-label="Siguiente">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $last_url; ?>" aria-label="Última">
                        <span aria-hidden="true">&raquo;&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-box-seam text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron materiales</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_busqueda) || $filtro_stock_bajo): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience agregando materiales al inventario.
                <?php endif; ?>
            </p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Agregar Primer Material
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
</form>

<!-- JS para selección masiva y envío con confirmación -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    const selectAll = document.getElementById('select_all');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const deleteBtn = document.getElementById('btn-delete-selected');
    const form = document.getElementById('bulk-delete-form');

    function updateDeleteButton(){
        const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
        deleteBtn.disabled = !anyChecked;
        // Alternar clase que hace el botón más rojo cuando está habilitado
        if (anyChecked) {
            deleteBtn.classList.add('btn-delete-active');
        } else {
            deleteBtn.classList.remove('btn-delete-active');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function(){
            rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
            updateDeleteButton();
        });
    }

    rowCheckboxes.forEach(cb => cb.addEventListener('change', function(){
        if (!this.checked) selectAll.checked = false;
        else if (Array.from(rowCheckboxes).every(cb => cb.checked)) selectAll.checked = true;
        updateDeleteButton();
    }));

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e){
            e.preventDefault();
            if (Array.from(rowCheckboxes).every(cb => !cb.checked)) return;
            if (confirm('¿Está seguro que desea eliminar los materiales seleccionados? Esta acción no se puede deshacer.')) {
                form.submit();
            }
        });
    }

    // Inicializar estado del botón
    updateDeleteButton();
});
</script>

<?php include '../../includes/footer.php'; ?>
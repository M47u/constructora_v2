<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden ver stock bajo
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Materiales con Stock Bajo';

$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener materiales con stock bajo
    $query = "SELECT * FROM materiales 
              WHERE stock_actual <= stock_minimo 
              ORDER BY 
                CASE WHEN stock_actual = 0 THEN 1 ELSE 2 END,
                (stock_actual / NULLIF(stock_minimo, 0)) ASC,
                nombre_material";
    
    $stmt = $conn->query($query);
    $materiales = $stmt->fetchAll();

    // Obtener estadísticas
    $stmt_stats = $conn->query("SELECT 
        COUNT(*) as total_materiales,
        COUNT(CASE WHEN stock_actual = 0 THEN 1 END) as sin_stock,
        COUNT(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 END) as stock_bajo,
        SUM(CASE WHEN stock_actual <= stock_minimo THEN (stock_minimo - stock_actual) * precio_referencia ELSE 0 END) as valor_faltante
        FROM materiales");
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener materiales con stock bajo: " . $e->getMessage());
    $materiales = [];
    $stats = ['total_materiales' => 0, 'sin_stock' => 0, 'stock_bajo' => 0, 'valor_faltante' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-exclamation-triangle text-warning"></i> Materiales con Stock Bajo
            </h1>
            <div>
                <a href="list.php" class="btn btn-outline-primary">
                    <i class="bi bi-box-seam"></i> Todos los Materiales
                </a>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo Material
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas de alerta -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card danger">
            <div class="card-body text-center">
                <h3 class="text-danger"><?php echo $stats['sin_stock']; ?></h3>
                <small class="text-muted">Sin Stock</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card warning">
            <div class="card-body text-center">
                <h3 class="text-warning"><?php echo $stats['stock_bajo']; ?></h3>
                <small class="text-muted">Stock Bajo</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h3 class="text-primary"><?php echo count($materiales); ?></h3>
                <small class="text-muted">Total Críticos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card info">
            <div class="card-body text-center">
                <h3 class="text-info">$<?php echo number_format($stats['valor_faltante'], 0); ?></h3>
                <small class="text-muted">Valor Faltante</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($materiales)): ?>
<div class="alert alert-warning" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>¡Atención!</strong> Hay <?php echo count($materiales); ?> materiales que requieren reposición urgente.
    Se recomienda generar pedidos de compra inmediatamente.
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-list-ul"></i> Materiales Críticos
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Faltante</th>
                        <th>Precio Unit.</th>
                        <th>Valor Faltante</th>
                        <th>Criticidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiales as $material): ?>
                    <?php 
                    $faltante = max(0, $material['stock_minimo'] - $material['stock_actual']);
                    $valor_faltante = $faltante * $material['precio_referencia'];
                    $es_critico = $material['stock_actual'] == 0;
                    ?>
                    <tr class="<?php echo $es_critico ? 'table-danger' : 'table-warning'; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($material['nombre_material']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($material['unidad_medida']); ?></small>
                        </td>
                        <td>
                            <span class="badge <?php echo $es_critico ? 'bg-danger' : 'bg-warning text-dark'; ?> fs-6">
                                <?php echo number_format($material['stock_actual']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo number_format($material['stock_minimo']); ?></span>
                        </td>
                        <td>
                            <span class="text-danger fw-bold"><?php echo number_format($faltante); ?></span>
                        </td>
                        <td>$<?php echo number_format($material['precio_referencia'], 2); ?></td>
                        <td>
                            <span class="text-danger fw-bold">$<?php echo number_format($valor_faltante, 2); ?></span>
                        </td>
                        <td>
                            <?php if ($es_critico): ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i> CRÍTICO
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-circle"></i> BAJO
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="adjust_stock.php?id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-warning" title="Ajustar stock">
                                    <i class="bi bi-arrow-up-down"></i>
                                </a>
                                <a href="../pedidos/create.php?material_id=<?php echo $material['id_material']; ?>" 
                                   class="btn btn-outline-success" title="Crear pedido">
                                    <i class="bi bi-cart-plus"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Acciones recomendadas -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightbulb"></i> Acciones Recomendadas
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <i class="bi bi-cart-plus text-success" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Generar Pedidos</h6>
                            <p class="small text-muted">Crear pedidos de compra para los materiales críticos</p>
                            <a href="../pedidos/create.php" class="btn btn-sm btn-success">
                                <i class="bi bi-plus"></i> Nuevo Pedido
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <i class="bi bi-arrow-up-down text-warning" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Ajustar Stocks</h6>
                            <p class="small text-muted">Actualizar inventario después de recibir mercadería</p>
                            <button class="btn btn-sm btn-warning" onclick="ajustarTodos()">
                                <i class="bi bi-gear"></i> Ajuste Masivo
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <i class="bi bi-bell text-info" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Configurar Alertas</h6>
                            <p class="small text-muted">Revisar y ajustar los stocks mínimos</p>
                            <a href="list.php" class="btn btn-sm btn-info">
                                <i class="bi bi-gear"></i> Configurar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
            <h3 class="mt-3 text-success">¡Excelente!</h3>
            <h5 class="text-muted">Todos los materiales tienen stock suficiente</h5>
            <p class="text-muted">No hay materiales con stock por debajo del mínimo requerido.</p>
            <div class="mt-4">
                <a href="list.php" class="btn btn-primary">
                    <i class="bi bi-box-seam"></i> Ver Todos los Materiales
                </a>
                <a href="create.php" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Agregar Material
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function ajustarTodos() {
    if (confirm('¿Desea abrir la página de ajuste de stock para revisar todos los materiales críticos?')) {
        // En una implementación real, esto podría abrir un modal o página especial
        window.location.href = 'list.php?stock_bajo=1';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

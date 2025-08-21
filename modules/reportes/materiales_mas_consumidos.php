<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos (solo administradores y responsables de obra)
if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

$page_title = "Materiales Más Consumidos";
require_once '../../includes/header.php';

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$limite = $_GET['limite'] ?? 10;

// Obtener datos del reporte
$datos_reporte = [];
$total_general = 0;

try {
    $sql = "SELECT 
                m.nombre as material_nombre,
                m.unidad_medida,
                SUM(pm.cantidad) as total_cantidad,
                AVG(m.precio_referencia) as precio_promedio,
                SUM(pm.cantidad * m.precio_referencia) as valor_total,
                COUNT(DISTINCT p.obra_id) as obras_utilizadas
            FROM pedidos_materiales pm
            INNER JOIN pedidos p ON pm.pedido_id = p.id
            INNER JOIN materiales m ON pm.material_id = m.id
            WHERE p.fecha_pedido BETWEEN ? AND ?
            GROUP BY m.id
            ORDER BY total_cantidad DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha_inicio, $fecha_fin, $limite]);
    $datos_reporte = $stmt->fetchAll();
    
    // Calcular total general
    foreach ($datos_reporte as $dato) {
        $total_general += $dato['valor_total'];
    }
    
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}

// Preparar datos para el gráfico
$datos_grafico = [
    'labels' => array_column($datos_reporte, 'material_nombre'),
    'data' => array_column($datos_reporte, 'total_cantidad')
];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-graph-up"></i> Materiales Más Consumidos</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-funnel"></i> Filtros de Búsqueda</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="limite" class="form-label">Top Materiales</label>
                    <select class="form-select" id="limite" name="limite">
                        <option value="5" <?php echo ($limite == 5) ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo ($limite == 10) ? 'selected' : ''; ?>>Top 10</option>
                        <option value="15" <?php echo ($limite == 15) ? 'selected' : ''; ?>>Top 15</option>
                        <option value="20" <?php echo ($limite == 20) ? 'selected' : ''; ?>>Top 20</option>
                        <option value="50" <?php echo ($limite == 50) ? 'selected' : ''; ?>>Top 50</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Generar Reporte
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($datos_reporte)): ?>
    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4>$<?php echo number_format($total_general, 2); ?></h4>
                    <p class="mb-0">Valor Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($datos_reporte); ?></h4>
                    <p class="mb-0">Materiales en Top</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format(array_sum(array_column($datos_reporte, 'total_cantidad')), 2); ?></h4>
                    <p class="mb-0">Cantidad Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4><?php echo !empty($datos_reporte) ? number_format($datos_reporte[0]['total_cantidad'], 2) : '0'; ?></h4>
                    <p class="mb-0">Más Consumido</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Material Más Consumido (Destacado) -->
    <?php if (!empty($datos_reporte)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5><i class="bi bi-trophy"></i> 🏆 Material Más Consumido</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h3><?php echo htmlspecialchars($datos_reporte[0]['material_nombre']); ?></h3>
                    <p class="text-muted mb-2">El material con mayor consumo en el período seleccionado</p>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Cantidad:</strong><br>
                            <span class="fs-5 text-primary"><?php echo number_format($datos_reporte[0]['total_cantidad'], 2); ?> <?php echo htmlspecialchars($datos_reporte[0]['unidad_medida']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Valor Total:</strong><br>
                            <span class="fs-5 text-success">$<?php echo number_format($datos_reporte[0]['valor_total'], 2); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Precio Promedio:</strong><br>
                            <span class="fs-5 text-info">$<?php echo number_format($datos_reporte[0]['precio_promedio'], 2); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Obras que lo usan:</strong><br>
                            <span class="fs-5 text-warning"><?php echo $datos_reporte[0]['obras_utilizadas']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <i class="bi bi-award text-warning" style="font-size: 4rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gráfico -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-bar-chart"></i> Ranking de Consumo</h5>
        </div>
        <div class="card-body">
            <canvas id="graficoMateriales" width="400" height="200"></canvas>
        </div>
    </div>

    <!-- Tabla de Datos -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-table"></i> Ranking Detallado</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaReporte">
                    <thead class="table-dark">
                        <tr>
                            <th>Posición</th>
                            <th>Material</th>
                            <th>Cantidad Total</th>
                            <th>Unidad</th>
                            <th>Precio Promedio</th>
                            <th>Valor Total</th>
                            <th>Obras</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_reporte as $index => $dato): ?>
                        <tr>
                            <td>
                                <?php if ($index == 0): ?>
                                    <span class="badge bg-warning">🥇 1°</span>
                                <?php elseif ($index == 1): ?>
                                    <span class="badge bg-secondary">🥈 2°</span>
                                <?php elseif ($index == 2): ?>
                                    <span class="badge bg-warning">🥉 3°</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?php echo $index + 1; ?>°</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($dato['material_nombre']); ?></td>
                            <td><?php echo number_format($dato['total_cantidad'], 2); ?></td>
                            <td><?php echo htmlspecialchars($dato['unidad_medida']); ?></td>
                            <td>$<?php echo number_format($dato['precio_promedio'], 2); ?></td>
                            <td>$<?php echo number_format($dato['valor_total'], 2); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $dato['obras_utilizadas']; ?> obras</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="alert alert-info text-center">
        <i class="bi bi-info-circle fs-1"></i>
        <h4>No hay datos para mostrar</h4>
        <p>No se encontraron registros para el período seleccionado.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de barras
<?php if (!empty($datos_grafico['labels'])): ?>
const ctx = document.getElementById('graficoMateriales').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($datos_grafico['labels']); ?>,
        datasets: [{
            label: 'Cantidad Consumida',
            data: <?php echo json_encode($datos_grafico['data']); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Cantidad: ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Función para exportar a Excel
function exportarExcel() {
    const tabla = document.getElementById('tablaReporte');
    const wb = XLSX.utils.table_to_book(tabla, {sheet: "Materiales Más Consumidos"});
    XLSX.writeFile(wb, 'materiales_mas_consumidos_<?php echo date("Y-m-d"); ?>.xlsx');
}

// Validación de fechas
document.getElementById('fecha_inicio').addEventListener('change', function() {
    const fechaInicio = new Date(this.value);
    const fechaFin = new Date(document.getElementById('fecha_fin').value);
    
    if (fechaInicio > fechaFin) {
        document.getElementById('fecha_fin').value = this.value;
    }
});
</script>

<!-- SheetJS para exportar Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php require_once '../../includes/footer.php'; ?>

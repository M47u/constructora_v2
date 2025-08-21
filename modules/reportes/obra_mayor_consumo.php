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

$page_title = "Obra con Mayor Consumo";
require_once '../../includes/header.php';

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

// Obtener datos del reporte
$datos_reporte = [];
$obra_ganadora = null;
$materiales_obra_ganadora = [];

try {
    // Obtener ranking de obras por consumo
    $sql = "SELECT 
                o.id,
                o.nombre as obra_nombre,
                o.ubicacion,
                o.responsable,
                SUM(pm.cantidad * m.precio_referencia) as valor_total,
                SUM(pm.cantidad) as cantidad_total,
                COUNT(DISTINCT m.id) as materiales_diferentes,
                COUNT(DISTINCT p.id) as pedidos_realizados
            FROM pedidos_materiales pm
            INNER JOIN pedidos p ON pm.pedido_id = p.id
            INNER JOIN obras o ON p.obra_id = o.id
            INNER JOIN materiales m ON pm.material_id = m.id
            WHERE p.fecha_pedido BETWEEN ? AND ?
            GROUP BY o.id
            ORDER BY valor_total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $datos_reporte = $stmt->fetchAll();
    
    // La obra ganadora es la primera del ranking
    if (!empty($datos_reporte)) {
        $obra_ganadora = $datos_reporte[0];
        
        // Obtener detalle de materiales de la obra ganadora
        $sql_detalle = "SELECT 
                            m.nombre as material_nombre,
                            m.unidad_medida,
                            SUM(pm.cantidad) as cantidad_consumida,
                            AVG(m.precio_referencia) as precio_promedio,
                            SUM(pm.cantidad * m.precio_referencia) as valor_total
                        FROM pedidos_materiales pm
                        INNER JOIN pedidos p ON pm.pedido_id = p.id
                        INNER JOIN materiales m ON pm.material_id = m.id
                        WHERE p.obra_id = ? AND p.fecha_pedido BETWEEN ? AND ?
                        GROUP BY m.id
                        ORDER BY valor_total DESC
                        LIMIT 10";
        
        $stmt_detalle = $pdo->prepare($sql_detalle);
        $stmt_detalle->execute([$obra_ganadora['id'], $fecha_inicio, $fecha_fin]);
        $materiales_obra_ganadora = $stmt_detalle->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}

// Preparar datos para el gráfico
$datos_grafico = [
    'labels' => array_column($datos_reporte, 'obra_nombre'),
    'data' => array_column($datos_reporte, 'valor_total')
];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-trophy"></i> Obra con Mayor Consumo</h1>
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
                <div class="col-md-5">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                </div>
                <div class="col-md-5">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($datos_reporte)): ?>
    
    <!-- Obra Ganadora (Destacada) -->
    <?php if ($obra_ganadora): ?>
    <div class="card mb-4 border-warning shadow-lg">
        <div class="card-header bg-warning text-dark">
            <h4><i class="bi bi-trophy"></i> 🏆 OBRA GANADORA</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h2 class="text-primary"><?php echo htmlspecialchars($obra_ganadora['obra_nombre']); ?></h2>
                    <p class="text-muted mb-3">
                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($obra_ganadora['ubicacion']); ?>
                    </p>
                    <p class="text-muted mb-3">
                        <i class="bi bi-person"></i> Responsable: <?php echo htmlspecialchars($obra_ganadora['responsable']); ?>
                    </p>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-success">$<?php echo number_format($obra_ganadora['valor_total'], 2); ?></h3>
                                <small class="text-muted">Valor Total</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-info"><?php echo number_format($obra_ganadora['cantidad_total'], 2); ?></h3>
                                <small class="text-muted">Cantidad Total</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning"><?php echo $obra_ganadora['materiales_diferentes']; ?></h3>
                                <small class="text-muted">Materiales Diferentes</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-primary"><?php echo $obra_ganadora['pedidos_realizados']; ?></h3>
                                <small class="text-muted">Pedidos Realizados</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <i class="bi bi-award text-warning" style="font-size: 6rem;"></i>
                    <h5 class="text-warning mt-2">¡CAMPEONA!</h5>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resumen General -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($datos_reporte); ?></h4>
                    <p class="mb-0">Obras con Consumo</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4>$<?php echo number_format(array_sum(array_column($datos_reporte, 'valor_total')), 2); ?></h4>
                    <p class="mb-0">Valor Total General</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format(array_sum(array_column($datos_reporte, 'cantidad_total')), 2); ?></h4>
                    <p class="mb-0">Cantidad Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4><?php echo array_sum(array_column($datos_reporte, 'pedidos_realizados')); ?></h4>
                    <p class="mb-0">Pedidos Totales</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Materiales Más Consumidos por la Obra Ganadora -->
    <?php if (!empty($materiales_obra_ganadora)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-list-ul"></i> Top 10 Materiales de la Obra Ganadora</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Posición</th>
                            <th>Material</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Precio Promedio</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiales_obra_ganadora as $index => $material): ?>
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
                            <td><?php echo htmlspecialchars($material['material_nombre']); ?></td>
                            <td><?php echo number_format($material['cantidad_consumida'], 2); ?></td>
                            <td><?php echo htmlspecialchars($material['unidad_medida']); ?></td>
                            <td>$<?php echo number_format($material['precio_promedio'], 2); ?></td>
                            <td>$<?php echo number_format($material['valor_total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gráfico de Comparación -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-bar-chart"></i> Comparación de Obras por Consumo</h5>
        </div>
        <div class="card-body">
            <canvas id="graficoObras" width="400" height="200"></canvas>
        </div>
    </div>

    <!-- Ranking Completo -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-table"></i> Ranking Completo de Obras</h5>
            <button type="button" class="btn btn-success btn-sm" onclick="exportarExcel()">
                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaReporte">
                    <thead class="table-dark">
                        <tr>
                            <th>Posición</th>
                            <th>Obra</th>
                            <th>Ubicación</th>
                            <th>Responsable</th>
                            <th>Valor Total</th>
                            <th>Cantidad Total</th>
                            <th>Materiales</th>
                            <th>Pedidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_reporte as $index => $obra): ?>
                        <tr <?php echo ($index == 0) ? 'class="table-warning"' : ''; ?>>
                            <td>
                                <?php if ($index == 0): ?>
                                    <span class="badge bg-warning">🏆 1°</span>
                                <?php elseif ($index == 1): ?>
                                    <span class="badge bg-secondary">🥈 2°</span>
                                <?php elseif ($index == 2): ?>
                                    <span class="badge bg-warning">🥉 3°</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?php echo $index + 1; ?>°</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($obra['obra_nombre']); ?></strong>
                                <?php if ($index == 0): ?>
                                    <span class="badge bg-warning ms-2">GANADORA</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($obra['ubicacion']); ?></td>
                            <td><?php echo htmlspecialchars($obra['responsable']); ?></td>
                            <td>$<?php echo number_format($obra['valor_total'], 2); ?></td>
                            <td><?php echo number_format($obra['cantidad_total'], 2); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $obra['materiales_diferentes']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $obra['pedidos_realizados']; ?></span>
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
// Gráfico de barras horizontales
<?php if (!empty($datos_grafico['labels'])): ?>
const ctx = document.getElementById('graficoObras').getContext('2d');
const chart = new Chart(ctx, {
    type: 'horizontalBar',
    data: {
        labels: <?php echo json_encode($datos_grafico['labels']); ?>,
        datasets: [{
            label: 'Valor Total ($)',
            data: <?php echo json_encode($datos_grafico['data']); ?>,
            backgroundColor: function(context) {
                // La primera barra (ganadora) en dorado
                return context.dataIndex === 0 ? 'rgba(255, 193, 7, 0.8)' : 'rgba(54, 162, 235, 0.8)';
            },
            borderColor: function(context) {
                return context.dataIndex === 0 ? 'rgba(255, 193, 7, 1)' : 'rgba(54, 162, 235, 1)';
            },
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Valor: $' + context.parsed.x.toLocaleString();
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
    const wb = XLSX.utils.table_to_book(tabla, {sheet: "Ranking Obras"});
    XLSX.writeFile(wb, 'obra_mayor_consumo_<?php echo date("Y-m-d"); ?>.xlsx');
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

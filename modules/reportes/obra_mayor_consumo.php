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

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

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
                o.id_obra as id,
                o.nombre_obra as obra_nombre,
                o.direccion as ubicacion,
                CONCAT(u.nombre, ' ', u.apellido) as responsable,
                SUM(dpm.cantidad_solicitada * m.precio_referencia) as valor_total,
                SUM(dpm.cantidad_solicitada) as cantidad_total,
                COUNT(DISTINCT m.id_material) as materiales_diferentes,
                COUNT(DISTINCT pm.id_pedido) as pedidos_realizados
            FROM detalle_pedidos_materiales dpm
            INNER JOIN pedidos_materiales pm ON dpm.id_pedido = pm.id_pedido
            INNER JOIN obras o ON pm.id_obra = o.id_obra
            LEFT JOIN usuarios u ON o.id_responsable = u.id_usuario
            INNER JOIN materiales m ON dpm.id_material = m.id_material
            WHERE pm.fecha_pedido BETWEEN ? AND ?
                AND pm.estado != 'cancelado'
            GROUP BY o.id_obra
            ORDER BY valor_total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $datos_reporte = $stmt->fetchAll();
    
    // La obra ganadora es la primera del ranking
    if (!empty($datos_reporte)) {
        $obra_ganadora = $datos_reporte[0];
        
        // Obtener detalle de materiales de la obra ganadora
        $sql_detalle = "SELECT 
                            m.nombre_material as material_nombre,
                            m.unidad_medida,
                            SUM(dpm.cantidad_solicitada) as cantidad_consumida,
                            AVG(m.precio_referencia) as precio_promedio,
                            SUM(dpm.cantidad_solicitada * m.precio_referencia) as valor_total
                        FROM detalle_pedidos_materiales dpm
                        INNER JOIN pedidos_materiales pm ON dpm.id_pedido = pm.id_pedido
                        INNER JOIN materiales m ON dpm.id_material = m.id_material
                        WHERE pm.id_obra = ? AND pm.fecha_pedido BETWEEN ? AND ?
                            AND pm.estado != 'cancelado'
                        GROUP BY m.id_material
                        ORDER BY valor_total DESC
                        LIMIT 10";
        
        $stmt_detalle = $pdo->prepare($sql_detalle);
        $stmt_detalle->execute([$obra_ganadora['id'], $fecha_inicio, $fecha_fin]);
        $materiales_obra_ganadora = $stmt_detalle->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}

// Preparar datos para el gráfico de obras
$datos_grafico = [
    'labels' => array_column($datos_reporte, 'obra_nombre'),
    'data'   => array_column($datos_reporte, 'valor_total'),
];

// Preparar datos para gráfico de materiales de la obra ganadora
$datos_materiales_grafico = [
    'labels'     => array_column($materiales_obra_ganadora, 'material_nombre'),
    'data_valor' => array_column($materiales_obra_ganadora, 'valor_total'),
];

// Preparar datos para radar (top 5 obras, valores normalizados al 100%)
$top5_obras   = array_slice($datos_reporte, 0, 5);
$max_valor    = !empty($datos_reporte) ? (float)max(array_column($datos_reporte, 'valor_total'))        : 1;
$max_cantidad = !empty($datos_reporte) ? (float)max(array_column($datos_reporte, 'cantidad_total'))      : 1;
$max_mat      = !empty($datos_reporte) ? (int)max(array_column($datos_reporte, 'materiales_diferentes')) : 1;
$max_pedidos  = !empty($datos_reporte) ? (int)max(array_column($datos_reporte, 'pedidos_realizados'))    : 1;

$radar_palette = [
    ['border' => 'rgba(255,193,7,1)',   'bg' => 'rgba(255,193,7,0.15)'],
    ['border' => 'rgba(54,162,235,1)',  'bg' => 'rgba(54,162,235,0.15)'],
    ['border' => 'rgba(75,192,192,1)',  'bg' => 'rgba(75,192,192,0.15)'],
    ['border' => 'rgba(255,99,132,1)',  'bg' => 'rgba(255,99,132,0.15)'],
    ['border' => 'rgba(153,102,255,1)', 'bg' => 'rgba(153,102,255,0.15)'],
];
$radar_datasets = [];
foreach ($top5_obras as $i => $obra) {
    $c = $radar_palette[$i] ?? $radar_palette[0];
    $radar_datasets[] = [
        'label'               => $obra['obra_nombre'],
        'data'                => [
            round(($obra['valor_total']          / $max_valor)    * 100, 1),
            round(($obra['cantidad_total']        / $max_cantidad) * 100, 1),
            round(($obra['materiales_diferentes'] / $max_mat)      * 100, 1),
            round(($obra['pedidos_realizados']    / $max_pedidos)  * 100, 1),
        ],
        'borderColor'         => $c['border'],
        'backgroundColor'     => $c['bg'],
        'borderWidth'         => 2,
        'pointBackgroundColor'=> $c['border'],
    ];
}
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

    <!-- Gráfico 3+4: Materiales obra ganadora -->
    <?php if (!empty($materiales_obra_ganadora)): ?>
    <div class="row mb-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5><i class="bi bi-bar-chart-horizontal"></i> Valor por Material — Obra Ganadora</h5>
                </div>
                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="graficoMaterialesBar"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5><i class="bi bi-pie-chart"></i> Distribución del Presupuesto por Material</h5>
                </div>
                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="graficoMaterialesDoughnut"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gráfico 1+2: Ranking de obras + Distribución -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5><i class="bi bi-bar-chart-horizontal"></i> Ranking de Obras por Valor Total</h5>
                </div>
                <div class="card-body">
                    <div style="height: 400px;">
                        <canvas id="graficoObras"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5><i class="bi bi-pie-chart"></i> Distribución del Consumo Total</h5>
                </div>
                <div class="card-body">
                    <div style="height: 400px;">
                        <canvas id="graficoDoughnutObras"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico 5: Radar multi-dimensional -->
    <?php if (count($datos_reporte) > 1): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-reception-4"></i> Comparación Multi-dimensional — Top <?php echo min(5, count($datos_reporte)); ?> Obras</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">Valores normalizados al 100% respecto a la obra líder en cada métrica.</p>
            <div style="height: 420px;">
                <canvas id="graficoRadar"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
<?php if (!empty($datos_grafico['labels'])): ?>

// ── Paleta dinámica: dorado para la ganadora, azules descendentes para las demás ──
const labelsObras  = <?php echo json_encode($datos_grafico['labels']); ?>;
const valoresObras = <?php echo json_encode($datos_grafico['data']); ?>;

const bgObras = labelsObras.map((_, i) =>
    i === 0 ? 'rgba(255,193,7,0.85)' : `rgba(54,162,235,${Math.max(0.35, 0.75 - i * 0.04)})`
);
const bdObras = labelsObras.map((_, i) =>
    i === 0 ? 'rgba(255,193,7,1)' : 'rgba(54,162,235,1)'
);

// ── Gráfico 1: Barras Horizontales — Ranking de Obras ──
const ctxObras = document.getElementById('graficoObras').getContext('2d');
new Chart(ctxObras, {
    type: 'bar',
    data: {
        labels: labelsObras,
        datasets: [{
            label: 'Valor Total ($)',
            data: valoresObras,
            backgroundColor: bgObras,
            borderColor: bdObras,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: { callback: v => '$' + v.toLocaleString() }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'Valor: $' + ctx.parsed.x.toLocaleString()
                }
            }
        }
    }
});

// ── Gráfico 2: Doughnut — Distribución del Consumo Total entre Obras ──
const ctxDoughnutObras = document.getElementById('graficoDoughnutObras').getContext('2d');
new Chart(ctxDoughnutObras, {
    type: 'doughnut',
    data: {
        labels: labelsObras,
        datasets: [{
            data: valoresObras,
            backgroundColor: bgObras,
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 14 } },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct   = ((ctx.parsed / total) * 100).toFixed(1);
                        return ctx.label + ': $' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                    }
                }
            }
        }
    }
});

<?php endif; ?>

<?php if (!empty($datos_materiales_grafico['labels'])): ?>
const labelsMat  = <?php echo json_encode($datos_materiales_grafico['labels']); ?>;
const valoresMat = <?php echo json_encode($datos_materiales_grafico['data_valor']); ?>;

const paletaMat = [
    'rgba(255,99,132,0.8)','rgba(54,162,235,0.8)','rgba(255,206,86,0.8)',
    'rgba(75,192,192,0.8)','rgba(153,102,255,0.8)','rgba(255,159,64,0.8)',
    'rgba(199,199,199,0.8)','rgba(83,102,255,0.8)','rgba(255,99,255,0.8)',
    'rgba(100,220,100,0.8)'
];

// ── Gráfico 3: Barras Horizontales — Valor por Material (obra ganadora) ──
const ctxMatBar = document.getElementById('graficoMaterialesBar').getContext('2d');
new Chart(ctxMatBar, {
    type: 'bar',
    data: {
        labels: labelsMat,
        datasets: [{
            label: 'Valor Total ($)',
            data: valoresMat,
            backgroundColor: paletaMat,
            borderColor: paletaMat.map(c => c.replace('0.8', '1')),
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: { callback: v => '$' + v.toLocaleString() }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'Valor: $' + ctx.parsed.x.toLocaleString()
                }
            }
        }
    }
});

// ── Gráfico 4: Doughnut — Distribución del Presupuesto por Material ──
const ctxMatDoughnut = document.getElementById('graficoMaterialesDoughnut').getContext('2d');
new Chart(ctxMatDoughnut, {
    type: 'doughnut',
    data: {
        labels: labelsMat,
        datasets: [{
            data: valoresMat,
            backgroundColor: paletaMat,
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 14 } },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct   = ((ctx.parsed / total) * 100).toFixed(1);
                        return ctx.label + ': $' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($radar_datasets)): ?>
// ── Gráfico 5: Radar — Comparación Multi-dimensional de las Top Obras ──
const ctxRadar = document.getElementById('graficoRadar').getContext('2d');
new Chart(ctxRadar, {
    type: 'radar',
    data: {
        labels: ['Valor Total', 'Cantidad Consumida', 'Materiales Distintos', 'Pedidos Realizados'],
        datasets: <?php echo json_encode($radar_datasets); ?>
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: v => v + '%',
                    stepSize: 25,
                    font: { size: 11 }
                },
                pointLabels: { font: { size: 12 } }
            }
        },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 14 } },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + ctx.parsed.r + '%'
                }
            }
        }
    }
});
<?php endif; ?>

// ── Exportar Excel ──
function exportarExcel() {
    const tabla = document.getElementById('tablaReporte');
    const wb = XLSX.utils.table_to_book(tabla, {sheet: "Ranking Obras"});
    XLSX.writeFile(wb, 'obra_mayor_consumo_<?php echo date("Y-m-d"); ?>.xlsx');
}

// ── Validación de fechas ──
document.getElementById('fecha_inicio').addEventListener('change', function() {
    const fechaInicio = new Date(this.value);
    const fechaFin    = new Date(document.getElementById('fecha_fin').value);
    if (fechaInicio > fechaFin) {
        document.getElementById('fecha_fin').value = this.value;
    }
});
</script>

<!-- SheetJS para exportar Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php require_once '../../includes/footer.php'; ?>

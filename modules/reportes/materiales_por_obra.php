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

$page_title = "Materiales por Obra"; 

// Inicializar conexión a la base de datos
$database = new Database(); 
$pdo = $database->getConnection();

require_once '../../includes/header.php';

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$obra_id = $_GET['obra_id'] ?? '';

// Obtener lista de obras para el filtro
try {
    $stmt = $pdo->query("SELECT id_obra AS id, nombre_obra AS nombre FROM obras WHERE estado != 'cancelada' ORDER BY nombre_obra");
    $obras = $stmt->fetchAll();
} catch (PDOException $e) {
    $obras = [];
    $error = "Error al cargar las obras: " . $e->getMessage();
}

// Obtener datos del reporte
$datos_reporte = [];
$total_general = 0;

try {
    $sql = "SELECT 
                o.nombre_obra as obra_nombre,
                m.nombre_material as material_nombre,
                SUM(dpm.cantidad_solicitada) as total_cantidad,
                m.unidad_medida,
                AVG(m.precio_referencia) as precio_promedio,
                SUM(dpm.cantidad_solicitada * m.precio_referencia) as valor_total
            FROM detalle_pedidos_materiales dpm
            INNER JOIN pedidos_materiales pm ON dpm.id_pedido = pm.id_pedido
            INNER JOIN obras o ON pm.id_obra = o.id_obra
            INNER JOIN materiales m ON dpm.id_material = m.id_material
            WHERE pm.fecha_pedido BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
    if (!empty($obra_id)) {
        $sql .= " AND o.id_obra = ?";
        $params[] = $obra_id;
    }
    $sql .= " GROUP BY o.id_obra, m.id_material
              ORDER BY o.nombre_obra, total_cantidad DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos_reporte = $stmt->fetchAll();
    // Calcular total general
    foreach ($datos_reporte as $dato) {
        $total_general += $dato['valor_total'];
    }
    
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}

// Preparar datos para el gráfico
$datos_grafico = [];
$obras_agrupadas = [];

foreach ($datos_reporte as $dato) {
    if (!isset($obras_agrupadas[$dato['obra_nombre']])) {
        $obras_agrupadas[$dato['obra_nombre']] = 0;
    }
    $obras_agrupadas[$dato['obra_nombre']] += $dato['valor_total'];
}

$datos_grafico = [
    'labels' => array_keys($obras_agrupadas),
    'data' => array_values($obras_agrupadas)
];

// Obtener parámetros de paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = 20;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Modificar la consulta para incluir límites de paginación
$sql .= " LIMIT $offset, $registros_por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$datos_reporte = $stmt->fetchAll();

// Calcular el total de registros para la paginación
$total_registros = 0;
try {
    $sql_total = "SELECT COUNT(*) as total
                  FROM detalle_pedidos_materiales dpm
                  INNER JOIN pedidos_materiales pm ON dpm.id_pedido = pm.id_pedido
                  INNER JOIN obras o ON pm.id_obra = o.id_obra
                  INNER JOIN materiales m ON dpm.id_material = m.id_material
                  WHERE pm.fecha_pedido BETWEEN ? AND ?";
    if (!empty($obra_id)) {
        $sql_total .= " AND o.id_obra = ?";
    }
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute($params);
    $total_registros = $stmt_total->fetchColumn();
} catch (PDOException $e) {
    $error = "Error al obtener el total de registros: " . $e->getMessage();
}

$total_paginas = ceil($total_registros / $registros_por_pagina);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-bar-chart-line"></i> Materiales por Obra</h1>
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
                    <label for="obra_id" class="form-label">Obra (Opcional)</label>
                    <select class="form-select" id="obra_id" name="obra_id">
                        <option value="">Todas las obras</option>
                        <?php foreach ($obras as $obra): ?>
                        <option value="<?php echo $obra['id']; ?>" 
                                <?php echo ($obra_id == $obra['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($obra['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Generar Reporte
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportarVista()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Vista
                    </button>
                    <button type="button" class="btn btn-info" onclick="exportarPorObra()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Por Obra
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($datos_reporte)): ?>
    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4>$<?php echo number_format($total_general, 2); ?></h4>
                    <p class="mb-0">Valor Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($obras_agrupadas); ?></h4>
                    <p class="mb-0">Obras con Consumo</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?php echo count($datos_reporte); ?></h4>
                    <p class="mb-0">Materiales Diferentes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-pie-chart"></i> Distribución por Obra</h5>
        </div>
        <div class="card-body">
            <div style="max-width:320px; height:320px; margin:auto; display:flex; align-items:center; justify-content:center;">
                <canvas id="graficoObras" width="300" height="300" style="max-width:100%; max-height:300px;"></canvas>
            </div>
            <div style="overflow-x:auto; margin-top:16px;">
                <table class="table table-sm" style="min-width:320px;">
                    <thead>
                        <tr>
                            <th>Obra</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_grafico['labels'] as $i => $obra): ?>
                        <tr>
                            <td style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($obra); ?>">
                                <?php echo htmlspecialchars($obra); ?>
                            </td>
                            <td>$<?php echo number_format($datos_grafico['data'][$i], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabla de Datos -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-table"></i> Detalle de Materiales por Obra</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaReporte">
                    <thead class="table-dark">
                        <tr>
                            <th>Obra</th>
                            <th>Material</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Precio Promedio</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_reporte as $dato): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dato['obra_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($dato['material_nombre']); ?></td>
                            <td><?php echo number_format($dato['total_cantidad'], 2); ?></td>
                            <td><?php echo htmlspecialchars($dato['unidad_medida']); ?></td>
                            <td>$<?php echo number_format($dato['precio_promedio'], 2); ?></td>
                            <td>$<?php echo number_format($dato['valor_total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&obra_id=<?php echo $obra_id; ?>" class="btn btn-outline-primary <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
            <i class="bi bi-arrow-left"></i> Anterior
        </a>
        <span>Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></span>
        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&obra_id=<?php echo $obra_id; ?>" class="btn btn-outline-primary <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
            Siguiente <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php else: ?>
    <div class="alert alert-info text-center">
        <i class="bi bi-info-circle fs-1"></i>
        <h4>No hay datos para mostrar</h4>
        <p>No se encontraron registros para el período y filtros seleccionados.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de distribución por obra
<?php if (!empty($datos_grafico['labels'])): ?>
const ctx = document.getElementById('graficoObras').getContext('2d');
const chart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($datos_grafico['labels']); ?>,
        datasets: [{
            data: <?php echo json_encode($datos_grafico['data']); ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': $' + context.parsed.toLocaleString();
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
    const wb = XLSX.utils.table_to_book(tabla, {sheet: "Materiales por Obra"});
    XLSX.writeFile(wb, 'materiales_por_obra_<?php echo date("Y-m-d"); ?>.xlsx');
}

// Función para exportar vista actual
function exportarVista() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = `exportar_materiales_por_obra.php?${params.toString()}`;
}

// Función para exportar por obra
function exportarPorObra() {
    const obraId = document.getElementById('obra_id').value;
    if (!obraId) {
        alert('Por favor, seleccione una obra para exportar.');
        return;
    }
    const params = new URLSearchParams(window.location.search);
    params.set('obra_id', obraId);
    window.location.href = `exportar_materiales_por_obra.php?${params.toString()}`;
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

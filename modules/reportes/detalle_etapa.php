<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden ver reportes
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = "Detalle de Etapa";
require_once '../../includes/header.php';

// Obtener parámetros
$etapa = $_GET['etapa'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$id_obra = $_GET['id_obra'] ?? '';

// Validar etapa
$etapas_validas = ['aprobacion', 'picking', 'retiro', 'entrega'];
if (!in_array($etapa, $etapas_validas)) {
    redirect(SITE_URL . '/modules/reportes/metricas_pedidos.php');
}

// Configuración de etapas
$config_etapas = [
    'aprobacion' => [
        'titulo' => 'Creación → Aprobación',
        'icon' => 'bi-1-circle',
        'color' => 'info',
        'campo_inicio' => 'fecha_pedido',
        'campo_fin' => 'fecha_aprobacion',
        'usuario_campo' => 'id_aprobado_por',
        'estado_buscado' => 'aprobado'
    ],
    'picking' => [
        'titulo' => 'Aprobación → Picking',
        'icon' => 'bi-2-circle',
        'color' => 'warning',
        'campo_inicio' => 'fecha_aprobacion',
        'campo_fin' => 'fecha_picking',
        'usuario_campo' => 'id_picking_por',
        'estado_buscado' => 'picking'
    ],
    'retiro' => [
        'titulo' => 'Picking → Retiro',
        'icon' => 'bi-3-circle',
        'color' => 'primary',
        'campo_inicio' => 'fecha_picking',
        'campo_fin' => 'fecha_retiro',
        'usuario_campo' => 'id_retirado_por',
        'estado_buscado' => 'retirado'
    ],
    'entrega' => [
        'titulo' => 'Retiro → Entrega',
        'icon' => 'bi-4-circle',
        'color' => 'success',
        'campo_inicio' => 'fecha_retiro',
        'campo_fin' => 'fecha_recibido',
        'usuario_campo' => 'id_recibido_por',
        'estado_buscado' => 'recibido'
    ]
];

$config = $config_etapas[$etapa];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // ==================== TABLA DE PEDIDOS EN LA TRANSICIÓN ====================
    
    $sql_pedidos = "SELECT 
                        p.id_pedido,
                        p.numero_pedido,
                        o.nombre_obra,
                        p.{$config['campo_inicio']} as fecha_inicio,
                        p.{$config['campo_fin']} as fecha_fin,
                        TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']}) as tiempo_horas,
                        p.estado,
                        CONCAT(u.nombre, ' ', u.apellido) as usuario_responsable,
                        p.prioridad
                    FROM pedidos_materiales p
                    LEFT JOIN obras o ON p.id_obra = o.id_obra
                    LEFT JOIN usuarios u ON p.{$config['usuario_campo']} = u.id_usuario
                    WHERE p.{$config['campo_inicio']} IS NOT NULL
                        AND p.{$config['campo_fin']} IS NOT NULL
                        AND p.fecha_pedido BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($id_obra)) {
        $sql_pedidos .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_pedidos .= " ORDER BY tiempo_horas DESC";
    
    $stmt = $conn->prepare($sql_pedidos);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
    
    // ==================== ESTADÍSTICAS DE LA ETAPA ====================
    
    $tiempos_array = array_column($pedidos, 'tiempo_horas');
    $total_pedidos = count($pedidos);
    
    if ($total_pedidos > 0) {
        $tiempo_minimo = min($tiempos_array);
        $tiempo_maximo = max($tiempos_array);
        $tiempo_promedio = array_sum($tiempos_array) / $total_pedidos;
        
        // Calcular mediana
        sort($tiempos_array);
        $middle = floor($total_pedidos / 2);
        $tiempo_mediana = $total_pedidos % 2 == 0 
            ? ($tiempos_array[$middle - 1] + $tiempos_array[$middle]) / 2
            : $tiempos_array[$middle];
        
        // Pedidos atrasados (más de 48 horas)
        $pedidos_atrasados = array_filter($tiempos_array, function($t) { return $t > 48; });
        $total_atrasados = count($pedidos_atrasados);
        $tasa_eficiencia = (($total_pedidos - $total_atrasados) / $total_pedidos) * 100;
    } else {
        $tiempo_minimo = $tiempo_maximo = $tiempo_promedio = $tiempo_mediana = 0;
        $total_atrasados = 0;
        $tasa_eficiencia = 0;
    }
    
    // ==================== ANÁLISIS POR OBRA ====================
    
    $sql_obras = "SELECT 
                    o.nombre_obra,
                    COUNT(p.id_pedido) as total_pedidos,
                    AVG(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_promedio,
                    MIN(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_minimo,
                    MAX(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_maximo
                FROM pedidos_materiales p
                INNER JOIN obras o ON p.id_obra = o.id_obra
                WHERE p.{$config['campo_inicio']} IS NOT NULL
                    AND p.{$config['campo_fin']} IS NOT NULL
                    AND p.fecha_pedido BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($id_obra)) {
        $sql_obras .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_obras .= " GROUP BY o.id_obra
                    ORDER BY tiempo_promedio ASC";
    
    $stmt = $conn->prepare($sql_obras);
    $stmt->execute($params);
    $analisis_obras = $stmt->fetchAll();
    
    // ==================== ANÁLISIS POR RESPONSABLE ====================
    
    $sql_usuarios = "SELECT 
                        CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                        COUNT(p.id_pedido) as total_pedidos,
                        AVG(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_promedio,
                        MIN(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_minimo,
                        MAX(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_maximo
                    FROM pedidos_materiales p
                    INNER JOIN usuarios u ON p.{$config['usuario_campo']} = u.id_usuario
                    WHERE p.{$config['campo_inicio']} IS NOT NULL
                        AND p.{$config['campo_fin']} IS NOT NULL
                        AND p.fecha_pedido BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($id_obra)) {
        $sql_usuarios .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_usuarios .= " GROUP BY u.id_usuario
                       ORDER BY tiempo_promedio ASC";
    
    $stmt = $conn->prepare($sql_usuarios);
    $stmt->execute($params);
    $analisis_usuarios = $stmt->fetchAll();
    
    // ==================== TENDENCIA TEMPORAL ====================
    
    $sql_tendencia = "SELECT 
                        DATE(p.{$config['campo_fin']}) as fecha,
                        AVG(TIMESTAMPDIFF(HOUR, p.{$config['campo_inicio']}, p.{$config['campo_fin']})) as tiempo_promedio,
                        COUNT(*) as cantidad
                    FROM pedidos_materiales p
                    WHERE p.{$config['campo_inicio']} IS NOT NULL
                        AND p.{$config['campo_fin']} IS NOT NULL
                        AND p.fecha_pedido BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($id_obra)) {
        $sql_tendencia .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_tendencia .= " GROUP BY DATE(p.{$config['campo_fin']})
                        ORDER BY fecha ASC";
    
    $stmt = $conn->prepare($sql_tendencia);
    $stmt->execute($params);
    $tendencia_temporal = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>
                    <i class="bi <?php echo $config['icon']; ?> text-<?php echo $config['color']; ?>"></i> 
                    Detalle de Etapa: <?php echo $config['titulo']; ?>
                </h1>
                <a href="metricas_pedidos.php?fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                   class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Resumen
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Período -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-calendar3"></i> <strong>Período:</strong> 
        <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
        <?php if (!empty($id_obra)): ?>
            | <strong>Obra Filtrada</strong>
        <?php endif; ?>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-<?php echo $config['color']; ?>">
                <div class="card-body text-center">
                    <h3 class="text-<?php echo $config['color']; ?>"><?php echo $total_pedidos; ?></h3>
                    <p class="mb-0 text-muted small">Total Pedidos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?php echo number_format($tiempo_promedio, 1); ?>h</h3>
                    <p class="mb-0 text-muted small">Tiempo Promedio</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h3 class="text-info"><?php echo number_format($tiempo_mediana, 1); ?>h</h3>
                    <p class="mb-0 text-muted small">Tiempo Mediana</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?php echo number_format($tiempo_minimo, 1); ?>h</h3>
                    <p class="mb-0 text-muted small">Tiempo Mínimo</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?php echo number_format($tiempo_maximo, 1); ?>h</h3>
                    <p class="mb-0 text-muted small">Tiempo Máximo</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-<?php echo $tasa_eficiencia >= 70 ? 'success' : 'danger'; ?>">
                <div class="card-body text-center">
                    <h3 class="text-<?php echo $tasa_eficiencia >= 70 ? 'success' : 'danger'; ?>">
                        <?php echo number_format($tasa_eficiencia, 1); ?>%
                    </h3>
                    <p class="mb-0 text-muted small">Eficiencia (<48h)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs de Análisis -->
    <ul class="nav nav-tabs mb-3" id="analysisTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos" type="button">
                <i class="bi bi-table"></i> Tabla de Pedidos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="obras-tab" data-bs-toggle="tab" data-bs-target="#obras" type="button">
                <i class="bi bi-building"></i> Por Obra
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button">
                <i class="bi bi-people"></i> Por Responsable
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tendencia-tab" data-bs-toggle="tab" data-bs-target="#tendencia" type="button">
                <i class="bi bi-graph-up"></i> Tendencia Temporal
            </button>
        </li>
    </ul>

    <div class="tab-content" id="analysisTabsContent">
        <!-- Tab: Tabla de Pedidos -->
        <div class="tab-pane fade show active" id="pedidos" role="tabpanel">
            <div class="card">
                <div class="card-header bg-<?php echo $config['color']; ?> text-white">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Pedidos en esta Transición</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pedidos)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Pedido</th>
                                    <th>Obra/Destino</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th class="text-end">Tiempo</th>
                                    <th>Usuario Responsable</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th class="text-center">Atraso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): 
                                    $dias = floor($pedido['tiempo_horas'] / 24);
                                    $horas = fmod($pedido['tiempo_horas'], 24);
                                    $atrasado = $pedido['tiempo_horas'] > 48;
                                ?>
                                <tr class="<?php echo $atrasado ? 'table-warning' : ''; ?>">
                                    <td>
                                        <a href="../pedidos/view.php?id=<?php echo $pedido['id_pedido']; ?>" target="_blank">
                                            <strong><?php echo htmlspecialchars($pedido['numero_pedido']); ?></strong>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($pedido['nombre_obra']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_inicio'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_fin'])); ?></td>
                                    <td class="text-end">
                                        <strong>
                                            <?php 
                                            if ($dias > 0) echo "{$dias}d ";
                                            echo number_format($horas, 1) . "h";
                                            ?>
                                        </strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($pedido['usuario_responsable'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo ucfirst($pedido['estado']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $prioridad_colors = ['baja' => 'success', 'media' => 'warning', 'alta' => 'danger'];
                                        $color = $prioridad_colors[$pedido['prioridad']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($pedido['prioridad']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($atrasado): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-danger" title="Más de 48 horas"></i>
                                        <?php else: ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay pedidos en esta etapa para el período seleccionado.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Por Obra -->
        <div class="tab-pane fade" id="obras" role="tabpanel">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-building"></i> Análisis por Obra</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($analisis_obras)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Obra</th>
                                    <th class="text-center">Total Pedidos</th>
                                    <th class="text-end">Tiempo Promedio</th>
                                    <th class="text-end">Tiempo Mínimo</th>
                                    <th class="text-end">Tiempo Máximo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analisis_obras as $obra): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($obra['nombre_obra']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $obra['total_pedidos']; ?></span>
                                    </td>
                                    <td class="text-end"><?php echo number_format($obra['tiempo_promedio'], 1); ?> horas</td>
                                    <td class="text-end text-success"><?php echo number_format($obra['tiempo_minimo'], 1); ?> horas</td>
                                    <td class="text-end text-danger"><?php echo number_format($obra['tiempo_maximo'], 1); ?> horas</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay datos de obras para mostrar.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Por Responsable -->
        <div class="tab-pane fade" id="usuarios" role="tabpanel">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Rendimiento por Responsable</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($analisis_usuarios)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th class="text-center">Pedidos Procesados</th>
                                    <th class="text-end">Tiempo Promedio</th>
                                    <th class="text-end">Tiempo Mínimo</th>
                                    <th class="text-end">Tiempo Máximo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analisis_usuarios as $usuario): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $usuario['total_pedidos']; ?></span>
                                    </td>
                                    <td class="text-end"><?php echo number_format($usuario['tiempo_promedio'], 1); ?> horas</td>
                                    <td class="text-end text-success"><?php echo number_format($usuario['tiempo_minimo'], 1); ?> horas</td>
                                    <td class="text-end text-danger"><?php echo number_format($usuario['tiempo_maximo'], 1); ?> horas</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay datos de usuarios para mostrar.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Tendencia Temporal -->
        <div class="tab-pane fade" id="tendencia" role="tabpanel">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Tendencia Temporal</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($tendencia_temporal)): ?>
                    <canvas id="chartTendencia" style="max-height: 400px;"></canvas>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay datos suficientes para mostrar tendencia.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($tendencia_temporal)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartTendencia');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($t) { 
                    return date('d/m', strtotime($t['fecha'])); 
                }, $tendencia_temporal)); ?>,
                datasets: [{
                    label: 'Tiempo Promedio (horas)',
                    data: <?php echo json_encode(array_column($tendencia_temporal, 'tiempo_promedio')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Cantidad de Pedidos',
                    data: <?php echo json_encode(array_column($tendencia_temporal, 'cantidad')); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Evolución del Tiempo de Procesamiento'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Horas'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cantidad'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>

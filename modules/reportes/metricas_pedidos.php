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

$page_title = "Métricas y Análisis de Pedidos";
require_once '../../includes/header.php';

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$id_obra = $_GET['id_obra'] ?? '';

// Validar fechas
if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
    $error = "Fechas inválidas proporcionadas.";
} elseif ($fecha_inicio > $fecha_fin) {
    $error = "La fecha de inicio no puede ser mayor que la fecha de fin.";
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // ==================== ESTADÍSTICAS GENERALES ====================
    
    // Total de pedidos por estado
    $sql_estados = "SELECT 
                        estado,
                        COUNT(*) as cantidad
                    FROM pedidos_materiales
                    WHERE fecha_pedido BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($id_obra)) {
        $sql_estados .= " AND id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_estados .= " GROUP BY estado ORDER BY cantidad DESC";
    $stmt = $conn->prepare($sql_estados);
    $stmt->execute($params);
    $pedidos_por_estado = $stmt->fetchAll();
    
    // ==================== TIEMPOS PROMEDIO ENTRE ETAPAS ====================
    
    // Estrategia híbrida: usa seguimiento_pedidos si existe, sino usa columnas directas
    $sql_tiempos = "SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, 
                            p.fecha_pedido,
                            COALESCE(
                                (SELECT MIN(s1.fecha_cambio) FROM seguimiento_pedidos s1 
                                 WHERE s1.id_pedido = p.id_pedido AND s1.estado_nuevo = 'aprobado'),
                                p.fecha_aprobacion,
                                p.fecha_entrega
                            )
                        )) as tiempo_aprobacion,
                        AVG(TIMESTAMPDIFF(HOUR, 
                            COALESCE(
                                (SELECT MIN(s2.fecha_cambio) FROM seguimiento_pedidos s2 
                                 WHERE s2.id_pedido = p.id_pedido AND s2.estado_nuevo = 'aprobado'),
                                p.fecha_aprobacion
                            ),
                            COALESCE(
                                (SELECT MIN(s3.fecha_cambio) FROM seguimiento_pedidos s3 
                                 WHERE s3.id_pedido = p.id_pedido AND s3.estado_nuevo = 'picking'),
                                p.fecha_picking
                            )
                        )) as tiempo_picking,
                        AVG(TIMESTAMPDIFF(HOUR, 
                            COALESCE(
                                (SELECT MIN(s4.fecha_cambio) FROM seguimiento_pedidos s4 
                                 WHERE s4.id_pedido = p.id_pedido AND s4.estado_nuevo = 'picking'),
                                p.fecha_picking
                            ),
                            COALESCE(
                                (SELECT MIN(s5.fecha_cambio) FROM seguimiento_pedidos s5 
                                 WHERE s5.id_pedido = p.id_pedido AND s5.estado_nuevo = 'en_camino'),
                                p.fecha_entrega
                            )
                        )) as tiempo_retiro,
                        AVG(TIMESTAMPDIFF(HOUR, 
                            COALESCE(
                                (SELECT MIN(s6.fecha_cambio) FROM seguimiento_pedidos s6 
                                 WHERE s6.id_pedido = p.id_pedido AND s6.estado_nuevo = 'en_camino'),
                                p.fecha_entrega
                            ),
                            COALESCE(
                                (SELECT MIN(s7.fecha_cambio) FROM seguimiento_pedidos s7 
                                 WHERE s7.id_pedido = p.id_pedido AND s7.estado_nuevo = 'entregado'),
                                p.fecha_entrega
                            )
                        )) as tiempo_entrega,
                        AVG(TIMESTAMPDIFF(HOUR, 
                            p.fecha_pedido,
                            COALESCE(
                                (SELECT MIN(s8.fecha_cambio) FROM seguimiento_pedidos s8 
                                 WHERE s8.id_pedido = p.id_pedido AND s8.estado_nuevo = 'entregado'),
                                p.fecha_entrega
                            )
                        )) as tiempo_total
                    FROM pedidos_materiales p
                    WHERE p.fecha_pedido BETWEEN ? AND ?
                        AND p.estado = 'entregado'";
    
    $params = [$fecha_inicio, $fecha_fin];
    if (!empty($id_obra)) {
        $sql_tiempos .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    $stmt = $conn->prepare($sql_tiempos);
    $stmt->execute($params);
    $tiempos = $stmt->fetch();
    
    // Asegurar valores por defecto si no hay datos
    if (!$tiempos || $tiempos['tiempo_total'] === null) {
        $tiempos = [
            'tiempo_aprobacion' => 0,
            'tiempo_picking' => 0,
            'tiempo_retiro' => 0,
            'tiempo_entrega' => 0,
            'tiempo_total' => 0
        ];
    }
    
    // ==================== PEDIDOS ATRASADOS ====================
    
    // Definir qué es "atrasado" (ejemplo: más de 48 horas en estado pendiente o aprobado)
    $sql_atrasados = "SELECT 
                        p.id_pedido,
                        p.numero_pedido,
                        o.nombre_obra,
                        p.estado,
                        p.fecha_pedido,
                        TIMESTAMPDIFF(HOUR, 
                            CASE 
                                WHEN p.estado = 'pendiente' THEN p.fecha_pedido
                                WHEN p.estado = 'aprobado' THEN COALESCE(p.fecha_aprobacion, p.fecha_pedido)
                                WHEN p.estado = 'retirado' THEN COALESCE(
                                    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s 
                                     WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'retirado'),
                                    p.fecha_aprobacion,
                                    p.fecha_pedido
                                )
                                WHEN p.estado = 'en_camino' THEN COALESCE(
                                    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s 
                                     WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'en_camino'),
                                    p.fecha_pedido
                                )
                            END, 
                            NOW()
                        ) as horas_en_estado
                    FROM pedidos_materiales p
                    INNER JOIN obras o ON p.id_obra = o.id_obra
                    WHERE p.estado IN ('pendiente', 'aprobado', 'retirado', 'en_camino')
                        AND TIMESTAMPDIFF(HOUR, 
                            CASE 
                                WHEN p.estado = 'pendiente' THEN p.fecha_pedido
                                WHEN p.estado = 'aprobado' THEN COALESCE(p.fecha_aprobacion, p.fecha_pedido)
                                WHEN p.estado = 'retirado' THEN COALESCE(
                                    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s 
                                     WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'retirado'),
                                    p.fecha_aprobacion,
                                    p.fecha_pedido
                                )
                                WHEN p.estado = 'en_camino' THEN COALESCE(
                                    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s 
                                     WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'en_camino'),
                                    p.fecha_pedido
                                )
                            END, 
                            NOW()
                        ) > 48";
    
    if (!empty($id_obra)) {
        $sql_atrasados .= " AND p.id_obra = ?";
        $params_atrasados = [$id_obra];
    } else {
        $params_atrasados = [];
    }
    
    $sql_atrasados .= " ORDER BY horas_en_estado DESC";
    $stmt = $conn->prepare($sql_atrasados);
    $stmt->execute($params_atrasados);
    $pedidos_atrasados = $stmt->fetchAll();
    
    // ==================== MATERIALES MÁS PEDIDOS ====================
    
    $sql_materiales = "SELECT 
                        m.nombre_material,
                        m.unidad_medida,
                        COUNT(DISTINCT dpm.id_pedido) as num_pedidos,
                        SUM(dpm.cantidad_solicitada) as cantidad_total,
                        SUM(dpm.cantidad_solicitada * m.precio_referencia) as valor_total
                    FROM detalle_pedidos_materiales dpm
                    INNER JOIN pedidos_materiales p ON dpm.id_pedido = p.id_pedido
                    INNER JOIN materiales m ON dpm.id_material = m.id_material
                    WHERE p.fecha_pedido BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    if (!empty($id_obra)) {
        $sql_materiales .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_materiales .= " GROUP BY m.id_material
                         ORDER BY cantidad_total DESC
                         LIMIT 10";
    $stmt = $conn->prepare($sql_materiales);
    $stmt->execute($params);
    $materiales_top = $stmt->fetchAll();
    
    // ==================== RENDIMIENTO POR OBRA ====================
    
    $sql_obras = "SELECT 
                    o.nombre_obra,
                    COUNT(p.id_pedido) as total_pedidos,
                    SUM(CASE WHEN p.estado IN ('entregado', 'recibido') THEN 1 ELSE 0 END) as pedidos_completados,
                    SUM(CASE WHEN p.estado = 'cancelado' THEN 1 ELSE 0 END) as pedidos_cancelados,
                    AVG(TIMESTAMPDIFF(HOUR, 
                        p.fecha_pedido,
                        COALESCE(
                            (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s 
                             WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'entregado'),
                            p.fecha_entrega
                        )
                    )) as tiempo_promedio,
                    SUM(p.valor_total) as valor_total
                FROM obras o
                LEFT JOIN pedidos_materiales p ON o.id_obra = p.id_obra 
                    AND p.fecha_pedido BETWEEN ? AND ?
                GROUP BY o.id_obra
                HAVING total_pedidos > 0
                ORDER BY total_pedidos DESC
                LIMIT 10";
    
    $stmt = $conn->prepare($sql_obras);
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $obras_ranking = $stmt->fetchAll();
    
    // ==================== TENDENCIA DIARIA ====================
    
    $sql_tendencia = "SELECT 
                        DATE(fecha_pedido) as fecha,
                        COUNT(*) as cantidad
                    FROM pedidos_materiales
                    WHERE fecha_pedido BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    if (!empty($id_obra)) {
        $sql_tendencia .= " AND id_obra = ?";
        $params[] = $id_obra;
    }
    
    $sql_tendencia .= " GROUP BY DATE(fecha_pedido)
                        ORDER BY fecha ASC";
    $stmt = $conn->prepare($sql_tendencia);
    $stmt->execute($params);
    $tendencia_diaria = $stmt->fetchAll();
    
    // Obtener lista de obras para el filtro
    $stmt = $conn->query("SELECT id_obra, nombre_obra FROM obras WHERE estado = 'en_progreso' ORDER BY nombre_obra");
    $obras = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-graph-up-arrow"></i> Métricas y Análisis de Pedidos</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="id_obra" class="form-label">Obra (Opcional)</label>
                    <select class="form-select" id="id_obra" name="id_obra">
                        <option value="">Todas las obras</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?php echo $obra['id_obra']; ?>" 
                                <?php echo ($id_obra == $obra['id_obra']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <?php 
        $total_pedidos = array_sum(array_column($pedidos_por_estado, 'cantidad'));
        $estado_colors = [
            'pendiente' => 'warning',
            'aprobado' => 'info',
            'retirado' => 'warning',
            'entregado' => 'success',
            'cancelado' => 'danger'
        ];
        $estado_icons = [
            'pendiente' => 'clock',
            'aprobado' => 'check-circle',
            'retirado' => 'box-arrow-right',
            'entregado' => 'check-circle-fill',
            'cancelado' => 'x-circle'
        ];
        
        foreach ($pedidos_por_estado as $estado): 
            $color = $estado_colors[$estado['estado']] ?? 'secondary';
            $icon = $estado_icons[$estado['estado']] ?? 'question';
            $porcentaje = $total_pedidos > 0 ? ($estado['cantidad'] / $total_pedidos) * 100 : 0;
        ?>
        <div class="col-md-2">
            <div class="card border-<?php echo $color; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-<?php echo $color; ?>"><?php echo $estado['cantidad']; ?></h3>
                            <p class="mb-0 text-muted"><?php echo ucfirst($estado['estado']); ?></p>
                            <small class="text-muted"><?php echo number_format($porcentaje, 1); ?>%</small>
                        </div>
                        <i class="bi bi-<?php echo $icon; ?> fs-1 text-<?php echo $color; ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="col-md-2">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-primary"><?php echo $total_pedidos; ?></h3>
                            <p class="mb-0 text-muted">Total</p>
                        </div>
                        <i class="bi bi-cart-check fs-1 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Tiempos Promedio -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-stopwatch"></i> Tiempo Promedio Entre Etapas</h5>
                </div>
                <div class="card-body">
                    <?php if ($tiempos && $tiempos['tiempo_total']): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-1-circle text-info"></i> Creación → Aprobación</span>
                            <div>
                                <strong class="me-2"><?php echo number_format($tiempos['tiempo_aprobacion'] ?? 0, 1); ?> horas</strong>
                                <a href="detalle_etapa.php?etapa=aprobacion&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-info" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_aprobacion'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_aprobacion'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-2-circle text-warning"></i> Aprobación → Picking</span>
                            <div>
                                <strong class="me-2"><?php echo number_format($tiempos['tiempo_picking'] ?? 0, 1); ?> horas</strong>
                                <a href="detalle_etapa.php?etapa=picking&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-warning" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_picking'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_picking'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-3-circle text-primary"></i> Picking → Retiro</span>
                            <div>
                                <strong class="me-2"><?php echo number_format($tiempos['tiempo_retiro'] ?? 0, 1); ?> horas</strong>
                                <a href="detalle_etapa.php?etapa=retiro&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-primary" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_retiro'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_retiro'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-4-circle text-success"></i> Retiro → Entrega</span>
                            <div>
                                <strong class="me-2"><?php echo number_format($tiempos['tiempo_entrega'] ?? 0, 1); ?> horas</strong>
                                <a href="detalle_etapa.php?etapa=entrega&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-success" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_entrega'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_entrega'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="alert alert-primary mb-0">
                        <strong><i class="bi bi-clock-history"></i> Tiempo Total Promedio:</strong>
                        <?php 
                        $dias = floor($tiempos['tiempo_total'] / 24);
                        $horas = fmod($tiempos['tiempo_total'], 24);
                        echo $dias > 0 ? "{$dias} días " : "";
                        echo number_format($horas, 1) . " horas";
                        ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay pedidos completados en el período seleccionado.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pedidos Atrasados -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Pedidos Atrasados (>48h)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pedidos_atrasados)): ?>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Pedido</th>
                                    <th>Obra</th>
                                    <th>Estado</th>
                                    <th class="text-end">Tiempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_atrasados as $atrasado): 
                                    $dias_atraso = floor($atrasado['horas_en_estado'] / 24);
                                    $horas_atraso = fmod($atrasado['horas_en_estado'], 24);
                                ?>
                                <tr>
                                    <td>
                                        <a href="../pedidos/view.php?id=<?php echo $atrasado['id_pedido']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($atrasado['numero_pedido']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($atrasado['nombre_obra']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $estado_colors[$atrasado['estado']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($atrasado['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-danger">
                                            <?php 
                                            echo $dias_atraso > 0 ? "{$dias_atraso}d " : "";
                                            echo round($horas_atraso) . "h";
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> ¡Excelente! No hay pedidos atrasados.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Tendencia -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Tendencia de Pedidos por Día</h5>
                </div>
                <div class="card-body">
                    <canvas id="tendenciaChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top 10 Materiales -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Top 10 Materiales Más Pedidos</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($materiales_top)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Material</th>
                                    <th class="text-end">Pedidos</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materiales_top as $index => $material): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($material['nombre_material']); ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-primary"><?php echo $material['num_pedidos']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($material['cantidad_total'], 2); ?> 
                                        <?php echo htmlspecialchars($material['unidad_medida']); ?>
                                    </td>
                                    <td class="text-end">
                                        $<?php echo number_format($material['valor_total'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay datos de materiales en el período seleccionado.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ranking de Obras -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-building"></i> Rendimiento por Obra</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($obras_ranking)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Obra</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Completados</th>
                                    <th class="text-end">% Éxito</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($obras_ranking as $obra): 
                                    $tasa_exito = $obra['total_pedidos'] > 0 
                                        ? ($obra['pedidos_completados'] / $obra['total_pedidos']) * 100 
                                        : 0;
                                    $color_exito = $tasa_exito >= 80 ? 'success' : ($tasa_exito >= 50 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($obra['nombre_obra']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $obra['total_pedidos']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo $obra['pedidos_completados']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $color_exito; ?>">
                                            <?php echo number_format($tasa_exito, 1); ?>%
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        $<?php echo number_format($obra['valor_total'] ?? 0, 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No hay datos de obras en el período seleccionado.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de tendencia
const ctx = document.getElementById('tendenciaChart');
const tendenciaData = <?php echo json_encode($tendencia_diaria); ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: tendenciaData.map(d => d.fecha),
        datasets: [{
            label: 'Pedidos Creados',
            data: tendenciaData.map(d => d.cantidad),
            borderColor: 'rgb(13, 110, 253)',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

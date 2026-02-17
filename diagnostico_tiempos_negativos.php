<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

echo "<h2>Diagnóstico de Tiempos Negativos</h2>";
echo "<hr>";

$fecha_inicio = date('Y-m-d', strtotime('-6 months'));
$fecha_fin = date('Y-m-d');

// Buscar pedidos con fechas en orden incorrecto
echo "<h3>1. Pedidos con fechas en orden incorrecto</h3>";

$sql = "SELECT 
    p.id_pedido,
    p.numero_pedido,
    p.estado,
    p.fecha_pedido,
    p.fecha_aprobacion,
    p.fecha_picking,
    p.fecha_retiro,
    p.fecha_recibido,
    p.fecha_entrega,
    -- Verificaciones de orden
    CASE WHEN p.fecha_aprobacion < p.fecha_pedido THEN 'ERROR' ELSE 'OK' END as orden_aprobacion,
    CASE WHEN p.fecha_picking IS NOT NULL AND p.fecha_aprobacion IS NOT NULL AND p.fecha_picking < p.fecha_aprobacion THEN 'ERROR' ELSE 'OK' END as orden_picking,
    CASE WHEN p.fecha_retiro IS NOT NULL AND p.fecha_picking IS NOT NULL AND p.fecha_retiro < p.fecha_picking THEN 'ERROR' ELSE 'OK' END as orden_retiro,
    CASE WHEN p.fecha_recibido IS NOT NULL AND p.fecha_retiro IS NOT NULL AND p.fecha_recibido < p.fecha_retiro THEN 'ERROR' ELSE 'OK' END as orden_recibido,
    -- Cálculo de horas
    CASE 
        WHEN p.fecha_picking IS NOT NULL AND p.fecha_retiro IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, p.fecha_picking, p.fecha_retiro)
        ELSE NULL 
    END as horas_picking_retiro,
    CASE 
        WHEN p.fecha_retiro IS NOT NULL AND COALESCE(p.fecha_recibido, p.fecha_entrega) IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, p.fecha_retiro, COALESCE(p.fecha_recibido, p.fecha_entrega))
        ELSE NULL 
    END as horas_retiro_entrega
FROM pedidos_materiales p
WHERE p.fecha_pedido BETWEEN ? AND ?
    AND p.estado IN ('entregado', 'recibido')
    AND (
        (p.fecha_aprobacion IS NOT NULL AND p.fecha_aprobacion < p.fecha_pedido)
        OR (p.fecha_picking IS NOT NULL AND p.fecha_aprobacion IS NOT NULL AND p.fecha_picking < p.fecha_aprobacion)
        OR (p.fecha_retiro IS NOT NULL AND p.fecha_picking IS NOT NULL AND p.fecha_retiro < p.fecha_picking)
        OR (p.fecha_recibido IS NOT NULL AND p.fecha_retiro IS NOT NULL AND p.fecha_recibido < p.fecha_retiro)
    )
ORDER BY p.fecha_pedido DESC
LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$pedidos_incorrectos = $stmt->fetchAll();

if (!empty($pedidos_incorrectos)) {
    echo "<div style='color: red;'><strong>¡ENCONTRADOS " . count($pedidos_incorrectos) . " PEDIDOS CON FECHAS EN ORDEN INCORRECTO!</strong></div>";
    echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
    echo "<tr>";
    echo "<th>Pedido</th>";
    echo "<th>Estado</th>";
    echo "<th>Creación</th>";
    echo "<th>Aprobación</th>";
    echo "<th>Picking</th>";
    echo "<th>Retiro</th>";
    echo "<th>Recibido</th>";
    echo "<th>Picking→Retiro<br>(horas)</th>";
    echo "<th>Retiro→Entrega<br>(horas)</th>";
    echo "<th>Problemas</th>";
    echo "</tr>";
    
    foreach ($pedidos_incorrectos as $p) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($p['numero_pedido']) . "</strong></td>";
        echo "<td>" . $p['estado'] . "</td>";
        echo "<td>" . ($p['fecha_pedido'] ?? '-') . "</td>";
        echo "<td style='background-color: " . ($p['orden_aprobacion'] == 'ERROR' ? '#ffcccc' : '#ccffcc') . "'>" . ($p['fecha_aprobacion'] ?? '-') . "</td>";
        echo "<td style='background-color: " . ($p['orden_picking'] == 'ERROR' ? '#ffcccc' : '#ccffcc') . "'>" . ($p['fecha_picking'] ?? '-') . "</td>";
        echo "<td style='background-color: " . ($p['orden_retiro'] == 'ERROR' ? '#ffcccc' : '#ccffcc') . "'>" . ($p['fecha_retiro'] ?? '-') . "</td>";
        echo "<td style='background-color: " . ($p['orden_recibido'] == 'ERROR' ? '#ffcccc' : '#ccffcc') . "'>" . ($p['fecha_recibido'] ?? ($p['fecha_entrega'] ?? '-')) . "</td>";
        
        $horas_pr = $p['horas_picking_retiro'];
        $color_pr = ($horas_pr !== null && $horas_pr < 0) ? '#ffcccc' : 'white';
        echo "<td style='background-color: $color_pr'>" . ($horas_pr !== null ? number_format($horas_pr, 1) : '-') . "</td>";
        
        $horas_re = $p['horas_retiro_entrega'];
        $color_re = ($horas_re !== null && $horas_re < 0) ? '#ffcccc' : 'white';
        echo "<td style='background-color: $color_re'>" . ($horas_re !== null ? number_format($horas_re, 1) : '-') . "</td>";
        
        $problemas = [];
        if ($p['orden_aprobacion'] == 'ERROR') $problemas[] = 'Aprobación';
        if ($p['orden_picking'] == 'ERROR') $problemas[] = 'Picking';
        if ($p['orden_retiro'] == 'ERROR') $problemas[] = 'Retiro';
        if ($p['orden_recibido'] == 'ERROR') $problemas[] = 'Recibido';
        echo "<td style='color: red;'>" . implode(', ', $problemas) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✓ No se encontraron pedidos con fechas en orden incorrecto en las columnas directas.</p>";
}

echo "<br><hr>";

// Verificar datos de seguimiento_pedidos
echo "<h3>2. Análisis de seguimiento_pedidos (últimos 20 pedidos entregados)</h3>";

$sql_seguimiento = "SELECT 
    p.id_pedido,
    p.numero_pedido,
    p.estado,
    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'aprobado') as seg_aprobado,
    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'picking') as seg_picking,
    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'retirado') as seg_retirado,
    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'en_camino') as seg_en_camino,
    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'recibido') as seg_recibido,
    (SELECT MIN(s.fecha_cambio) FROM seguimiento_pedidos s WHERE s.id_pedido = p.id_pedido AND s.estado_nuevo = 'entregado') as seg_entregado,
    p.fecha_aprobacion as col_aprobacion,
    p.fecha_picking as col_picking,
    p.fecha_retiro as col_retiro,
    p.fecha_recibido as col_recibido,
    p.fecha_entrega as col_entrega
FROM pedidos_materiales p
WHERE p.fecha_pedido BETWEEN ? AND ?
    AND p.estado IN ('entregado', 'recibido')
ORDER BY p.fecha_pedido DESC
LIMIT 20";

$stmt = $conn->prepare($sql_seguimiento);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$seguimientos = $stmt->fetchAll();

if (!empty($seguimientos)) {
    echo "<table border='1' style='border-collapse: collapse; font-size: 11px;'>";
    echo "<tr>";
    echo "<th rowspan='2'>Pedido</th>";
    echo "<th colspan='6'>Seguimiento_pedidos</th>";
    echo "<th colspan='5'>Columnas directas</th>";
    echo "</tr>";
    echo "<tr>";
    echo "<th>Aprobado</th>";
    echo "<th>Picking</th>";
    echo "<th>Retirado</th>";
    echo "<th>En Camino</th>";
    echo "<th>Recibido</th>";
    echo "<th>Entregado</th>";
    echo "<th>Aprobación</th>";
    echo "<th>Picking</th>";
    echo "<th>Retiro</th>";
    echo "<th>Recibido</th>";
    echo "<th>Entrega</th>";
    echo "</tr>";
    
    foreach ($seguimientos as $s) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($s['numero_pedido']) . "</strong></td>";
        echo "<td>" . ($s['seg_aprobado'] ? date('d/m H:i', strtotime($s['seg_aprobado'])) : '-') . "</td>";
        echo "<td>" . ($s['seg_picking'] ? date('d/m H:i', strtotime($s['seg_picking'])) : '-') . "</td>";
        echo "<td>" . ($s['seg_retirado'] ? date('d/m H:i', strtotime($s['seg_retirado'])) : '-') . "</td>";
        echo "<td>" . ($s['seg_en_camino'] ? date('d/m H:i', strtotime($s['seg_en_camino'])) : '-') . "</td>";
        echo "<td>" . ($s['seg_recibido'] ? date('d/m H:i', strtotime($s['seg_recibido'])) : '-') . "</td>";
        echo "<td>" . ($s['seg_entregado'] ? date('d/m H:i', strtotime($s['seg_entregado'])) : '-') . "</td>";
        echo "<td>" . ($s['col_aprobacion'] ? date('d/m H:i', strtotime($s['col_aprobacion'])) : '-') . "</td>";
        echo "<td>" . ($s['col_picking'] ? date('d/m H:i', strtotime($s['col_picking'])) : '-') . "</td>";
        echo "<td>" . ($s['col_retiro'] ? date('d/m H:i', strtotime($s['col_retiro'])) : '-') . "</td>";
        echo "<td>" . ($s['col_recibido'] ? date('d/m H:i', strtotime($s['col_recibido'])) : '-') . "</td>";
        echo "<td>" . ($s['col_entrega'] ? date('d/m H:i', strtotime($s['col_entrega'])) : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><hr>";

// Calcular promedios con la lógica ACTUAL para ver qué está pasando
echo "<h3>3. Cálculo de promedios (lógica actual)</h3>";

$sql_promedios = "SELECT 
    COUNT(*) as total_pedidos,
    AVG(CASE 
        WHEN COALESCE(
            (SELECT MIN(s4.fecha_cambio) FROM seguimiento_pedidos s4 
             WHERE s4.id_pedido = p.id_pedido AND s4.estado_nuevo = 'picking'),
            p.fecha_picking
        ) IS NOT NULL 
        AND COALESCE(
            (SELECT MIN(s5.fecha_cambio) FROM seguimiento_pedidos s5 
             WHERE s5.id_pedido = p.id_pedido AND s5.estado_nuevo IN ('retirado', 'en_camino')),
            p.fecha_retiro
        ) IS NOT NULL
        THEN TIMESTAMPDIFF(HOUR, 
            COALESCE(
                (SELECT MIN(s4.fecha_cambio) FROM seguimiento_pedidos s4 
                 WHERE s4.id_pedido = p.id_pedido AND s4.estado_nuevo = 'picking'),
                p.fecha_picking
            ),
            COALESCE(
                (SELECT MIN(s5.fecha_cambio) FROM seguimiento_pedidos s5 
                 WHERE s5.id_pedido = p.id_pedido AND s5.estado_nuevo IN ('retirado', 'en_camino')),
                p.fecha_retiro
            )
        )
        ELSE NULL 
    END) as tiempo_picking_retiro,
    SUM(CASE 
        WHEN COALESCE(
            (SELECT MIN(s4.fecha_cambio) FROM seguimiento_pedidos s4 
             WHERE s4.id_pedido = p.id_pedido AND s4.estado_nuevo = 'picking'),
            p.fecha_picking
        ) IS NOT NULL 
        AND COALESCE(
            (SELECT MIN(s5.fecha_cambio) FROM seguimiento_pedidos s5 
             WHERE s5.id_pedido = p.id_pedido AND s5.estado_nuevo IN ('retirado', 'en_camino')),
            p.fecha_retiro
        ) IS NOT NULL
        THEN 1 ELSE 0 
    END) as con_datos_picking_retiro
FROM pedidos_materiales p
WHERE p.fecha_pedido BETWEEN ? AND ?
    AND p.estado IN ('entregado', 'recibido')";

$stmt = $conn->prepare($sql_promedios);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$promedios = $stmt->fetch();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Métrica</th><th>Valor</th></tr>";
echo "<tr><td>Total de pedidos</td><td>{$promedios['total_pedidos']}</td></tr>";
echo "<tr><td>Pedidos con datos Picking→Retiro</td><td>{$promedios['con_datos_picking_retiro']}</td></tr>";
echo "<tr><td>Tiempo promedio Picking→Retiro</td><td style='background-color: " . ($promedios['tiempo_picking_retiro'] < 0 ? '#ffcccc' : 'white') . "'>" . 
    ($promedios['tiempo_picking_retiro'] !== null ? number_format($promedios['tiempo_picking_retiro'], 2) . ' horas' : 'NULL') . "</td></tr>";
echo "</table>";

echo "<br>";
echo "<p><a href='modules/reportes/metricas_pedidos.php'>← Volver a Métricas</a></p>";
?>

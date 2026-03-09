<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');

$id_material  = (int)($_GET['id_material']  ?? 0);
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-t');

if (!$id_material) {
    echo json_encode(['error' => 'Material no especificado']);
    exit();
}

// Validar fechas
if (!strtotime($fecha_inicio) || !strtotime($fecha_fin) || $fecha_inicio > $fecha_fin) {
    echo json_encode(['error' => 'Fechas inválidas']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $sql = "SELECT
                pm.id_pedido,
                pm.fecha_pedido,
                pm.estado       AS estado_pedido,
                pm.prioridad,
                o.id_obra,
                o.nombre_obra,
                dpm.cantidad_solicitada,
                -- Si el pedido fue recibido/entregado en obra, se entregó todo lo solicitado
                CASE
                    WHEN pm.estado IN ('recibido', 'entregado') THEN dpm.cantidad_solicitada
                    ELSE dpm.cantidad_entregada
                END AS cantidad_entregada_efectiva,
                -- Y el faltante es cero
                CASE
                    WHEN pm.estado IN ('recibido', 'entregado') THEN 0
                    ELSE dpm.cantidad_faltante
                END AS cantidad_faltante_efectiva
            FROM detalle_pedidos_materiales dpm
            INNER JOIN pedidos_materiales pm ON dpm.id_pedido = pm.id_pedido
            INNER JOIN obras o ON pm.id_obra = o.id_obra
            WHERE dpm.id_material = ?
              AND pm.fecha_pedido BETWEEN ? AND ?
            ORDER BY o.nombre_obra ASC, pm.fecha_pedido ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_material, $fecha_inicio, $fecha_fin]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por obra
    $obras = [];
    foreach ($rows as $row) {
        $key = $row['id_obra'];
        if (!isset($obras[$key])) {
            $obras[$key] = [
                'id_obra'          => $row['id_obra'],
                'nombre_obra'      => $row['nombre_obra'],
                'total_solicitado' => 0,
                'total_entregado'  => 0,
                'total_faltante'   => 0,
                'pedidos'          => []
            ];
        }
        // Los cancelados se listan para info visual pero no suman a los totales
        if ($row['estado_pedido'] !== 'cancelado') {
            $obras[$key]['total_solicitado'] += (float)$row['cantidad_solicitada'];
            $obras[$key]['total_entregado']  += (float)$row['cantidad_entregada_efectiva'];
            $obras[$key]['total_faltante']   += (float)$row['cantidad_faltante_efectiva'];
        }
        $obras[$key]['pedidos'][] = [
            'id_pedido'          => $row['id_pedido'],
            'fecha_pedido'       => $row['fecha_pedido'],
            'estado_pedido'      => $row['estado_pedido'],
            'prioridad'          => $row['prioridad'],
            'cantidad_solicitada'=> (float)$row['cantidad_solicitada'],
            'cantidad_entregada' => (float)$row['cantidad_entregada_efectiva'],
            'cantidad_faltante'  => (float)$row['cantidad_faltante_efectiva']
        ];
    }

    echo json_encode([
        'success'       => true,
        'obras'         => array_values($obras),
        'total_pedidos' => count($rows)
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener datos: ' . $e->getMessage()]);
}

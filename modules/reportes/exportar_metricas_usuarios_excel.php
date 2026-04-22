<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-3 months'));
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');
$id_usuario   = isset($_GET['id_usuario']) && is_numeric($_GET['id_usuario'])
                    ? (int)$_GET['id_usuario'] : 0;
$id_obra      = isset($_GET['id_obra']) && is_numeric($_GET['id_obra'])
                    ? (int)$_GET['id_obra'] : 0;

$fi_dt = $fecha_inicio . ' 00:00:00';
$ff_dt = $fecha_fin    . ' 23:59:59';

function xenc(string $text): string {
    return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
}
function fmt_dec(?float $n, int $dec = 1): string {
    if ($n === null) return '-';
    return number_format((float)$n, $dec, ',', '');
}
function fmt_h(?float $h): string {
    if ($h === null || $h < 0) return '-';
    if ($h < 1) return round($h * 60) . ' min';
    return fmt_dec((float)$h, 1) . ' h';
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ── Métricas de tareas ─────────────────────────────────────────────────
    $w_usr  = $id_usuario ? 'AND u.id_usuario = ?' : '';
    $w_obra = $id_obra    ? 'AND p.id_obra = ?'  : '';
    $p_t    = [$fi_dt, $ff_dt];
    if ($id_usuario) $p_t[] = $id_usuario;

    $stmt = $conn->prepare("
        SELECT
            u.id_usuario, u.nombre, u.apellido, u.rol,
            COUNT(t.id_tarea)                                                              AS total_tareas,
            SUM(t.estado = 'finalizada')                                                   AS finalizadas,
            SUM(t.estado IN ('pendiente','en_proceso'))                                    AS activas,
            SUM(t.estado = 'cancelada')                                                    AS canceladas,
            SUM(t.estado = 'finalizada'
                AND (t.fecha_vencimiento IS NULL
                     OR t.fecha_finalizacion <= t.fecha_vencimiento))                      AS a_tiempo,
            SUM(t.estado = 'finalizada'
                AND t.fecha_vencimiento IS NOT NULL
                AND t.fecha_finalizacion > t.fecha_vencimiento)                            AS con_retraso,
            ROUND(AVG(CASE
                WHEN t.tiempo_estimado > 0 AND t.tiempo_real > 0
                THEN (t.tiempo_real / t.tiempo_estimado) * 100
            END), 1)                                                                        AS ratio_eficiencia,
            ROUND(AVG(CASE WHEN t.tiempo_real > 0 THEN t.tiempo_real END), 1)             AS promedio_hrs_real,
            COALESCE(SUM(t.tiempo_real), 0)                                                AS total_hrs,
            ROUND(AVG(CASE
                WHEN t.fecha_inicio IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, t.fecha_asignacion, t.fecha_inicio) / 60.0
            END), 1)                                                                        AS promedio_reaccion_hrs
        FROM usuarios u
        INNER JOIN tareas t ON t.id_empleado = u.id_usuario
        WHERE u.estado = 'activo'
          AND t.fecha_asignacion BETWEEN ? AND ?
          $w_usr
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.rol
        ORDER BY finalizadas DESC, total_tareas DESC
    ");
    $stmt->execute($p_t);
    $metricas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexar
    $datos = [];
    foreach ($metricas as $r) {
        $datos[$r['id_usuario']] = array_merge($r, ['etapas' => [], 'prestamos' => []]);
    }

    // ── Etapas de pedidos ──────────────────────────────────────────────────
    $w_ap = $id_usuario ? 'AND p.id_aprobado_por = ?' : '';
    $w_re = $id_usuario ? 'AND p.id_retirado_por  = ?' : '';
    $w_rc = $id_usuario ? 'AND p.id_recibido_por  = ?' : '';
    $p_e  = [$fecha_inicio, $fecha_fin];
    if ($id_usuario) $p_e[] = $id_usuario;
    if ($id_obra)    $p_e[] = $id_obra;
    $p_et = array_merge($p_e, $p_e, $p_e);

    $stmt_e = $conn->prepare("
        SELECT id_usuario, etapa, cantidad, ROUND(AVG(promedio_hrs) OVER (PARTITION BY id_usuario, etapa), 1) AS promedio_hrs
        FROM (
            SELECT p.id_aprobado_por AS id_usuario, 'aprobacion' AS etapa,
                   COUNT(*) AS cantidad,
                   AVG(TIMESTAMPDIFF(MINUTE, p.fecha_pedido, p.fecha_aprobacion) / 60.0) AS promedio_hrs
            FROM pedidos_materiales p
            WHERE p.fecha_aprobacion IS NOT NULL AND DATE(p.fecha_pedido) BETWEEN ? AND ? $w_ap $w_obra
            GROUP BY p.id_aprobado_por
            UNION ALL
            SELECT p.id_retirado_por, 'retiro', COUNT(*),
                   AVG(TIMESTAMPDIFF(MINUTE, p.fecha_aprobacion, p.fecha_retiro) / 60.0)
            FROM pedidos_materiales p
            WHERE p.fecha_retiro IS NOT NULL AND p.fecha_aprobacion IS NOT NULL AND DATE(p.fecha_pedido) BETWEEN ? AND ? $w_re $w_obra
            GROUP BY p.id_retirado_por
            UNION ALL
            SELECT p.id_recibido_por, 'recibido', COUNT(*),
                   AVG(TIMESTAMPDIFF(MINUTE, p.fecha_retiro, p.fecha_recibido) / 60.0)
            FROM pedidos_materiales p
            WHERE p.fecha_recibido IS NOT NULL AND p.fecha_retiro IS NOT NULL AND DATE(p.fecha_pedido) BETWEEN ? AND ? $w_rc $w_obra
            GROUP BY p.id_recibido_por
        ) sub
        GROUP BY id_usuario, etapa, cantidad, promedio_hrs
    ");
    $stmt_e->execute($p_et);
    foreach ($stmt_e->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = $row['id_usuario'];
        if (!isset($datos[$uid])) continue;
        $datos[$uid]['etapas'][$row['etapa']] = [
            'cantidad'     => (int)$row['cantidad'],
            'promedio_hrs' => (float)$row['promedio_hrs'],
        ];
    }

    // ── Préstamos ──────────────────────────────────────────────────────────
    $w_pr = $id_usuario ? 'AND id_empleado = ?' : '';
    $p_pr = [$fecha_inicio, $fecha_fin];
    if ($id_usuario) $p_pr[] = $id_usuario;

    $stmt_pr = $conn->prepare("
        SELECT id_empleado AS id_usuario,
               COUNT(*) AS total_prestamos,
               SUM(estado = 'devuelto') AS devueltos,
               SUM(estado = 'activo')   AS activos,
               SUM(estado = 'vencido')  AS vencidos
        FROM prestamos
        WHERE DATE(fecha_retiro) BETWEEN ? AND ? $w_pr
        GROUP BY id_empleado
    ");
    $stmt_pr->execute($p_pr);
    foreach ($stmt_pr->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($datos[$row['id_usuario']])) {
            $datos[$row['id_usuario']]['prestamos'] = $row;
        }
    }

} catch (Exception $e) {
    die('Error al generar el reporte: ' . $e->getMessage());
}

// ── Headers Excel ──────────────────────────────────────────────────────────
header('Content-Type: application/vnd.ms-excel; charset=Windows-1252');
header('Content-Disposition: attachment; filename="metricas_usuarios_' . date('Y-m-d_His') . '.xls"');
header('Cache-Control: max-age=0');

$cols = 16;
$th   = "style='background-color:#0d6efd;color:white;font-weight:bold;border:1px solid #0a58ca;padding:6px;text-align:center;'";
$td   = "style='border:1px solid #dee2e6;padding:4px 6px;'";
$tdc  = "style='border:1px solid #dee2e6;padding:4px 6px;text-align:center;'";
$tdh  = "style='background-color:#e9ecef;border:1px solid #dee2e6;font-weight:bold;padding:6px;text-align:center;'";

$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$logo_url  = $protocolo . '://' . $_SERVER['HTTP_HOST'] . '/constructora_v2/assets/img/logo_san_simon.png';

$periodo   = date('d/m/Y', strtotime($fecha_inicio)) . ' — ' . date('d/m/Y', strtotime($fecha_fin));
$generado  = date('d/m/Y H:i');

echo "<table border='0' cellpadding='0' cellspacing='0' style='font-family:Arial,sans-serif;font-size:11px;width:100%;'>";

// Logo + cabecera
echo "<tr><td colspan='$cols' align='center' style='padding:10px 4px 4px;'>
        <img src='$logo_url' height='55' alt='Logo'/>
      </td></tr>";
echo "<tr><td colspan='$cols' align='center' style='font-size:9px;color:#555;padding:0 4px 8px;letter-spacing:1px;'>"
     . xenc('SAN SIMON SRL - EMPRESA CONSTRUCTORA') . "</td></tr>";
echo "<tr><td colspan='$cols' align='center' style='font-size:15px;font-weight:bold;border-top:2px solid #0d6efd;padding:6px;'>"
     . xenc('Métricas de Eficiencia por Usuario') . "</td></tr>";
echo "<tr><td colspan='$cols' align='center' style='font-size:11px;padding:2px;'>"
     . xenc("Período: $periodo") . "</td></tr>";
echo "<tr><td colspan='$cols' align='center' style='font-size:11px;padding:2px 2px 10px;'>"
     . xenc("Generado: $generado") . "</td></tr>";

// Fila de totales globales
$total_u   = count($datos);
$total_fin = array_sum(array_column(array_values($datos), 'finalizadas'));
echo "<tr>";
echo "<td colspan='5' $tdh>" . xenc("Usuarios evaluados: $total_u") . "</td>";
echo "<td colspan='5' $tdh>" . xenc("Tareas finalizadas: $total_fin") . "</td>";
echo "<td colspan='6' $tdh>" . xenc("Exportado: $generado") . "</td>";
echo "</tr>";
echo "<tr><td colspan='$cols' style='padding:4px;'>&nbsp;</td></tr>";

// Encabezados
echo "<tr>";
$headers = [
    'Usuario','Rol',
    'Total Tareas','Finalizadas','Activas','Canceladas',
    'A Tiempo','Con Retraso','% Cumplimiento',
    'Eficiencia (real/estim.)',
    'Prom. hrs/tarea','Total hrs acum.','T. Reacción prom.',
    'Aprobaciones (cant./prom.hrs)',
    'Retiros (cant./prom.hrs)',
    'Recepciones (cant./prom.hrs)',
];
foreach ($headers as $h) {
    echo "<th $th>" . xenc($h) . "</th>";
}
echo "</tr>";

// Filas de datos
foreach ($datos as $d) {
    $pct_cumpl = ($d['finalizadas'] > 0)
        ? fmt_dec((($d['a_tiempo'] / $d['finalizadas']) * 100), 1) . '%' : '-';

    $rol_label = match($d['rol']) {
        'administrador'    => 'Admin',
        'responsable_obra' => 'Responsable',
        'empleado'         => 'Empleado',
        default            => $d['rol'],
    };

    $etapa_str = function(string $etapa) use ($d): string {
        $e = $d['etapas'][$etapa] ?? null;
        if (!$e) return '-';
        return $e['cantidad'] . ' / ' . fmt_h($e['promedio_hrs']);
    };

    echo "<tr>";
    echo "<td $td>" . xenc($d['nombre'] . ' ' . $d['apellido']) . "</td>";
    echo "<td $tdc>" . xenc($rol_label) . "</td>";
    echo "<td $tdc>" . $d['total_tareas']  . "</td>";
    echo "<td $tdc>" . $d['finalizadas']   . "</td>";
    echo "<td $tdc>" . $d['activas']       . "</td>";
    echo "<td $tdc>" . $d['canceladas']    . "</td>";
    echo "<td $tdc>" . $d['a_tiempo']      . "</td>";
    echo "<td $tdc>" . $d['con_retraso']   . "</td>";
    echo "<td $tdc>" . xenc($pct_cumpl)    . "</td>";
    echo "<td $tdc>" . ($d['ratio_eficiencia'] !== null ? fmt_dec((float)$d['ratio_eficiencia'], 1) . '%' : '-') . "</td>";
    echo "<td $tdc>" . xenc(fmt_h($d['promedio_hrs_real']))       . "</td>";
    echo "<td $tdc>" . xenc(fmt_h((float)$d['total_hrs']))        . "</td>";
    echo "<td $tdc>" . xenc(fmt_h($d['promedio_reaccion_hrs']))   . "</td>";
    echo "<td $tdc>" . xenc($etapa_str('aprobacion')) . "</td>";
    echo "<td $tdc>" . xenc($etapa_str('retiro'))     . "</td>";
    echo "<td $tdc>" . xenc($etapa_str('recibido'))   . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='$cols' style='padding:8px;'>&nbsp;</td></tr>";

// Leyenda eficiencia
echo "<tr><td colspan='$cols' style='padding:4px;font-style:italic;color:#555;'>"
     . xenc('Eficiencia: <100% = terminó antes de lo estimado (óptimo) | 100% = exacto | >100% = tardó más de lo estimado') . "</td></tr>";
echo "<tr><td colspan='$cols' style='padding:4px;font-style:italic;color:#555;'>"
     . xenc('T. Reacción: tiempo entre asignación de la tarea e inicio efectivo.') . "</td></tr>";

echo "</table>";
exit;

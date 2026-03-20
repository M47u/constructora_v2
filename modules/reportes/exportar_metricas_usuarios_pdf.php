<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../lib/fpdf/fpdf.php';

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

function f(string $text, int $max = 0): string {
    $t = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text ?? '-');
    return $max > 0 ? substr($t, 0, $max) : $t;
}
function fh(?float $h): string {
    if ($h === null || $h < 0) return '-';
    if ($h < 1) return round($h * 60) . 'min';
    return number_format($h, 1) . 'h';
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Métricas de tareas
    $w_usr = $id_usuario ? 'AND u.id_usuario = ?' : '';
    $p_t   = [$fi_dt, $ff_dt];
    if ($id_usuario) $p_t[] = $id_usuario;

    $stmt = $conn->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido, u.rol,
               COUNT(t.id_tarea)                                                  AS total_tareas,
               SUM(t.estado = 'finalizada')                                       AS finalizadas,
               SUM(t.estado IN ('pendiente','en_proceso'))                        AS activas,
               SUM(t.estado = 'finalizada'
                   AND (t.fecha_vencimiento IS NULL
                        OR t.fecha_finalizacion <= t.fecha_vencimiento))          AS a_tiempo,
               SUM(t.estado = 'finalizada'
                   AND t.fecha_vencimiento IS NOT NULL
                   AND t.fecha_finalizacion > t.fecha_vencimiento)                AS con_retraso,
               ROUND(AVG(CASE
                   WHEN t.tiempo_estimado > 0 AND t.tiempo_real > 0
                   THEN (t.tiempo_real / t.tiempo_estimado) * 100
               END), 1)                                                            AS ratio_eficiencia,
               COALESCE(SUM(t.tiempo_real), 0)                                    AS total_hrs,
               ROUND(AVG(CASE
                   WHEN t.fecha_inicio IS NOT NULL
                   THEN TIMESTAMPDIFF(MINUTE, t.fecha_asignacion, t.fecha_inicio) / 60.0
               END), 1)                                                            AS promedio_reaccion_hrs
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

    $datos = [];
    foreach ($metricas as $r) {
        $datos[$r['id_usuario']] = array_merge($r, ['etapas' => []]);
    }

    // Etapas de pedidos
    $w_ap   = $id_usuario ? 'AND p.id_aprobado_por = ?' : '';
    $w_re   = $id_usuario ? 'AND p.id_retirado_por  = ?' : '';
    $w_rc   = $id_usuario ? 'AND p.id_recibido_por  = ?' : '';
    $w_obra = $id_obra    ? 'AND p.id_obra = ?'          : '';
    $p_e    = [$fecha_inicio, $fecha_fin];
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
        if (isset($datos[$row['id_usuario']])) {
            $datos[$row['id_usuario']]['etapas'][$row['etapa']] = [
                'cantidad'     => (int)$row['cantidad'],
                'promedio_hrs' => (float)$row['promedio_hrs'],
            ];
        }
    }

} catch (Exception $e) {
    die('Error al generar PDF: ' . $e->getMessage());
}

// ── Clase FPDF personalizada ──────────────────────────────────────────────
class PDF_Metricas extends FPDF
{
    public string $periodo = '';
    public string $generado = '';

    function Header()
    {
        $logo = __DIR__ . '/../../assets/img/logo_san_simon.png';
        if (file_exists($logo)) {
            $this->Image($logo, 10, 8, 22);
        }
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(13, 110, 253);
        $this->SetXY(35, 10);
        $this->Cell(0, 6, f('Métricas de Eficiencia por Usuario'), 0, 1, 'L');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(35);
        $this->Cell(0, 5, f('SAN SIMON SRL  |  Período: ' . $this->periodo . '  |  Generado: ' . $this->generado), 0, 1, 'L');
        $this->SetDrawColor(13, 110, 253);
        $this->SetLineWidth(0.5);
        $this->Line(10, 24, 200, 24);
        $this->Ln(4);
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, f('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
    }

    function FillHeader(array $headers, array $widths, array $aligns = []): void
    {
        $this->SetFont('Helvetica', 'B', 7);
        $this->SetFillColor(13, 110, 253);
        $this->SetTextColor(255, 255, 255);
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 6, f($h), 1, 0, $aligns[$i] ?? 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }

    function DataRow(array $cells, array $widths, array $aligns = [], bool $alt = false): void
    {
        $this->SetFont('Helvetica', '', 7);
        $this->SetFillColor($alt ? 240 : 255, $alt ? 244 : 255, $alt ? 255 : 255);
        foreach ($cells as $i => $c) {
            $this->Cell($widths[$i], 5, f((string)$c, 40), 1, 0, $aligns[$i] ?? 'L', true);
        }
        $this->Ln();
    }
}

$pdf = new PDF_Metricas('L', 'mm', 'A4');
$pdf->periodo  = date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));
$pdf->generado = date('d/m/Y H:i');
$pdf->AliasNbPages();
$pdf->AddPage();

// ── Tabla principal de métricas ───────────────────────────────────────────
$hdrs = [
    'Usuario', 'Rol',
    'Total\nTareas', 'Finz.', 'Activ.', 'A Tiempo', 'C/Retr.',
    '% Cumpl.', 'Efic. %', 'Total hrs', 'T. Reac.',
    'Aprob.\ncant/h', 'Retiro\ncant/h', 'Recepc.\ncant/h',
];
$w = [36, 22, 13, 11, 11, 13, 12, 14, 13, 14, 14, 20, 20, 20];
$a = ['L','C','C','C','C','C','C','C','C','C','C','C','C','C'];

$pdf->FillHeader($hdrs, $w, $a);

$alt = false;
foreach ($datos as $d) {
    $pct = $d['finalizadas'] > 0
        ? round(($d['a_tiempo'] / $d['finalizadas']) * 100) . '%' : '-';
    $eff  = $d['ratio_eficiencia'] !== null ? $d['ratio_eficiencia'] . '%' : '-';

    $rol_label = match($d['rol']) {
        'administrador'    => 'Admin',
        'responsable_obra' => 'Responsable',
        'empleado'         => 'Empleado',
        default            => $d['rol'],
    };

    $etapa = function(string $et) use ($d): string {
        $e = $d['etapas'][$et] ?? null;
        return $e ? $e['cantidad'] . '/' . fh($e['promedio_hrs']) : '-';
    };

    $row = [
        $d['nombre'] . ' ' . $d['apellido'],
        $rol_label,
        $d['total_tareas'], $d['finalizadas'], $d['activas'],
        $d['a_tiempo'], $d['con_retraso'],
        $pct, $eff,
        fh((float)$d['total_hrs']),
        fh($d['promedio_reaccion_hrs']),
        $etapa('aprobacion'), $etapa('retiro'), $etapa('recibido'),
    ];

    // Nueva página si queda poco espacio
    if ($pdf->GetY() > 175) $pdf->AddPage();

    $pdf->DataRow($row, $w, $a, $alt);
    $alt = !$alt;
}

// ── Nota al pie ───────────────────────────────────────────────────────────
$pdf->Ln(4);
$pdf->SetFont('Helvetica', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 4, f(
    'Eficiencia: <100% = terminó antes de lo estimado (óptimo) | 100% = exacto | >100% = tardó más de lo estimado. ' .
    'T. Reac. = tiempo entre asignación e inicio efectivo de la tarea.'
), 0, 'L');

$pdf->Output('I', 'metricas_usuarios_' . date('Y-m-d') . '.pdf');
exit;

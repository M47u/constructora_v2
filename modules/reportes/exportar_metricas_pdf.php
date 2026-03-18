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

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-6 months'));
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');
$id_obra      = $_GET['id_obra']      ?? '';

function fmt($text, $max = 0) {
    $t = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text ?? '-');
    return $max > 0 ? substr($t, 0, $max) : $t;
}

function interval_to_sec($interval) {
    return ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
}

function fmt_demora($secs) {
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    return sprintf('%dh %02dm', $h, $m);
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $sql = "SELECT
                p.id_pedido,
                o.nombre_obra,
                p.fecha_pedido,
                p.fecha_aprobacion,
                p.fecha_picking,
                p.fecha_retiro,
                p.fecha_recibido,
                p.fecha_entrega,
                u_creador.nombre  as creador_nombre,  u_creador.apellido  as creador_apellido,
                u_aprobador.nombre as aprobador_nombre, u_aprobador.apellido as aprobador_apellido,
                u_picking.nombre  as picking_nombre,  u_picking.apellido  as picking_apellido,
                u_retirado.nombre as retirado_nombre, u_retirado.apellido as retirado_apellido,
                u_recibido.nombre as recibido_nombre, u_recibido.apellido as recibido_apellido
            FROM pedidos_materiales p
            INNER JOIN obras o ON p.id_obra = o.id_obra
            LEFT JOIN usuarios u_creador   ON p.id_solicitante  = u_creador.id_usuario
            LEFT JOIN usuarios u_aprobador ON p.id_aprobado_por = u_aprobador.id_usuario
            LEFT JOIN usuarios u_picking   ON p.id_picking_por  = u_picking.id_usuario
            LEFT JOIN usuarios u_retirado  ON p.id_retirado_por = u_retirado.id_usuario
            LEFT JOIN usuarios u_recibido  ON p.id_recibido_por = u_recibido.id_usuario
            WHERE p.fecha_pedido BETWEEN ? AND ?";

    $params = [$fecha_inicio, $fecha_fin];
    if (!empty($id_obra)) {
        $sql .= " AND p.id_obra = ?";
        $params[] = $id_obra;
    }
    $sql .= " ORDER BY p.fecha_pedido DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    // Resumen
    $total     = count($pedidos);
    $c_pick = $c_ret = $c_rec = 0;
    foreach ($pedidos as $p) {
        if ($p['fecha_picking'])  $c_pick++;
        if ($p['fecha_retiro'])   $c_ret++;
        if ($p['fecha_recibido']) $c_rec++;
    }

    $filtrado_obra  = !empty($id_obra);
    $nombre_obra_pdf = '';
    if ($filtrado_obra && !empty($pedidos)) {
        $nombre_obra_pdf = $pedidos[0]['nombre_obra'];
    }

    // ==================== GENERAR PDF ====================
    // A3 landscape (420 x 297 mm), margen 10mm → 400mm útiles
    $pdf = new FPDF('L', 'mm', 'A3');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();

    // --- Título ---
    $pdf->SetFont('Arial', 'B', 15);
    $pdf->Cell(0, 8, fmt('Métricas de Pedidos'), 0, 1, 'C');
    if ($filtrado_obra && $nombre_obra_pdf) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, fmt('Obra: ' . $nombre_obra_pdf), 0, 1, 'C');
    }
    $pdf->SetFont('Arial', '', 9);
    $periodo = date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));
    $pdf->Cell(0, 5, fmt('Período: ' . $periodo), 0, 1, 'C');
    $pdf->Cell(0, 5, fmt('Generado: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(3);

    // --- Resumen ---
    $pdf->SetFillColor(233, 236, 239);
    $pdf->SetFont('Arial', 'B', 9);
    $w3 = 400 / 3;
    $pdf->Cell($w3, 7, fmt("Total Pedidos: $total"), 1, 0, 'C', true);
    $pdf->Cell($w3, 7, fmt("Con Picking: $c_pick  |  Con Retiro: $c_ret"), 1, 0, 'C', true);
    $pdf->Cell($w3, 7, fmt("Con Recibido: $c_rec"), 1, 1, 'C', true);
    $pdf->Ln(4);

    // --- Definición de columnas ---
    // Ancho total disponible: 400mm
    // Sin filtro obra: 17 cols  |  Con filtro obra: 16 cols
    if ($filtrado_obra) {
        $cols = [
            ['txt' => 'ID',                 'w' => 11],
            ['txt' => 'Creac.\nResponsable', 'w' => 26],
            ['txt' => 'Creac.\nFecha/Hora',  'w' => 27],
            ['txt' => 'Apro.\nResponsable',  'w' => 26],
            ['txt' => 'Apro.\nFecha/Hora',   'w' => 27],
            ['txt' => 'Apro.\nDemora',       'w' => 19],
            ['txt' => 'Pick.\nResponsable',  'w' => 26],
            ['txt' => 'Pick.\nFecha/Hora',   'w' => 27],
            ['txt' => 'Pick.\nDemora',       'w' => 19],
            ['txt' => 'Retiro\nResponsable', 'w' => 26],
            ['txt' => 'Retiro\nFecha/Hora',  'w' => 27],
            ['txt' => 'Retiro\nDemora',      'w' => 19],
            ['txt' => 'Recib.\nResponsable', 'w' => 26],
            ['txt' => 'Recib.\nFecha/Hora',  'w' => 27],
            ['txt' => 'Recib.\nDemora',      'w' => 19],
            ['txt' => 'Demora\nTotal',       'w' => 23],
        ];
    } else {
        $cols = [
            ['txt' => 'ID',                 'w' => 11],
            ['txt' => 'Obra',               'w' => 26],
            ['txt' => 'Creac.\nResponsable', 'w' => 25],
            ['txt' => 'Creac.\nFecha/Hora',  'w' => 27],
            ['txt' => 'Apro.\nResponsable',  'w' => 25],
            ['txt' => 'Apro.\nFecha/Hora',   'w' => 27],
            ['txt' => 'Apro.\nDemora',       'w' => 19],
            ['txt' => 'Pick.\nResponsable',  'w' => 25],
            ['txt' => 'Pick.\nFecha/Hora',   'w' => 27],
            ['txt' => 'Pick.\nDemora',       'w' => 19],
            ['txt' => 'Retiro\nResponsable', 'w' => 25],
            ['txt' => 'Retiro\nFecha/Hora',  'w' => 27],
            ['txt' => 'Retiro\nDemora',      'w' => 19],
            ['txt' => 'Recib.\nResponsable', 'w' => 25],
            ['txt' => 'Recib.\nFecha/Hora',  'w' => 27],
            ['txt' => 'Recib.\nDemora',      'w' => 19],
            ['txt' => 'Demora\nTotal',       'w' => 22],
        ];
    }

    // --- Cabeceras ---
    $pdf->SetFillColor(13, 110, 253);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 6.5);
    $row_h = 8;
    $y_before = $pdf->GetY();
    $x_start  = $pdf->GetX();
    $max_h = 0;

    // Dibujar encabezados con dos líneas (usando MultiCell + posicionado manual)
    foreach ($cols as $col) {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($col['w'], 4, fmt($col['txt']), 1, 'C', true);
        $cell_h = $pdf->GetY() - $y;
        if ($cell_h > $max_h) $max_h = $cell_h;
        $pdf->SetXY($x + $col['w'], $y);
    }
    $pdf->SetXY($x_start, $y_before + $max_h);

    // --- Filas de datos ---
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 6);
    $data_h = 5.5;
    $fill = false;

    foreach ($pedidos as $pedido) {
        // Calcular tiempos
        $dt_creacion   = new DateTime($pedido['fecha_pedido']);
        $dt_aprobacion = $pedido['fecha_aprobacion'] ? new DateTime($pedido['fecha_aprobacion']) : null;
        $dt_picking    = $pedido['fecha_picking']    ? new DateTime($pedido['fecha_picking'])    : null;
        $dt_retiro     = $pedido['fecha_retiro']     ? new DateTime($pedido['fecha_retiro'])     : null;
        $dt_recibido   = $pedido['fecha_recibido']
                         ? new DateTime($pedido['fecha_recibido'])
                         : ($pedido['fecha_entrega'] ? new DateTime($pedido['fecha_entrega']) : null);

        // Responsables (máx 18 chars)
        $r_creac = fmt(trim($pedido['creador_nombre']   . ' ' . $pedido['creador_apellido']),   18);
        $r_apro  = $dt_aprobacion ? fmt(trim(($pedido['aprobador_nombre'] ?? '') . ' ' . ($pedido['aprobador_apellido'] ?? '')), 18) : '-';
        $r_pick  = $dt_picking    ? fmt(trim(($pedido['picking_nombre']   ?? '') . ' ' . ($pedido['picking_apellido']   ?? '')), 18) : '-';
        $r_ret   = $dt_retiro     ? fmt(trim(($pedido['retirado_nombre']  ?? '') . ' ' . ($pedido['retirado_apellido']  ?? '')), 18) : '-';
        $r_rec   = $dt_recibido   ? fmt(trim(($pedido['recibido_nombre']  ?? '') . ' ' . ($pedido['recibido_apellido']  ?? '')), 18) : '-';

        // Fechas formateadas
        $f_creac = $dt_creacion->format('d/m/y H:i');
        $f_apro  = $dt_aprobacion ? $dt_aprobacion->format('d/m/y H:i') : '-';
        $f_pick  = $dt_picking    ? $dt_picking->format('d/m/y H:i')    : '-';
        $f_ret   = $dt_retiro     ? $dt_retiro->format('d/m/y H:i')     : '-';
        $f_rec   = $dt_recibido   ? $dt_recibido->format('d/m/y H:i')   : '-';

        // Demoras
        $d_apro = '-'; $d_pick = '-'; $d_ret = '-'; $d_rec = '-';
        $total_secs = 0;
        if ($dt_aprobacion) {
            $s = interval_to_sec($dt_creacion->diff($dt_aprobacion));
            $d_apro = fmt_demora($s); $total_secs += $s;
        }
        if ($dt_picking && $dt_aprobacion) {
            $s = interval_to_sec($dt_aprobacion->diff($dt_picking));
            $d_pick = fmt_demora($s); $total_secs += $s;
        }
        if ($dt_retiro && $dt_picking) {
            $s = interval_to_sec($dt_picking->diff($dt_retiro));
            $d_ret = fmt_demora($s); $total_secs += $s;
        }
        if ($dt_recibido && $dt_retiro) {
            $s = interval_to_sec($dt_retiro->diff($dt_recibido));
            $d_rec = fmt_demora($s); $total_secs += $s;
        }
        $d_total = $total_secs > 0 ? fmt_demora($total_secs) : '-';

        // Salto de página si no cabe
        if ($pdf->GetY() + $data_h > $pdf->GetPageHeight() - 12) {
            $pdf->AddPage();
            // Re-imprimir encabezados
            $pdf->SetFillColor(13, 110, 253);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 6.5);
            $y_bef = $pdf->GetY();
            $x_ini = $pdf->GetX();
            foreach ($cols as $col) {
                $xc = $pdf->GetX(); $yc = $pdf->GetY();
                $pdf->MultiCell($col['w'], 4, fmt($col['txt']), 1, 'C', true);
                $pdf->SetXY($xc + $col['w'], $yc);
            }
            $pdf->SetXY($x_ini, $y_bef + $max_h);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 6);
        }

        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

        $row_data = [];
        if ($filtrado_obra) {
            $row_data = [
                str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT),
                $r_creac, $f_creac,
                $r_apro,  $f_apro,  $d_apro,
                $r_pick,  $f_pick,  $d_pick,
                $r_ret,   $f_ret,   $d_ret,
                $r_rec,   $f_rec,   $d_rec,
                $d_total,
            ];
        } else {
            $row_data = [
                str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT),
                fmt($pedido['nombre_obra'], 16),
                $r_creac, $f_creac,
                $r_apro,  $f_apro,  $d_apro,
                $r_pick,  $f_pick,  $d_pick,
                $r_ret,   $f_ret,   $d_ret,
                $r_rec,   $f_rec,   $d_rec,
                $d_total,
            ];
        }

        $y_row = $pdf->GetY();
        foreach ($cols as $i => $col) {
            $align = ($i === 0 || strpos($col['txt'], 'Demora') !== false || $col['txt'] === 'Demora\nTotal') ? 'C' : 'L';
            $pdf->Cell($col['w'], $data_h, $row_data[$i], 1, 0, $align, true);
        }
        $pdf->Ln();
        $fill = !$fill;
    }

    // --- Footer ---
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 5, fmt('Documento generado automáticamente - Sistema de Gestión de Constructora'), 0, 1, 'C');

    $filename = 'metricas_pedidos_' . date('Y-m-d_His') . '.pdf';
    $pdf->Output('D', $filename);

} catch (Exception $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}

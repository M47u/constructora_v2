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

// Obtener parámetros de filtro - Por defecto: últimos 6 meses para tener datos suficientes
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-6 months'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$id_obra = $_GET['id_obra'] ?? '';
$exportar = $_GET['exportar'] ?? '';

// Validar fechas
if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
    $error = "Fechas inválidas proporcionadas.";
} elseif ($fecha_inicio > $fecha_fin) {
    $error = "La fecha de inicio no puede ser mayor que la fecha de fin.";
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // ==================== EXPORTACIÓN A EXCEL ====================
    if ($exportar === 'excel') {
        // Consulta para obtener todos los detalles de pedidos (incluye columnas de todas las etapas)
        $sql_excel = "SELECT
                        p.id_pedido,
                        o.nombre_obra,
                        p.fecha_pedido,
                        p.fecha_aprobacion,
                        p.fecha_picking,
                        p.fecha_retiro,
                        p.fecha_recibido,
                        p.fecha_entrega,
                        u_creador.nombre as creador_nombre,
                        u_creador.apellido as creador_apellido,
                        u_aprobador.nombre as aprobador_nombre,
                        u_aprobador.apellido as aprobador_apellido,
                        u_picking.nombre as picking_nombre,
                        u_picking.apellido as picking_apellido,
                        u_retirado.nombre as retirado_nombre,
                        u_retirado.apellido as retirado_apellido,
                        u_recibido.nombre as recibido_nombre,
                        u_recibido.apellido as recibido_apellido,
                        u_entregador.nombre as entregador_nombre,
                        u_entregador.apellido as entregador_apellido
                    FROM pedidos_materiales p
                    INNER JOIN obras o ON p.id_obra = o.id_obra
                    LEFT JOIN usuarios u_creador ON p.id_solicitante = u_creador.id_usuario
                    LEFT JOIN usuarios u_aprobador ON p.id_aprobado_por = u_aprobador.id_usuario
                    LEFT JOIN usuarios u_picking ON p.id_picking_por = u_picking.id_usuario
                    LEFT JOIN usuarios u_retirado ON p.id_retirado_por = u_retirado.id_usuario
                    LEFT JOIN usuarios u_recibido ON p.id_recibido_por = u_recibido.id_usuario
                    LEFT JOIN usuarios u_entregador ON p.id_entregado_por = u_entregador.id_usuario
                    WHERE p.fecha_pedido BETWEEN ? AND ?";
        
        $params = [$fecha_inicio, $fecha_fin];
        
        if (!empty($id_obra)) {
            $sql_excel .= " AND p.id_obra = ?";
            $params[] = $id_obra;
        }
        
        $sql_excel .= " ORDER BY p.fecha_pedido DESC";
        
        $stmt = $conn->prepare($sql_excel);
        $stmt->execute($params);
        $pedidos_excel = $stmt->fetchAll();
        
        // Función helper para convertir UTF-8 a Windows-1252 (Excel)
        function excel_encode($text) {
            return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        }

        function interval_to_seconds($interval) {
            return ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        }

        function format_demora($total_seconds) {
            $h = floor($total_seconds / 3600);
            $m = floor(($total_seconds % 3600) / 60);
            $s = $total_seconds % 60;
            return sprintf('%dh %02dm %02ds', $h, $m, $s);
        }

        // Calcular stats resumen
        $total_export = count($pedidos_excel);
        $con_picking = 0; $con_retiro = 0; $con_recibido = 0;
        foreach ($pedidos_excel as $p) {
            if ($p['fecha_picking'])  $con_picking++;
            if ($p['fecha_retiro'])   $con_retiro++;
            if ($p['fecha_recibido']) $con_recibido++;
        }
        $periodo_label = date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));
        $generado_label = date('d/m/Y H:i');

        $filtrado_obra = !empty($id_obra);
        $nombre_obra_export = '';
        if ($filtrado_obra && !empty($pedidos_excel)) {
            $nombre_obra_export = $pedidos_excel[0]['nombre_obra'];
        }

        // Configurar headers para exportación Excel
        header('Content-Type: application/vnd.ms-excel; charset=Windows-1252');
        header('Content-Disposition: attachment;filename="metricas_pedidos_' . date('Y-m-d_His') . '.xls"');
        header('Cache-Control: max-age=0');

        $cols = $filtrado_obra ? 19 : 20;
        $td_header = "style='background-color:#0d6efd;color:white;font-weight:bold;border:1px solid #0a58ca;padding:6px;text-align:center;'";
        $td_data    = "style='border:1px solid #dee2e6;padding:4px 6px;'";
        $td_demora  = "style='border:1px solid #dee2e6;padding:4px 6px;text-align:center;font-weight:bold;'";

        echo "<table border='0' cellpadding='0' cellspacing='0' style='font-family:Arial,sans-serif;font-size:11px;width:100%;'>";

        // Título
        echo "<tr><td colspan='{$cols}' align='center' style='font-size:16px;font-weight:bold;padding:12px 4px 4px;'>"
            . excel_encode('Métricas de Pedidos') . "</td></tr>";
        if ($filtrado_obra && $nombre_obra_export) {
            echo "<tr><td colspan='{$cols}' align='center' style='font-size:13px;font-weight:bold;padding:2px;'>"
                . excel_encode("Obra: $nombre_obra_export") . "</td></tr>";
        }
        echo "<tr><td colspan='{$cols}' align='center' style='font-size:11px;padding:2px;'>"
            . excel_encode("Período: $periodo_label") . "</td></tr>";
        echo "<tr><td colspan='{$cols}' align='center' style='font-size:11px;padding:2px 2px 10px;'>"
            . excel_encode("Generado: $generado_label") . "</td></tr>";

        // Stats resumen
        $td_stat = "style='background-color:#e9ecef;border:1px solid #dee2e6;font-weight:bold;padding:6px;text-align:center;'";
        echo "<tr>";
        $c1 = $filtrado_obra ? 6 : 6;
        $c2 = $filtrado_obra ? 6 : 7;
        $c3 = $filtrado_obra ? 7 : 7;
        echo "<td colspan='{$c1}' {$td_stat}>" . excel_encode("Total Pedidos: $total_export") . "</td>";
        echo "<td colspan='{$c2}' {$td_stat}>" . excel_encode("Con Picking: $con_picking  |  Con Retiro: $con_retiro") . "</td>";
        echo "<td colspan='{$c3}' {$td_stat}>" . excel_encode("Con Recibido: $con_recibido") . "</td>";
        echo "</tr>";
        echo "<tr><td colspan='{$cols}' style='padding:6px;'>&nbsp;</td></tr>";

        // Encabezados de tabla
        echo "<tr>";
        $headers = ['ID Pedido'];
        if (!$filtrado_obra) $headers[] = 'Nombre de Obra';
        $headers = array_merge($headers, [
            'Creación - Responsable','Creación - Fecha y Hora',
            'Aprobación - Responsable','Aprobación - Fecha y Hora','Aprobación - Demora',
            'Picking - Responsable','Picking - Fecha','Picking - Hora','Picking - Demora',
            'Retiro - Responsable','Retiro - Fecha','Retiro - Hora','Retiro - Demora',
            'Recibido - Responsable','Recibido - Fecha','Recibido - Hora','Recibido - Demora',
            'Demora Total'
        ]);
        foreach ($headers as $col) {
            echo "<th {$td_header}>" . excel_encode($col) . "</th>";
        }
        echo "</tr>";

        foreach ($pedidos_excel as $pedido) {
            // Fecha de creación
            $fecha_creacion = new DateTime($pedido['fecha_pedido']);
            $creacion_responsable_display = htmlspecialchars($pedido['creador_nombre'] . ' ' . $pedido['creador_apellido']);
            $creacion_fecha_display = $fecha_creacion->format('d/m/Y H:i:s');

            // Aprobación
            $aprobacion_responsable_display = '-';
            $aprobacion_fecha_display = '-';
            $aprobacion_demora_display = '-';
            $fecha_aprobacion = null;

            if ($pedido['fecha_aprobacion']) {
                $aprobacion_responsable_display = htmlspecialchars(($pedido['aprobador_nombre'] ?? '-') . ' ' . ($pedido['aprobador_apellido'] ?? ''));
                $fecha_aprobacion = new DateTime($pedido['fecha_aprobacion']);
                $aprobacion_fecha_display = $fecha_aprobacion->format('d/m/Y H:i:s');
                $interval = $fecha_creacion->diff($fecha_aprobacion);
                $aprobacion_segundos = interval_to_seconds($interval);
                $aprobacion_demora_display = format_demora($aprobacion_segundos);
            }

            // Picking
            $picking_responsable_display = '-';
            $picking_fecha_display = '-';
            $picking_hora_display = '-';
            $picking_demora_display = '-';
            $fecha_picking = null;

            if ($pedido['fecha_picking']) {
                $picking_responsable_display = htmlspecialchars(($pedido['picking_nombre'] ?? '-') . ' ' . ($pedido['picking_apellido'] ?? ''));
                $fecha_picking = new DateTime($pedido['fecha_picking']);
                $picking_fecha_display = $fecha_picking->format('d/m/Y');
                $picking_hora_display = $fecha_picking->format('H:i:s');

                if ($fecha_aprobacion) {
                    $interval = $fecha_aprobacion->diff($fecha_picking);
                    $picking_segundos = interval_to_seconds($interval);
                    $picking_demora_display = format_demora($picking_segundos);
                }
            }

            // Retiro
            $retiro_responsable_display = '-';
            $retiro_fecha_display = '-';
            $retiro_hora_display = '-';
            $retiro_demora_display = '-';
            $fecha_retiro = null;

            if ($pedido['fecha_retiro']) {
                $retiro_responsable_display = htmlspecialchars(($pedido['retirado_nombre'] ?? '-') . ' ' . ($pedido['retirado_apellido'] ?? ''));
                $fecha_retiro = new DateTime($pedido['fecha_retiro']);
                $retiro_fecha_display = $fecha_retiro->format('d/m/Y');
                $retiro_hora_display = $fecha_retiro->format('H:i:s');

                if ($fecha_picking) {
                    $interval = $fecha_picking->diff($fecha_retiro);
                    $retiro_segundos = interval_to_seconds($interval);
                    $retiro_demora_display = format_demora($retiro_segundos);
                }
            }

            // Recibido
            $recibido_responsable_display = '-';
            $recibido_fecha_display = '-';
            $recibido_hora_display = '-';
            $recibido_demora_display = '-';
            $fecha_recibido = null;

            if ($pedido['fecha_recibido']) {
                $recibido_responsable_display = htmlspecialchars(($pedido['recibido_nombre'] ?? '-') . ' ' . ($pedido['recibido_apellido'] ?? ''));
                $fecha_recibido = new DateTime($pedido['fecha_recibido']);
                $recibido_fecha_display = $fecha_recibido->format('d/m/Y');
                $recibido_hora_display = $fecha_recibido->format('H:i:s');

                if ($fecha_retiro) {
                    $interval = $fecha_retiro->diff($fecha_recibido);
                    $recibido_segundos = interval_to_seconds($interval);
                    $recibido_demora_display = format_demora($recibido_segundos);
                }
            }

            // Demora total
            $demora_total_segundos = 0;
            if (isset($aprobacion_segundos)) $demora_total_segundos += $aprobacion_segundos;
            if (isset($picking_segundos))    $demora_total_segundos += $picking_segundos;
            if (isset($retiro_segundos))     $demora_total_segundos += $retiro_segundos;
            if (isset($recibido_segundos))   $demora_total_segundos += $recibido_segundos;
            $demora_total_display = $demora_total_segundos > 0 ? format_demora($demora_total_segundos) : '-';

            echo "<tr>";
            echo "<td {$td_data}>" . excel_encode($pedido['id_pedido']) . "</td>";
            if (!$filtrado_obra) {
                echo "<td {$td_data}>" . excel_encode(htmlspecialchars($pedido['nombre_obra'])) . "</td>";
            }
            echo "<td {$td_data}>" . excel_encode($creacion_responsable_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($creacion_fecha_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($aprobacion_responsable_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($aprobacion_fecha_display) . "</td>";
            echo "<td {$td_demora}>" . excel_encode($aprobacion_demora_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($picking_responsable_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($picking_fecha_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($picking_hora_display) . "</td>";
            echo "<td {$td_demora}>" . excel_encode($picking_demora_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($retiro_responsable_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($retiro_fecha_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($retiro_hora_display) . "</td>";
            echo "<td {$td_demora}>" . excel_encode($retiro_demora_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($recibido_responsable_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($recibido_fecha_display) . "</td>";
            echo "<td {$td_data}>" . excel_encode($recibido_hora_display) . "</td>";
            echo "<td {$td_demora}>" . excel_encode($recibido_demora_display) . "</td>";
            echo "<td style='border:1px solid #dee2e6;padding:4px 6px;text-align:center;font-weight:bold;background-color:#f8f9fa;'>" . excel_encode($demora_total_display) . "</td>";
            echo "</tr>";
        }

        // Footer
        echo "<tr><td colspan='{$cols}' style='padding:6px;'>&nbsp;</td></tr>";
        echo "<tr><td colspan='{$cols}' align='center' style='font-style:italic;color:#6c757d;font-size:10px;padding:4px;'>"
            . excel_encode('Documento generado automáticamente - Sistema de Gestión de Constructora') . "</td></tr>";

        echo "</table>";
        exit();
    }

    // ==================== EXPORTACIÓN A PDF ====================
    if ($exportar === 'pdf') {
        $sql_pdf = "SELECT
                        p.id_pedido,
                        o.nombre_obra,
                        p.fecha_pedido,
                        p.fecha_aprobacion,
                        p.fecha_picking,
                        p.fecha_retiro,
                        p.fecha_recibido,
                        p.fecha_entrega,
                        u_creador.nombre as creador_nombre,
                        u_creador.apellido as creador_apellido,
                        u_aprobador.nombre as aprobador_nombre,
                        u_aprobador.apellido as aprobador_apellido,
                        u_picking.nombre as picking_nombre,
                        u_picking.apellido as picking_apellido,
                        u_retirado.nombre as retirado_nombre,
                        u_retirado.apellido as retirado_apellido,
                        u_recibido.nombre as recibido_nombre,
                        u_recibido.apellido as recibido_apellido,
                        u_entregador.nombre as entregador_nombre,
                        u_entregador.apellido as entregador_apellido
                    FROM pedidos_materiales p
                    INNER JOIN obras o ON p.id_obra = o.id_obra
                    LEFT JOIN usuarios u_creador ON p.id_solicitante = u_creador.id_usuario
                    LEFT JOIN usuarios u_aprobador ON p.id_aprobado_por = u_aprobador.id_usuario
                    LEFT JOIN usuarios u_picking ON p.id_picking_por = u_picking.id_usuario
                    LEFT JOIN usuarios u_retirado ON p.id_retirado_por = u_retirado.id_usuario
                    LEFT JOIN usuarios u_recibido ON p.id_recibido_por = u_recibido.id_usuario
                    LEFT JOIN usuarios u_entregador ON p.id_entregado_por = u_entregador.id_usuario
                    WHERE p.fecha_pedido BETWEEN ? AND ?";

        $params_pdf = [$fecha_inicio, $fecha_fin];
        if (!empty($id_obra)) {
            $sql_pdf .= " AND p.id_obra = ?";
            $params_pdf[] = $id_obra;
        }
        $sql_pdf .= " ORDER BY p.fecha_pedido DESC";

        $stmt = $conn->prepare($sql_pdf);
        $stmt->execute($params_pdf);
        $pedidos_pdf = $stmt->fetchAll();

        function pdf_enc($text) {
            $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$text);
            if ($converted === false) {
                $converted = iconv('UTF-8', 'ISO-8859-1//IGNORE', (string)$text);
            }
            return $converted === false ? '' : $converted;
        }
        function pdf_trunc($text, $length) {
            $text = (string)$text;
            if (function_exists('mb_substr')) {
                return mb_substr($text, 0, $length, 'UTF-8');
            }
            return substr($text, 0, $length);
        }
        function pdf_seconds($interval) {
            $seconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
            return !empty($interval->invert) ? -$seconds : $seconds;
        }
        function pdf_demora($s) {
            $h = floor($s / 3600); $m = floor(($s % 3600) / 60); $sec = $s % 60;
            return sprintf('%dh %02dm %02ds', $h, $m, $sec);
        }

        $total_export = count($pedidos_pdf);
        $con_picking = 0; $con_retiro = 0; $con_recibido = 0;
        foreach ($pedidos_pdf as $p) {
            if ($p['fecha_picking'])  $con_picking++;
            if ($p['fecha_retiro'])   $con_retiro++;
            if ($p['fecha_recibido']) $con_recibido++;
        }
        $periodo_label = date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));
        $generado_label = date('d/m/Y H:i');

        $filtrado_obra = !empty($id_obra);
        $nombre_obra_export = '';
        if ($filtrado_obra && !empty($pedidos_pdf)) {
            $nombre_obra_export = $pedidos_pdf[0]['nombre_obra'];
        }

        require_once '../../lib/fpdf/fpdf.php';
        $pdf = new FPDF('L', 'mm', 'A3');
        $pdf->SetMargins(5, 10, 5);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        // Anchos de columna según si se filtra por obra
        // Sin filtro (20 cols) | Con filtro (19 cols, sin columna Obra)
        if ($filtrado_obra) {
            $w = [10, 28, 26, 28, 26, 18, 28, 18, 15, 18, 28, 18, 15, 18, 28, 18, 15, 18, 18];
        } else {
            $w = [10, 28, 24, 23, 24, 23, 17, 24, 17, 13, 17, 24, 17, 13, 17, 24, 17, 13, 17, 17];
        }
        $total_w = array_sum($w);

        // Título
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, pdf_enc('Métricas de Pedidos'), 0, 1, 'C');
        if ($filtrado_obra && $nombre_obra_export) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, pdf_enc("Obra: $nombre_obra_export"), 0, 1, 'C');
        }
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, pdf_enc("Período: $periodo_label"), 0, 1, 'C');
        $pdf->Cell(0, 5, pdf_enc("Generado: $generado_label"), 0, 1, 'C');
        $pdf->Ln(4);

        // Resumen estadístico
        $sw = intval($total_w / 3);
        $pdf->SetFillColor(233, 236, 239);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($sw, 7, pdf_enc("Total Pedidos: $total_export"), 1, 0, 'L', true);
        $pdf->Cell($sw, 7, pdf_enc("Con Picking: $con_picking  |  Con Retiro: $con_retiro"), 1, 0, 'L', true);
        $pdf->Cell($total_w - $sw * 2, 7, pdf_enc("Con Recibido: $con_recibido"), 1, 1, 'L', true);
        $pdf->Ln(4);

        // Encabezados de columna
        $pdf->SetFillColor(13, 110, 253);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 5);

        $header_labels = ['ID Pedido'];
        if (!$filtrado_obra) $header_labels[] = 'Nombre Obra';
        $header_labels = array_merge($header_labels, [
            'Creac. Responsable', 'Creac. Fecha/Hora',
            'Apr. Responsable', 'Apr. Fecha/Hora', 'Apr. Demora',
            'Pick. Responsable', 'Pick. Fecha', 'Pick. Hora', 'Pick. Demora',
            'Ret. Responsable', 'Ret. Fecha', 'Ret. Hora', 'Ret. Demora',
            'Recib. Responsable', 'Recib. Fecha', 'Recib. Hora', 'Recib. Demora',
            'Demora Total',
        ]);
        foreach ($header_labels as $i => $label) {
            $pdf->Cell($w[$i], 7, pdf_enc($label), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Filas de datos
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 5);
        $row_h = 5;

        foreach ($pedidos_pdf as $pedido) {
            $fecha_creacion = new DateTime($pedido['fecha_pedido']);
            $creacion_responsable = trim(($pedido['creador_nombre'] ?? '') . ' ' . ($pedido['creador_apellido'] ?? ''));
            $creacion_fh = $fecha_creacion->format('d/m/Y H:i');

            $aprobacion_responsable = '-'; $aprobacion_fh = '-'; $aprobacion_demora = '-';
            $fecha_aprobacion = null; $aprobacion_segundos = 0;
            if ($pedido['fecha_aprobacion']) {
                $fecha_aprobacion = new DateTime($pedido['fecha_aprobacion']);
                $aprobacion_responsable = trim(($pedido['aprobador_nombre'] ?? '') . ' ' . ($pedido['aprobador_apellido'] ?? ''));
                $aprobacion_fh = $fecha_aprobacion->format('d/m/Y H:i');
                if ($fecha_aprobacion >= $fecha_creacion) {
                    $aprobacion_segundos = pdf_seconds($fecha_creacion->diff($fecha_aprobacion));
                    $aprobacion_demora = pdf_demora($aprobacion_segundos);
                }
            }

            $picking_responsable = '-'; $picking_fecha = '-'; $picking_hora = '-'; $picking_demora = '-';
            $fecha_picking = null; $picking_segundos = 0;
            if ($pedido['fecha_picking']) {
                $fecha_picking = new DateTime($pedido['fecha_picking']);
                $picking_responsable = trim(($pedido['picking_nombre'] ?? '') . ' ' . ($pedido['picking_apellido'] ?? ''));
                $picking_fecha = $fecha_picking->format('d/m/Y');
                $picking_hora = $fecha_picking->format('H:i');
                if ($fecha_aprobacion && $fecha_picking >= $fecha_aprobacion) {
                    $picking_segundos = pdf_seconds($fecha_aprobacion->diff($fecha_picking));
                    $picking_demora = pdf_demora($picking_segundos);
                }
            }

            $retiro_responsable = '-'; $retiro_fecha = '-'; $retiro_hora = '-'; $retiro_demora = '-';
            $fecha_retiro = null; $retiro_segundos = 0;
            if ($pedido['fecha_retiro']) {
                $fecha_retiro = new DateTime($pedido['fecha_retiro']);
                $retiro_responsable = trim(($pedido['retirado_nombre'] ?? '') . ' ' . ($pedido['retirado_apellido'] ?? ''));
                $retiro_fecha = $fecha_retiro->format('d/m/Y');
                $retiro_hora = $fecha_retiro->format('H:i');
                if ($fecha_picking && $fecha_retiro >= $fecha_picking) {
                    $retiro_segundos = pdf_seconds($fecha_picking->diff($fecha_retiro));
                    $retiro_demora = pdf_demora($retiro_segundos);
                }
            }

            $recibido_responsable = '-'; $recibido_fecha = '-'; $recibido_hora = '-'; $recibido_demora = '-';
            $fecha_recibido = null; $recibido_segundos = 0;
            if ($pedido['fecha_recibido']) {
                $fecha_recibido = new DateTime($pedido['fecha_recibido']);
                $recibido_responsable = trim(($pedido['recibido_nombre'] ?? '') . ' ' . ($pedido['recibido_apellido'] ?? ''));
                $recibido_fecha = $fecha_recibido->format('d/m/Y');
                $recibido_hora = $fecha_recibido->format('H:i');
                if ($fecha_retiro && $fecha_recibido >= $fecha_retiro) {
                    $recibido_segundos = pdf_seconds($fecha_retiro->diff($fecha_recibido));
                    $recibido_demora = pdf_demora($recibido_segundos);
                }
            }

            $demora_total_s = $aprobacion_segundos + $picking_segundos + $retiro_segundos + $recibido_segundos;
            $demora_total = $demora_total_s > 0 ? pdf_demora($demora_total_s) : '-';

            $ci = 0;
            $pdf->Cell($w[$ci++], $row_h, $pedido['id_pedido'], 1, 0, 'C');
            if (!$filtrado_obra) {
                $pdf->Cell($w[$ci++], $row_h, pdf_enc(pdf_trunc($pedido['nombre_obra'], 17)), 1, 0, 'L');
            }
            $pdf->Cell($w[$ci++], $row_h, pdf_enc(pdf_trunc($creacion_responsable, 16)), 1, 0, 'L');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($creacion_fh), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc(pdf_trunc($aprobacion_responsable, 16)), 1, 0, 'L');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($aprobacion_fh), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($aprobacion_demora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc(pdf_trunc($picking_responsable, 16)), 1, 0, 'L');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($picking_fecha), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($picking_hora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($picking_demora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc(pdf_trunc($retiro_responsable, 16)), 1, 0, 'L');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($retiro_fecha), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($retiro_hora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($retiro_demora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc(pdf_trunc($recibido_responsable, 16)), 1, 0, 'L');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($recibido_fecha), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($recibido_hora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($recibido_demora), 1, 0, 'C');
            $pdf->Cell($w[$ci++], $row_h, pdf_enc($demora_total), 1, 1, 'C');
        }

        // Pie de página
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell(0, 5, pdf_enc('Documento generado automáticamente - Sistema de Gestión de Constructora'), 0, 1, 'C');

        $pdf->Output('D', 'metricas_pedidos_' . date('Y-m-d_His') . '.pdf');
        exit();
    }

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
    
    // IMPORTANTE: Usa SOLO las columnas directas de la tabla pedidos_materiales
    // para evitar inconsistencias con seguimiento_pedidos (fechas contradictorias)
    // Solo calcula promedios cuando hay fechas válidas (no NULL) y en orden correcto
    $sql_tiempos = "SELECT 
                        -- Creación → Aprobación (solo si fecha_aprobacion existe y es >= fecha_pedido)
                        AVG(CASE 
                            WHEN p.fecha_aprobacion IS NOT NULL 
                                AND p.fecha_aprobacion >= p.fecha_pedido
                            THEN TIMESTAMPDIFF(HOUR, p.fecha_pedido, p.fecha_aprobacion)
                            ELSE NULL 
                        END) as tiempo_aprobacion,
                        
                        -- Aprobación → Picking (solo si ambas fechas existen y picking >= aprobacion)
                        AVG(CASE 
                            WHEN p.fecha_aprobacion IS NOT NULL 
                                AND p.fecha_picking IS NOT NULL
                                AND p.fecha_picking >= p.fecha_aprobacion
                            THEN TIMESTAMPDIFF(HOUR, p.fecha_aprobacion, p.fecha_picking)
                            ELSE NULL 
                        END) as tiempo_picking,
                        
                        -- Picking → Retiro (solo si ambas fechas existen y retiro >= picking)
                        AVG(CASE 
                            WHEN p.fecha_picking IS NOT NULL 
                                AND p.fecha_retiro IS NOT NULL
                                AND p.fecha_retiro >= p.fecha_picking
                            THEN TIMESTAMPDIFF(HOUR, p.fecha_picking, p.fecha_retiro)
                            ELSE NULL 
                        END) as tiempo_retiro,
                        
                        -- Retiro → Entrega (solo si ambas fechas existen, usa recibido o entrega)
                        AVG(CASE 
                            WHEN p.fecha_retiro IS NOT NULL 
                                AND COALESCE(p.fecha_recibido, p.fecha_entrega) IS NOT NULL
                                AND COALESCE(p.fecha_recibido, p.fecha_entrega) >= p.fecha_retiro
                            THEN TIMESTAMPDIFF(HOUR, p.fecha_retiro, COALESCE(p.fecha_recibido, p.fecha_entrega))
                            ELSE NULL 
                        END) as tiempo_entrega,
                        
                        -- Tiempo total (desde creación hasta entrega/recibido)
                        AVG(CASE 
                            WHEN COALESCE(p.fecha_recibido, p.fecha_entrega) IS NOT NULL
                                AND COALESCE(p.fecha_recibido, p.fecha_entrega) >= p.fecha_pedido
                            THEN TIMESTAMPDIFF(HOUR, p.fecha_pedido, COALESCE(p.fecha_recibido, p.fecha_entrega))
                            ELSE NULL 
                        END) as tiempo_total,
                        
                        -- Contadores para saber cuántos pedidos tienen cada etapa (solo columnas directas)
                        COUNT(*) as total_pedidos,
                        SUM(CASE WHEN p.fecha_aprobacion IS NOT NULL AND p.fecha_aprobacion >= p.fecha_pedido THEN 1 ELSE 0 END) as con_aprobacion,
                        SUM(CASE WHEN p.fecha_picking IS NOT NULL AND p.fecha_aprobacion IS NOT NULL AND p.fecha_picking >= p.fecha_aprobacion THEN 1 ELSE 0 END) as con_picking,
                        SUM(CASE WHEN p.fecha_retiro IS NOT NULL AND p.fecha_picking IS NOT NULL AND p.fecha_retiro >= p.fecha_picking THEN 1 ELSE 0 END) as con_retiro,
                        SUM(CASE WHEN COALESCE(p.fecha_recibido, p.fecha_entrega) IS NOT NULL AND p.fecha_retiro IS NOT NULL AND COALESCE(p.fecha_recibido, p.fecha_entrega) >= p.fecha_retiro THEN 1 ELSE 0 END) as con_entrega
                    FROM pedidos_materiales p
                    WHERE p.fecha_pedido BETWEEN ? AND ?
                        AND p.estado IN ('entregado', 'recibido')";  
    
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
            'tiempo_total' => 0,
            'total_pedidos' => 0,
            'con_aprobacion' => 0,
            'con_picking' => 0,
            'con_retiro' => 0,
            'con_entrega' => 0
        ];
    }
    
    // Convertir NULL a 0 para evitar errores de división
    $tiempos['tiempo_aprobacion'] = $tiempos['tiempo_aprobacion'] ?? 0;
    $tiempos['tiempo_picking'] = $tiempos['tiempo_picking'] ?? 0;
    $tiempos['tiempo_retiro'] = $tiempos['tiempo_retiro'] ?? 0;
    $tiempos['tiempo_entrega'] = $tiempos['tiempo_entrega'] ?? 0;
    $tiempos['tiempo_total'] = $tiempos['tiempo_total'] ?? 0;
    
    // ==================== PEDIDOS ATRASADOS ====================
    
    // Un pedido está atrasado si han pasado más de 8 horas desde la fecha_necesaria
    // y todavía no ha sido entregado/recibido/cancelado
    $sql_atrasados = "SELECT
                        p.id_pedido,
                        o.nombre_obra,
                        p.estado,
                        p.fecha_pedido,
                        p.fecha_necesaria,
                        p.prioridad,
                        -- Calcular horas de atraso desde la fecha necesaria + 8 horas de margen
                        TIMESTAMPDIFF(HOUR, 
                            DATE_ADD(p.fecha_necesaria, INTERVAL 8 HOUR),
                            NOW()
                        ) as horas_atraso,
                        -- Mostrar cuánto tiempo falta o cuánto se pasó
                        TIMESTAMPDIFF(HOUR, NOW(), p.fecha_necesaria) as horas_hasta_vencimiento
                    FROM pedidos_materiales p
                    INNER JOIN obras o ON p.id_obra = o.id_obra
                    WHERE p.estado NOT IN ('entregado', 'recibido', 'cancelado', 'devuelto')
                        AND p.fecha_necesaria IS NOT NULL
                        AND TIMESTAMPDIFF(HOUR, 
                            DATE_ADD(p.fecha_necesaria, INTERVAL 8 HOUR),
                            NOW()
                        ) > 0";
    
    if (!empty($id_obra)) {
        $sql_atrasados .= " AND p.id_obra = ?";
        $params_atrasados = [$id_obra];
    } else {
        $params_atrasados = [];
    }
    
    $sql_atrasados .= " ORDER BY horas_atraso DESC, 
                        FIELD(p.prioridad, 'urgente', 'alta', 'media', 'baja')";
    $stmt = $conn->prepare($sql_atrasados);
    $stmt->execute($params_atrasados);
    $pedidos_atrasados = $stmt->fetchAll();
    
    // También obtener pedidos próximos a vencer (dentro de las próximas 24 horas)
    $sql_por_vencer = "SELECT
                        p.id_pedido,
                        o.nombre_obra,
                        p.estado,
                        p.fecha_necesaria,
                        p.prioridad,
                        TIMESTAMPDIFF(HOUR, NOW(), p.fecha_necesaria) as horas_restantes
                    FROM pedidos_materiales p
                    INNER JOIN obras o ON p.id_obra = o.id_obra
                    WHERE p.estado NOT IN ('entregado', 'recibido', 'cancelado', 'devuelto')
                        AND p.fecha_necesaria IS NOT NULL
                        AND TIMESTAMPDIFF(HOUR, NOW(), p.fecha_necesaria) > 0
                        AND TIMESTAMPDIFF(HOUR, NOW(), p.fecha_necesaria) <= 24";
    
    if (!empty($id_obra)) {
        $sql_por_vencer .= " AND p.id_obra = ?";
    }
    
    $sql_por_vencer .= " ORDER BY horas_restantes ASC,
                        FIELD(p.prioridad, 'urgente', 'alta', 'media', 'baja')";
    $stmt = $conn->prepare($sql_por_vencer);
    $stmt->execute($params_atrasados);
    $pedidos_por_vencer = $stmt->fetchAll();
    
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

require_once '../../includes/header.php';
?>

<!-- Encabezado de la página -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-graph-up-arrow"></i> Métricas y Análisis de Pedidos</h1>
            <div>
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <a href="?fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>&exportar=excel" class="btn btn-success me-2">
                    <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                </a>
                <a href="?fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>&exportar=pdf" class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Exportar a PDF
                </a>
            </div>
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
            'picking' => 'primary',
            'retirado' => 'warning',
            'recibido' => 'success',
            'en_camino' => 'info',
            'entregado' => 'success',
            'cancelado' => 'danger',
            'devuelto' => 'secondary'
        ];
        $estado_icons = [
            'pendiente' => 'clock',
            'aprobado' => 'check-circle',
            'picking' => 'box',
            'retirado' => 'box-arrow-right',
            'recibido' => 'check-circle-fill',
            'en_camino' => 'truck',
            'entregado' => 'check-circle-fill',
            'cancelado' => 'x-circle',
            'devuelto' => 'arrow-counterclockwise'
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
                    
                    <!-- Advertencia si hay pocos datos -->
                    <?php if (isset($tiempos['total_pedidos']) && $tiempos['total_pedidos'] < 10): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Datos limitados:</strong> Solo hay <?php echo $tiempos['total_pedidos']; ?> pedido(s) completado(s) en este período.
                        Los promedios pueden no ser representativos.
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-1-circle text-info"></i> Creación → Aprobación</span>
                            <div>
                                <?php if (isset($tiempos['con_aprobacion']) && $tiempos['con_aprobacion'] > 0): ?>
                                    <strong class="me-2"><?php echo number_format($tiempos['tiempo_aprobacion'], 1); ?> horas</strong>
                                    <small class="text-muted">(<?php echo $tiempos['con_aprobacion']; ?> pedidos)</small>
                                <?php else: ?>
                                    <span class="text-muted me-2"><em>Sin datos</em></span>
                                <?php endif; ?>
                                <a href="detalle_etapa.php?etapa=aprobacion&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-info" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php if ($tiempos['tiempo_aprobacion'] > 0 && $tiempos['tiempo_total'] > 0): ?>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_aprobacion'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_aprobacion'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="progress mb-3" style="height: 25px; background-color: #e9ecef;">
                            <div class="text-center w-100 text-muted" style="line-height: 25px;">
                                <small>No hay datos suficientes</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-2-circle text-warning"></i> Aprobación → Picking</span>
                            <div>
                                <?php if (isset($tiempos['con_picking']) && $tiempos['con_picking'] > 0): ?>
                                    <strong class="me-2"><?php echo number_format($tiempos['tiempo_picking'], 1); ?> horas</strong>
                                    <small class="text-muted">(<?php echo $tiempos['con_picking']; ?> pedidos)</small>
                                <?php else: ?>
                                    <span class="text-muted me-2"><em>Sin datos</em></span>
                                    <small class="text-warning"><i class="bi bi-info-circle"></i> Etapa no registrada</small>
                                <?php endif; ?>
                                <a href="detalle_etapa.php?etapa=picking&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-warning" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php if (isset($tiempos['tiempo_picking']) && $tiempos['tiempo_picking'] > 0 && $tiempos['tiempo_total'] > 0): ?>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_picking'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_picking'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="progress mb-3" style="height: 25px; background-color: #e9ecef;">
                            <div class="text-center w-100 text-muted" style="line-height: 25px;">
                                <small>No hay datos suficientes</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-3-circle text-primary"></i> Picking → Retiro</span>
                            <div>
                                <?php if (isset($tiempos['con_retiro']) && $tiempos['con_retiro'] > 0): ?>
                                    <strong class="me-2"><?php echo number_format($tiempos['tiempo_retiro'], 1); ?> horas</strong>
                                    <small class="text-muted">(<?php echo $tiempos['con_retiro']; ?> pedidos)</small>
                                <?php else: ?>
                                    <span class="text-muted me-2"><em>Sin datos</em></span>
                                    <small class="text-warning"><i class="bi bi-info-circle"></i> Etapa no registrada</small>
                                <?php endif; ?>
                                <a href="detalle_etapa.php?etapa=retiro&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-primary" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php if (isset($tiempos['tiempo_retiro']) && $tiempos['tiempo_retiro'] > 0 && $tiempos['tiempo_total'] > 0): ?>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_retiro'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_retiro'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="progress mb-3" style="height: 25px; background-color: #e9ecef;">
                            <div class="text-center w-100 text-muted" style="line-height: 25px;">
                                <small>No hay datos suficientes</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-4-circle text-success"></i> Retiro → Entrega</span>
                            <div>
                                <?php if (isset($tiempos['con_entrega']) && $tiempos['con_entrega'] > 0): ?>
                                    <strong class="me-2"><?php echo number_format($tiempos['tiempo_entrega'], 1); ?> horas</strong>
                                    <small class="text-muted">(<?php echo $tiempos['con_entrega']; ?> pedidos)</small>
                                <?php else: ?>
                                    <span class="text-muted me-2"><em>Sin datos</em></span>
                                    <small class="text-warning"><i class="bi bi-info-circle"></i> Etapa no registrada</small>
                                <?php endif; ?>
                                <a href="detalle_etapa.php?etapa=entrega&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&id_obra=<?php echo urlencode($id_obra); ?>" 
                                   class="btn btn-sm btn-success" title="Ver detalle de esta etapa">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php if (isset($tiempos['tiempo_entrega']) && $tiempos['tiempo_entrega'] > 0 && $tiempos['tiempo_total'] > 0): ?>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo ($tiempos['tiempo_entrega'] / $tiempos['tiempo_total']) * 100; ?>%">
                                <?php echo number_format(($tiempos['tiempo_entrega'] / $tiempos['tiempo_total']) * 100, 0); ?>%
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="progress mb-3" style="height: 25px; background-color: #e9ecef;">
                            <div class="text-center w-100 text-muted" style="line-height: 25px;">
                                <small>No hay datos suficientes</small>
                            </div>
                        </div>
                        <?php endif; ?>
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
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle"></i> 
                        Pedidos Atrasados 
                        <small>(+8hs después de fecha necesaria)</small>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pedidos_atrasados)): ?>
                    <div class="alert alert-danger">
                        <strong><?php echo count($pedidos_atrasados); ?></strong> pedido(s) vencido(s)
                    </div>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Pedido</th>
                                    <th>Obra</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Fecha Necesaria</th>
                                    <th class="text-end">Atraso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_atrasados as $atrasado): 
                                    $dias_atraso = floor($atrasado['horas_atraso'] / 24);
                                    $horas_atraso_restantes = $atrasado['horas_atraso'] % 24;
                                    
                                    // Color según la severidad del atraso
                                    $severidad_color = 'danger';
                                    if ($atrasado['horas_atraso'] > 168) { // más de 7 días
                                        $severidad_color = 'dark';
                                    } elseif ($atrasado['horas_atraso'] > 72) { // más de 3 días
                                        $severidad_color = 'danger';
                                    } else {
                                        $severidad_color = 'warning';
                                    }
                                    
                                    // Color de prioridad
                                    $prioridad_colors = [
                                        'urgente' => 'danger',
                                        'alta' => 'warning',
                                        'media' => 'info',
                                        'baja' => 'secondary'
                                    ];
                                    $prioridad_color = $prioridad_colors[$atrasado['prioridad']] ?? 'secondary';
                                ?>
                                <tr class="<?php echo $atrasado['prioridad'] == 'urgente' ? 'table-danger' : ''; ?>">
                                    <td>
                                        <a href="../pedidos/view.php?id=<?php echo $atrasado['id_pedido']; ?>" 
                                           target="_blank" class="text-decoration-none">
                                            <strong>#<?php echo str_pad($atrasado['id_pedido'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        </a>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($atrasado['nombre_obra'], 0, 20)); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $estado_colors[$atrasado['estado']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($atrasado['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $prioridad_color; ?>">
                                            <?php echo ucfirst($atrasado['prioridad']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($atrasado['fecha_necesaria'])); ?></small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $severidad_color; ?>">
                                            <?php 
                                            if ($dias_atraso > 0) {
                                                echo "{$dias_atraso}d ";
                                            }
                                            echo "{$horas_atraso_restantes}h";
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
                    
                    <!-- Pedidos por vencer (próximas 24 horas) -->
                    <?php if (!empty($pedidos_por_vencer)): ?>
                    <hr>
                    <h6 class="text-warning">
                        <i class="bi bi-clock-history"></i> 
                        Por vencer en 24hs (<?php echo count($pedidos_por_vencer); ?>)
                    </h6>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Pedido</th>
                                    <th>Obra</th>
                                    <th>Estado</th>
                                    <th>Fecha Necesaria</th>
                                    <th class="text-end">Tiempo Restante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_por_vencer as $por_vencer): 
                                    $horas_restantes = $por_vencer['horas_restantes'];
                                    $color_urgencia = $horas_restantes <= 8 ? 'danger' : 'warning';
                                ?>
                                <tr>
                                    <td>
                                        <a href="../pedidos/view.php?id=<?php echo $por_vencer['id_pedido']; ?>" 
                                           target="_blank" class="text-decoration-none">
                                            <small>#<?php echo str_pad($por_vencer['id_pedido'], 4, '0', STR_PAD_LEFT); ?></small>
                                        </a>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($por_vencer['nombre_obra'], 0, 15)); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $estado_colors[$por_vencer['estado']] ?? 'secondary'; ?> badge-sm">
                                            <?php echo ucfirst($por_vencer['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m H:i', strtotime($por_vencer['fecha_necesaria'])); ?></small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $color_urgencia; ?>">
                                            <?php echo round($horas_restantes); ?>h
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

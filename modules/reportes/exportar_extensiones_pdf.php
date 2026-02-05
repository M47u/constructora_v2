<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../lib/fpdf/fpdf.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos
if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Obtener filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? get_current_date();
$id_obra = $_GET['id_obra'] ?? '';
$id_usuario = $_GET['id_usuario'] ?? '';

// Obtener nombre de obra para el título (si está filtrado)
$nombre_obra_filtro = '';
if ($id_obra) {
    $stmt_obra = $pdo->prepare("SELECT nombre_obra FROM obras WHERE id_obra = ?");
    $stmt_obra->execute([$id_obra]);
    $obra_filtro = $stmt_obra->fetch();
    if ($obra_filtro) {
        $nombre_obra_filtro = ' - ' . $obra_filtro['nombre_obra'];
    }
}

try {
    // Construir consulta con filtros
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($fecha_desde) {
        $where_conditions[] = "h.fecha_modificacion >= ?";
        $params[] = $fecha_desde . ' 00:00:00';
    }
    
    if ($fecha_hasta) {
        $where_conditions[] = "h.fecha_modificacion <= ?";
        $params[] = $fecha_hasta . ' 23:59:59';
    }
    
    if ($id_obra) {
        $where_conditions[] = "p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    if ($id_usuario) {
        $where_conditions[] = "h.id_usuario_modifico = ?";
        $params[] = $id_usuario;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Consulta principal
    $sql = "
        SELECT 
            h.id_extension,
            h.id_prestamo,
            h.fecha_anterior,
            h.fecha_nueva,
            h.motivo,
            h.fecha_modificacion,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            emp.nombre as empleado_nombre,
            emp.apellido as empleado_apellido,
            o.nombre_obra,
            DATEDIFF(h.fecha_nueva, h.fecha_anterior) as dias_extendidos
        FROM historial_extensiones_prestamo h
        JOIN usuarios u ON h.id_usuario_modifico = u.id_usuario
        JOIN prestamos p ON h.id_prestamo = p.id_prestamo
        JOIN usuarios emp ON p.id_empleado = emp.id_usuario
        JOIN obras o ON p.id_obra = o.id_obra
        $where_clause
        ORDER BY h.fecha_modificacion DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $extensiones = $stmt->fetchAll();
    
    // Calcular estadísticas
    $total_extensiones = count($extensiones);
    $total_dias_extendidos = 0;
    foreach ($extensiones as $ext) {
        if ($ext['dias_extendidos'] > 0) {
            $total_dias_extendidos += $ext['dias_extendidos'];
        }
    }
    $promedio_dias = $total_extensiones > 0 ? round($total_dias_extendidos / $total_extensiones, 1) : 0;
    
    // Crear PDF en formato horizontal (landscape)
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Título
    $pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'Reporte de Extensiones de Préstamos' . $nombre_obra_filtro), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'Período: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta))), 0, 1, 'C');
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'Generado: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Estadísticas
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 7, iconv('UTF-8', 'ISO-8859-1', 'Total Extensiones: ') . $total_extensiones, 1, 0, 'L', true);
    $pdf->Cell(70, 7, iconv('UTF-8', 'ISO-8859-1', 'Total Días Extendidos: ') . $total_dias_extendidos, 1, 0, 'L', true);
    $pdf->Cell(70, 7, iconv('UTF-8', 'ISO-8859-1', 'Promedio Días/Ext: ') . $promedio_dias, 1, 1, 'L', true);
    $pdf->Ln(5);
    
    // Encabezados de tabla
    $pdf->SetFillColor(66, 135, 245);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);
    
    $pdf->Cell(15, 7, 'ID Ext', 1, 0, 'C', true);
    $pdf->Cell(18, 7, 'ID Prest', 1, 0, 'C', true);
    $pdf->Cell(45, 7, 'Obra', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Empleado', 1, 0, 'C', true);
    $pdf->Cell(22, 7, 'Fec. Anterior', 1, 0, 'C', true);
    $pdf->Cell(22, 7, 'Fec. Nueva', 1, 0, 'C', true);
    $pdf->Cell(18, 7, iconv('UTF-8', 'ISO-8859-1', 'Días Ext'), 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Modificado Por', 1, 0, 'C', true);
    $pdf->Cell(28, 7, 'Fec. Modific.', 1, 1, 'C', true);
    
    // Datos
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 7);
    
    foreach ($extensiones as $ext) {
        $pdf->Cell(15, 6, $ext['id_extension'], 1, 0, 'C');
        $pdf->Cell(18, 6, $ext['id_prestamo'], 1, 0, 'C');
        $pdf->Cell(45, 6, iconv('UTF-8', 'ISO-8859-1', substr($ext['nombre_obra'], 0, 30)), 1, 0, 'L');
        $pdf->Cell(35, 6, iconv('UTF-8', 'ISO-8859-1', substr($ext['empleado_nombre'] . ' ' . $ext['empleado_apellido'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(22, 6, $ext['fecha_anterior'] ? date('d/m/Y', strtotime($ext['fecha_anterior'])) : 'No def.', 1, 0, 'C');
        $pdf->Cell(22, 6, date('d/m/Y', strtotime($ext['fecha_nueva'])), 1, 0, 'C');
        $pdf->Cell(18, 6, $ext['dias_extendidos'] > 0 ? '+' . $ext['dias_extendidos'] : '0', 1, 0, 'C');
        $pdf->Cell(35, 6, iconv('UTF-8', 'ISO-8859-1', substr($ext['usuario_nombre'] . ' ' . $ext['usuario_apellido'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(28, 6, date('d/m/Y H:i', strtotime($ext['fecha_modificacion'])), 1, 1, 'C');
        
        // Si hay motivo, agregarlo en una fila adicional
        if (!empty($ext['motivo'])) {
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->Cell(15, 5, '', 0, 0);
            $pdf->Cell(223, 5, iconv('UTF-8', 'ISO-8859-1', 'Motivo: ' . substr($ext['motivo'], 0, 120)), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 7);
        }
    }
    
    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Documento generado automáticamente - Sistema de Gestión de Constructora'), 0, 1, 'C');
    
    // Salida del PDF
    $pdf->Output('D', 'extensiones_prestamos_' . date('Y-m-d_His') . '.pdf');
    
} catch (Exception $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}

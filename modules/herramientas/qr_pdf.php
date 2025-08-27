<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Librería FPDF
require_once '../../lib/fpdf/fpdf.php';

$auth = new Auth();
$auth->check_session();

// Permitir recibir datos por POST (reimpresión) o GET (alta masiva)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $herramienta_id = (int)($_POST['id'] ?? 0);
    $estado_actual = $_POST['estado'] ?? 'disponible';
    $qr_codes = isset($_POST['qrs']) ? $_POST['qrs'] : [];
} else {
    $herramienta_id = (int)($_GET['id'] ?? 0);
    $estado_actual = $_GET['estado'] ?? 'disponible';
    $qr_codes = isset($_GET['qrs']) ? explode(',', $_GET['qrs']) : [];
    // Limpiar códigos QR vacíos del array
    $qr_codes = array_filter($qr_codes, function($qr) {
        return !empty(trim($qr));
    });
}

// Validación más específica con mensajes de error detallados
if ($herramienta_id <= 0) {
    die('Error: ID de herramienta no válido o no proporcionado.');
}

if (empty($qr_codes)) {
    die('Error: No se han seleccionado códigos QR para generar el PDF. Por favor, seleccione al menos una unidad.');
}

// Filtrar códigos QR vacíos
$qr_codes = array_filter($qr_codes, function($qr) {
    return !empty(trim($qr));
});

if (empty($qr_codes)) {
    die('Error: Los códigos QR proporcionados están vacíos.');
}

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT * FROM herramientas WHERE id_herramienta = ?");
$stmt->execute([$herramienta_id]);
$herramienta = $stmt->fetch();
if (!$herramienta) {
    die('Error: Herramienta no encontrada en la base de datos.');
}

// Verificar que los códigos QR existen en la base de datos
$placeholders = str_repeat('?,', count($qr_codes) - 1) . '?';
$stmt_qr = $conn->prepare("SELECT COUNT(*) as count FROM herramientas_unidades WHERE qr_code IN ($placeholders)");
$stmt_qr->execute($qr_codes);
$qr_count = $stmt_qr->fetch()['count'];

if ($qr_count != count($qr_codes)) {
    die('Error: Algunos códigos QR no existen en la base de datos.');
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Etiquetas QR - ' . $herramienta['marca'] . ' ' . $herramienta['modelo'], 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'Descripcion: ' . $herramienta['descripcion'], 0, 'L');
$pdf->Ln(5);

foreach ($qr_codes as $qr) {
    $qr = trim($qr); // Limpiar espacios en blanco
    if (empty($qr)) continue; // Saltar códigos vacíos
    
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'QR: ' . $qr, 0, 1);
    $pdf->SetFont('Arial', '', 12);
    //$pdf->Cell(0, 8, 'Estado: ' . ucfirst($estado_actual), 0, 1);
    // Imagen QR desde API
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($qr) . '&size=100x100';
    $img_file = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    
    // Manejar errores al descargar la imagen QR
    $qr_image_content = @file_get_contents($qr_url);
    if ($qr_image_content === false) {
        $pdf->Cell(0, 8, 'Error: No se pudo generar la imagen QR para: ' . $qr, 0, 1);
        $pdf->Ln(35);
        continue;
    }
    
    file_put_contents($img_file, $qr_image_content);
    $pdf->Image($img_file, $pdf->GetX(), $pdf->GetY(), 30, 30);
    unlink($img_file);
    $pdf->Ln(35);
}

$pdf->Output('I', 'etiquetas_qr_herramienta_' . $herramienta_id . '.pdf');
exit;

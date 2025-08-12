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
}

if ($herramienta_id <= 0 || empty($qr_codes)) {
    die('Datos insuficientes para generar el PDF.');
}

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT * FROM herramientas WHERE id_herramienta = ?");
$stmt->execute([$herramienta_id]);
$herramienta = $stmt->fetch();
if (!$herramienta) {
    die('Herramienta no encontrada.');
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Etiquetas QR - ' . $herramienta['marca'] . ' ' . $herramienta['modelo'], 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'Descripcion: ' . $herramienta['descripcion'], 0, 'L');
$pdf->Ln(5);

foreach ($qr_codes as $qr) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'QR: ' . $qr, 0, 1);
    $pdf->SetFont('Arial', '', 12);
    //$pdf->Cell(0, 8, 'Estado: ' . ucfirst($estado_actual), 0, 1);
    // Imagen QR desde API
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($qr) . '&size=100x100';
    $img_file = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    file_put_contents($img_file, file_get_contents($qr_url));
    $pdf->Image($img_file, $pdf->GetX(), $pdf->GetY(), 30, 30);
    unlink($img_file);
    $pdf->Ln(35);
}

$pdf->Output('I', 'etiquetas_qr_herramienta_' . $herramienta_id . '.pdf');
exit;

<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
$auth->check_session();

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="herramientas_export_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');

$usuario = $_SESSION['user_name'] ?? 'Usuario desconocido';
$fecha = date('d/m/Y H:i');

// Obtener herramientas
$stmt = $conn->prepare("SELECT marca, modelo, descripcion, stock_total, 
                               COUNT(CASE WHEN hu.estado_actual = 'disponible' THEN 1 ELSE NULL END) as disponibles,
                               condicion_general
                        FROM herramientas h
                        LEFT JOIN herramientas_unidades hu ON h.id_herramienta = hu.id_herramienta
                        GROUP BY h.id_herramienta
                        ORDER BY h.marca, h.modelo");
$stmt->execute();
$herramientas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <td colspan="5" style="text-align: center; font-weight: bold;">Reporte de Herramientas</td>
            </tr>
            <tr>
                <td colspan="3">Usuario: <?php echo htmlspecialchars($usuario); ?></td>
                <td colspan="2" style="text-align: right;">Fecha: <?php echo $fecha; ?></td>
            </tr>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Stock Total</th>
                <th>Disponibles</th>
                <th>Condición General</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($herramientas as $herramienta): ?>
            <tr>
                <td><?php echo htmlspecialchars($herramienta['marca'] . ' ' . $herramienta['modelo']); ?></td>
                <td><?php echo htmlspecialchars($herramienta['descripcion']); ?></td>
                <td><?php echo number_format($herramienta['stock_total']); ?></td>
                <td><?php echo number_format($herramienta['disponibles']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($herramienta['condicion_general'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
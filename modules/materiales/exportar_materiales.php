<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
$auth->check_session();

$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="materiales_export_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');

// Reemplazar 'get_user_name' con una alternativa válida para obtener el nombre del usuario
$usuario = $_SESSION['user_name'] ?? 'Usuario desconocido';
$fecha = date('d/m/Y H:i');

// Obtener materiales
$stmt = $conn->prepare("SELECT nombre_material AS nombre, stock_actual, stock_minimo, precio_referencia, unidad_medida, 
                               (stock_actual * precio_referencia) AS valor_total,
                               CASE 
                                   WHEN stock_actual = 0 THEN 'Sin Stock'
                                   WHEN stock_actual <= stock_minimo THEN 'Stock Bajo'
                                   ELSE 'Disponible'
                               END AS estado
                        FROM materiales
                        ORDER BY nombre_material");
$stmt->execute();
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <td colspan="7" style="text-align: center; font-weight: bold;">
                </td>
            </tr>
            <tr>
                <td colspan="7" style="text-align: center; font-weight: bold;">Reporte de Materiales</td>
            </tr>
            <tr>
                <td colspan="4">Usuario: <?php echo htmlspecialchars($usuario); ?></td>
                <td colspan="3" style="text-align: right;">Fecha: <?php echo $fecha; ?></td>
            </tr>
            <tr>
                <th>Nombre</th>
                <th>Stock Actual</th>
                <th>Stock Mínimo</th>
                <th>Precio de Referencia</th>
                <th>Unidad</th>
                <th>Valor Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($materiales as $material): ?>
            <tr>
                <td><?php echo htmlspecialchars($material['nombre']); ?></td>
                <td><?php echo $material['stock_actual'] === null ? 'No establecido' :number_format($material['stock_actual']); ?></td>
                <td><?php echo $material['stock_minimo'] === null ? 'No establecido' : number_format($material['stock_minimo']); ?></td>
                <td><?php echo number_format($material['precio_referencia'], 1); ?></td>
                <td><?php echo htmlspecialchars($material['unidad_medida']); ?></td>
                <td><?php echo $material['valor_total'] === null ? 'No establecido' : number_format($material['valor_total']); ?></td>
                <td><?php echo htmlspecialchars($material['estado']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
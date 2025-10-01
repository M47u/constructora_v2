<?php
// filepath: c:\xampp\htdocs\constructora_v2\modules\herramientas\print_prestamo.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$prestamo_id = (int)($_GET['id'] ?? 0);

if ($prestamo_id <= 0) {
    die("ID de préstamo inválido.");
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener datos del préstamo principal
    $query_prestamo = "SELECT p.*, 
                              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido, emp.email as empleado_email,
                              obra.nombre_obra, obra.direccion as obra_direccion, obra.localidad as obra_localidad,
                              aut.nombre as autorizado_nombre, aut.apellido as autorizado_apellido
                       FROM prestamos p 
                       JOIN usuarios emp ON p.id_empleado = emp.id_usuario
                       JOIN obras obra ON p.id_obra = obra.id_obra
                       JOIN usuarios aut ON p.id_autorizado_por = aut.id_usuario
                       WHERE p.id_prestamo = ?";
    
    $stmt_prestamo = $conn->prepare($query_prestamo);
    $stmt_prestamo->execute([$prestamo_id]);
    $prestamo = $stmt_prestamo->fetch();

    if (!$prestamo) {
        die("Préstamo no encontrado.");
    }

    // Obtener detalles de las herramientas prestadas
    $query_detalle = "SELECT dp.*, hu.qr_code, h.marca, h.modelo
                      FROM detalle_prestamo dp
                      JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
                      JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                      WHERE dp.id_prestamo = ?";
    
    $stmt_detalle = $conn->prepare($query_detalle);
    $stmt_detalle->execute([$prestamo_id]);
    $herramientas_prestadas = $stmt_detalle->fetchAll();

} catch (Exception $e) {
    die("Error al obtener los datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamo #<?php echo $prestamo['id_prestamo']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1, h2, h3 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        .no-print {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <h1>Detalles del Préstamo</h1>
    <h2>Préstamo #<?php echo $prestamo['id_prestamo']; ?></h2>

    <h3>Información General</h3>
    <p><strong>Empleado que Retira:</strong> <?php echo htmlspecialchars($prestamo['empleado_nombre'] . ' ' . $prestamo['empleado_apellido']); ?></p>
    <p><strong>Obra Destino:</strong> <?php echo htmlspecialchars($prestamo['nombre_obra']); ?>, <?php echo htmlspecialchars($prestamo['obra_direccion'] . ', ' . $prestamo['obra_localidad']); ?></p>
    <p><strong>Fecha de Retiro:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_retiro'])); ?></p>
    <p><strong>Autorizado Por:</strong> <?php echo htmlspecialchars($prestamo['autorizado_nombre'] . ' ' . $prestamo['autorizado_apellido']); ?></p>

    <?php if (!empty($prestamo['observaciones_retiro'])): ?>
        <h3>Observaciones</h3>
        <p><?php echo nl2br(htmlspecialchars($prestamo['observaciones_retiro'])); ?></p>
    <?php endif; ?>

    <h3>Herramientas Prestadas</h3>
    <table>
        <thead>
            <tr>
                <th>Herramienta</th>
                <th>Código QR</th>
                <th>Condición al Retiro</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($herramientas_prestadas as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['marca'] . ' ' . $item['modelo']); ?></td>
                <td><?php echo htmlspecialchars($item['qr_code']); ?></td>
                <td><?php echo ucfirst(htmlspecialchars($item['condicion_retiro'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
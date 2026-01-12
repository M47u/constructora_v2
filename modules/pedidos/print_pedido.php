<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$id_pedido = $_GET['id'] ?? 0;

if (!$id_pedido) {
    die('ID de pedido no proporcionado.');
}

try {
    // Obtener información del pedido
    $stmt = $conn->prepare("SELECT p.observaciones, p.id_pedido, o.nombre_obra, 
                            p.id_aprobado_por, p.fecha_aprobacion,
                            CONCAT(u.nombre, ' ', u.apellido) AS autorizado_por,
                            p.fecha_pedido, p.fecha_entrega, p.estado
                            FROM pedidos_materiales p
                            LEFT JOIN obras o ON p.id_obra = o.id_obra
                            LEFT JOIN usuarios u ON p.id_aprobado_por = u.id_usuario
                            WHERE p.id_pedido = ?");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die('Pedido no encontrado.');
    }
    
    // Debug temporal
    error_log("DEBUG Pedido: " . print_r($pedido, true));

    // Obtener detalles del pedido
    $stmt_detalles = $conn->prepare("SELECT m.nombre_material, d.cantidad_solicitada
                                     FROM detalle_pedidos_materiales d
                                     LEFT JOIN materiales m ON d.id_material = m.id_material
                                     WHERE d.id_pedido = ?");
    $stmt_detalles->execute([$id_pedido]);
    $detalles = $stmt_detalles->fetchAll();
} catch (Exception $e) {
    die('Error al obtener datos del pedido: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Pedido</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 200px;
            height: auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .materials {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .materials th, .materials td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        .materials th {
            background-color: #f2f2f2;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="logo">
        <img src="<?php echo SITE_URL; ?>/assets/img/logo_san_simon.png" alt="SAN SIMON SRL">
    </div>

    <div class="header">
        <h2>Pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?></h2>
        <p><strong>Destino:</strong> <?php echo htmlspecialchars($pedido['nombre_obra']); ?></p>
        <p><strong>Fecha de Generación:</strong> <?php echo $pedido['fecha_pedido'] ? date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) : 'N/A'; ?></p>
        <?php if (!empty($pedido['fecha_entrega'])): ?>
        <p><strong>Fecha de Entrega:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_entrega'])); ?></p>
        <?php endif; ?>
        <p><strong>Observaciones:</strong> <?php echo htmlspecialchars($pedido['observaciones']); ?></p>
    </div>

    <table class="materials">
        <thead>
            <tr>
                <th>Material</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $detalle): ?>
            <tr>
                <td><?php echo htmlspecialchars($detalle['nombre_material']); ?></td>
                <td><?php echo number_format($detalle['cantidad_solicitada']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <p><strong>Autorizado por:</strong> 
    <?php 
    if (!empty($pedido['autorizado_por'])) {
        echo htmlspecialchars($pedido['autorizado_por']);
        if (!empty($pedido['fecha_aprobacion'])) {
            echo ' - ' . date('d/m/Y H:i', strtotime($pedido['fecha_aprobacion']));
        }
    } else {
        echo '_____________________________';
    }
    ?></p>
    
    <p><strong>TRANSPORTISTA:</strong>__________________________________</p>
    
    <p><strong>FECHA Y HORA DE RECEPCION:</strong>__________________________________</p>
    
    <p><strong>COMPLETO:</strong>__________________________________</p>
    
    <p><strong>PARCIAL:</strong>__________________________________</p>

    <div class="footer">
        <p><strong>Nota:</strong> Por favor controlar al momento de la descarga, dejar firma por triplicado con fecha y lugar.</p>
        <p>Entregado a: ___________________________</p>
        <p>Formosa, ..... de ............................. de 20.....</p>
    </div>
</body>
</html>
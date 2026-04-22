<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-t');
$obra_id      = $_GET['obra_id']      ?? '';
$usuario      = $_SESSION['user_name'] ?? 'Usuario desconocido';

$obra_nombre_header = 'Todas las obras';
if (!empty($obra_id)) {
    try {
        $stmt_obra = $pdo->prepare("SELECT nombre_obra FROM obras WHERE id_obra = ?");
        $stmt_obra->execute([$obra_id]);
        $obra_row = $stmt_obra->fetch();
        if ($obra_row) {
            $obra_nombre_header = $obra_row['nombre_obra'];
        }
    } catch (PDOException $e) {}
}

try {
    $sql = "SELECT
                o.nombre_obra as obra_nombre,
                m.nombre_material as material_nombre,
                SUM(dpm.cantidad_solicitada) as total_cantidad,
                m.unidad_medida,
                AVG(m.precio_referencia) as precio_promedio,
                SUM(dpm.cantidad_solicitada * m.precio_referencia) as valor_total
            FROM detalle_pedidos_materiales dpm
            INNER JOIN pedidos_materiales pm ON dpm.id_pedido = pm.id_pedido
            INNER JOIN obras o ON pm.id_obra = o.id_obra
            INNER JOIN materiales m ON dpm.id_material = m.id_material
            WHERE pm.fecha_pedido BETWEEN ? AND ?
                AND pm.estado != 'cancelado'";
    $params = [$fecha_inicio, $fecha_fin];
    if (!empty($obra_id)) {
        $sql .= " AND o.id_obra = ?";
        $params[] = $obra_id;
    }
    $sql .= " GROUP BY o.id_obra, m.id_material
              ORDER BY m.nombre_material ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos_reporte = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

$total_general = 0;
foreach ($datos_reporte as $dato) {
    $total_general += $dato['valor_total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Materiales por Obra</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; background: #fff; padding: 20px; }

        .header { text-align: center; margin-bottom: 16px; }
        .header h2 { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .header p  { font-size: 11px; color: #444; }

        .info-table { width: 100%; margin-bottom: 14px; font-size: 11px; }
        .info-table td { padding: 2px 4px; }
        .info-table td:first-child { font-weight: bold; width: 120px; }

        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.datos th { background: #2c3e50; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.datos td { padding: 5px 8px; border-bottom: 1px solid #ddd; font-size: 11px; }
        table.datos tr:nth-child(even) td { background: #f5f5f5; }
        table.datos td.num { text-align: right; }

        .total-row td { font-weight: bold; border-top: 2px solid #2c3e50; background: #ecf0f1 !important; }

        .footer { margin-top: 20px; font-size: 10px; color: #666; text-align: right; }

        .btn-print {
            display: inline-block;
            margin-bottom: 16px;
            padding: 8px 18px;
            background: #c0392b;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 16px;
            margin-right: 8px;
            padding: 8px 18px;
            background: #555;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom:12px;">
    <a class="btn-back" href="javascript:history.back()">&#8592; Volver</a>
    <button class="btn-print" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
</div>

<div class="header">
    <h2>Reporte de Materiales por Obra</h2>
    <p>Generado el <?php echo date('d/m/Y H:i'); ?></p>
</div>

<table class="info-table">
    <tr>
        <td>Obra:</td>
        <td><?php echo htmlspecialchars($obra_nombre_header); ?></td>
        <td style="width:120px; font-weight:bold;">Período:</td>
        <td><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> &ndash; <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></td>
    </tr>
    <tr>
        <td>Usuario:</td>
        <td><?php echo htmlspecialchars($usuario); ?></td>
    </tr>
</table>

<?php if (!empty($datos_reporte)): ?>
<table class="datos">
    <thead>
        <tr>
            <th>Material</th>
            <th>Obra</th>
            <th class="num">Cantidad</th>
            <th>Unidad</th>
            <th class="num">Precio x U.</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($datos_reporte as $dato): ?>
        <tr>
            <td><?php echo htmlspecialchars($dato['material_nombre']); ?></td>
            <td><?php echo htmlspecialchars($dato['obra_nombre']); ?></td>
            <td class="num"><?php echo number_format((float)$dato['total_cantidad'], 2, ',', ''); ?></td>
            <td><?php echo htmlspecialchars($dato['unidad_medida']); ?></td>
            <td class="num">$<?php echo number_format((float)$dato['precio_promedio'], 2, ',', ''); ?></td>
            <td class="num">$<?php echo number_format((float)$dato['valor_total'], 2, ',', ''); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="5">Total General</td>
            <td class="num">$<?php echo number_format((float)$total_general, 2, ',', ''); ?></td>
        </tr>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center; color:#888; padding:20px 0;">No se encontraron registros para el período y filtros seleccionados.</p>
<?php endif; ?>

<div class="footer">
    Reporte generado por: <?php echo htmlspecialchars($usuario); ?> &mdash; <?php echo date('d/m/Y H:i:s'); ?>
</div>

</body>
</html>

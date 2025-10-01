<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos (solo administradores y responsables de obra)
if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$obra_id = $_GET['obra_id'] ?? '';
$usuario = $_SESSION['user_name'] ?? 'Usuario desconocido';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="materiales_por_obra_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');

// Obtener datos del reporte
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
            WHERE pm.fecha_pedido BETWEEN ? AND ?";
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
    echo "Error al obtener datos: " . $e->getMessage();
    exit();
}

?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <td colspan="5" style="text-align: center; font-weight: bold;">Reporte de Materiales por Obra</td>
            </tr>
            <tr>
                <td colspan="3">Obra: <?php echo htmlspecialchars($datos_reporte[0]['obra_nombre'] ?? 'Todas las obras'); ?></td>
                <td colspan="2" style="text-align: right;">Fecha: <?php echo date('d/m/Y'); ?></td>
            </tr>
            <tr>
                <td colspan="5">Usuario: <?php echo htmlspecialchars($usuario); ?></td>
            </tr>
            <tr>
                <th>Materiales</th>
                <th>Cantidad Total</th>
                <th>Unidad de Medida</th>
                <th>Precio x U.</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($datos_reporte as $dato): ?>
            <tr>
                <td><?php echo htmlspecialchars($dato['material_nombre']); ?></td>
                <td><?php echo number_format($dato['total_cantidad'], 2); ?></td>
                <td><?php echo htmlspecialchars($dato['unidad_medida']); ?></td>
                <td><?php echo number_format($dato['precio_promedio'], 2); ?></td>
                <td><?php echo number_format($dato['valor_total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
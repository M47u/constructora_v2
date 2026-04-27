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
$mostrar_columna_obra = empty($obra_id);
$periodo_reporte = date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));

// Resolver nombre de obra para el encabezado
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
    echo "Error al obtener datos: " . $e->getMessage();
    exit();
}

$total_general = 0;
foreach ($datos_reporte as $dato) {
    $total_general += (float)$dato['valor_total'];
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
                <td colspan="<?php echo $mostrar_columna_obra ? '6' : '5'; ?>" style="text-align: center; font-weight: bold;">Reporte de Materiales por Obra</td>
            </tr>
            <tr>
                <td colspan="<?php echo $mostrar_columna_obra ? '4' : '3'; ?>">Obra: <?php echo htmlspecialchars($obra_nombre_header); ?></td>
                <td colspan="2" style="text-align: right;">Fecha: <?php echo date('d/m/Y'); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo $mostrar_columna_obra ? '6' : '5'; ?>">Período: <?php echo htmlspecialchars($periodo_reporte); ?></td>
            </tr>
            <tr>
                <td colspan="<?php echo $mostrar_columna_obra ? '6' : '5'; ?>">Usuario: <?php echo htmlspecialchars($usuario); ?></td>
            </tr>
            <tr>
                <th>Materiales</th>
                <?php if ($mostrar_columna_obra): ?>
                <th>Obra</th>
                <?php endif; ?>
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
                <?php if ($mostrar_columna_obra): ?>
                <td><?php echo htmlspecialchars($dato['obra_nombre']); ?></td>
                <?php endif; ?>
                <td><?php echo number_format((float)$dato['total_cantidad'], 2, ',', ''); ?></td>
                <td><?php echo htmlspecialchars($dato['unidad_medida']); ?></td>
                <td><?php echo number_format((float)$dato['precio_promedio'], 2, ',', ''); ?></td>
                <td><?php echo number_format((float)$dato['valor_total'], 2, ',', ''); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="<?php echo $mostrar_columna_obra ? '5' : '4'; ?>" style="font-weight: bold; text-align: right;">Total General</td>
                <td style="font-weight: bold; text-align: right;"><?php echo number_format((float)$total_general, 2, ',', ''); ?></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
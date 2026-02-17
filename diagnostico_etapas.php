<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

echo "<h2>Diagnóstico de Etapas de Pedidos</h2>";
echo "<hr>";

// 1. Verificar columnas en pedidos_materiales
echo "<h3>1. Columnas de pedidos_materiales</h3>";
$stmt = $conn->query("DESCRIBE pedidos_materiales");
$columnas = $stmt->fetchAll();
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Campo</th><th>Tipo</th></tr>";
foreach ($columnas as $col) {
    if (strpos($col['Field'], 'fecha_') === 0 || strpos($col['Field'], 'id_') === 0 || $col['Field'] === 'estado') {
        echo "<tr><td><strong>{$col['Field']}</strong></td><td>{$col['Type']}</td></tr>";
    }
}
echo "</table>";
echo "<br>";

// 2. Verificar estados en seguimiento_pedidos
echo "<h3>2. Estados en seguimiento_pedidos</h3>";
$stmt = $conn->query("SELECT DISTINCT estado_nuevo FROM seguimiento_pedidos ORDER BY estado_nuevo");
$estados = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<p><strong>Estados encontrados:</strong> " . implode(', ', $estados) . "</p>";
echo "<br>";

// 3. Contar registros en seguimiento_pedidos por estado
echo "<h3>3. Cantidad de registros por estado en seguimiento_pedidos</h3>";
$stmt = $conn->query("SELECT estado_nuevo, COUNT(*) as total FROM seguimiento_pedidos GROUP BY estado_nuevo ORDER BY total DESC");
$conteos = $stmt->fetchAll();
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Estado</th><th>Cantidad de registros</th></tr>";
foreach ($conteos as $row) {
    echo "<tr><td>{$row['estado_nuevo']}</td><td>{$row['total']}</td></tr>";
}
echo "</table>";
echo "<br>";

// 4. Verificar pedidos entregados en el último mes
echo "<h3>4. Pedidos entregados en el último mes</h3>";
$fecha_inicio = date('Y-m-01');
$fecha_fin = date('Y-m-t');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos_materiales WHERE estado = 'entregado' AND fecha_pedido BETWEEN ? AND ?");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$total_entregados = $stmt->fetch()['total'];
echo "<p><strong>Total de pedidos entregados:</strong> $total_entregados</p>";
echo "<br>";

// 5. Verificar pedidos con datos de etapas intermedias
echo "<h3>5. Pedidos con datos en columnas de etapas</h3>";
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN fecha_aprobacion IS NOT NULL THEN 1 ELSE 0 END) as con_aprobacion,
    SUM(CASE WHEN fecha_picking IS NOT NULL THEN 1 ELSE 0 END) as con_picking,
    SUM(CASE WHEN fecha_retiro IS NOT NULL THEN 1 ELSE 0 END) as con_retiro,
    SUM(CASE WHEN fecha_recibido IS NOT NULL THEN 1 ELSE 0 END) as con_recibido,
    SUM(CASE WHEN fecha_entrega IS NOT NULL THEN 1 ELSE 0 END) as con_entrega
FROM pedidos_materiales 
WHERE estado = 'entregado' AND fecha_pedido BETWEEN ? AND ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $etapas = $stmt->fetch();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Cantidad</th></tr>";
    foreach ($etapas as $key => $value) {
        echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Esto puede indicar que algunas columnas no existen en la tabla.</p>";
}
echo "<br>";

// 6. Verificar estados disponibles en el ENUM de pedidos_materiales
echo "<h3>6. Valores del ENUM 'estado' en pedidos_materiales</h3>";
$stmt = $conn->query("SHOW COLUMNS FROM pedidos_materiales LIKE 'estado'");
$enum_info = $stmt->fetch();
echo "<p><strong>Tipo:</strong> {$enum_info['Type']}</p>";
echo "<br>";

// 7. Muestra de datos de un pedido entregado
echo "<h3>7. Muestra de datos de pedidos entregados (últimos 5)</h3>";
$sql_muestra = "SELECT 
    id_pedido,
    numero_pedido,
    estado,
    fecha_pedido,
    fecha_aprobacion";

// Intentamos agregar las otras columnas si existen
$columnas_opcionales = ['fecha_picking', 'fecha_retiro', 'fecha_recibido'];
foreach ($columnas_opcionales as $col) {
    try {
        $test = $conn->query("SELECT $col FROM pedidos_materiales LIMIT 1");
        $sql_muestra .= ", $col";
    } catch (PDOException $e) {
        // La columna no existe
    }
}

$sql_muestra .= ", fecha_entrega
FROM pedidos_materiales 
WHERE estado = 'entregado' AND fecha_pedido BETWEEN ? AND ?
ORDER BY fecha_pedido DESC
LIMIT 5";

$stmt = $conn->prepare($sql_muestra);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$muestras = $stmt->fetchAll();

if (!empty($muestras)) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr>";
    foreach (array_keys($muestras[0]) as $campo) {
        if (!is_numeric($campo)) {
            echo "<th>$campo</th>";
        }
    }
    echo "</tr>";
    foreach ($muestras as $row) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                echo "<td>" . ($value ?? '<em>NULL</em>') . "</td>";
            }
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay pedidos entregados en el período seleccionado.</p>";
}

echo "<br>";
echo "<p><a href='modules/reportes/metricas_pedidos.php'>Volver a Métricas</a></p>";
?>

<?php
/**
 * Script de corrección: asigna fecha_asignacion a tareas pre-creadas (habilitada=0)
 * que tienen fecha_asignacion NULL, usando la fecha del pedido correspondiente.
 *
 * Solo afecta tareas con habilitada=0 AND fecha_asignacion IS NULL:
 *   - Pedidos pre-migración (legacy): NO afectados (todas sus tareas tienen habilitada=1)
 *   - Pedidos en curso creados después de la migración: SÍ afectados (etapas futuras pre-creadas)
 *
 * Ejecutar UNA sola vez desde el navegador y luego eliminar.
 */
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<pre>\n";
echo "=== Fix: fecha_asignacion en tareas pre-creadas ===\n\n";

// Mostrar detalle de qué pedidos y etapas serán afectadas
$stmt_detalle = $conn->query("
    SELECT
        pm.id_pedido,
        pm.numero_pedido,
        pm.estado           AS estado_pedido,
        pm.fecha_pedido,
        pt.etapa,
        t.id_tarea,
        t.titulo,
        t.habilitada
    FROM tareas t
    JOIN pedido_tareas pt        ON pt.id_tarea  = t.id_tarea
    JOIN pedidos_materiales pm   ON pm.id_pedido = pt.id_pedido
    WHERE t.habilitada = 0
      AND t.fecha_asignacion IS NULL
    ORDER BY pm.id_pedido, pt.etapa
");
$filas = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

if (empty($filas)) {
    echo "No hay tareas a corregir. Nada que hacer.\n";
    echo "</pre>\n";
    exit;
}

echo "Tareas que serán actualizadas (" . count($filas) . " filas):\n";
echo str_repeat('-', 80) . "\n";
printf("%-8s %-10s %-12s %-20s %-10s\n", 'Pedido', 'Número', 'Estado', 'Etapa', 'fecha→');
echo str_repeat('-', 80) . "\n";
foreach ($filas as $f) {
    printf(
        "%-8s %-10s %-12s %-20s %-10s\n",
        $f['id_pedido'],
        $f['numero_pedido'] ?? '-',
        $f['estado_pedido'],
        $f['etapa'],
        date('d/m/Y H:i', strtotime($f['fecha_pedido']))
    );
}
echo str_repeat('-', 80) . "\n\n";

// Aplicar la corrección
$stmt = $conn->prepare("
    UPDATE tareas t
    JOIN pedido_tareas pt        ON pt.id_tarea  = t.id_tarea
    JOIN pedidos_materiales pm   ON pm.id_pedido = pt.id_pedido
    SET t.fecha_asignacion = pm.fecha_pedido
    WHERE t.habilitada = 0
      AND t.fecha_asignacion IS NULL
");
$stmt->execute();
$actualizadas = $stmt->rowCount();

echo "Filas actualizadas: $actualizadas\n";
echo "Corrección aplicada correctamente.\n";
echo "\n¡Listo! Puedes eliminar este archivo.\n";
echo "</pre>\n";

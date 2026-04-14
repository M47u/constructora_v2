<?php
/**
 * Script de corrección: asigna fecha_asignacion a tareas pre-creadas (habilitada=0)
 * que tienen fecha_asignacion NULL, usando la fecha del pedido correspondiente.
 *
 * Ejecutar UNA sola vez desde el navegador y luego eliminar.
 */
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Verificar cuántas filas serán afectadas
$stmt_check = $conn->query("
    SELECT COUNT(*) AS total
    FROM tareas t
    JOIN pedido_tareas pt ON pt.id_tarea = t.id_tarea
    JOIN pedidos_materiales pm ON pm.id_pedido = pt.id_pedido
    WHERE t.habilitada = 0
      AND t.fecha_asignacion IS NULL
");
$total = $stmt_check->fetchColumn();

echo "<pre>\n";
echo "Tareas a corregir (habilitada=0 y fecha_asignacion IS NULL): $total\n\n";

if ($total > 0) {
    $stmt = $conn->prepare("
        UPDATE tareas t
        JOIN pedido_tareas pt ON pt.id_tarea = t.id_tarea
        JOIN pedidos_materiales pm ON pm.id_pedido = pt.id_pedido
        SET t.fecha_asignacion = pm.fecha_pedido
        WHERE t.habilitada = 0
          AND t.fecha_asignacion IS NULL
    ");
    $stmt->execute();
    $actualizadas = $stmt->rowCount();
    echo "Filas actualizadas: $actualizadas\n";
    echo "Corrección aplicada correctamente.\n";
} else {
    echo "No hay filas a corregir. Nada que hacer.\n";
}

echo "\n¡Listo! Puedes eliminar este archivo.\n";
echo "</pre>\n";

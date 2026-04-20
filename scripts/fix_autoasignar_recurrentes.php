<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("\n    UPDATE tareas\n    SET tipo = 'manual',\n        fecha_asignacion = COALESCE(fecha_asignacion, NOW()),\n        estado = CASE WHEN estado = 'pendiente' THEN 'en_proceso' ELSE estado END,\n        fecha_inicio = CASE\n            WHEN estado = 'pendiente' THEN COALESCE(fecha_inicio, NOW())\n            ELSE fecha_inicio\n        END,\n        fecha_finalizacion = CASE\n            WHEN estado = 'pendiente' THEN NULL\n            ELSE fecha_finalizacion\n        END\n    WHERE tipo = 'recurrente'\n      AND id_tarea_recurrente IS NOT NULL\n");
$stmt->execute();

echo 'Tareas corregidas: ' . $stmt->rowCount() . PHP_EOL;

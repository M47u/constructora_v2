<?php
/**
 * Migración de una sola vez:
 * Corrige títulos y descripciones de tareas automáticas que aún usan
 * el formato '#PED20XXXXXX' → '#XXXX' (id_pedido con 4 dígitos).
 *
 * EJECUTAR UNA VEZ y luego BORRAR este archivo.
 */
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

$conn->beginTransaction();

try {
    // 1. Corregir títulos
    $stmt_titulos = $conn->prepare("
        UPDATE tareas
        SET titulo = CONCAT(
            REGEXP_REPLACE(titulo, ' #PED[0-9]+', ''),
            ' #',
            LPAD(id_pedido, 4, '0')
        )
        WHERE tipo = 'pedido'
          AND id_pedido IS NOT NULL
          AND titulo REGEXP '#PED[0-9]+'
    ");
    $stmt_titulos->execute();
    $titulos = $stmt_titulos->rowCount();

    // 2. Corregir descripciones
    $stmt_desc = $conn->prepare("
        UPDATE tareas
        SET descripcion = REGEXP_REPLACE(
            descripcion,
            'PED[0-9]+',
            CONCAT('#', LPAD(id_pedido, 4, '0'))
        )
        WHERE tipo = 'pedido'
          AND id_pedido IS NOT NULL
          AND descripcion REGEXP 'PED[0-9]+'
    ");
    $stmt_desc->execute();
    $descs = $stmt_desc->rowCount();

    $conn->commit();

    echo "<h2 style='color:green'>Migración completada</h2>";
    echo "<p>Títulos corregidos: <strong>{$titulos}</strong></p>";
    echo "<p>Descripciones corregidas: <strong>{$descs}</strong></p>";
    echo "<p style='color:red'><strong>BORRA este archivo ahora: migrate_tareas_titulos.php</strong></p>";

} catch (Exception $e) {
    $conn->rollBack();
    echo "<h2 style='color:red'>Error</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

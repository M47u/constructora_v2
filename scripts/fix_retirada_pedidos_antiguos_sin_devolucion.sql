-- Corrección retroactiva de retiros en pedidos antiguos sin devoluciones
-- Objetivo:
--   Para pedidos con fecha_pedido de hace más de 1 año y sin registros en devoluciones_pedidos,
--   igualar cantidad_retirada a cantidad_solicitada en detalle_pedidos_materiales.

-- Paso 1: Diagnóstico (antes)
SELECT
    COUNT(DISTINCT p.id_pedido) AS pedidos_afectados,
    COUNT(*) AS detalles_afectados
FROM detalle_pedidos_materiales d
JOIN pedidos_materiales p ON p.id_pedido = d.id_pedido
WHERE p.fecha_pedido < DATE_SUB(NOW(), INTERVAL 6 MONTH)
  AND p.estado IN ('retirado', 'recibido', 'devuelto')
  AND d.cantidad_retirada <> d.cantidad_solicitada;

-- Paso 2: Backup de filas a corregir
CREATE TABLE IF NOT EXISTS backup_detalle_retirada_pedidos_antiguos AS
SELECT
    d.*, NOW() AS fecha_backup
FROM detalle_pedidos_materiales d
JOIN pedidos_materiales p ON p.id_pedido = d.id_pedido
WHERE p.fecha_pedido < DATE_SUB(NOW(), INTERVAL 6 MONTH)
  AND p.estado IN ('retirado', 'recibido', 'devuelto')
  AND d.cantidad_retirada <> d.cantidad_solicitada;

-- Paso 3: Corrección
UPDATE detalle_pedidos_materiales d
JOIN pedidos_materiales p ON p.id_pedido = d.id_pedido
SET d.cantidad_retirada = d.cantidad_solicitada
WHERE p.fecha_pedido < DATE_SUB(NOW(), INTERVAL 6 MONTH)
  AND p.estado IN ('retirado', 'recibido', 'devuelto')
  AND d.cantidad_retirada <> d.cantidad_solicitada;

-- Paso 4: Verificación (después)
SELECT
    COUNT(DISTINCT p.id_pedido) AS pedidos_pendientes,
    COUNT(*) AS detalles_pendientes
FROM detalle_pedidos_materiales d
JOIN pedidos_materiales p ON p.id_pedido = d.id_pedido
WHERE p.fecha_pedido < DATE_SUB(NOW(), INTERVAL 6 MONTH)
  AND p.estado IN ('retirado', 'recibido', 'devuelto')
  AND d.cantidad_retirada <> d.cantidad_solicitada;

-- Script para corregir los problemas de stock en herramientas
-- Este script debe ejecutarse en la base de datos para aplicar las correcciones

-- 1. Eliminar el trigger de actualización si existe (para evitar duplicados)
DROP TRIGGER IF EXISTS `tr_herramientas_stock_update`;

-- 2. Crear el trigger para manejar actualizaciones de estado
DELIMITER $$
CREATE TRIGGER `tr_herramientas_stock_update` AFTER UPDATE ON `herramientas_unidades` FOR EACH ROW BEGIN
    -- Solo actualizar si el estado cambió
    IF OLD.estado_actual != NEW.estado_actual THEN
        UPDATE herramientas 
        SET stock_total = (
            SELECT COUNT(*) 
            FROM herramientas_unidades 
            WHERE id_herramienta = NEW.id_herramienta
        )
        WHERE id_herramienta = NEW.id_herramienta;
    END IF;
END
$$
DELIMITER ;

-- 3. Corregir el stock_total de todas las herramientas para asegurar consistencia
UPDATE herramientas h 
SET stock_total = (
    SELECT COUNT(*) 
    FROM herramientas_unidades hu 
    WHERE hu.id_herramienta = h.id_herramienta
);

-- 4. Mostrar el resultado de la corrección
SELECT 
    h.id_herramienta,
    h.marca,
    h.modelo,
    h.stock_total as stock_actual,
    COUNT(hu.id_unidad) as unidades_reales
FROM herramientas h
LEFT JOIN herramientas_unidades hu ON h.id_herramienta = hu.id_herramienta
GROUP BY h.id_herramienta, h.marca, h.modelo, h.stock_total
ORDER BY h.id_herramienta;

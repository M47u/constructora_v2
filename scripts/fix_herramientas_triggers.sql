-- Trigger para actualizar stock_total cuando se actualiza el estado de una unidad
-- Este trigger se ejecuta automáticamente cuando se actualiza el estado de una unidad
-- y recalcula el stock_total contando todas las unidades de esa herramienta

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

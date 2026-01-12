-- Script para eliminar restricciones de orden de etapas en pedidos
-- Permite saltar etapas según necesidades operativas

-- Eliminar el trigger restrictivo anterior
DROP TRIGGER IF EXISTS validate_pedido_stage_order;

-- Crear nuevo trigger solo con validaciones de fechas (sin restricciones de orden)
DELIMITER $$

CREATE TRIGGER validate_pedido_stage_order
BEFORE UPDATE ON pedidos_materiales
FOR EACH ROW
BEGIN
    DECLARE error_msg VARCHAR(255);
    
    -- Validar que las fechas sean coherentes (cada etapa posterior debe tener fecha >= etapa anterior)
    -- Pero permitir que las etapas se completen en cualquier orden
    
    IF NEW.fecha_aprobacion IS NOT NULL AND NEW.fecha_aprobacion < NEW.fecha_pedido THEN
        SET error_msg = 'La fecha de aprobación no puede ser anterior a la fecha de creación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_picking IS NOT NULL AND NEW.fecha_pedido IS NOT NULL AND NEW.fecha_picking < NEW.fecha_pedido THEN
        SET error_msg = 'La fecha de picking no puede ser anterior a la fecha de creación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_retiro IS NOT NULL AND NEW.fecha_pedido IS NOT NULL AND NEW.fecha_retiro < NEW.fecha_pedido THEN
        SET error_msg = 'La fecha de retiro no puede ser anterior a la fecha de creación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_recibido IS NOT NULL AND NEW.fecha_pedido IS NOT NULL AND NEW.fecha_recibido < NEW.fecha_pedido THEN
        SET error_msg = 'La fecha de recepción no puede ser anterior a la fecha de creación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Validación opcional: Las fechas deben seguir un orden lógico cuando todas están presentes
    -- pero NO se requiere que todas las etapas estén completadas
    IF NEW.fecha_aprobacion IS NOT NULL AND NEW.fecha_picking IS NOT NULL 
       AND NEW.fecha_picking < NEW.fecha_aprobacion THEN
        SET error_msg = 'La fecha de picking no puede ser anterior a la fecha de aprobación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_picking IS NOT NULL AND NEW.fecha_retiro IS NOT NULL 
       AND NEW.fecha_retiro < NEW.fecha_picking THEN
        SET error_msg = 'La fecha de retiro no puede ser anterior a la fecha de picking';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_retiro IS NOT NULL AND NEW.fecha_recibido IS NOT NULL 
       AND NEW.fecha_recibido < NEW.fecha_retiro THEN
        SET error_msg = 'La fecha de recepción no puede ser anterior a la fecha de retiro';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
END$$

DELIMITER ;

-- También actualizar el trigger de picking si existe
DROP TRIGGER IF EXISTS before_update_pedidos_materiales_etapas;

DELIMITER $$

CREATE TRIGGER before_update_pedidos_materiales_etapas
BEFORE UPDATE ON pedidos_materiales
FOR EACH ROW
BEGIN
    -- Solo validaciones de fechas, sin restricciones de orden de etapas
    
    -- Validación: fecha_picking debe ser >= fecha_aprobacion (si ambas existen)
    IF NEW.fecha_picking IS NOT NULL AND NEW.fecha_aprobacion IS NOT NULL THEN
        IF NEW.fecha_picking < NEW.fecha_aprobacion THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha de picking no puede ser anterior a la fecha de aprobación';
        END IF;
    END IF;
    
    -- Validación: fecha_retiro debe ser >= fecha_picking (si ambas existen)
    IF NEW.fecha_retiro IS NOT NULL AND NEW.fecha_picking IS NOT NULL THEN
        IF NEW.fecha_retiro < NEW.fecha_picking THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha de retiro no puede ser anterior a la fecha de picking';
        END IF;
    END IF;
    
    -- Validación: fecha_recibido debe ser >= fecha_picking (si ambas existen)
    IF NEW.fecha_recibido IS NOT NULL AND NEW.fecha_picking IS NOT NULL THEN
        IF NEW.fecha_recibido < NEW.fecha_picking THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha de recepción no puede ser anterior a la fecha de picking';
        END IF;
    END IF;
END$$

DELIMITER ;

-- Insertar registro en logs
INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion)
SELECT 1, 'schema_update', 'pedidos', 'Eliminadas restricciones de orden de etapas - se permite saltar etapas según necesidad operativa', NOW()
WHERE EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = 1);

SELECT '✓ Triggers actualizados - Se permite completar etapas en cualquier orden' AS resultado;

-- Script de migración para agregar sistema de etapas a pedidos
-- Agrega: Creación, Aprobación, Picking, Retiro, Entrega
-- Permite al admin asignar usuarios y editar fechas de cada etapa

-- Backup de la tabla actual
CREATE TABLE IF NOT EXISTS backup_pedidos_materiales_stages AS SELECT * FROM pedidos_materiales;

-- Agregar nuevas columnas para las etapas
ALTER TABLE pedidos_materiales
ADD COLUMN IF NOT EXISTS id_picking_por INT NULL COMMENT 'Usuario que realizó el picking',
ADD COLUMN IF NOT EXISTS fecha_picking TIMESTAMP NULL COMMENT 'Fecha y hora de picking',
ADD COLUMN IF NOT EXISTS id_retirado_por INT NULL COMMENT 'Usuario que retiró el pedido',
ADD COLUMN IF NOT EXISTS fecha_retiro TIMESTAMP NULL COMMENT 'Fecha y hora de retiro',
ADD COLUMN IF NOT EXISTS id_recibido_por INT NULL COMMENT 'Usuario que recibió el pedido',
ADD COLUMN IF NOT EXISTS fecha_recibido TIMESTAMP NULL COMMENT 'Fecha y hora de recepción';

-- Actualizar el ENUM de estados para incluir 'picking'
ALTER TABLE pedidos_materiales 
MODIFY COLUMN estado ENUM('pendiente', 'aprobado', 'picking', 'retirado', 'recibido', 'en_camino', 'entregado', 'devuelto', 'cancelado') DEFAULT 'pendiente';

-- Agregar las foreign keys para los nuevos campos (eliminar primero si existen)
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
              WHERE CONSTRAINT_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'pedidos_materiales' 
              AND CONSTRAINT_NAME = 'fk_pedidos_picking_por' 
              AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE pedidos_materiales DROP FOREIGN KEY fk_pedidos_picking_por', 'SELECT ''Constraint fk_pedidos_picking_por no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
              WHERE CONSTRAINT_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'pedidos_materiales' 
              AND CONSTRAINT_NAME = 'fk_pedidos_retirado_por' 
              AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE pedidos_materiales DROP FOREIGN KEY fk_pedidos_retirado_por', 'SELECT ''Constraint fk_pedidos_retirado_por no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
              WHERE CONSTRAINT_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'pedidos_materiales' 
              AND CONSTRAINT_NAME = 'fk_pedidos_recibido_por' 
              AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE pedidos_materiales DROP FOREIGN KEY fk_pedidos_recibido_por', 'SELECT ''Constraint fk_pedidos_recibido_por no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ahora crear las foreign keys
ALTER TABLE pedidos_materiales
ADD CONSTRAINT fk_pedidos_picking_por FOREIGN KEY (id_picking_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
ADD CONSTRAINT fk_pedidos_retirado_por FOREIGN KEY (id_retirado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
ADD CONSTRAINT fk_pedidos_recibido_por FOREIGN KEY (id_recibido_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL;

-- Actualizar la tabla de seguimiento para incluir los nuevos estados
ALTER TABLE seguimiento_pedidos 
MODIFY COLUMN estado_anterior ENUM('pendiente', 'aprobado', 'picking', 'retirado', 'recibido', 'en_camino', 'entregado', 'devuelto', 'cancelado'),
MODIFY COLUMN estado_nuevo ENUM('pendiente', 'aprobado', 'picking', 'retirado', 'recibido', 'en_camino', 'entregado', 'devuelto', 'cancelado') NOT NULL;

-- Crear tabla para control de edición de etapas (historial de cambios por admin)
CREATE TABLE IF NOT EXISTS historial_edicion_etapas_pedidos (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    etapa ENUM('creacion', 'aprobacion', 'picking', 'retiro', 'recibido') NOT NULL,
    campo_editado ENUM('usuario', 'fecha') NOT NULL,
    valor_anterior VARCHAR(255),
    valor_nuevo VARCHAR(255),
    id_usuario_editor INT NOT NULL COMMENT 'Usuario administrador que realizó el cambio',
    fecha_edicion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_usuario VARCHAR(45),
    observaciones TEXT,
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_editor) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para optimizar consultas (eliminar primero si existen)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'pedidos_materiales' 
              AND INDEX_NAME = 'idx_pedidos_retirado_por');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_pedidos_retirado_por ON pedidos_materiales', 'SELECT ''Index idx_pedidos_retirado_por no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'pedidos_materiales' 
              AND INDEX_NAME = 'idx_pedidos_recibido_por');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_pedidos_recibido_por ON pedidos_materiales', 'SELECT ''Index idx_pedidos_recibido_por no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'pedidos_materiales' 
              AND INDEX_NAME = 'idx_pedidos_estado');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_pedidos_estado ON pedidos_materiales', 'SELECT ''Index idx_pedidos_estado no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'historial_edicion_etapas_pedidos' 
              AND INDEX_NAME = 'idx_historial_pedido');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_historial_pedido ON historial_edicion_etapas_pedidos', 'SELECT ''Index idx_historial_pedido no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'historial_edicion_etapas_pedidos' 
              AND INDEX_NAME = 'idx_historial_fecha');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_historial_fecha ON historial_edicion_etapas_pedidos', 'SELECT ''Index idx_historial_fecha no existe''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ahora crear los índices
CREATE INDEX idx_pedidos_picking_por ON pedidos_materiales(id_picking_por);
CREATE INDEX idx_pedidos_retirado_por ON pedidos_materiales(id_retirado_por);
CREATE INDEX idx_pedidos_recibido_por ON pedidos_materiales(id_recibido_por);
CREATE INDEX idx_pedidos_estado ON pedidos_materiales(estado);
CREATE INDEX idx_historial_pedido ON historial_edicion_etapas_pedidos(id_pedido);
CREATE INDEX idx_historial_fecha ON historial_edicion_etapas_pedidos(fecha_edicion);

-- Vista mejorada para ver el estado completo de pedidos con todas las etapas
CREATE OR REPLACE VIEW vista_pedidos_etapas_completas AS
SELECT 
    p.id_pedido,
    p.numero_pedido,
    p.estado,
    p.fecha_pedido,
    
    -- Etapa 1: Creación
    p.id_solicitante,
    CONCAT(u_solicitante.nombre, ' ', u_solicitante.apellido) as solicitante,
    p.fecha_pedido as fecha_creacion,
    
    -- Etapa 2: Aprobación
    p.id_aprobado_por,
    CONCAT(u_aprobado.nombre, ' ', u_aprobado.apellido) as aprobado_por,
    p.fecha_aprobacion,
    
    -- Etapa 3: Picking
    p.id_picking_por,
    CONCAT(u_picking.nombre, ' ', u_picking.apellido) as picking_por,
    p.fecha_picking,
    
    -- Etapa 4: Retiro
    p.id_retirado_por,
    CONCAT(u_retirado.nombre, ' ', u_retirado.apellido) as retirado_por,
    p.fecha_retiro,
    
    -- Etapa 5: Entrega/Recibido
    p.id_recibido_por,
    CONCAT(u_recibido.nombre, ' ', u_recibido.apellido) as recibido_por,
    p.fecha_recibido,
    
    -- Información adicional
    p.id_obra,
    o.nombre_obra,
    p.prioridad,
    p.valor_total,
    p.observaciones
FROM 
    pedidos_materiales p
    LEFT JOIN usuarios u_solicitante ON p.id_solicitante = u_solicitante.id_usuario
    LEFT JOIN usuarios u_aprobado ON p.id_aprobado_por = u_aprobado.id_usuario
    LEFT JOIN usuarios u_picking ON p.id_picking_por = u_picking.id_usuario
    LEFT JOIN usuarios u_retirado ON p.id_retirado_por = u_retirado.id_usuario
    LEFT JOIN usuarios u_recibido ON p.id_recibido_por = u_recibido.id_usuario
    LEFT JOIN obras o ON p.id_obra = o.id_obra;

-- Trigger para validar el orden de las etapas
DELIMITER $$

DROP TRIGGER IF EXISTS validate_pedido_stage_order$$

CREATE TRIGGER validate_pedido_stage_order
BEFORE UPDATE ON pedidos_materiales
FOR EACH ROW
BEGIN
    DECLARE error_msg VARCHAR(255);
    
    -- Validar que no se pueda retroceder en las etapas
    IF OLD.estado = 'aprobado' AND NEW.estado = 'pendiente' THEN
        SET error_msg = 'No se puede retroceder de aprobado a pendiente';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF OLD.estado = 'picking' AND NEW.estado IN ('pendiente', 'aprobado') THEN
        SET error_msg = 'No se puede retroceder de picking a estados anteriores';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF OLD.estado = 'retirado' AND NEW.estado IN ('pendiente', 'aprobado', 'picking') THEN
        SET error_msg = 'No se puede retroceder de retirado a estados anteriores';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF OLD.estado IN ('recibido', 'entregado') AND NEW.estado IN ('pendiente', 'aprobado', 'picking', 'retirado') THEN
        SET error_msg = 'No se puede retroceder de recibido/entregado a estados anteriores';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Validar que no se pueda saltar etapas (excepto para cancelado)
    IF NEW.estado != 'cancelado' THEN
        IF NEW.estado = 'picking' AND OLD.estado NOT IN ('aprobado', 'picking') THEN
            SET error_msg = 'Un pedido debe estar aprobado antes de pasar a picking';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
        END IF;
        
        IF NEW.estado = 'retirado' AND OLD.estado NOT IN ('picking', 'retirado') THEN
            SET error_msg = 'Un pedido debe pasar por picking antes de ser retirado';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
        END IF;
        
        IF NEW.estado IN ('recibido', 'entregado') AND OLD.estado NOT IN ('retirado', 'en_camino', 'recibido', 'entregado') THEN
            SET error_msg = 'Un pedido debe estar retirado/en camino antes de ser marcado como recibido/entregado';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
        END IF;
    END IF;
    
    -- Validar que las fechas sean coherentes (cada etapa posterior debe tener fecha >= etapa anterior)
    IF NEW.fecha_aprobacion IS NOT NULL AND NEW.fecha_aprobacion < NEW.fecha_pedido THEN
        SET error_msg = 'La fecha de aprobación no puede ser anterior a la fecha de creación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_picking IS NOT NULL AND NEW.fecha_aprobacion IS NOT NULL AND NEW.fecha_picking < NEW.fecha_aprobacion THEN
        SET error_msg = 'La fecha de picking no puede ser anterior a la fecha de aprobación';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_retiro IS NOT NULL AND NEW.fecha_picking IS NOT NULL AND NEW.fecha_retiro < NEW.fecha_picking THEN
        SET error_msg = 'La fecha de retiro no puede ser anterior a la fecha de picking';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    IF NEW.fecha_recibido IS NOT NULL AND NEW.fecha_retiro IS NOT NULL AND NEW.fecha_recibido < NEW.fecha_retiro THEN
        SET error_msg = 'La fecha de recepción no puede ser anterior a la fecha de retiro';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
END$$

DELIMITER ;

-- Comentarios sobre el uso del sistema
-- 1. Las etapas deben seguir el orden: pendiente -> aprobado -> picking -> retirado -> entregado
-- 2. El estado 'cancelado' puede aplicarse desde cualquier etapa
-- 3. El administrador puede editar usuarios y fechas de cada etapa
-- 4. Todos los cambios realizados por el admin se registran en historial_edicion_etapas_pedidos
-- 5. Los triggers validan la coherencia de fechas y orden de etapas

-- Insertar un registro de ejemplo en el log de sistema
INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion)
SELECT 1, 'schema_update', 'pedidos', 'Actualización de esquema: sistema de 5 etapas de pedidos (Creación -> Aprobación -> Picking -> Retiro -> Entrega)', NOW()
WHERE EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = 1);

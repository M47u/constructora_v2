-- =====================================================================
-- Script de migración: Agregar etapa "Picking" al sistema de pedidos
-- =====================================================================
-- Descripción: 
--   Este script agrega la nueva etapa "Picking" (preparación de materiales)
--   entre las etapas de "Aprobación" y "Retiro" en el flujo de pedidos.
--
-- Cambios principales:
--   1. Agrega columnas id_picking_por y fecha_picking a pedidos_materiales
--   2. Actualiza ENUMs para incluir 'picking' como nuevo estado
--   3. Agrega foreign key constraint para id_picking_por
--   4. Crea índices para optimizar consultas
--   5. Actualiza triggers de validación para incluir picking
--   6. Actualiza vista vista_pedidos_etapas_completas
--
-- Fecha: 2025-01-09
-- Versión: 1.0
-- =====================================================================

USE sistema_constructora;

-- =====================================================================
-- PASO 1: AGREGAR NUEVAS COLUMNAS
-- =====================================================================

ALTER TABLE pedidos_materiales
    ADD COLUMN IF NOT EXISTS id_picking_por INT NULL COMMENT 'ID del usuario que preparó el pedido (picking)' AFTER id_aprobado_por,
    ADD COLUMN IF NOT EXISTS fecha_picking TIMESTAMP NULL COMMENT 'Fecha cuando se realizó el picking' AFTER fecha_aprobacion;

-- =====================================================================
-- PASO 2: ACTUALIZAR ENUMS PARA INCLUIR 'picking'
-- =====================================================================

-- Actualizar ENUM de estado en pedidos_materiales
ALTER TABLE pedidos_materiales 
    MODIFY COLUMN estado ENUM('pendiente','aprobado','picking','retirado','recibido','en_camino','entregado','devuelto','cancelado') 
    DEFAULT 'pendiente';

-- Actualizar ENUM de seguimiento_pedidos si existe
ALTER TABLE seguimiento_pedidos 
    MODIFY COLUMN estado_anterior ENUM('pendiente','aprobado','picking','retirado','recibido','en_camino','entregado','devuelto','cancelado') 
    NULL;

ALTER TABLE seguimiento_pedidos 
    MODIFY COLUMN estado_nuevo ENUM('pendiente','aprobado','picking','retirado','recibido','en_camino','entregado','devuelto','cancelado') 
    NULL;

-- Actualizar ENUM de historial_edicion_etapas_pedidos si existe
ALTER TABLE historial_edicion_etapas_pedidos 
    MODIFY COLUMN etapa ENUM('creacion','aprobacion','picking','retiro','recibido','estado') 
    NOT NULL;

-- =====================================================================
-- PASO 3: AGREGAR FOREIGN KEY CONSTRAINT
-- =====================================================================

-- Eliminar constraint si ya existe (para evitar error de duplicado)
SET @drop_fk = (
    SELECT CONCAT('ALTER TABLE pedidos_materiales DROP FOREIGN KEY ', CONSTRAINT_NAME, ';')
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pedidos_materiales'
    AND CONSTRAINT_NAME = 'fk_pedidos_picking_por'
    LIMIT 1
);

-- Ejecutar DROP solo si existe
SET @drop_fk = IFNULL(@drop_fk, 'SELECT "No FK to drop" AS info;');
PREPARE stmt FROM @drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Crear constraint (ahora seguro que no existe)
ALTER TABLE pedidos_materiales 
ADD CONSTRAINT fk_pedidos_picking_por 
FOREIGN KEY (id_picking_por) REFERENCES usuarios(id_usuario) 
ON DELETE SET NULL;

-- =====================================================================
-- PASO 4: CREAR ÍNDICES PARA OPTIMIZACIÓN
-- =====================================================================

-- Eliminar índice si ya existe (para evitar error de duplicado)
SET @drop_idx = (
    SELECT CONCAT('DROP INDEX ', INDEX_NAME, ' ON pedidos_materiales;')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pedidos_materiales'
    AND INDEX_NAME = 'idx_picking_por'
    LIMIT 1
);

-- Ejecutar DROP solo si existe
SET @drop_idx = IFNULL(@drop_idx, 'SELECT "No index to drop" AS info;');
PREPARE stmt FROM @drop_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Crear índice (ahora seguro que no existe)
CREATE INDEX idx_picking_por ON pedidos_materiales(id_picking_por);

-- =====================================================================
-- PASO 5: ACTUALIZAR TRIGGERS DE VALIDACIÓN
-- =====================================================================

-- Eliminar trigger existente si existe
DROP TRIGGER IF EXISTS before_update_pedidos_materiales_etapas;

-- Crear nuevo trigger con validaciones para picking
DELIMITER $$

CREATE TRIGGER before_update_pedidos_materiales_etapas
BEFORE UPDATE ON pedidos_materiales
FOR EACH ROW
BEGIN
    -- Validación: No se puede picking sin aprobar primero
    IF NEW.id_picking_por IS NOT NULL AND NEW.id_aprobado_por IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede asignar picking sin aprobación previa';
    END IF;
    
    -- Validación: No se puede retirar sin picking (si picking está definido)
    IF NEW.id_retirado_por IS NOT NULL AND OLD.id_picking_por IS NOT NULL AND NEW.id_picking_por IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede retirar sin completar picking primero';
    END IF;
    
    -- Validación: fecha_picking debe ser >= fecha_aprobacion
    IF NEW.fecha_picking IS NOT NULL AND NEW.fecha_aprobacion IS NOT NULL THEN
        IF NEW.fecha_picking < NEW.fecha_aprobacion THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha de picking no puede ser anterior a la fecha de aprobación';
        END IF;
    END IF;
    
    -- Validación: fecha_retiro debe ser >= fecha_picking (si existe)
    IF NEW.fecha_retiro IS NOT NULL AND NEW.fecha_picking IS NOT NULL THEN
        IF NEW.fecha_retiro < NEW.fecha_picking THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha de retiro no puede ser anterior a la fecha de picking';
        END IF;
    END IF;
    
    -- Validación: fecha_recibido debe ser >= fecha_picking (si existe)
    IF NEW.fecha_recibido IS NOT NULL AND NEW.fecha_picking IS NOT NULL THEN
        IF NEW.fecha_recibido < NEW.fecha_picking THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha de recepción no puede ser anterior a la fecha de picking';
        END IF;
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- PASO 6: ACTUALIZAR VISTA vista_pedidos_etapas_completas
-- =====================================================================

-- Eliminar vista si existe
DROP VIEW IF EXISTS vista_pedidos_etapas_completas;

-- Crear vista actualizada con información de picking
CREATE VIEW vista_pedidos_etapas_completas AS
SELECT 
    p.id_pedido,
    p.id_obra,
    o.nombre_obra,
    p.fecha_pedido,
    p.estado,
    
    -- Etapa 1: Creación (Solicitante)
    p.id_solicitante,
    us.nombre AS nombre_solicitante,
    us.apellido AS apellido_solicitante,
    us.email AS email_solicitante,
    
    -- Etapa 2: Aprobación
    p.id_aprobado_por,
    ua.nombre AS nombre_aprobado,
    ua.apellido AS apellido_aprobado,
    p.fecha_aprobacion,
    
    -- Etapa 3: Picking (NUEVO)
    p.id_picking_por,
    upk.nombre AS nombre_picking,
    upk.apellido AS apellido_picking,
    p.fecha_picking,
    
    -- Etapa 4: Retiro
    p.id_retirado_por,
    ur.nombre AS nombre_retirado,
    ur.apellido AS apellido_retirado,
    p.fecha_retiro,
    
    -- Etapa 5: Recibido/Entrega
    p.id_recibido_por,
    urec.nombre AS nombre_recibido,
    urec.apellido AS apellido_recibido,
    p.fecha_recibido,
    p.fecha_entrega
    
FROM pedidos_materiales p
LEFT JOIN obras o ON p.id_obra = o.id_obra
LEFT JOIN usuarios us ON p.id_solicitante = us.id_usuario
LEFT JOIN usuarios ua ON p.id_aprobado_por = ua.id_usuario
LEFT JOIN usuarios upk ON p.id_picking_por = upk.id_usuario
LEFT JOIN usuarios ur ON p.id_retirado_por = ur.id_usuario
LEFT JOIN usuarios urec ON p.id_recibido_por = urec.id_usuario;

-- =====================================================================
-- VERIFICACIÓN FINAL
-- =====================================================================

SELECT 'Script ejecutado correctamente' AS resultado;

-- Verificar que las columnas fueron agregadas
SELECT 
    CASE 
        WHEN COUNT(*) = 2 THEN '✓ Columnas id_picking_por y fecha_picking agregadas correctamente'
        ELSE '✗ ERROR: Faltan columnas'
    END AS verificacion
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'pedidos_materiales'
AND COLUMN_NAME IN ('id_picking_por', 'fecha_picking');

-- Verificar que la vista fue creada
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ Vista vista_pedidos_etapas_completas actualizada correctamente'
        ELSE '✗ ERROR: Vista no encontrada'
    END AS verificacion
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'vista_pedidos_etapas_completas';

-- Verificar que el trigger fue creado
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ Trigger before_update_pedidos_materiales_etapas creado correctamente'
        ELSE '✗ ERROR: Trigger no encontrado'
    END AS verificacion
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME = 'before_update_pedidos_materiales_etapas';

-- =====================================================================
-- FIN DEL SCRIPT
-- =====================================================================

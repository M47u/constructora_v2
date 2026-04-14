-- =============================================================================
-- Migración: Pre-crear todas las tareas operativas de un pedido al crearlo
-- =============================================================================
-- Objetivo:
--   Al crear un pedido, se pre-crean las 4 tareas (Aprobación, Picking, Retiro,
--   Recepción). Las tareas futuras quedan con habilitada=0 hasta que la etapa
--   anterior sea completada.
--
-- Cambios:
--   1. fecha_asignacion pasa a NULL para tareas pre-creadas no habilitadas.
--   2. Se agrega columna 'habilitada' para distinguir la tarea activa
--      de las pre-creadas pendientes de activación.
--
-- Ejecutar UNA sola vez antes de desplegar el nuevo código.
-- =============================================================================

-- 1. Hacer fecha_asignacion nullable (las tareas pre-creadas no tienen fecha aún)
ALTER TABLE tareas
    MODIFY COLUMN fecha_asignacion DATETIME NULL DEFAULT NULL;

-- 2. Agregar columna habilitada
--    1 = tarea activa, lista para ejecutarse
--    0 = pre-creada, esperando habilitación por etapa anterior
ALTER TABLE tareas
    ADD COLUMN habilitada TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1=habilitada para ejecución; 0=pendiente de habilitación por etapa anterior'
        AFTER etapa_pedido;

-- 3. Garantizar índice para búsquedas frecuentes por pedido+etapa+habilitada
--    (pedido_tareas ya tiene UNIQUE(id_pedido, etapa), no hace falta índice extra)

-- Verificación
SELECT
    COUNT(*)                                                AS total_tareas,
    SUM(habilitada = 1)                                    AS habilitadas,
    SUM(habilitada = 0)                                    AS no_habilitadas,
    SUM(fecha_asignacion IS NULL)                          AS sin_fecha_asignacion
FROM tareas;

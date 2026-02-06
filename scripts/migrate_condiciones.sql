-- ================================================================
-- MIGRACIÓN DE CONDICIONES DE HERRAMIENTAS UNIDADES
-- ================================================================
-- Este script migra el campo 'condicion' al nuevo sistema 'condicion_actual'
-- con los valores correctos del sistema centralizado
-- ================================================================

-- Paso 1: Agregar el nuevo campo condicion_actual
ALTER TABLE herramientas_unidades 
ADD COLUMN condicion_actual ENUM('nueva', 'usada', 'reparada', 'para_reparacion', 'perdida', 'de_baja') 
DEFAULT NULL
AFTER estado_actual;

-- Paso 2: Migrar datos del campo antiguo al nuevo
-- Mapeo de valores antiguos a nuevos:
-- excelente -> nueva
-- buena -> usada  
-- regular -> reparada
-- mala -> para_reparacion

UPDATE herramientas_unidades 
SET condicion_actual = CASE 
    WHEN condicion = 'excelente' THEN 'nueva'
    WHEN condicion = 'buena' THEN 'usada'
    WHEN condicion = 'regular' THEN 'reparada'
    WHEN condicion = 'mala' THEN 'para_reparacion'
    ELSE 'usada' -- Valor por defecto
END;

-- Paso 3: Establecer un valor por defecto para condicion_actual en nuevas unidades
ALTER TABLE herramientas_unidades 
MODIFY COLUMN condicion_actual ENUM('nueva', 'usada', 'reparada', 'para_reparacion', 'perdida', 'de_baja') 
DEFAULT 'nueva';

-- Paso 4: Eliminar el campo antiguo condicion (OPCIONAL - comentado por seguridad)
-- Descomenta la siguiente línea si estás seguro de que no necesitas el campo antiguo
-- ALTER TABLE herramientas_unidades DROP COLUMN condicion;

-- Verificación: Mostrar la distribución de condiciones después de la migración
SELECT 
    condicion_actual,
    COUNT(*) as total_unidades
FROM herramientas_unidades
GROUP BY condicion_actual
ORDER BY total_unidades DESC;

-- Verificación: Mostrar unidades sin condición asignada (no debería haber ninguna)
SELECT COUNT(*) as unidades_sin_condicion
FROM herramientas_unidades
WHERE condicion_actual IS NULL;

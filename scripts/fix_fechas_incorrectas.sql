-- Script para corregir fechas de aprobación incorrectas
-- Problema: fecha_aprobacion es anterior a fecha_pedido por error de timezone o carga de datos

-- Paso 1: Ver cuántos pedidos tienen este problema
SELECT 
    COUNT(*) as total_incorrectos,
    'Pedidos con fecha_aprobacion < fecha_pedido' as problema
FROM pedidos_materiales
WHERE fecha_aprobacion IS NOT NULL 
    AND fecha_aprobacion < fecha_pedido;

-- Paso 2: Crear backup antes de corregir
CREATE TABLE IF NOT EXISTS backup_pedidos_fechas_incorrectas AS
SELECT * FROM pedidos_materiales
WHERE fecha_aprobacion IS NOT NULL 
    AND fecha_aprobacion < fecha_pedido;

-- Paso 3: Corregir las fechas incorrectas
-- Opción A: Si la diferencia es de 3-4 horas, probablemente es timezone
--           Agregar 3 horas a fecha_aprobacion
UPDATE pedidos_materiales
SET fecha_aprobacion = DATE_ADD(fecha_aprobacion, INTERVAL 3 HOUR)
WHERE fecha_aprobacion IS NOT NULL 
    AND fecha_aprobacion < fecha_pedido
    AND TIMESTAMPDIFF(HOUR, fecha_aprobacion, fecha_pedido) <= 4;

-- Paso 4: Para los que siguen incorrectos, igualar aprobacion a pedido + 1 minuto
UPDATE pedidos_materiales
SET fecha_aprobacion = DATE_ADD(fecha_pedido, INTERVAL 1 MINUTE)
WHERE fecha_aprobacion IS NOT NULL 
    AND fecha_aprobacion < fecha_pedido;

-- Paso 5: Verificar que ya no hay fechas incorrectas
SELECT 
    COUNT(*) as total_incorrectos_restantes,
    'Verificación post-corrección' as estado
FROM pedidos_materiales
WHERE fecha_aprobacion IS NOT NULL 
    AND fecha_aprobacion < fecha_pedido;

-- Paso 6: Verificar otras inconsistencias
SELECT 
    'Picking antes de Aprobación' as problema,
    COUNT(*) as total
FROM pedidos_materiales
WHERE fecha_picking IS NOT NULL 
    AND fecha_aprobacion IS NOT NULL
    AND fecha_picking < fecha_aprobacion
UNION ALL
SELECT 
    'Retiro antes de Picking' as problema,
    COUNT(*) as total
FROM pedidos_materiales
WHERE fecha_retiro IS NOT NULL 
    AND fecha_picking IS NOT NULL
    AND fecha_retiro < fecha_picking
UNION ALL
SELECT 
    'Recibido antes de Retiro' as problema,
    COUNT(*) as total
FROM pedidos_materiales
WHERE fecha_recibido IS NOT NULL 
    AND fecha_retiro IS NOT NULL
    AND fecha_recibido < fecha_retiro;

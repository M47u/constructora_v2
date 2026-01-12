-- ============================================================================
-- Script de Migración de Zona Horaria
-- ============================================================================
-- PROPÓSITO: Corregir datos existentes que se guardaron con UTC (GMT +00:00)
--            para convertirlos a hora de Argentina (GMT -03:00)
--
-- ACCIÓN: Resta 3 horas a todas las fechas existentes en el sistema
--
-- ADVERTENCIA: Este script modifica datos existentes. 
--              Se recomienda hacer BACKUP antes de ejecutar.
--
-- EJECUCIÓN: 
--   1. Hacer backup de la base de datos
--   2. Ejecutar este script una sola vez
--   3. Verificar los resultados
-- ============================================================================

-- Mostrar fecha actual antes de la migración
SELECT 
    'ANTES DE LA MIGRACION' as estado,
    NOW() as fecha_servidor,
    @@session.time_zone as zona_horaria;

-- Configurar zona horaria de la sesión
SET time_zone = '-03:00';

-- ============================================================================
-- TABLA: usuarios
-- ============================================================================
UPDATE usuarios 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

UPDATE usuarios 
SET fecha_actualizacion = DATE_SUB(fecha_actualizacion, INTERVAL 3 HOUR)
WHERE fecha_actualizacion IS NOT NULL;

UPDATE usuarios 
SET fecha_ultimo_acceso = DATE_SUB(fecha_ultimo_acceso, INTERVAL 3 HOUR)
WHERE fecha_ultimo_acceso IS NOT NULL;

-- ============================================================================
-- TABLA: obras
-- ============================================================================
UPDATE obras 
SET fecha_inicio = DATE_SUB(fecha_inicio, INTERVAL 3 HOUR)
WHERE fecha_inicio IS NOT NULL;

UPDATE obras 
SET fecha_fin = DATE_SUB(fecha_fin, INTERVAL 3 HOUR)
WHERE fecha_fin IS NOT NULL;

UPDATE obras 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

-- ============================================================================
-- TABLA: materiales
-- ============================================================================
UPDATE materiales 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

UPDATE materiales 
SET fecha_actualizacion = DATE_SUB(fecha_actualizacion, INTERVAL 3 HOUR)
WHERE fecha_actualizacion IS NOT NULL;

-- ============================================================================
-- TABLA: pedidos_materiales
-- ============================================================================
UPDATE pedidos_materiales 
SET fecha_pedido = DATE_SUB(fecha_pedido, INTERVAL 3 HOUR)
WHERE fecha_pedido IS NOT NULL;

UPDATE pedidos_materiales 
SET fecha_necesaria = DATE_SUB(fecha_necesaria, INTERVAL 3 HOUR)
WHERE fecha_necesaria IS NOT NULL;

UPDATE pedidos_materiales 
SET fecha_aprobacion = DATE_SUB(fecha_aprobacion, INTERVAL 3 HOUR)
WHERE fecha_aprobacion IS NOT NULL;

UPDATE pedidos_materiales 
SET fecha_entrega = DATE_SUB(fecha_entrega, INTERVAL 3 HOUR)
WHERE fecha_entrega IS NOT NULL;

-- ============================================================================
-- TABLA: seguimiento_pedidos
-- ============================================================================
UPDATE seguimiento_pedidos 
SET fecha_cambio = DATE_SUB(fecha_cambio, INTERVAL 3 HOUR)
WHERE fecha_cambio IS NOT NULL;

-- ============================================================================
-- TABLA: historial_edicion_etapas_pedidos
-- ============================================================================
UPDATE historial_edicion_etapas_pedidos 
SET fecha_edicion = DATE_SUB(fecha_edicion, INTERVAL 3 HOUR)
WHERE fecha_edicion IS NOT NULL;

-- ============================================================================
-- TABLA: herramientas
-- ============================================================================
UPDATE herramientas 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

-- ============================================================================
-- TABLA: herramientas_unidades
-- ============================================================================
UPDATE herramientas_unidades 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

-- ============================================================================
-- TABLA: prestamos
-- ============================================================================
UPDATE prestamos 
SET fecha_retiro = DATE_SUB(fecha_retiro, INTERVAL 3 HOUR)
WHERE fecha_retiro IS NOT NULL;

UPDATE prestamos 
SET fecha_devolucion_programada = DATE_SUB(fecha_devolucion_programada, INTERVAL 3 HOUR)
WHERE fecha_devolucion_programada IS NOT NULL;

-- ============================================================================
-- TABLA: devoluciones
-- ============================================================================
UPDATE devoluciones 
SET fecha_devolucion = DATE_SUB(fecha_devolucion, INTERVAL 3 HOUR)
WHERE fecha_devolucion IS NOT NULL;
-- ============================================================================
-- TABLA: tareas
-- ============================================================================
UPDATE tareas 
SET fecha_asignacion = DATE_SUB(fecha_asignacion, INTERVAL 3 HOUR)
WHERE fecha_asignacion IS NOT NULL;

UPDATE tareas 
SET fecha_vencimiento = DATE_SUB(fecha_vencimiento, INTERVAL 3 HOUR)
WHERE fecha_vencimiento IS NOT NULL;

UPDATE tareas 
SET fecha_inicio = DATE_SUB(fecha_inicio, INTERVAL 3 HOUR)
WHERE fecha_inicio IS NOT NULL;

UPDATE tareas 
SET fecha_finalizacion = DATE_SUB(fecha_finalizacion, INTERVAL 3 HOUR)
WHERE fecha_finalizacion IS NOT NULL;

-- ============================================================================
-- TABLA: movimientos_stock (si existe)
-- ============================================================================
-- UPDATE movimientos_stock 
-- SET fecha_movimiento = DATE_SUB(fecha_movimiento, INTERVAL 3 HOUR)
-- WHERE fecha_movimiento IS NOT NULL;

-- ============================================================================
-- TABLA: notificaciones (si existe)
-- ============================================================================
UPDATE notificaciones 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

UPDATE notificaciones 
SET fecha_lectura = DATE_SUB(fecha_lectura, INTERVAL 3 HOUR)
WHERE fecha_lectura IS NOT NULL;

-- ============================================================================
-- TABLA: logs_sistema (si existe)
-- ============================================================================
UPDATE logs_sistema 
SET fecha_creacion = DATE_SUB(fecha_creacion, INTERVAL 3 HOUR)
WHERE fecha_creacion IS NOT NULL;

-- ============================================================================
-- VERIFICACIÓN FINAL
-- ============================================================================

-- Mostrar estadísticas de registros actualizados
SELECT 'RESUMEN DE MIGRACIÓN' as estado;

SELECT 
    'usuarios' as tabla,
    COUNT(*) as registros_totales,
    SUM(CASE WHEN fecha_creacion IS NOT NULL THEN 1 ELSE 0 END) as con_fecha_creacion
FROM usuarios
UNION ALL

SELECT 
    'obras' as tabla,
    COUNT(*) as registros_totales,
    SUM(CASE WHEN fecha_inicio IS NOT NULL THEN 1 ELSE 0 END) as con_fecha_inicio
FROM obras

UNION ALL

SELECT 
    'pedidos_materiales' as tabla,
    COUNT(*) as registros_totales,
    SUM(CASE WHEN fecha_pedido IS NOT NULL THEN 1 ELSE 0 END) as con_fecha_pedido
FROM pedidos_materiales

UNION ALL

SELECT 
    'prestamos' as tabla,
    COUNT(*) as registros_totales,
    SUM(CASE WHEN fecha_retiro IS NOT NULL THEN 1 ELSE 0 END) as con_fecha_retiro
FROM prestamos

UNION ALL

SELECT 
    'tareas' as tabla,
    COUNT(*) as registros_totales,
    SUM(CASE WHEN fecha_inicio IS NOT NULL THEN 1 ELSE 0 END) as con_fecha_inicio
FROM tareas;

-- Mostrar algunos ejemplos de fechas después de la migración
SELECT 
    'EJEMPLO: Últimos 5 pedidos' as verificacion,
    id_pedido,
    numero_pedido,
    fecha_pedido,
    fecha_aprobacion,
    fecha_entrega
FROM pedidos_materiales
ORDER BY id_pedido DESC
LIMIT 5;

SELECT 
    'EJEMPLO: Últimas 5 tareas' as verificacion,
    id_tarea,
    titulo,
    fecha_inicio,
    fecha_finalizacion
FROM tareas
ORDER BY id_tarea DESC
LIMIT 5;

-- Mostrar fecha actual después de la migración
SELECT 
    'DESPUES DE LA MIGRACION' as estado,
    NOW() as fecha_servidor,
    @@session.time_zone as zona_horaria;

SELECT '¡MIGRACIÓN COMPLETADA!' as mensaje;
SELECT 'Verifica que las fechas se vean correctas en la aplicación' as siguiente_paso;
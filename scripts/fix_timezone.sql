-- Script para corregir la zona horaria de la base de datos
-- Ejecutar este script para configurar la zona horaria de Argentina (-03:00)

-- Configurar la zona horaria global de MySQL
SET GLOBAL time_zone = '-03:00';

-- Configurar la zona horaria de la sesión actual
SET time_zone = '-03:00';

-- Verificar la configuración actual
SELECT @@global.time_zone, @@session.time_zone;

-- Mostrar la fecha y hora actual del servidor
SELECT NOW() as fecha_hora_actual;

-- Mostrar información de zona horaria
SELECT 
    @@system_time_zone as zona_horaria_sistema,
    @@global.time_zone as zona_horaria_global,
    @@session.time_zone as zona_horaria_sesion,
    NOW() as fecha_hora_actual,
    UTC_TIMESTAMP() as fecha_hora_utc;


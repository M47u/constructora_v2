-- =====================================================
-- MIGRACIÓN: NUEVAS CONDICIONES Y ESTADOS DE HERRAMIENTAS
-- Fecha: 2026-02-05
-- =====================================================

-- BACKUP RECOMENDADO: Hacer backup antes de ejecutar este script

-- =====================================================
-- PASO 1: MIGRAR DATOS EXISTENTES (ANTES DE CAMBIAR ESTRUCTURA)
-- =====================================================

-- MAPEO DE CONDICIONES ANTIGUAS A NUEVAS:
-- 'excelente' -> 'usada' (si ya fue usada)
-- 'buena' -> 'usada'
-- 'regular' -> 'usada'
-- 'mala' -> 'para_reparacion'
-- 'dañada' -> 'de_baja'
-- 'perdida' -> 'perdida'

-- Actualizar herramientas.condicion_general
UPDATE herramientas 
SET condicion_general = CASE 
    WHEN condicion_general IN ('excelente', 'buena', 'regular') THEN 'usada'
    WHEN condicion_general = 'mala' THEN 'para_reparacion'
    WHEN condicion_general = 'dañada' THEN 'de_baja'
    WHEN condicion_general = 'perdida' THEN 'perdida'
    ELSE 'usada'
END;

-- Actualizar detalle_prestamo.condicion_retiro
UPDATE detalle_prestamo 
SET condicion_retiro = CASE 
    WHEN condicion_retiro IN ('excelente', 'buena', 'regular') THEN 'usada'
    WHEN condicion_retiro = 'mala' THEN 'para_reparacion'
    WHEN condicion_retiro = 'dañada' THEN 'de_baja'
    WHEN condicion_retiro = 'perdida' THEN 'perdida'
    ELSE 'usada'
END;

-- Actualizar detalle_devolucion.condicion_devolucion
UPDATE detalle_devolucion 
SET condicion_devolucion = CASE 
    WHEN condicion_devolucion IN ('excelente', 'buena', 'regular') THEN 'usada'
    WHEN condicion_devolucion = 'mala' THEN 'para_reparacion'
    WHEN condicion_devolucion = 'dañada' THEN 'de_baja'
    WHEN condicion_devolucion = 'perdida' THEN 'perdida'
    ELSE 'usada'
END;

-- MAPEO DE ESTADOS ANTIGUOS A NUEVOS:
-- 'disponible' -> 'disponible'
-- 'prestada' -> 'prestada'
-- 'mantenimiento' -> 'en_reparacion'
-- 'perdida' -> 'no_disponible'
-- 'dañada' -> 'no_disponible'

-- Actualizar herramientas_unidades.estado_actual
UPDATE herramientas_unidades 
SET estado_actual = CASE 
    WHEN estado_actual = 'disponible' THEN 'disponible'
    WHEN estado_actual = 'prestada' THEN 'prestada'
    WHEN estado_actual = 'mantenimiento' THEN 'en_reparacion'
    WHEN estado_actual IN ('perdida', 'dañada') THEN 'no_disponible'
    ELSE 'disponible'
END;

-- =====================================================
-- PASO 2: ACTUALIZAR ESTRUCTURA DE TABLAS (DESPUÉS DE MIGRAR DATOS)
-- =====================================================

-- Modificar tipo ENUM en tabla herramientas (condicion_general)
ALTER TABLE herramientas 
MODIFY COLUMN condicion_general ENUM('nueva', 'usada', 'reparada', 'para_reparacion', 'perdida', 'de_baja') 
DEFAULT 'nueva';

-- Modificar tipo ENUM en tabla herramientas_unidades (estado_actual)
ALTER TABLE herramientas_unidades 
MODIFY COLUMN estado_actual ENUM('disponible', 'prestada', 'en_reparacion', 'no_disponible') 
DEFAULT 'disponible';

-- Modificar tipo ENUM en tabla detalle_prestamo (condicion_retiro)
ALTER TABLE detalle_prestamo 
MODIFY COLUMN condicion_retiro ENUM('nueva', 'usada', 'reparada', 'para_reparacion', 'perdida', 'de_baja') 
DEFAULT 'usada';

-- Modificar tipo ENUM en tabla detalle_devolucion (condicion_devolucion)
ALTER TABLE detalle_devolucion 
MODIFY COLUMN condicion_devolucion ENUM('nueva', 'usada', 'reparada', 'para_reparacion', 'perdida', 'de_baja') 
DEFAULT 'usada';

-- =====================================================
-- PASO 3: CREAR TABLA DE HISTORIAL DE REPARACIONES
-- =====================================================

CREATE TABLE IF NOT EXISTS historial_reparaciones (
    id_reparacion INT AUTO_INCREMENT PRIMARY KEY,
    id_unidad INT NOT NULL,
    fecha_inicio_reparacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin_reparacion TIMESTAMP NULL,
    descripcion_problema TEXT,
    descripcion_solucion TEXT NULL,
    costo_reparacion DECIMAL(10,2) NULL,
    id_usuario_registro INT NOT NULL,
    estado_reparacion ENUM('en_proceso', 'completada', 'cancelada') DEFAULT 'en_proceso',
    FOREIGN KEY (id_unidad) REFERENCES herramientas_unidades(id_unidad) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    INDEX idx_id_unidad (id_unidad),
    INDEX idx_fecha_inicio (fecha_inicio_reparacion),
    INDEX idx_estado (estado_reparacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PASO 4: VERIFICACIÓN DE DATOS
-- =====================================================

-- Verificar distribución de condiciones
SELECT 'Distribución de Condiciones en herramientas:' as Verificacion;
SELECT condicion_general, COUNT(*) as cantidad 
FROM herramientas 
GROUP BY condicion_general;

-- Verificar distribución de estados
SELECT 'Distribución de Estados en herramientas_unidades:' as Verificacion;
SELECT estado_actual, COUNT(*) as cantidad 
FROM herramientas_unidades 
GROUP BY estado_actual;

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Este script modifica la estructura de 4 tablas principales
-- 2. Los datos existentes se migran automáticamente según el mapeo definido
-- 3. Se crea una nueva tabla para historial de reparaciones
-- 4. IMPORTANTE: Hacer backup de la base de datos antes de ejecutar
-- 5. Después de ejecutar, actualizar los archivos PHP correspondientes

-- =====================================================
-- ROLLBACK (en caso de necesitar revertir):
-- =====================================================
-- NOTA: Este rollback solo funciona si tienes un backup de la BD
-- Restaurar desde backup es la forma más segura de revertir

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================

-- ================================================================
-- AGREGAR INFORMACIÓN DE COMPRA A HERRAMIENTAS UNIDADES
-- ================================================================
-- Este script agrega campos para registrar información de compra
-- de cada unidad individual de herramienta
-- ================================================================

-- Agregar campos de información de compra
ALTER TABLE herramientas_unidades 
ADD COLUMN precio_compra DECIMAL(10,2) NULL COMMENT 'Precio de compra de la unidad',
ADD COLUMN proveedor VARCHAR(100) NULL COMMENT 'Nombre del proveedor',
ADD COLUMN fecha_compra DATE NULL COMMENT 'Fecha de compra de la unidad';

-- Crear índice para búsquedas por proveedor
CREATE INDEX idx_proveedor ON herramientas_unidades(proveedor);

-- Crear índice para búsquedas por fecha de compra
CREATE INDEX idx_fecha_compra ON herramientas_unidades(fecha_compra);

-- Verificación: Mostrar la estructura actualizada
DESCRIBE herramientas_unidades;

-- Mostrar estadísticas de unidades con/sin información de compra
SELECT 
    COUNT(*) as total_unidades,
    COUNT(precio_compra) as con_precio,
    COUNT(proveedor) as con_proveedor,
    COUNT(fecha_compra) as con_fecha
FROM herramientas_unidades;

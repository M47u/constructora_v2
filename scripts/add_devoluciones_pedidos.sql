-- ============================================================
-- Migración: Sistema de devoluciones parciales y totales
-- Módulo: pedidos_materiales
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Incremental, no destructiva — segura para re-ejecución
-- ============================================================

-- Ajustar según el entorno:
-- USE u251673992_sistema_constr;  -- producción
-- USE constructora_v2;            -- local

-- ------------------------------------------------------------
-- 1a. Agregar columna cantidad_retirada (solo si no existe)
-- ------------------------------------------------------------
SET @exist_ret := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'detalle_pedidos_materiales'
      AND COLUMN_NAME  = 'cantidad_retirada'
);
SET @sql_ret := IF(
    @exist_ret = 0,
    'ALTER TABLE detalle_pedidos_materiales ADD COLUMN cantidad_retirada INT NOT NULL DEFAULT 0 COMMENT ''Cantidad efectivamente retirada del almacen al marcar el pedido como Retirado''',
    'SELECT ''Columna cantidad_retirada ya existe'''
);
PREPARE _stmt FROM @sql_ret;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ------------------------------------------------------------
-- 1b. Agregar columna cantidad_devuelta (solo si no existe)
-- ------------------------------------------------------------
SET @exist_dev := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'detalle_pedidos_materiales'
      AND COLUMN_NAME  = 'cantidad_devuelta'
);
SET @sql_dev := IF(
    @exist_dev = 0,
    'ALTER TABLE detalle_pedidos_materiales ADD COLUMN cantidad_devuelta INT NOT NULL DEFAULT 0 COMMENT ''Total acumulado de unidades devueltas de este item''',
    'SELECT ''Columna cantidad_devuelta ya existe'''
);
PREPARE _stmt FROM @sql_dev;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ------------------------------------------------------------
-- 2. Retrocompatibilidad: poblar cantidad_retirada en pedidos
--    que ya están en estado retirado / recibido / devuelto
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_detalles_retirada;
CREATE TEMPORARY TABLE tmp_detalles_retirada (
        id_detalle INT PRIMARY KEY,
        cantidad_solicitada INT NOT NULL
);

INSERT INTO tmp_detalles_retirada (id_detalle, cantidad_solicitada)
SELECT d.id_detalle, d.cantidad_solicitada
FROM   detalle_pedidos_materiales d
JOIN   pedidos_materiales p ON p.id_pedido = d.id_pedido
WHERE  d.cantidad_retirada = 0
    AND  p.estado IN ('retirado', 'recibido', 'devuelto');

UPDATE detalle_pedidos_materiales d
JOIN   tmp_detalles_retirada t ON t.id_detalle = d.id_detalle
SET    d.cantidad_retirada = t.cantidad_solicitada;

DROP TEMPORARY TABLE IF EXISTS tmp_detalles_retirada;

-- ------------------------------------------------------------
-- 3. Tabla cabecera de devoluciones
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS devoluciones_pedidos (
    id_devolucion     INT         AUTO_INCREMENT PRIMARY KEY,
    id_pedido         INT         NOT NULL,
    numero_devolucion VARCHAR(30) NOT NULL UNIQUE
        COMMENT 'Ej: DEV20260001',
    tipo              ENUM('parcial','total') NOT NULL,
    fecha_devolucion  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario        INT         NOT NULL
        COMMENT 'Usuario que registro la devolucion',
    observaciones     TEXT,

    FOREIGN KEY (id_pedido)  REFERENCES pedidos_materiales(id_pedido)  ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)           ON DELETE RESTRICT,

    INDEX idx_dev_pedido (id_pedido),
    INDEX idx_dev_fecha  (fecha_devolucion),
    INDEX idx_dev_tipo   (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cabecera de eventos de devolucion de materiales de pedidos';

-- ------------------------------------------------------------
-- 4. Tabla detalle de devoluciones (una fila por item por evento)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS detalle_devoluciones_pedidos (
    id_detalle_devolucion INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_devolucion         INT NOT NULL,
    id_detalle_pedido     INT NOT NULL
        COMMENT 'Referencia al item en detalle_pedidos_materiales',
    id_material           INT NOT NULL,
    cantidad_devuelta     INT NOT NULL
        COMMENT 'Cantidad devuelta en este evento especifico',

    FOREIGN KEY (id_devolucion)     REFERENCES devoluciones_pedidos(id_devolucion)    ON DELETE CASCADE,
    FOREIGN KEY (id_detalle_pedido) REFERENCES detalle_pedidos_materiales(id_detalle) ON DELETE CASCADE,
    FOREIGN KEY (id_material)       REFERENCES materiales(id_material)                ON DELETE RESTRICT,

    INDEX idx_detdev_devolucion (id_devolucion),
    INDEX idx_detdev_material   (id_material),
    INDEX idx_detdev_detalle    (id_detalle_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detalle por item de cada evento de devolucion';

-- ------------------------------------------------------------
-- 5. Índice en cantidad_retirada (solo si no existe)
-- ------------------------------------------------------------
SET @exist_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'detalle_pedidos_materiales'
      AND INDEX_NAME   = 'idx_detalle_cantidad_retirada'
);
SET @sql_idx := IF(
    @exist_idx = 0,
    'CREATE INDEX idx_detalle_cantidad_retirada ON detalle_pedidos_materiales(cantidad_retirada)',
    'SELECT ''Index idx_detalle_cantidad_retirada ya existe'''
);
PREPARE _stmt FROM @sql_idx;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ------------------------------------------------------------
-- 6. Registrar migración en logs_sistema
-- ------------------------------------------------------------
INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion)
SELECT 1,
       'schema_update',
       'pedidos',
       'Migracion add_devoluciones_pedidos.sql: tablas devoluciones_pedidos y detalle_devoluciones_pedidos, columnas cantidad_retirada/cantidad_devuelta en detalle_pedidos_materiales',
       NOW()
WHERE EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = 1);

-- ============================================================
-- FIN DE MIGRACION
-- Tablas creadas:   devoluciones_pedidos, detalle_devoluciones_pedidos
-- Columnas nuevas:  detalle_pedidos_materiales.cantidad_retirada
--                   detalle_pedidos_materiales.cantidad_devuelta
-- ============================================================

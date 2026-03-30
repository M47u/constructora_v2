-- ============================================================
-- Migración: Tareas Recurrentes
-- Crea el catálogo de tareas recurrentes autoasignables y
-- extiende la tabla tareas para referenciarlas.
-- ============================================================

-- 1. Catálogo de tareas recurrentes (gestionado por admins)
CREATE TABLE IF NOT EXISTS tareas_recurrentes (
    id_tarea_recurrente INT          NOT NULL AUTO_INCREMENT,
    titulo              VARCHAR(200) NOT NULL,
    descripcion         TEXT,
    prioridad           ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
    estado              ENUM('activa','inactiva')             NOT NULL DEFAULT 'activa',
    id_creador          INT          NOT NULL,
    fecha_creacion      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id_tarea_recurrente),
    INDEX idx_tr_estado (estado),
    CONSTRAINT fk_tr_creador FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de tareas recurrentes autoasignables por cualquier usuario';

-- 2. Extender tareas: nuevo tipo 'recurrente' + referencia al catálogo
ALTER TABLE tareas
    MODIFY COLUMN tipo ENUM('manual','pedido','recurrente') NOT NULL DEFAULT 'manual';

-- Agregar columna solo si no existe
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tareas'
      AND COLUMN_NAME  = 'id_tarea_recurrente'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tareas ADD COLUMN id_tarea_recurrente INT NULL AFTER etapa_pedido',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Agregar FK solo si no existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
      AND TABLE_NAME      = 'tareas'
      AND CONSTRAINT_NAME = 'fk_tarea_recurrente'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE tareas ADD CONSTRAINT fk_tarea_recurrente FOREIGN KEY (id_tarea_recurrente) REFERENCES tareas_recurrentes(id_tarea_recurrente) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Agregar índice solo si no existe
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tareas'
      AND INDEX_NAME   = 'idx_tareas_recurrente'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tareas ADD INDEX idx_tareas_recurrente (id_tarea_recurrente)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

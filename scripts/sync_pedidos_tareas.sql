-- ============================================================
-- MIGRACIÓN: Sincronización Pedidos ↔ Tareas (Opción C)
-- Fecha: 2026-03-18
-- Descripción: Extiende la tabla tareas para vincularlas a
--   etapas de pedidos y crea la tabla de vínculo pedido_tareas.
--   Ejecutar UNA sola vez sobre la base de datos.
-- ============================================================

-- 1. Agregar columnas a la tabla tareas
-- (ignorar error si ya existen al re-ejecutar manualmente)

ALTER TABLE tareas
    ADD COLUMN tipo          ENUM('manual','pedido') NOT NULL DEFAULT 'manual'  AFTER observaciones,
    ADD COLUMN id_pedido     INT  NULL                                           AFTER tipo,
    ADD COLUMN etapa_pedido  ENUM('creacion','aprobacion','retiro','recibido') NULL AFTER id_pedido;

-- Clave foránea al pedido (ON DELETE CASCADE: si se borra el pedido, se borran sus tareas automáticas)
ALTER TABLE tareas
    ADD CONSTRAINT fk_tarea_pedido
        FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE;

-- Índices de soporte para consultas de trazabilidad
ALTER TABLE tareas
    ADD INDEX idx_tareas_tipo       (tipo),
    ADD INDEX idx_tareas_id_pedido  (id_pedido),
    ADD INDEX idx_tareas_etapa      (etapa_pedido);

-- ============================================================
-- 2. Tabla de vínculo pedido_tareas
--    Permite consultar fácilmente qué tarea corresponde
--    a qué etapa de qué pedido.
-- ============================================================

CREATE TABLE IF NOT EXISTS pedido_tareas (
    id         INT          NOT NULL AUTO_INCREMENT,
    id_pedido  INT          NOT NULL,
    id_tarea   INT          NOT NULL,
    etapa      ENUM('creacion','aprobacion','retiro','recibido') NOT NULL,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_pedido_etapa (id_pedido, etapa),   -- 1 tarea activa por etapa por pedido
    INDEX       idx_pt_tarea    (id_tarea),

    CONSTRAINT fk_pt_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    CONSTRAINT fk_pt_tarea  FOREIGN KEY (id_tarea)  REFERENCES tareas(id_tarea)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vínculo entre pedidos y sus tareas automáticas por etapa';

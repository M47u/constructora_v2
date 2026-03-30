-- ============================================================
-- Migración: Responsables por etapa en pedidos
-- Agrega 4 columnas FK a pedidos_materiales para asignar
-- un responsable específico a cada etapa del flujo del pedido.
-- Flujo: creacion → aprobacion → picking → retiro → recibido
-- ============================================================

-- 1. Columnas de responsables pre-asignados en pedidos_materiales
--    Nota: 'recibido' no tiene columna propia porque la tarea se asigna
--    automáticamente al mismo usuario que completó la etapa de 'retiro'.
ALTER TABLE pedidos_materiales
    ADD COLUMN id_responsable_aprobacion INT NULL AFTER id_solicitante,
    ADD COLUMN id_responsable_picking    INT NULL AFTER id_responsable_aprobacion,
    ADD COLUMN id_responsable_retiro     INT NULL AFTER id_responsable_picking,
    ADD CONSTRAINT fk_pm_resp_aprobacion FOREIGN KEY (id_responsable_aprobacion) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    ADD CONSTRAINT fk_pm_resp_picking    FOREIGN KEY (id_responsable_picking)    REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    ADD CONSTRAINT fk_pm_resp_retiro     FOREIGN KEY (id_responsable_retiro)     REFERENCES usuarios(id_usuario) ON DELETE SET NULL;

-- 2. Agregar 'picking' al ENUM de etapa_pedido en tareas
ALTER TABLE tareas
    MODIFY COLUMN etapa_pedido ENUM('creacion','aprobacion','picking','retiro','recibido') NULL;

-- 3. Agregar 'picking' al ENUM de etapa en pedido_tareas
ALTER TABLE pedido_tareas
    MODIFY COLUMN etapa ENUM('creacion','aprobacion','picking','retiro','recibido') NOT NULL;

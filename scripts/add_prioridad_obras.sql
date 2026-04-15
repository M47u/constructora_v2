-- Migración: agregar prioridad a obras
-- Valores permitidos: alta, media, baja
-- Las obras sin prioridad quedan con NULL y se ordenan al final

ALTER TABLE obras
    ADD COLUMN prioridad ENUM('baja', 'media', 'alta') NULL DEFAULT NULL
        AFTER cliente;

-- Opcional: normalizar prioridades existentes si hiciera falta
-- UPDATE obras SET prioridad = 'media' WHERE prioridad IS NULL;

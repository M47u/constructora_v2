-- Tabla para registrar el historial de extensiones de fechas de devolución
CREATE TABLE IF NOT EXISTS historial_extensiones_prestamo (
    id_extension INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    fecha_anterior DATE NULL COMMENT 'Fecha de devolución programada anterior',
    fecha_nueva DATE NOT NULL COMMENT 'Nueva fecha de devolución programada',
    id_usuario_modifico INT NOT NULL COMMENT 'Usuario que realizó la modificación',
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de la modificación',
    motivo VARCHAR(500) NULL COMMENT 'Motivo opcional de la extensión',
    FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_modifico) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    INDEX idx_prestamo (id_prestamo),
    INDEX idx_fecha_modificacion (fecha_modificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historial de extensiones de fechas de devolución de préstamos';

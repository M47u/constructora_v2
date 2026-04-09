-- =====================================================
-- MIGRACION: HISTORIAL DE MANTENIMIENTOS DE TRANSPORTES
-- =====================================================

CREATE TABLE IF NOT EXISTS transportes_mantenimientos (
    id_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_transporte INT NOT NULL,
    tipo_evento ENUM('preventivo', 'correctivo', 'service', 'inspeccion', 'otro') NOT NULL DEFAULT 'service',
    fecha_evento DATE NOT NULL,
    kilometraje INT NULL,
    proveedor_taller VARCHAR(150) NULL,
    descripcion_problema TEXT NULL,
    trabajo_realizado TEXT NULL,
    costo_mano_obra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    costo_repuestos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    costo_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    observaciones TEXT NULL,
    id_usuario_registro INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_transporte) REFERENCES transportes(id_transporte) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    INDEX idx_tm_transporte (id_transporte),
    INDEX idx_tm_fecha_evento (fecha_evento),
    INDEX idx_tm_tipo_evento (tipo_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTAS
-- =====================================================
-- 1) Registros como eventos históricos sin estados complejos.
-- 2) costo_total persistido para simplificar reportes.
-- 3) Aplicar luego de backup de base de datos.
-- =====================================================

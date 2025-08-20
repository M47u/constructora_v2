-- Base de datos para Sistema de Gestión de Empresa Constructora
-- Creación de base de datos
 CREATE DATABASE IF NOT EXISTS sistema_constructora; --linea 3 y 4 se comenta en produccion
 USE sistema_constructora;
-- use u251673992_sistema_constr; -- Descomentar en producción  

-- Tabla de usuarios (mejorada para el módulo completo)
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    contraseña VARCHAR(255) NOT NULL,
    rol ENUM('administrador', 'responsable_obra', 'empleado') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    telefono VARCHAR(20),
    direccion TEXT,
    fecha_nacimiento DATE,
    documento VARCHAR(20),
    fecha_ultimo_acceso TIMESTAMP NULL,
    intentos_login INT DEFAULT 0,
    bloqueado_hasta TIMESTAMP NULL,
    avatar VARCHAR(255),
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de obras
CREATE TABLE obras (
    id_obra INT AUTO_INCREMENT PRIMARY KEY,
    nombre_obra VARCHAR(200) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    localidad VARCHAR(100) NOT NULL,
    direccion TEXT NOT NULL,
    id_responsable INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE,
    cliente VARCHAR(200) NOT NULL,
    estado ENUM('planificada', 'en_progreso', 'finalizada', 'cancelada') DEFAULT 'planificada',
    presupuesto DECIMAL(15,2),
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_responsable) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de materiales (mejorada)
CREATE TABLE materiales (
    id_material INT AUTO_INCREMENT PRIMARY KEY,
    nombre_material VARCHAR(200) NOT NULL,
    descripcion TEXT,
    categoria VARCHAR(100),
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 0,
    stock_maximo INT DEFAULT 0,
    precio_referencia DECIMAL(10,2) DEFAULT 0.00,
    precio_compra DECIMAL(10,2) DEFAULT 0.00,
    unidad_medida VARCHAR(50) DEFAULT 'unidad',
    proveedor VARCHAR(200),
    codigo_interno VARCHAR(50),
    ubicacion_deposito VARCHAR(100),
    estado ENUM('activo', 'inactivo', 'descontinuado') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de transportes
CREATE TABLE transportes (
    id_transporte INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    matricula VARCHAR(20) UNIQUE NOT NULL,
    año INT,
    color VARCHAR(50),
    tipo_vehiculo ENUM('camion', 'camioneta', 'auto', 'moto', 'otro') DEFAULT 'camioneta',
    capacidad_carga DECIMAL(8,2),
    id_encargado INT,
    estado ENUM('disponible', 'en_uso', 'mantenimiento', 'fuera_servicio') DEFAULT 'disponible',
    kilometraje INT DEFAULT 0,
    fecha_vencimiento_vtv DATE,
    fecha_vencimiento_seguro DATE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_encargado) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla de herramientas (catálogo general)
CREATE TABLE herramientas (
    id_herramienta INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(100) NOT NULL,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    stock_total INT DEFAULT 0,
    condicion_general ENUM('excelente', 'buena', 'regular', 'mala') DEFAULT 'buena',
    precio_referencia DECIMAL(10,2) DEFAULT 0.00,
    categoria VARCHAR(100),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de unidades individuales de herramientas
CREATE TABLE herramientas_unidades (
    id_unidad INT AUTO_INCREMENT PRIMARY KEY,
    id_herramienta INT NOT NULL,
    codigo_unico VARCHAR(20) UNIQUE NOT NULL,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    estado_actual ENUM('disponible', 'prestada', 'mantenimiento', 'perdida', 'dañada') DEFAULT 'disponible',
    condicion ENUM('excelente', 'buena', 'regular', 'mala') DEFAULT 'buena',
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_herramienta) REFERENCES herramientas(id_herramienta) ON DELETE CASCADE
);

-- Tabla de pedidos de materiales (mejorada)
CREATE TABLE pedidos_materiales (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    numero_pedido VARCHAR(20) UNIQUE NOT NULL,
    id_obra INT NOT NULL,
    id_solicitante INT NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_necesaria DATE,
    estado ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado') DEFAULT 'pendiente',
    prioridad ENUM('baja', 'media', 'alta', 'urgente') DEFAULT 'media',
    valor_total DECIMAL(15,2) DEFAULT 0.00,
    valor_disponible DECIMAL(15,2) DEFAULT 0.00,
    valor_a_comprar DECIMAL(15,2) DEFAULT 0.00,
    observaciones TEXT,
    id_aprobado_por INT NULL,
    fecha_aprobacion TIMESTAMP NULL,
    id_entregado_por INT NULL,
    fecha_entrega TIMESTAMP NULL,
    FOREIGN KEY (id_obra) REFERENCES obras(id_obra) ON DELETE CASCADE,
    FOREIGN KEY (id_solicitante) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_aprobado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_entregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla de detalle de pedidos (mejorada)
CREATE TABLE detalle_pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_material INT NOT NULL,
    cantidad_solicitada INT NOT NULL,
    cantidad_disponible INT DEFAULT 0,
    cantidad_faltante INT DEFAULT 0,
    cantidad_entregada INT DEFAULT 0,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    requiere_compra BOOLEAN DEFAULT FALSE,
    observaciones TEXT,
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_material) REFERENCES materiales(id_material) ON DELETE RESTRICT
);

-- Tabla de seguimiento de pedidos (mejorada)
CREATE TABLE seguimiento_pedidos (
    id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    estado_anterior ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado'),
    estado_nuevo ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado') NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    id_usuario_cambio INT NOT NULL,
    ip_usuario VARCHAR(45),
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_cambio) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de préstamos de herramientas
CREATE TABLE prestamos (
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    numero_prestamo VARCHAR(20) UNIQUE NOT NULL,
    id_empleado INT NOT NULL,
    id_obra INT NOT NULL,
    fecha_retiro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_devolucion_programada DATE,
    id_autorizado_por INT NOT NULL,
    observaciones_retiro TEXT,
    estado ENUM('activo', 'devuelto', 'vencido') DEFAULT 'activo',
    FOREIGN KEY (id_empleado) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_obra) REFERENCES obras(id_obra) ON DELETE RESTRICT,
    FOREIGN KEY (id_autorizado_por) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de detalle de préstamos
CREATE TABLE detalle_prestamo (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    id_unidad INT NOT NULL,
    condicion_retiro ENUM('excelente', 'buena', 'regular', 'mala') NOT NULL,
    observaciones_retiro TEXT,
    devuelto BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE,
    FOREIGN KEY (id_unidad) REFERENCES herramientas_unidades(id_unidad) ON DELETE RESTRICT
);

-- Tabla de devoluciones
CREATE TABLE devoluciones (
    id_devolucion INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    fecha_devolucion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_recibido_por INT NOT NULL,
    observaciones_devolucion TEXT,
    completa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE RESTRICT,
    FOREIGN KEY (id_recibido_por) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de detalle de devoluciones
CREATE TABLE detalle_devolucion (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_devolucion INT NOT NULL,
    id_unidad INT NOT NULL,
    condicion_devolucion ENUM('excelente', 'buena', 'regular', 'mala', 'dañada', 'perdida') NOT NULL,
    observaciones_devolucion TEXT,
    FOREIGN KEY (id_devolucion) REFERENCES devoluciones(id_devolucion) ON DELETE CASCADE,
    FOREIGN KEY (id_unidad) REFERENCES herramientas_unidades(id_unidad) ON DELETE RESTRICT
);

-- Tabla de tareas/agenda de trabajo
CREATE TABLE tareas (
    id_tarea INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT NOT NULL,
    id_obra INT,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE,
    fecha_inicio TIMESTAMP NULL,
    fecha_finalizacion TIMESTAMP NULL,
    estado ENUM('pendiente', 'en_proceso', 'finalizada', 'cancelada') DEFAULT 'pendiente',
    id_asignador INT NOT NULL,
    prioridad ENUM('baja', 'media', 'alta', 'urgente') DEFAULT 'media',
    progreso INT DEFAULT 0,
    observaciones TEXT,
    tiempo_estimado INT, -- en horas
    tiempo_real INT, -- en horas
    FOREIGN KEY (id_empleado) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_obra) REFERENCES obras(id_obra) ON DELETE SET NULL,
    FOREIGN KEY (id_asignador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de notificaciones (nueva)
CREATE TABLE notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    modulo VARCHAR(50),
    referencia_id INT,
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_lectura TIMESTAMP NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- Tabla de configuraciones del sistema (nueva)
CREATE TABLE configuraciones (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de logs del sistema (nueva)
CREATE TABLE logs_sistema (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    accion VARCHAR(100) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    ip_usuario VARCHAR(45),
    user_agent TEXT,
    datos_adicionales JSON,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Índices para optimizar consultas
CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_rol ON usuarios(rol);
CREATE INDEX idx_usuarios_estado ON usuarios(estado);
CREATE INDEX idx_usuarios_ultimo_acceso ON usuarios(fecha_ultimo_acceso);

CREATE INDEX idx_obras_responsable ON obras(id_responsable);
CREATE INDEX idx_obras_estado ON obras(estado);
CREATE INDEX idx_obras_fechas ON obras(fecha_inicio, fecha_fin);

CREATE INDEX idx_materiales_stock ON materiales(stock_actual);
CREATE INDEX idx_materiales_categoria ON materiales(categoria);
CREATE INDEX idx_materiales_estado ON materiales(estado);

CREATE INDEX idx_transportes_estado ON transportes(estado);
CREATE INDEX idx_transportes_encargado ON transportes(id_encargado);

CREATE INDEX idx_herramientas_categoria ON herramientas(categoria);
CREATE INDEX idx_herramientas_unidades_estado ON herramientas_unidades(estado_actual);
CREATE INDEX idx_herramientas_unidades_codigo ON herramientas_unidades(codigo_unico);

CREATE INDEX idx_pedidos_obra ON pedidos_materiales(id_obra);
CREATE INDEX idx_pedidos_estado ON pedidos_materiales(estado);
CREATE INDEX idx_pedidos_solicitante ON pedidos_materiales(id_solicitante);
CREATE INDEX idx_pedidos_fecha ON pedidos_materiales(fecha_pedido);
CREATE INDEX idx_pedidos_numero ON pedidos_materiales(numero_pedido);

CREATE INDEX idx_detalle_pedido_material ON detalle_pedido(id_material);
CREATE INDEX idx_detalle_pedido_compra ON detalle_pedido(requiere_compra);

CREATE INDEX idx_seguimiento_pedido ON seguimiento_pedidos(id_pedido);
CREATE INDEX idx_seguimiento_fecha ON seguimiento_pedidos(fecha_cambio);

CREATE INDEX idx_prestamos_empleado ON prestamos(id_empleado);
CREATE INDEX idx_prestamos_obra ON prestamos(id_obra);
CREATE INDEX idx_prestamos_estado ON prestamos(estado);
CREATE INDEX idx_prestamos_numero ON prestamos(numero_prestamo);

CREATE INDEX idx_tareas_empleado ON tareas(id_empleado);
CREATE INDEX idx_tareas_estado ON tareas(estado);
CREATE INDEX idx_tareas_obra ON tareas(id_obra);
CREATE INDEX idx_tareas_vencimiento ON tareas(fecha_vencimiento);

CREATE INDEX idx_notificaciones_usuario ON notificaciones(id_usuario);
CREATE INDEX idx_notificaciones_leida ON notificaciones(leida);
CREATE INDEX idx_notificaciones_fecha ON notificaciones(fecha_creacion);

CREATE INDEX idx_logs_usuario ON logs_sistema(id_usuario);
CREATE INDEX idx_logs_modulo ON logs_sistema(modulo);
CREATE INDEX idx_logs_fecha ON logs_sistema(fecha_creacion);

-- Triggers para automatización

-- Trigger para generar número de pedido automáticamente
DELIMITER //
CREATE TRIGGER tr_pedidos_numero BEFORE INSERT ON pedidos_materiales
FOR EACH ROW
BEGIN
    DECLARE next_num INT;
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_pedido, 4) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM pedidos_materiales 
    WHERE numero_pedido LIKE CONCAT('PED', YEAR(CURDATE()), '%');
    
    SET NEW.numero_pedido = CONCAT('PED', YEAR(CURDATE()), LPAD(next_num, 4, '0'));
END//

-- Trigger para generar número de préstamo automáticamente
CREATE TRIGGER tr_prestamos_numero BEFORE INSERT ON prestamos
FOR EACH ROW
BEGIN
    DECLARE next_num INT;
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_prestamo, 4) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM prestamos 
    WHERE numero_prestamo LIKE CONCAT('PRE', YEAR(CURDATE()), '%');
    
    SET NEW.numero_prestamo = CONCAT('PRE', YEAR(CURDATE()), LPAD(next_num, 4, '0'));
END//

-- Trigger para actualizar stock total de herramientas
CREATE TRIGGER tr_herramientas_stock_insert AFTER INSERT ON herramientas_unidades
FOR EACH ROW
BEGIN
    UPDATE herramientas 
    SET stock_total = (
        SELECT COUNT(*) 
        FROM herramientas_unidades 
        WHERE id_herramienta = NEW.id_herramienta
    )
    WHERE id_herramienta = NEW.id_herramienta;
END//

CREATE TRIGGER tr_herramientas_stock_delete AFTER DELETE ON herramientas_unidades
FOR EACH ROW
BEGIN
    UPDATE herramientas 
    SET stock_total = (
        SELECT COUNT(*) 
        FROM herramientas_unidades 
        WHERE id_herramienta = OLD.id_herramienta
    )
    WHERE id_herramienta = OLD.id_herramienta;
END//

-- Trigger para registrar cambios de estado en seguimiento_pedidos
CREATE TRIGGER tr_seguimiento_pedidos AFTER UPDATE ON pedidos_materiales
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO seguimiento_pedidos (
            id_pedido, 
            estado_anterior, 
            estado_nuevo, 
            id_usuario_cambio,
            observaciones
        ) VALUES (
            NEW.id_pedido,
            OLD.estado,
            NEW.estado,
            COALESCE(NEW.id_aprobado_por, NEW.id_entregado_por, NEW.id_solicitante),
            CONCAT('Estado cambiado de ', OLD.estado, ' a ', NEW.estado)
        );
    END IF;
END//

DELIMITER ;

-- Datos iniciales
-- Usuario administrador por defecto
INSERT INTO usuarios (nombre, apellido, email, contraseña, rol, telefono, documento) VALUES 
('Admin', 'Sistema', 'admin@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador', '123456789', '12345678');

-- Algunos usuarios de ejemplo
INSERT INTO usuarios (nombre, apellido, email, contraseña, rol, telefono, documento) VALUES 
('Juan', 'Pérez', 'juan.perez@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'responsable_obra', '987654321', '87654321'),
('María', 'González', 'maria.gonzalez@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empleado', '456789123', '45678912');

-- Algunos materiales básicos con categorías
INSERT INTO materiales (nombre_material, descripcion, categoria, stock_actual, stock_minimo, precio_referencia, precio_compra, unidad_medida, proveedor) VALUES
('Cemento Portland', 'Cemento Portland tipo CPN-40', 'Cemento', 100, 20, 850.00, 800.00, 'bolsa', 'Loma Negra'),
('Arena gruesa', 'Arena gruesa para construcción', 'Áridos', 50, 10, 1200.00, 1100.00, 'm3', 'Arenera Central'),
('Ladrillos comunes', 'Ladrillos comunes 6x12x25', 'Mampostería', 5000, 1000, 25.00, 22.00, 'unidad', 'Ladrillera Norte'),
('Hierro 8mm', 'Hierro construcción 8mm x 12m', 'Hierros', 200, 50, 180.00, 165.00, 'barra', 'Acindar'),
('Hierro 10mm', 'Hierro construcción 10mm x 12m', 'Hierros', 150, 30, 280.00, 260.00, 'barra', 'Acindar'),
('Cal hidratada', 'Cal hidratada bolsa 25kg', 'Cal', 80, 15, 320.00, 300.00, 'bolsa', 'Cal del Centro');

-- Algunas herramientas básicas con tipos
INSERT INTO herramientas (tipo, marca, modelo, descripcion, categoria, precio_referencia) VALUES
('Taladro', 'Bosch', 'GSB 13 RE', 'Taladro percutor 650W', 'Herramientas Eléctricas', 25000.00),
('Amoladora', 'Makita', 'DGA452Z', 'Amoladora angular 115mm', 'Herramientas Eléctricas', 18000.00),
('Martillo', 'Stanley', 'STHT0-33559', 'Martillo de carpintero 450g', 'Herramientas Manuales', 3500.00),
('Sierra', 'Irwin', '10505212', 'Sierra de mano 20 TPI', 'Herramientas Manuales', 2800.00);

-- Configuraciones iniciales del sistema
INSERT INTO configuraciones (clave, valor, descripcion, tipo) VALUES
('empresa_nombre', 'Constructora ABC', 'Nombre de la empresa', 'string'),
('empresa_direccion', 'Av. Principal 123, Ciudad', 'Dirección de la empresa', 'string'),
('empresa_telefono', '+54 11 1234-5678', 'Teléfono de la empresa', 'string'),
('empresa_email', 'info@constructora.com', 'Email de contacto', 'string'),
('sistema_version', '1.0.0', 'Versión del sistema', 'string'),
('backup_automatico', 'true', 'Realizar backup automático', 'boolean'),
('notificaciones_email', 'true', 'Enviar notificaciones por email', 'boolean'),
('stock_minimo_alerta', '10', 'Días de anticipación para alerta de stock mínimo', 'number');

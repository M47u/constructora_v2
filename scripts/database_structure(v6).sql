-- Base de datos para Sistema de Gestión de Empresa Constructora
-- Creación de base de datos
CREATE DATABASE IF NOT EXISTS sistema_constructora;
USE sistema_constructora;

-- Tabla de usuarios (base para roles y autenticación)
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    contraseña VARCHAR(255) NOT NULL,
    rol ENUM('administrador', 'responsable_obra', 'empleado') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
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
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_responsable) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de materiales
CREATE TABLE materiales (
    id_material INT AUTO_INCREMENT PRIMARY KEY,
    nombre_material VARCHAR(200) NOT NULL,
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 0,
    precio_referencia DECIMAL(10,2) DEFAULT 0.00,
    unidad_medida VARCHAR(50) DEFAULT 'unidad',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de transportes
CREATE TABLE transportes (
    id_transporte INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    matricula VARCHAR(20) UNIQUE NOT NULL,
    id_encargado INT,
    estado ENUM('disponible', 'en_uso', 'mantenimiento', 'fuera_servicio') DEFAULT 'disponible',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_encargado) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla de herramientas (catálogo general)
CREATE TABLE herramientas (
    id_herramienta INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    stock_total INT DEFAULT 0,
    condicion_general ENUM('excelente', 'buena', 'regular', 'mala') DEFAULT 'buena',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de unidades individuales de herramientas
CREATE TABLE herramientas_unidades (
    id_unidad INT AUTO_INCREMENT PRIMARY KEY,
    id_herramienta INT NOT NULL,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    estado_actual ENUM('disponible', 'prestada', 'mantenimiento', 'perdida', 'dañada') DEFAULT 'disponible',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_herramienta) REFERENCES herramientas(id_herramienta) ON DELETE CASCADE
);

-- Tabla de pedidos de materiales
CREATE TABLE pedidos_materiales (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_obra INT NOT NULL,
    id_solicitante INT NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado') DEFAULT 'pendiente',
    observaciones TEXT,
    FOREIGN KEY (id_obra) REFERENCES obras(id_obra) ON DELETE CASCADE,
    FOREIGN KEY (id_solicitante) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de detalle de pedidos
CREATE TABLE detalle_pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_material INT NOT NULL,
    cantidad INT NOT NULL,
    cantidad_entregada INT DEFAULT 0,
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_material) REFERENCES materiales(id_material) ON DELETE RESTRICT
);

-- Tabla de seguimiento de pedidos
CREATE TABLE seguimiento_pedidos (
    id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado') NOT NULL,
    fecha_estado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    id_usuario_cambio INT NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_cambio) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Tabla de préstamos de herramientas
CREATE TABLE prestamos (
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT NOT NULL,
    id_obra INT NOT NULL,
    fecha_retiro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_autorizado_por INT NOT NULL,
    observaciones_retiro TEXT,
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
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE,
    estado ENUM('pendiente', 'en_proceso', 'finalizada', 'cancelada') DEFAULT 'pendiente',
    id_asignador INT NOT NULL,
    prioridad ENUM('baja', 'media', 'alta', 'urgente') DEFAULT 'media',
    observaciones TEXT,
    fecha_finalizacion TIMESTAMP NULL,
    FOREIGN KEY (id_empleado) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_asignador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
);

-- Índices para optimizar consultas
CREATE INDEX idx_obras_responsable ON obras(id_responsable);
CREATE INDEX idx_obras_estado ON obras(estado);
CREATE INDEX idx_materiales_stock ON materiales(stock_actual);
CREATE INDEX idx_pedidos_obra ON pedidos_materiales(id_obra);
CREATE INDEX idx_pedidos_estado ON pedidos_materiales(estado);
CREATE INDEX idx_herramientas_unidades_estado ON herramientas_unidades(estado_actual);
CREATE INDEX idx_prestamos_empleado ON prestamos(id_empleado);
CREATE INDEX idx_prestamos_obra ON prestamos(id_obra);
CREATE INDEX idx_tareas_empleado ON tareas(id_empleado);
CREATE INDEX idx_tareas_estado ON tareas(estado);

-- Datos iniciales
-- Usuario administrador por defecto
INSERT INTO usuarios (nombre, apellido, email, contraseña, rol) VALUES 
('Admin', 'Sistema', 'admin@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador');

-- Algunos materiales básicos
INSERT INTO materiales (nombre_material, stock_actual, stock_minimo, precio_referencia, unidad_medida) VALUES
('Cemento Portland', 100, 20, 850.00, 'bolsa'),
('Arena gruesa', 50, 10, 1200.00, 'm3'),
('Ladrillos comunes', 5000, 1000, 25.00, 'unidad'),
('Hierro 8mm', 200, 50, 180.00, 'barra'),
('Hierro 10mm', 150, 30, 280.00, 'barra'),
('Cal hidratada', 80, 15, 320.00, 'bolsa');

-- Algunas herramientas básicas
INSERT INTO herramientas (marca, modelo, descripcion, stock_total) VALUES
('Bosch', 'GSB 13 RE', 'Taladro percutor 650W', 5),
('Makita', 'DGA452Z', 'Amoladora angular 115mm', 8),
('Stanley', 'STHT0-33559', 'Martillo de carpintero 450g', 12),
('Irwin', '10505212', 'Sierra de mano 20 TPI', 6);

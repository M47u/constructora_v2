-- Script para corregir la estructura de las tablas de pedidos
-- cambiar esto dependiendo del contexto, produccion o local_
USE u251673992_sistema_constr;

-- Primero, hacer backup de los datos existentes si los hay
CREATE TABLE IF NOT EXISTS backup_pedidos_materiales AS SELECT * FROM pedidos_materiales;
CREATE TABLE IF NOT EXISTS backup_detalle_pedido AS SELECT * FROM detalle_pedido;

-- Eliminar las tablas existentes para recrearlas con la estructura correcta
DROP TABLE IF EXISTS detalle_pedido;
DROP TABLE IF EXISTS seguimiento_pedidos;
DROP TABLE IF EXISTS pedidos_materiales;

-- Recrear tabla pedidos_materiales (solo información general del pedido)
CREATE TABLE pedidos_materiales (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    numero_pedido VARCHAR(20) UNIQUE NOT NULL,
    id_obra INT NOT NULL,
    id_solicitante INT NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_necesaria DATE,
    estado ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado') DEFAULT 'pendiente',
    prioridad ENUM('baja', 'media', 'alta', 'urgente') DEFAULT 'media',
    observaciones TEXT,
    
    -- Campos calculados que se actualizan automáticamente
    total_items INT DEFAULT 0,
    valor_total DECIMAL(15,2) DEFAULT 0.00,
    valor_disponible DECIMAL(15,2) DEFAULT 0.00,
    valor_a_comprar DECIMAL(15,2) DEFAULT 0.00,
    
    -- Campos de auditoría
    id_aprobado_por INT NULL,
    fecha_aprobacion TIMESTAMP NULL,
    id_entregado_por INT NULL,
    fecha_entrega TIMESTAMP NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Claves foráneas
    FOREIGN KEY (id_obra) REFERENCES obras(id_obra) ON DELETE CASCADE,
    FOREIGN KEY (id_solicitante) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_aprobado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_entregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    
    -- Índices
    INDEX idx_pedidos_obra (id_obra),
    INDEX idx_pedidos_estado (estado),
    INDEX idx_pedidos_solicitante (id_solicitante),
    INDEX idx_pedidos_fecha (fecha_pedido),
    INDEX idx_pedidos_numero (numero_pedido)
);

-- Crear tabla detalle_pedidos_materiales (detalles de cada material en el pedido)
CREATE TABLE detalle_pedidos_materiales (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_material INT NOT NULL,
    
    -- Cantidades
    cantidad_solicitada INT NOT NULL,
    cantidad_disponible INT DEFAULT 0,
    cantidad_faltante INT DEFAULT 0,
    cantidad_entregada INT DEFAULT 0,
    
    -- Precios y valores
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    
    -- Estado del item
    requiere_compra BOOLEAN DEFAULT FALSE,
    estado_item ENUM('pendiente', 'disponible', 'parcial', 'sin_stock', 'entregado') DEFAULT 'pendiente',
    
    -- Observaciones específicas del item
    observaciones_item TEXT,
    
    -- Auditoría
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Claves foráneas
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_material) REFERENCES materiales(id_material) ON DELETE RESTRICT,
    
    -- Índices
    INDEX idx_detalle_pedido (id_pedido),
    INDEX idx_detalle_material (id_material),
    INDEX idx_detalle_estado (estado_item),
    INDEX idx_detalle_compra (requiere_compra),
    
    -- Constraint para evitar duplicados
    UNIQUE KEY unique_pedido_material (id_pedido, id_material)
);

-- Recrear tabla de seguimiento
CREATE TABLE seguimiento_pedidos (
    id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    estado_anterior ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado'),
    estado_nuevo ENUM('pendiente', 'aprobado', 'en_camino', 'entregado', 'devuelto', 'cancelado') NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    id_usuario_cambio INT NOT NULL,
    ip_usuario VARCHAR(45),
    datos_adicionales JSON,
    
    FOREIGN KEY (id_pedido) REFERENCES pedidos_materiales(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_cambio) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    
    INDEX idx_seguimiento_pedido (id_pedido),
    INDEX idx_seguimiento_fecha (fecha_cambio),
    INDEX idx_seguimiento_usuario (id_usuario_cambio)
);

-- Triggers para automatización y consistencia

-- Trigger para generar número de pedido automáticamente
DELIMITER //
CREATE TRIGGER tr_pedidos_numero BEFORE INSERT ON pedidos_materiales
FOR EACH ROW
BEGIN
    DECLARE next_num INT;
    SELECT COALESCE(MAX(CAST(RIGHT(numero_pedido, 4) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM pedidos_materiales 
    WHERE numero_pedido LIKE CONCAT('PED', YEAR(CURDATE()), '%');
    
    SET NEW.numero_pedido = CONCAT('PED', YEAR(CURDATE()), LPAD(next_num, 4, '0'));
END//

-- Trigger para actualizar totales del pedido cuando se insertan detalles
CREATE TRIGGER tr_actualizar_totales_insert AFTER INSERT ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    UPDATE pedidos_materiales SET
        total_items = (SELECT COUNT(*) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido),
        valor_total = (SELECT COALESCE(SUM(subtotal), 0) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido),
        valor_disponible = (SELECT COALESCE(SUM(cantidad_disponible * precio_unitario), 0) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido),
        valor_a_comprar = (SELECT COALESCE(SUM(cantidad_faltante * precio_unitario), 0) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido)
    WHERE id_pedido = NEW.id_pedido;
END//

-- Trigger para actualizar totales del pedido cuando se actualizan detalles
CREATE TRIGGER tr_actualizar_totales_update AFTER UPDATE ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    UPDATE pedidos_materiales SET
        total_items = (SELECT COUNT(*) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido),
        valor_total = (SELECT COALESCE(SUM(subtotal), 0) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido),
        valor_disponible = (SELECT COALESCE(SUM(cantidad_disponible * precio_unitario), 0) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido),
        valor_a_comprar = (SELECT COALESCE(SUM(cantidad_faltante * precio_unitario), 0) FROM detalle_pedidos_materiales WHERE id_pedido = NEW.id_pedido)
    WHERE id_pedido = NEW.id_pedido;
END//

-- Trigger para actualizar totales del pedido cuando se eliminan detalles
CREATE TRIGGER tr_actualizar_totales_delete AFTER DELETE ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    UPDATE pedidos_materiales SET
        total_items = (SELECT COUNT(*) FROM detalle_pedidos_materiales WHERE id_pedido = OLD.id_pedido),
        valor_total = (SELECT COALESCE(SUM(subtotal), 0) FROM detalle_pedidos_materiales WHERE id_pedido = OLD.id_pedido),
        valor_disponible = (SELECT COALESCE(SUM(cantidad_disponible * precio_unitario), 0) FROM detalle_pedidos_materiales WHERE id_pedido = OLD.id_pedido),
        valor_a_comprar = (SELECT COALESCE(SUM(cantidad_faltante * precio_unitario), 0) FROM detalle_pedidos_materiales WHERE id_pedido = OLD.id_pedido)
    WHERE id_pedido = OLD.id_pedido;
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

-- Trigger para calcular disponibilidad automáticamente al insertar detalle
CREATE TRIGGER tr_calcular_disponibilidad BEFORE INSERT ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    DECLARE stock_actual INT DEFAULT 0;
    
    -- Obtener stock actual del material
    SELECT m.stock_actual INTO stock_actual
    FROM materiales m 
    WHERE m.id_material = NEW.id_material;
    
    -- Calcular disponibilidad
    SET NEW.cantidad_disponible = LEAST(NEW.cantidad_solicitada, stock_actual);
    SET NEW.cantidad_faltante = GREATEST(0, NEW.cantidad_solicitada - stock_actual);
    SET NEW.subtotal = NEW.cantidad_solicitada * NEW.precio_unitario;
    SET NEW.requiere_compra = (NEW.cantidad_faltante > 0);
    
    -- Determinar estado del item
    IF stock_actual = 0 THEN
        SET NEW.estado_item = 'sin_stock';
    ELSEIF stock_actual < NEW.cantidad_solicitada THEN
        SET NEW.estado_item = 'parcial';
    ELSE
        SET NEW.estado_item = 'disponible';
    END IF;
END//

-- Ajuste de stock en materiales según detalle de pedidos
DELIMITER //
CREATE TRIGGER tr_stock_materiales_insert AFTER INSERT ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    UPDATE materiales
    SET stock_actual = GREATEST(stock_actual - NEW.cantidad_solicitada, 0)
    WHERE id_material = NEW.id_material;
END//

CREATE TRIGGER tr_stock_materiales_update AFTER UPDATE ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    IF OLD.id_material = NEW.id_material THEN
        UPDATE materiales
        SET stock_actual = GREATEST(stock_actual + (OLD.cantidad_solicitada - NEW.cantidad_solicitada), 0)
        WHERE id_material = NEW.id_material;
    ELSE
        UPDATE materiales
        SET stock_actual = stock_actual + OLD.cantidad_solicitada
        WHERE id_material = OLD.id_material;
        UPDATE materiales
        SET stock_actual = GREATEST(stock_actual - NEW.cantidad_solicitada, 0)
        WHERE id_material = NEW.id_material;
    END IF;
END//

CREATE TRIGGER tr_stock_materiales_delete AFTER DELETE ON detalle_pedidos_materiales
FOR EACH ROW
BEGIN
    UPDATE materiales
    SET stock_actual = stock_actual + OLD.cantidad_solicitada
    WHERE id_material = OLD.id_material;
END//
DELIMITER ;

-- Crear vistas útiles para consultas complejas
CREATE VIEW vista_pedidos_completos AS
SELECT 
    p.id_pedido,
    p.numero_pedido,
    p.fecha_pedido,
    p.estado,
    p.prioridad,
    o.nombre_obra,
    o.cliente,
    CONCAT(u.nombre, ' ', u.apellido) as solicitante,
    p.total_items,
    p.valor_total,
    p.valor_disponible,
    p.valor_a_comprar,
    CASE 
        WHEN p.valor_a_comprar = 0 THEN 'Completo'
        WHEN p.valor_disponible = 0 THEN 'Sin Stock'
        ELSE 'Parcial'
    END as estado_stock
FROM pedidos_materiales p
LEFT JOIN obras o ON p.id_obra = o.id_obra
LEFT JOIN usuarios u ON p.id_solicitante = u.id_usuario;

CREATE VIEW vista_detalle_pedidos_completo AS
SELECT 
    d.id_detalle,
    d.id_pedido,
    p.numero_pedido,
    d.id_material,
    m.nombre_material,
    m.unidad_medida,
    d.cantidad_solicitada,
    d.cantidad_disponible,
    d.cantidad_faltante,
    d.cantidad_entregada,
    d.precio_unitario,
    d.subtotal,
    d.estado_item,
    d.requiere_compra,
    m.stock_actual,
    m.stock_minimo
FROM detalle_pedidos_materiales d
LEFT JOIN pedidos_materiales p ON d.id_pedido = p.id_pedido
LEFT JOIN materiales m ON d.id_material = m.id_material;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-08-2025 a las 15:37:43
-- Versión del servidor: 8.0.42
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_constructora`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones`
--

CREATE TABLE `configuraciones` (
  `id_config` int NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text,
  `descripcion` text,
  `tipo` enum('string','number','boolean','json') DEFAULT 'string',
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `configuraciones`
--

INSERT INTO `configuraciones` (`id_config`, `clave`, `valor`, `descripcion`, `tipo`, `fecha_actualizacion`) VALUES
(1, 'empresa_nombre', 'Constructora ABC', 'Nombre de la empresa', 'string', '2025-08-09 02:09:39'),
(2, 'empresa_direccion', 'Av. Principal 123, Ciudad', 'Dirección de la empresa', 'string', '2025-08-09 02:09:39'),
(3, 'empresa_telefono', '+54 11 1234-5678', 'Teléfono de la empresa', 'string', '2025-08-09 02:09:39'),
(4, 'empresa_email', 'info@constructora.com', 'Email de contacto', 'string', '2025-08-09 02:09:39'),
(5, 'sistema_version', '1.0.0', 'Versión del sistema', 'string', '2025-08-09 02:09:39'),
(6, 'backup_automatico', 'true', 'Realizar backup automático', 'boolean', '2025-08-09 02:09:39'),
(7, 'notificaciones_email', 'true', 'Enviar notificaciones por email', 'boolean', '2025-08-09 02:09:39'),
(8, 'stock_minimo_alerta', '10', 'Días de anticipación para alerta de stock mínimo', 'number', '2025-08-09 02:09:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_devolucion`
--

CREATE TABLE `detalle_devolucion` (
  `id_detalle` int NOT NULL,
  `id_devolucion` int NOT NULL,
  `id_unidad` int NOT NULL,
  `condicion_devolucion` enum('excelente','buena','regular','mala','dañada','perdida') NOT NULL,
  `observaciones_devolucion` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id_detalle` int NOT NULL,
  `id_pedido` int NOT NULL,
  `id_material` int NOT NULL,
  `cantidad_solicitada` int NOT NULL,
  `cantidad_disponible` int DEFAULT '0',
  `cantidad_faltante` int DEFAULT '0',
  `cantidad_entregada` int DEFAULT '0',
  `precio_unitario` decimal(10,2) DEFAULT '0.00',
  `subtotal` decimal(15,2) DEFAULT '0.00',
  `requiere_compra` tinyint(1) DEFAULT '0',
  `observaciones` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_prestamo`
--

CREATE TABLE `detalle_prestamo` (
  `id_detalle` int NOT NULL,
  `id_prestamo` int NOT NULL,
  `id_unidad` int NOT NULL,
  `condicion_retiro` enum('excelente','buena','regular','mala') NOT NULL,
  `observaciones_retiro` text,
  `devuelto` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `devoluciones`
--

CREATE TABLE `devoluciones` (
  `id_devolucion` int NOT NULL,
  `id_prestamo` int NOT NULL,
  `fecha_devolucion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `id_recibido_por` int NOT NULL,
  `observaciones_devolucion` text,
  `completa` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `herramientas`
--

CREATE TABLE `herramientas` (
  `id_herramienta` int NOT NULL,
  `tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `marca` varchar(100) NOT NULL,
  `modelo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `descripcion` text,
  `stock_total` int DEFAULT '0',
  `condicion_general` enum('excelente','buena','regular','mala') DEFAULT 'buena',
  `precio_referencia` decimal(10,2) DEFAULT '0.00',
  `categoria` varchar(100) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `herramientas`
--

INSERT INTO `herramientas` (`id_herramienta`, `tipo`, `marca`, `modelo`, `descripcion`, `stock_total`, `condicion_general`, `precio_referencia`, `categoria`, `fecha_creacion`) VALUES
(5, NULL, 'Black & Decker', 'Taladro BD 1200', '1200W. 220V', 2, 'buena', 0.00, NULL, '2025-08-09 02:36:23'),
(6, NULL, 'Sthill', 'Motoguadaña 12NM', '25cc, 3/4hp', 1, 'buena', 0.00, NULL, '2025-08-09 02:55:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `herramientas_pendientes`
--

CREATE TABLE `herramientas_pendientes` (
  `id` int NOT NULL,
  `nombreherramienta` varchar(255) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `herramientas_pendientes`
--

INSERT INTO `herramientas_pendientes` (`id`, `nombreherramienta`, `fecha_registro`) VALUES
(1, 'FOXTTER. MASCARA FOTOSENSIBLE/EDGE POR908/1', '2025-08-14 18:11:10'),
(2, 'HORMIGONERA', '2025-08-14 18:11:10'),
(3, 'SOPLADOR', '2025-08-14 18:11:10'),
(4, 'HIDRO LAVADORA A EXPLOSIÓN DUCATI. 1', '2025-08-14 18:11:10'),
(5, 'PISADORA A EXPLOSIÓN SORRENTO.', '2025-08-14 18:11:10'),
(6, 'SHANTUI-TOPADORA-SD13F-BULLDOZER-CHSD13', '2025-08-14 18:11:10'),
(7, 'TERMOFUSORA MOD AST2022- SERIE30583', '2025-08-14 18:11:10'),
(8, 'CARRETILLA', '2025-08-14 18:11:10'),
(9, 'TABLON METALICO/ANDAMIO', '2025-08-14 18:11:10'),
(10, 'TABLON METALICO/ANDAMIO', '2025-08-14 18:11:10'),
(11, 'ARNES PROTECCION PERSONAL', '2025-08-14 18:11:10'),
(12, 'NIVEL LASER/BOSCH/GLL3-80-C6/GREEN LASER', '2025-08-14 18:11:10'),
(13, 'AFILADOR/KLD/AFI2004', '2025-08-14 18:11:10'),
(14, 'FRESADORA/SKIL/MOD1831', '2025-08-14 18:11:10'),
(15, 'FRESADORA/SKIL/MOD1831', '2025-08-14 18:11:10'),
(16, 'ALARGUES MONOFÁSICO', '2025-08-14 18:11:10'),
(17, 'ALARGUES TRIFÁSICO', '2025-08-14 18:11:10'),
(18, 'BUSCAPOLO SICA/COD 378201/', '2025-08-14 18:11:10'),
(19, 'FREEZZER TOKIO 300L', '2025-08-14 18:11:10'),
(20, 'SOPLADOR ASPIRADOR NIWA SNW-260/26CC2T', '2025-08-14 18:11:10'),
(21, 'LIJADORA-PULIDORA/GNC/NEUMATICA C/ACCESORIOS', '2025-08-14 18:11:10'),
(22, '0001', '2025-08-14 18:11:10'),
(23, '0005', '2025-08-14 18:11:10'),
(24, 'ESCUADRA MULTIANGULO-WEMBLEY.ROJA-ALUM', '2025-08-14 18:11:10'),
(25, 'HACHA ROJA- C/CABO VIZCAINA', '2025-08-14 18:11:10'),
(26, 'NIVEL MAGNETICO ALUMINIO 24\"-AMARILLO', '2025-08-14 18:11:10'),
(27, 'MACHETE CIRIRI N° 18', '2025-08-14 18:11:10'),
(28, 'MAGUERA CRISTAL/NIVEL', '2025-08-14 18:11:10'),
(29, 'BARRETA P/CABRA', '2025-08-14 18:11:10'),
(30, 'GRINFA', '2025-08-14 18:11:10'),
(31, 'GRINFAØ10', '2025-08-14 18:11:10'),
(32, 'GRINFAØ6', '2025-08-14 18:11:10'),
(33, 'ANAFE COCINA.', '2025-08-14 18:11:10'),
(34, 'CARRETILLAS', '2025-08-14 18:11:10'),
(35, 'REVOCADORA INDUSTRIAL. REVOSOL/AMARILLA', '2025-08-14 18:11:10'),
(36, 'SOLDADORA/INVERTER/DISCOVERY275/LA-SER', '2025-08-14 18:11:10'),
(37, 'MASCARA FOTOSENSIBLE KUSHIRO/ROJA', '2025-08-14 18:11:10'),
(38, 'SOLADORA ELECTRICA/DISCOVERY 225/LA-SER/COLORADO', '2025-08-14 18:11:10'),
(39, 'HORMIGONERA TIPO CARRETILLA/NARANJA', '2025-08-14 18:11:10'),
(40, 'TENAZA ARMADOR FORJADA/GERARDI', '2025-08-14 18:11:11'),
(41, 'SOLDADORA ELECTRICA/HM300A/INVERTERASTERMAQ', '2025-08-14 18:11:11'),
(42, 'CABLES SOLDADORA ELEC/PORTA MASA/PORTA ELECTRODO', '2025-08-14 18:11:11'),
(43, 'CUCHARA ALBAÑIL', '2025-08-14 18:11:11'),
(44, 'MOTOGUAÑA STIGA/SB 520 D', '2025-08-14 18:11:11'),
(45, 'REMACHADARORA A FUELLE/LUDO', '2025-08-14 18:11:11'),
(46, 'ROTOMARTILLO 1100W 7.9J C/MALENTIN- H-DKZC03-38', '2025-08-14 18:11:11'),
(47, 'ROTOMARTILLO SDSPLUS 1300W 5J- HH-HRM004', '2025-08-14 18:11:11'),
(48, 'VIBRADOR ELEC. SORRENTO C/MANGA', '2025-08-14 18:11:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `herramientas_unidades`
--

CREATE TABLE `herramientas_unidades` (
  `id_unidad` int NOT NULL,
  `id_herramienta` int NOT NULL,
  `codigo_unico` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qr_code` varchar(255) NOT NULL,
  `estado_actual` enum('disponible','prestada','mantenimiento','perdida','dañada') DEFAULT 'disponible',
  `condicion` enum('excelente','buena','regular','mala') DEFAULT 'buena',
  `observaciones` text,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `herramientas_unidades`
--

INSERT INTO `herramientas_unidades` (`id_unidad`, `id_herramienta`, `codigo_unico`, `qr_code`, `estado_actual`, `condicion`, `observaciones`, `fecha_creacion`) VALUES
(1, 5, NULL, '123', 'disponible', 'buena', NULL, '2025-08-09 02:43:40'),
(2, 5, NULL, '124', 'disponible', 'buena', NULL, '2025-08-09 02:47:48'),
(3, 6, NULL, '125', 'disponible', 'buena', NULL, '2025-08-09 02:55:55');

--
-- Disparadores `herramientas_unidades`
--
DELIMITER $$
CREATE TRIGGER `tr_herramientas_stock_delete` AFTER DELETE ON `herramientas_unidades` FOR EACH ROW BEGIN
    UPDATE herramientas 
    SET stock_total = (
        SELECT COUNT(*) 
        FROM herramientas_unidades 
        WHERE id_herramienta = OLD.id_herramienta
    )
    WHERE id_herramienta = OLD.id_herramienta;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_herramientas_stock_insert` AFTER INSERT ON `herramientas_unidades` FOR EACH ROW BEGIN
    UPDATE herramientas 
    SET stock_total = (
        SELECT COUNT(*) 
        FROM herramientas_unidades 
        WHERE id_herramienta = NEW.id_herramienta
    )
    WHERE id_herramienta = NEW.id_herramienta;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id_log` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `descripcion` text,
  `ip_usuario` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `datos_adicionales` json DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `id_material` int NOT NULL,
  `nombre_material` varchar(200) NOT NULL,
  `descripcion` text,
  `categoria` varchar(100) DEFAULT NULL,
  `stock_actual` int DEFAULT '0',
  `stock_minimo` int DEFAULT '0',
  `stock_maximo` int DEFAULT '0',
  `precio_referencia` decimal(10,2) DEFAULT '0.00',
  `precio_compra` decimal(10,2) DEFAULT '0.00',
  `unidad_medida` varchar(50) DEFAULT 'unidad',
  `proveedor` varchar(200) DEFAULT NULL,
  `codigo_interno` varchar(50) DEFAULT NULL,
  `ubicacion_deposito` varchar(100) DEFAULT NULL,
  `estado` enum('activo','inactivo','descontinuado') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id_material`, `nombre_material`, `descripcion`, `categoria`, `stock_actual`, `stock_minimo`, `stock_maximo`, `precio_referencia`, `precio_compra`, `unidad_medida`, `proveedor`, `codigo_interno`, `ubicacion_deposito`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Cemento Portland', 'Cemento Portland tipo CPN-40', 'Cemento', 100, 20, 0, 850.00, 800.00, 'bolsa', 'Loma Negra', NULL, NULL, 'activo', '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(2, 'Arena gruesa', 'Arena gruesa para construcción', 'Áridos', 50, 10, 0, 1200.00, 1100.00, 'm3', 'Arenera Central', NULL, NULL, 'activo', '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(3, 'Ladrillos comunes', 'Ladrillos comunes 6x12x25', 'Mampostería', 5000, 1000, 0, 25.00, 22.00, 'unidad', 'Ladrillera Norte', NULL, NULL, 'activo', '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(4, 'Hierro 8mm', 'Hierro construcción 8mm x 12m', 'Hierros', 200, 50, 0, 180.00, 165.00, 'barra', 'Acindar', NULL, NULL, 'activo', '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(5, 'Hierro 10mm', 'Hierro construcción 10mm x 12m', 'Hierros', 150, 30, 0, 280.00, 260.00, 'barra', 'Acindar', NULL, NULL, 'activo', '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(6, 'Cal hidratada', 'Cal hidratada bolsa 25kg', 'Cal', 80, 15, 0, 320.00, 300.00, 'bolsa', 'Cal del Centro', NULL, NULL, 'activo', '2025-08-09 02:09:39', '2025-08-09 02:09:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int NOT NULL,
  `id_usuario` int NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('info','warning','error','success') DEFAULT 'info',
  `modulo` varchar(50) DEFAULT NULL,
  `referencia_id` int DEFAULT NULL,
  `leida` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_lectura` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `obras`
--

CREATE TABLE `obras` (
  `id_obra` int NOT NULL,
  `nombre_obra` varchar(200) NOT NULL,
  `provincia` varchar(100) NOT NULL,
  `localidad` varchar(100) NOT NULL,
  `direccion` text NOT NULL,
  `id_responsable` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `cliente` varchar(200) NOT NULL,
  `estado` enum('planificada','en_progreso','finalizada','cancelada') DEFAULT 'planificada',
  `presupuesto` decimal(15,2) DEFAULT NULL,
  `observaciones` text,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_materiales`
--

CREATE TABLE `pedidos_materiales` (
  `id_pedido` int NOT NULL,
  `numero_pedido` varchar(20) NOT NULL,
  `id_obra` int NOT NULL,
  `id_solicitante` int NOT NULL,
  `fecha_pedido` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_necesaria` date DEFAULT NULL,
  `estado` enum('pendiente','aprobado','en_camino','entregado','devuelto','cancelado') DEFAULT 'pendiente',
  `prioridad` enum('baja','media','alta','urgente') DEFAULT 'media',
  `valor_total` decimal(15,2) DEFAULT '0.00',
  `valor_disponible` decimal(15,2) DEFAULT '0.00',
  `valor_a_comprar` decimal(15,2) DEFAULT '0.00',
  `observaciones` text,
  `id_aprobado_por` int DEFAULT NULL,
  `fecha_aprobacion` timestamp NULL DEFAULT NULL,
  `id_entregado_por` int DEFAULT NULL,
  `fecha_entrega` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Disparadores `pedidos_materiales`
--
DELIMITER //
CREATE TRIGGER tr_pedidos_numero BEFORE INSERT ON `pedidos_materiales`
FOR EACH ROW
BEGIN
    DECLARE next_num INT;
    SELECT COALESCE(MAX(CAST(RIGHT(numero_pedido, 4) AS UNSIGNED)), 0) + 1
    INTO next_num
    FROM pedidos_materiales
    WHERE numero_pedido LIKE CONCAT('PED', YEAR(CURDATE()), '%');

    SET NEW.numero_pedido = CONCAT('PED', YEAR(CURDATE()), LPAD(next_num, 4, '0'));
END//
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_seguimiento_pedidos` AFTER UPDATE ON `pedidos_materiales` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `id_prestamo` int NOT NULL,
  `numero_prestamo` varchar(20) NOT NULL,
  `id_empleado` int NOT NULL,
  `id_obra` int NOT NULL,
  `fecha_retiro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_devolucion_programada` date DEFAULT NULL,
  `id_autorizado_por` int NOT NULL,
  `observaciones_retiro` text,
  `estado` enum('activo','devuelto','vencido') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Disparadores `prestamos`
--
DELIMITER $$
CREATE TRIGGER `tr_prestamos_numero` BEFORE INSERT ON `prestamos` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_prestamo, 4) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM prestamos 
    WHERE numero_prestamo LIKE CONCAT('PRE', YEAR(CURDATE()), '%');
    
    SET NEW.numero_prestamo = CONCAT('PRE', YEAR(CURDATE()), LPAD(next_num, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguimiento_pedidos`
--

CREATE TABLE `seguimiento_pedidos` (
  `id_seguimiento` int NOT NULL,
  `id_pedido` int NOT NULL,
  `estado_anterior` enum('pendiente','aprobado','en_camino','entregado','devuelto','cancelado') DEFAULT NULL,
  `estado_nuevo` enum('pendiente','aprobado','en_camino','entregado','devuelto','cancelado') NOT NULL,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text,
  `id_usuario_cambio` int NOT NULL,
  `ip_usuario` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tareas`
--

CREATE TABLE `tareas` (
  `id_tarea` int NOT NULL,
  `id_empleado` int NOT NULL,
  `id_obra` int DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text,
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_inicio` timestamp NULL DEFAULT NULL,
  `fecha_finalizacion` timestamp NULL DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','finalizada','cancelada') DEFAULT 'pendiente',
  `id_asignador` int NOT NULL,
  `prioridad` enum('baja','media','alta','urgente') DEFAULT 'media',
  `progreso` int DEFAULT '0',
  `observaciones` text,
  `tiempo_estimado` int DEFAULT NULL,
  `tiempo_real` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transportes`
--

CREATE TABLE `transportes` (
  `id_transporte` int NOT NULL,
  `marca` varchar(100) NOT NULL,
  `modelo` varchar(100) NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `año` int DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `tipo_vehiculo` enum('camion','camioneta','auto','moto','otro') DEFAULT 'camioneta',
  `capacidad_carga` decimal(8,2) DEFAULT NULL,
  `id_encargado` int DEFAULT NULL,
  `estado` enum('disponible','en_uso','mantenimiento','fuera_servicio') DEFAULT 'disponible',
  `kilometraje` int DEFAULT '0',
  `fecha_vencimiento_vtv` date DEFAULT NULL,
  `fecha_vencimiento_seguro` date DEFAULT NULL,
  `observaciones` text,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `rol` enum('administrador','responsable_obra','empleado') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text,
  `fecha_nacimiento` date DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `fecha_ultimo_acceso` timestamp NULL DEFAULT NULL,
  `intentos_login` int DEFAULT '0',
  `bloqueado_hasta` timestamp NULL DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `observaciones` text,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `email`, `contraseña`, `rol`, `estado`, `telefono`, `direccion`, `fecha_nacimiento`, `documento`, `fecha_ultimo_acceso`, `intentos_login`, `bloqueado_hasta`, `avatar`, `observaciones`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Admin', 'Sistema', 'admin@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador', 'activo', '123456789', NULL, NULL, '12345678', NULL, 0, NULL, NULL, NULL, '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(2, 'Juan', 'Pérez', 'juan.perez_@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'responsable_obra', 'activo', '987654321', '', NULL, '87654321', NULL, 0, NULL, NULL, NULL, '2025-08-09 02:09:39', '2025-08-18 11:16:46'),
(3, 'María', 'González', 'maria.gonzalez@constructora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empleado', 'activo', '456789123', NULL, NULL, '45678912', NULL, 0, NULL, NULL, NULL, '2025-08-09 02:09:39', '2025-08-09 02:09:39'),
(4, 'Matías', 'Aveiro', 'aveiromatias@gmail.com', '$2y$10$wVf6ZqSrrJ.XWZ8pb/zhcOmgcJaoFT8GnwmKdudXUh0j5jmeDu/hG', 'responsable_obra', 'activo', '3704216650', 'armada nacional 1270', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2025-08-09 02:20:26', '2025-08-09 02:20:26'),
(5, 'Armando', 'Vargas', 'actualizarcorreo@gmail.com', '$2y$10$q4Wfc21SXAOYD6Kwn/E/9uQeqWtRV7TFT6jGP4NfjAebu4I7Vge1O', 'administrador', 'activo', '3704749414', 'PADRE PATIÑO 1363', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2025-08-18 11:16:20', '2025-08-18 11:16:20');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD PRIMARY KEY (`id_config`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `detalle_devolucion`
--
ALTER TABLE `detalle_devolucion`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_devolucion` (`id_devolucion`),
  ADD KEY `id_unidad` (`id_unidad`);

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_pedido` (`id_pedido`),
  ADD KEY `idx_detalle_pedido_material` (`id_material`),
  ADD KEY `idx_detalle_pedido_compra` (`requiere_compra`);

--
-- Indices de la tabla `detalle_prestamo`
--
ALTER TABLE `detalle_prestamo`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_prestamo` (`id_prestamo`),
  ADD KEY `id_unidad` (`id_unidad`);

--
-- Indices de la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD PRIMARY KEY (`id_devolucion`),
  ADD KEY `id_prestamo` (`id_prestamo`),
  ADD KEY `id_recibido_por` (`id_recibido_por`);

--
-- Indices de la tabla `herramientas`
--
ALTER TABLE `herramientas`
  ADD PRIMARY KEY (`id_herramienta`),
  ADD KEY `idx_herramientas_categoria` (`categoria`);

--
-- Indices de la tabla `herramientas_pendientes`
--
ALTER TABLE `herramientas_pendientes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `herramientas_unidades`
--
ALTER TABLE `herramientas_unidades`
  ADD PRIMARY KEY (`id_unidad`),
  ADD UNIQUE KEY `qr_code` (`qr_code`),
  ADD UNIQUE KEY `codigo_unico` (`codigo_unico`),
  ADD KEY `id_herramienta` (`id_herramienta`),
  ADD KEY `idx_herramientas_unidades_estado` (`estado_actual`),
  ADD KEY `idx_herramientas_unidades_codigo` (`codigo_unico`);

--
-- Indices de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_logs_usuario` (`id_usuario`),
  ADD KEY `idx_logs_modulo` (`modulo`),
  ADD KEY `idx_logs_fecha` (`fecha_creacion`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id_material`),
  ADD KEY `idx_materiales_stock` (`stock_actual`),
  ADD KEY `idx_materiales_categoria` (`categoria`),
  ADD KEY `idx_materiales_estado` (`estado`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notificaciones_usuario` (`id_usuario`),
  ADD KEY `idx_notificaciones_leida` (`leida`),
  ADD KEY `idx_notificaciones_fecha` (`fecha_creacion`);

--
-- Indices de la tabla `obras`
--
ALTER TABLE `obras`
  ADD PRIMARY KEY (`id_obra`),
  ADD KEY `idx_obras_responsable` (`id_responsable`),
  ADD KEY `idx_obras_estado` (`estado`),
  ADD KEY `idx_obras_fechas` (`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `pedidos_materiales`
--
ALTER TABLE `pedidos_materiales`
  ADD PRIMARY KEY (`id_pedido`),
  ADD UNIQUE KEY `numero_pedido` (`numero_pedido`),
  ADD KEY `id_aprobado_por` (`id_aprobado_por`),
  ADD KEY `id_entregado_por` (`id_entregado_por`),
  ADD KEY `idx_pedidos_obra` (`id_obra`),
  ADD KEY `idx_pedidos_estado` (`estado`),
  ADD KEY `idx_pedidos_solicitante` (`id_solicitante`),
  ADD KEY `idx_pedidos_fecha` (`fecha_pedido`),
  ADD KEY `idx_pedidos_numero` (`numero_pedido`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD UNIQUE KEY `numero_prestamo` (`numero_prestamo`),
  ADD KEY `id_autorizado_por` (`id_autorizado_por`),
  ADD KEY `idx_prestamos_empleado` (`id_empleado`),
  ADD KEY `idx_prestamos_obra` (`id_obra`),
  ADD KEY `idx_prestamos_estado` (`estado`),
  ADD KEY `idx_prestamos_numero` (`numero_prestamo`);

--
-- Indices de la tabla `seguimiento_pedidos`
--
ALTER TABLE `seguimiento_pedidos`
  ADD PRIMARY KEY (`id_seguimiento`),
  ADD KEY `id_usuario_cambio` (`id_usuario_cambio`),
  ADD KEY `idx_seguimiento_pedido` (`id_pedido`),
  ADD KEY `idx_seguimiento_fecha` (`fecha_cambio`);

--
-- Indices de la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD PRIMARY KEY (`id_tarea`),
  ADD KEY `id_asignador` (`id_asignador`),
  ADD KEY `idx_tareas_empleado` (`id_empleado`),
  ADD KEY `idx_tareas_estado` (`estado`),
  ADD KEY `idx_tareas_obra` (`id_obra`),
  ADD KEY `idx_tareas_vencimiento` (`fecha_vencimiento`);

--
-- Indices de la tabla `transportes`
--
ALTER TABLE `transportes`
  ADD PRIMARY KEY (`id_transporte`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `idx_transportes_estado` (`estado`),
  ADD KEY `idx_transportes_encargado` (`id_encargado`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_rol` (`rol`),
  ADD KEY `idx_usuarios_estado` (`estado`),
  ADD KEY `idx_usuarios_ultimo_acceso` (`fecha_ultimo_acceso`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  MODIFY `id_config` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `detalle_devolucion`
--
ALTER TABLE `detalle_devolucion`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_prestamo`
--
ALTER TABLE `detalle_prestamo`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  MODIFY `id_devolucion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `herramientas`
--
ALTER TABLE `herramientas`
  MODIFY `id_herramienta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT de la tabla `herramientas_pendientes`
--
ALTER TABLE `herramientas_pendientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de la tabla `herramientas_unidades`
--
ALTER TABLE `herramientas_unidades`
  MODIFY `id_unidad` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id_material` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `obras`
--
ALTER TABLE `obras`
  MODIFY `id_obra` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos_materiales`
--
ALTER TABLE `pedidos_materiales`
  MODIFY `id_pedido` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `id_prestamo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `seguimiento_pedidos`
--
ALTER TABLE `seguimiento_pedidos`
  MODIFY `id_seguimiento` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tareas`
--
ALTER TABLE `tareas`
  MODIFY `id_tarea` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transportes`
--
ALTER TABLE `transportes`
  MODIFY `id_transporte` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_devolucion`
--
ALTER TABLE `detalle_devolucion`
  ADD CONSTRAINT `detalle_devolucion_ibfk_1` FOREIGN KEY (`id_devolucion`) REFERENCES `devoluciones` (`id_devolucion`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_devolucion_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `herramientas_unidades` (`id_unidad`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `detalle_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_materiales` (`id_pedido`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_pedido_ibfk_2` FOREIGN KEY (`id_material`) REFERENCES `materiales` (`id_material`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `detalle_prestamo`
--
ALTER TABLE `detalle_prestamo`
  ADD CONSTRAINT `detalle_prestamo_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id_prestamo`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_prestamo_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `herramientas_unidades` (`id_unidad`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD CONSTRAINT `devoluciones_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id_prestamo`) ON DELETE RESTRICT,
  ADD CONSTRAINT `devoluciones_ibfk_2` FOREIGN KEY (`id_recibido_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `herramientas_unidades`
--
ALTER TABLE `herramientas_unidades`
  ADD CONSTRAINT `herramientas_unidades_ibfk_1` FOREIGN KEY (`id_herramienta`) REFERENCES `herramientas` (`id_herramienta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `obras`
--
ALTER TABLE `obras`
  ADD CONSTRAINT `obras_ibfk_1` FOREIGN KEY (`id_responsable`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `pedidos_materiales`
--
ALTER TABLE `pedidos_materiales`
  ADD CONSTRAINT `pedidos_materiales_ibfk_1` FOREIGN KEY (`id_obra`) REFERENCES `obras` (`id_obra`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedidos_materiales_ibfk_2` FOREIGN KEY (`id_solicitante`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT,
  ADD CONSTRAINT `pedidos_materiales_ibfk_3` FOREIGN KEY (`id_aprobado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `pedidos_materiales_ibfk_4` FOREIGN KEY (`id_entregado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `prestamos_ibfk_1` FOREIGN KEY (`id_empleado`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT,
  ADD CONSTRAINT `prestamos_ibfk_2` FOREIGN KEY (`id_obra`) REFERENCES `obras` (`id_obra`) ON DELETE RESTRICT,
  ADD CONSTRAINT `prestamos_ibfk_3` FOREIGN KEY (`id_autorizado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `seguimiento_pedidos`
--
ALTER TABLE `seguimiento_pedidos`
  ADD CONSTRAINT `seguimiento_pedidos_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_materiales` (`id_pedido`) ON DELETE CASCADE,
  ADD CONSTRAINT `seguimiento_pedidos_ibfk_2` FOREIGN KEY (`id_usuario_cambio`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD CONSTRAINT `tareas_ibfk_1` FOREIGN KEY (`id_empleado`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `tareas_ibfk_2` FOREIGN KEY (`id_obra`) REFERENCES `obras` (`id_obra`) ON DELETE SET NULL,
  ADD CONSTRAINT `tareas_ibfk_3` FOREIGN KEY (`id_asignador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `transportes`
--
ALTER TABLE `transportes`
  ADD CONSTRAINT `transportes_ibfk_1` FOREIGN KEY (`id_encargado`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

DELIMITER //
CREATE TRIGGER tr_stock_materiales_insert AFTER INSERT ON `detalle_pedidos_materiales`
FOR EACH ROW
BEGIN
    UPDATE materiales
    SET stock_actual = GREATEST(stock_actual - NEW.cantidad_solicitada, 0)
    WHERE id_material = NEW.id_material;
END//

CREATE TRIGGER tr_stock_materiales_update AFTER UPDATE ON `detalle_pedidos_materiales`
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

CREATE TRIGGER tr_stock_materiales_delete AFTER DELETE ON `detalle_pedidos_materiales`
FOR EACH ROW
BEGIN
    UPDATE materiales
    SET stock_actual = stock_actual + OLD.cantidad_solicitada
    WHERE id_material = OLD.id_material;
END//
DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

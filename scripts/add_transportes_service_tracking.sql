-- =====================================================
-- MIGRACION: TRACKING DE SERVICIOS EN TRANSPORTES
-- Compatible con MySQL sin acceso a information_schema
-- =====================================================

DROP PROCEDURE IF EXISTS sp_migracion_transportes_service_tracking;

DELIMITER $$
CREATE PROCEDURE sp_migracion_transportes_service_tracking()
BEGIN
	/*
	  1060 = Duplicate column name
	  1061 = Duplicate key name
	  Ignoramos ambos para que el script sea re-ejecutable.
	*/
	DECLARE CONTINUE HANDLER FOR 1060 BEGIN END;
	DECLARE CONTINUE HANDLER FOR 1061 BEGIN END;

	-- 1) Columnas
	ALTER TABLE transportes
		ADD COLUMN ultimo_service_km INT NULL AFTER kilometraje;

	ALTER TABLE transportes
		ADD COLUMN ultimo_service_fecha DATE NULL AFTER ultimo_service_km;

	ALTER TABLE transportes
		ADD COLUMN proximo_service_km INT NULL AFTER ultimo_service_fecha;

	ALTER TABLE transportes
		ADD COLUMN proximo_service_fecha DATE NULL AFTER proximo_service_km;

	-- 2) Índices
	ALTER TABLE transportes
		ADD INDEX idx_proximo_service_km (proximo_service_km);

	ALTER TABLE transportes
		ADD INDEX idx_proximo_service_fecha (proximo_service_fecha);

	ALTER TABLE transportes
		ADD INDEX idx_estado_mantenimiento (estado, proximo_service_fecha);
END$$
DELIMITER ;

CALL sp_migracion_transportes_service_tracking();
DROP PROCEDURE IF EXISTS sp_migracion_transportes_service_tracking;

-- =====================================================
-- NOTAS
-- =====================================================
-- 1) ultimo_service_km y ultimo_service_fecha: se actualizan al registrar mantenimiento.
-- 2) proximo_service_km y proximo_service_fecha: cálculo automático en la app.
-- 3) Script re-ejecutable sin usar information_schema.

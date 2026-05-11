-- ============================================================
-- INTÉRMICA S.A.S - DATABASE TRIGGERS
-- Alineado con database/migrations (created_at, auditoría 014, movimientos_stock 012)
-- RN-06 y números SVC: secuencias_documento (concurrencia segura)
-- RN-02 movimientos: capa PHP (StockService); sin trigger en stock para evitar duplicados
-- ============================================================

USE intermica_db;

DROP TRIGGER IF EXISTS tr_generar_numero_servicio;
DELIMITER $$

CREATE TRIGGER tr_generar_numero_servicio
BEFORE INSERT ON servicios
FOR EACH ROW
BEGIN
  DECLARE seq INT UNSIGNED;
  INSERT INTO secuencias_documento (tipo, anio, siguiente)
  VALUES ('SVC', YEAR(NOW()), LAST_INSERT_ID(1))
  ON DUPLICATE KEY UPDATE siguiente = LAST_INSERT_ID(siguiente + 1);
  SET seq = LAST_INSERT_ID();
  SET NEW.numero_servicio = CONCAT('SVC-', YEAR(NOW()), '-', LPAD(seq, 4, '0'));
END$$

DELIMITER ;

DROP TRIGGER IF EXISTS tr_generar_numero_cuenta_cobro;
DELIMITER $$

CREATE TRIGGER tr_generar_numero_cuenta_cobro
BEFORE INSERT ON cuentas_cobro
FOR EACH ROW
BEGIN
  DECLARE seq INT UNSIGNED;
  INSERT INTO secuencias_documento (tipo, anio, siguiente)
  VALUES ('CC', YEAR(NOW()), LAST_INSERT_ID(1))
  ON DUPLICATE KEY UPDATE siguiente = LAST_INSERT_ID(siguiente + 1);
  SET seq = LAST_INSERT_ID();
  SET NEW.numero = CONCAT('CC-', YEAR(NOW()), '-', LPAD(seq, 4, '0'));
END$$

DELIMITER ;

DROP TRIGGER IF EXISTS tr_auditoria_crear_cuenta_cobro;
DELIMITER $$

CREATE TRIGGER tr_auditoria_crear_cuenta_cobro
AFTER INSERT ON cuentas_cobro
FOR EACH ROW
BEGIN
  INSERT INTO auditoria (
    usuario_id,
    entidad_tipo,
    entidad_id,
    accion,
    estado_anterior,
    estado_nuevo,
    detalles
  ) VALUES (
    NEW.usuario_creador_id,
    'CuentaCobro',
    NEW.id,
    'crear',
    NULL,
    NEW.estado,
    JSON_OBJECT(
      'numero', NEW.numero,
      'cliente_id', NEW.cliente_id,
      'total', NEW.total,
      'mensaje', CONCAT('Cuenta de cobro ', NEW.numero, ' creada')
    )
  );
END$$

DELIMITER ;

DROP TRIGGER IF EXISTS tr_auditoria_transicion_estado_servicio;
DELIMITER $$

CREATE TRIGGER tr_auditoria_transicion_estado_servicio
AFTER UPDATE ON servicios
FOR EACH ROW
BEGIN
  IF OLD.estado <> NEW.estado THEN
    INSERT INTO auditoria (
      usuario_id,
      entidad_tipo,
      entidad_id,
      accion,
      estado_anterior,
      estado_nuevo,
      detalles
    ) VALUES (
      NULL,
      'Servicio',
      NEW.id,
      'transicion_estado',
      OLD.estado,
      NEW.estado,
      JSON_OBJECT(
        'numero_servicio', NEW.numero_servicio,
        'mensaje', CONCAT('Servicio ', NEW.numero_servicio, ' cambió de ', OLD.estado, ' a ', NEW.estado)
      )
    );
  END IF;
END$$

DELIMITER ;

DROP TRIGGER IF EXISTS tr_validar_tecnico_disponible;
DELIMITER $$

CREATE TRIGGER tr_validar_tecnico_disponible
BEFORE UPDATE ON servicios
FOR EACH ROW
BEGIN
  DECLARE tecnico_bloqueado INT DEFAULT 0;

  IF NEW.tecnico_asignado_id IS NOT NULL AND NEW.fecha_programada IS NOT NULL THEN
    SELECT COUNT(*) INTO tecnico_bloqueado
    FROM bloqueos_agenda
    WHERE tecnico_id = NEW.tecnico_asignado_id
      AND NEW.fecha_programada BETWEEN fecha_inicio AND fecha_fin
      AND activo = 1;

    IF tecnico_bloqueado > 0 THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Técnico no disponible en la fecha programada';
    END IF;
  END IF;
END$$

DELIMITER ;

DROP TRIGGER IF EXISTS tr_soft_delete_usuarios;
DELIMITER $$

CREATE TRIGGER tr_soft_delete_usuarios
BEFORE DELETE ON usuarios
FOR EACH ROW
BEGIN
  UPDATE usuarios SET activo = 0 WHERE id = OLD.id;
END$$

DELIMITER ;

-- ============================================================
-- FIN DE TRIGGERS
-- ============================================================

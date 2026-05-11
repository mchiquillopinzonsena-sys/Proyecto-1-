-- ============================================================
-- INTÉRMICA S.A.S - DATABASE TRIGGERS
-- Purpose: Automatizar reglas de negocio y auditoría
-- ============================================================

USE intermica_db;

-- ============================================================
-- TRIGGER: Generar número de servicio automático
-- ============================================================
DELIMITER $$

CREATE TRIGGER tr_generar_numero_servicio
BEFORE INSERT ON servicios
FOR EACH ROW
BEGIN
  DECLARE next_id INT;
  SELECT COALESCE(MAX(CAST(SUBSTRING(numero_servicio, 9) AS UNSIGNED)), 0) + 1
  INTO next_id FROM servicios
  WHERE YEAR(fecha_creacion) = YEAR(NOW());
  
  SET NEW.numero_servicio = CONCAT('SVC-', YEAR(NOW()), '-', LPAD(next_id, 4, '0'));
END$$

DELIMITER ;

-- ============================================================
-- TRIGGER: RN-06 - Generar número de Cuenta de Cobro automático
-- ============================================================
DELIMITER $$

CREATE TRIGGER tr_generar_numero_cuenta_cobro
BEFORE INSERT ON cuentas_cobro
FOR EACH ROW
BEGIN
  DECLARE next_id INT;
  SELECT COALESCE(MAX(CAST(SUBSTRING(numero, 9) AS UNSIGNED)), 0) + 1
  INTO next_id FROM cuentas_cobro
  WHERE YEAR(fecha_creacion) = YEAR(NOW());
  
  SET NEW.numero = CONCAT('CC-', YEAR(NOW()), '-', LPAD(next_id, 4, '0'));
END$$

DELIMITER ;

-- ============================================================
-- TRIGGER: RN-16 - Auditar creación de cuentas de cobro
-- ============================================================
DELIMITER $$

CREATE TRIGGER tr_auditoria_crear_cuenta_cobro
AFTER INSERT ON cuentas_cobro
FOR EACH ROW
BEGIN
  INSERT INTO auditoria (
    tabla_afectada,
    registro_id,
    accion,
    descripcion,
    estado_nuevo,
    valores_nuevos,
    usuario_id
  ) VALUES (
    'cuentas_cobro',
    NEW.id,
    'crear',
    CONCAT('Cuenta de cobro ', NEW.numero, ' creada'),
    NEW.estado,
    JSON_OBJECT(
      'numero', NEW.numero,
      'cliente_id', NEW.cliente_id,
      'total', NEW.total,
      'estado', NEW.estado
    ),
    NEW.usuario_creador_id
  );
END$$

DELIMITER ;

-- ============================================================
-- TRIGGER: RN-16 - Auditar transiciones de estado de servicios
-- ============================================================
DELIMITER $$

CREATE TRIGGER tr_auditoria_transicion_estado_servicio
AFTER UPDATE ON servicios
FOR EACH ROW
BEGIN
  IF OLD.estado <> NEW.estado THEN
    INSERT INTO auditoria (
      tabla_afectada,
      registro_id,
      accion,
      descripcion,
      estado_anterior,
      estado_nuevo,
      usuario_id
    ) VALUES (
      'servicios',
      NEW.id,
      'transicion_estado',
      CONCAT('Servicio ', NEW.numero_servicio, ' cambió de ', OLD.estado, ' a ', NEW.estado),
      OLD.estado,
      NEW.estado,
      NULL
    );
  END IF;
END$$

DELIMITER ;

-- ============================================================
-- TRIGGER: RN-02 - Registrar movimiento automático al usar stock
-- ============================================================
DELIMITER $$

CREATE TRIGGER tr_registrar_movimiento_stock
AFTER UPDATE ON stock
FOR EACH ROW
BEGIN
  IF OLD.cantidad_disponible <> NEW.cantidad_disponible THEN
    INSERT INTO movimientos_stock (
      stock_id,
      tipo_movimiento,
      cantidad,
      cantidad_anterior,
      cantidad_posterior,
      razon
    ) VALUES (
      NEW.id,
      IF(NEW.cantidad_disponible > OLD.cantidad_disponible, 'entrada', 'salida'),
      ABS(NEW.cantidad_disponible - OLD.cantidad_disponible),
      OLD.cantidad_disponible,
      NEW.cantidad_disponible,
      'Ajuste automático'
    );
  END IF;
END$$

DELIMITER ;

-- ============================================================
-- TRIGGER: Validar disponibilidad de técnico antes de asignar
-- ============================================================
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

-- ============================================================
-- TRIGGER: Actualizar campo activo en lugar de eliminar (RN-23)
-- ============================================================
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

-- ============================================================
-- INTÉRMICA S.A.S - DATABASE VIEWS
-- Purpose: Vistas útiles para reportes y consultas frecuentes
-- ============================================================

USE intermica_db;

-- ============================================================
-- VIEW: Servicios con información de cliente y técnico
-- ============================================================
CREATE OR REPLACE VIEW v_servicios_completo AS
SELECT
  s.id,
  s.numero_servicio,
  s.estado,
  s.fecha_solicitud,
  s.fecha_programada,
  s.fecha_ejecucion,
  c.nombre_empresa AS cliente_nombre,
  c.nit AS cliente_nit,
  t.nombre_completo AS tecnico_nombre,
  t.especialidad,
  s.descripcion,
  s.ubicacion,
  s.costo_total,
  s.fecha_creacion
FROM servicios s
LEFT JOIN clientes c ON s.cliente_id = c.id
LEFT JOIN tecnicos t ON s.tecnico_asignado_id = t.id
WHERE s.activo = 1;

-- ============================================================
-- VIEW: Cuentas de cobro con detalles de cliente
-- ============================================================
CREATE OR REPLACE VIEW v_cuentas_cobro_detallado AS
SELECT
  cc.id,
  cc.numero,
  cc.estado,
  c.nombre_empresa,
  c.nit,
  c.email_contacto,
  cc.fecha_emision,
  cc.fecha_vencimiento,
  cc.fecha_pago,
  DATEDIFF(cc.fecha_vencimiento, CURDATE()) AS dias_vencimiento,
  cc.subtotal,
  cc.impuesto_iva,
  cc.descuento,
  cc.total,
  cc.metodo_pago,
  u.email AS usuario_creador,
  cc.fecha_creacion
FROM cuentas_cobro cc
LEFT JOIN clientes c ON cc.cliente_id = c.id
LEFT JOIN usuarios u ON cc.usuario_creador_id = u.id
WHERE cc.activo = 1;

-- ============================================================
-- VIEW: Resumen de cuentas vencidas
-- ============================================================
CREATE OR REPLACE VIEW v_cuentas_vencidas AS
SELECT
  cc.numero,
  c.nombre_empresa,
  c.email_contacto,
  c.telefono,
  cc.total,
  DATEDIFF(CURDATE(), cc.fecha_vencimiento) AS dias_vencidos,
  ROUND(cc.total * 0.02 * DATEDIFF(CURDATE(), cc.fecha_vencimiento) / 30, 2) AS interes_estimado
FROM cuentas_cobro cc
LEFT JOIN clientes c ON cc.cliente_id = c.id
WHERE cc.estado IN ('pendiente', 'vencida', 'parcial')
AND cc.fecha_vencimiento < CURDATE()
AND cc.activo = 1;

-- ============================================================
-- VIEW: Disponibilidad de técnicos
-- ============================================================
CREATE OR REPLACE VIEW v_tecnicos_disponibilidad AS
SELECT
  t.id,
  t.nombre_completo,
  t.especialidad,
  t.disponible,
  COUNT(s.id) AS servicios_activos,
  MAX(s.fecha_ejecucion) AS ultimo_servicio,
  CASE
    WHEN ba.id IS NOT NULL THEN CONCAT('Bloqueado hasta ', ba.fecha_fin)
    WHEN t.disponible = 0 THEN 'No disponible'
    ELSE 'Disponible'
  END AS estado_actual
FROM tecnicos t
LEFT JOIN servicios s ON t.id = s.tecnico_asignado_id AND s.estado IN ('en_proceso', 'aceptado')
LEFT JOIN bloqueos_agenda ba ON t.id = ba.tecnico_id AND CURDATE() BETWEEN ba.fecha_inicio AND ba.fecha_fin AND ba.activo = 1
WHERE t.activo = 1
GROUP BY t.id;

-- ============================================================
-- VIEW: Movimientos de stock con detalles
-- ============================================================
CREATE OR REPLACE VIEW v_movimientos_stock_detallado AS
SELECT
  ms.id,
  s.codigo_interno,
  s.nombre_producto,
  ms.tipo_movimiento,
  ms.cantidad,
  ms.cantidad_anterior,
  ms.cantidad_posterior,
  s.cantidad_minima,
  CASE WHEN ms.cantidad_posterior < s.cantidad_minima THEN 'ALERTA' ELSE 'OK' END AS estado_inventario,
  ms.referencia_documento,
  ms.razon,
  u.email AS usuario,
  ms.fecha_movimiento
FROM movimientos_stock ms
LEFT JOIN stock s ON ms.stock_id = s.id
LEFT JOIN usuarios u ON ms.usuario_id = u.id
ORDER BY ms.fecha_movimiento DESC;

-- ============================================================
-- VIEW: Resumen de auditoría por usuario
-- ============================================================
CREATE OR REPLACE VIEW v_auditoria_resumen AS
SELECT
  DATE(a.fecha_registro) AS fecha,
  u.nombre AS usuario,
  a.tabla_afectada,
  a.accion,
  COUNT(*) AS cantidad_acciones
FROM auditoria a
LEFT JOIN usuarios u ON a.usuario_id = u.id
GROUP BY DATE(a.fecha_registro), u.id, a.tabla_afectada, a.accion
ORDER BY a.fecha_registro DESC;

-- ============================================================
-- FIN DE VISTAS
-- ============================================================

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
  c.nombre_empresa AS cliente_nombre,
  c.nit AS cliente_nit,
  ut.nombre_completo AS tecnico_nombre,
  t.especialidad,
  s.descripcion,
  s.ubicacion,
  s.valor_estimado,
  s.valor_final,
  s.created_at
FROM servicios s
LEFT JOIN clientes c ON s.cliente_id = c.id
LEFT JOIN tecnicos t ON s.tecnico_asignado_id = t.id
LEFT JOIN usuarios ut ON t.usuario_id = ut.id
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
  uc.email AS email_cliente,
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
  cc.created_at
FROM cuentas_cobro cc
LEFT JOIN clientes c ON cc.cliente_id = c.id
LEFT JOIN usuarios uc ON c.usuario_id = uc.id
LEFT JOIN usuarios u ON cc.usuario_creador_id = u.id
WHERE cc.activo = 1;

-- ============================================================
-- VIEW: Resumen de cuentas vencidas
-- ============================================================
CREATE OR REPLACE VIEW v_cuentas_vencidas AS
SELECT
  cc.numero,
  c.nombre_empresa,
  uc.email AS email_cliente,
  cc.total,
  DATEDIFF(CURDATE(), cc.fecha_vencimiento) AS dias_vencidos,
  ROUND(cc.total * 0.02 * DATEDIFF(CURDATE(), cc.fecha_vencimiento) / 30, 2) AS interes_estimado
FROM cuentas_cobro cc
LEFT JOIN clientes c ON cc.cliente_id = c.id
LEFT JOIN usuarios uc ON c.usuario_id = uc.id
WHERE cc.estado IN ('pendiente', 'vencida', 'parcial')
AND cc.fecha_vencimiento < CURDATE()
AND cc.activo = 1;

-- ============================================================
-- VIEW: Disponibilidad de técnicos
-- ============================================================
CREATE OR REPLACE VIEW v_tecnicos_disponibilidad AS
SELECT
  t.id,
  u.nombre_completo,
  t.especialidad,
  t.disponible,
  (SELECT COUNT(*) FROM servicios s
   WHERE s.tecnico_asignado_id = t.id AND s.activo = 1
     AND s.estado IN ('en_proceso', 'programado')) AS servicios_activos,
  (SELECT MAX(s2.fecha_programada) FROM servicios s2
   WHERE s2.tecnico_asignado_id = t.id AND s2.activo = 1) AS ultima_fecha_programada,
  CASE
    WHEN EXISTS (
      SELECT 1 FROM bloqueos_agenda ba
      WHERE ba.tecnico_id = t.id AND ba.activo = 1
        AND CURDATE() BETWEEN ba.fecha_inicio AND ba.fecha_fin
    ) THEN 'Bloqueado en la fecha actual'
    WHEN t.disponible = 0 THEN 'No disponible'
    ELSE 'Disponible'
  END AS estado_actual
FROM tecnicos t
LEFT JOIN usuarios u ON t.usuario_id = u.id
WHERE t.activo = 1;

-- ============================================================
-- VIEW: Movimientos de stock con detalles
-- ============================================================
CREATE OR REPLACE VIEW v_movimientos_stock_detallado AS
SELECT
  ms.id,
  s.codigo_articulo,
  s.nombre_articulo,
  ms.tipo_movimiento,
  ms.cantidad,
  ms.cantidad_anterior,
  ms.cantidad_nueva,
  s.cantidad_minima,
  CASE WHEN ms.cantidad_nueva < s.cantidad_minima THEN 'ALERTA' ELSE 'OK' END AS estado_inventario,
  ms.razon,
  u.email AS usuario,
  ms.created_at
FROM movimientos_stock ms
LEFT JOIN stock s ON ms.stock_id = s.id
LEFT JOIN usuarios u ON ms.usuario_id = u.id;

-- ============================================================
-- VIEW: Resumen de auditoría por usuario
-- ============================================================
CREATE OR REPLACE VIEW v_auditoria_resumen AS
SELECT
  DATE(a.created_at) AS fecha,
  u.nombre_completo AS usuario,
  a.entidad_tipo,
  a.accion,
  COUNT(*) AS cantidad_acciones
FROM auditoria a
LEFT JOIN usuarios u ON a.usuario_id = u.id
GROUP BY DATE(a.created_at), u.id, a.entidad_tipo, a.accion
ORDER BY MAX(a.created_at) DESC;

-- ============================================================
-- FIN DE VISTAS
-- ============================================================

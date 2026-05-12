-- ============================================================
-- Tabla AUDITORIA - Auditoría granular de cambios
-- Date: 2026-05-12
-- Purpose: Registro detallado de todas las acciones de usuarios
-- ============================================================

CREATE TABLE IF NOT EXISTS auditoria_cambios (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  accion VARCHAR(50) NOT NULL COMMENT 'crear, actualizar, eliminar, aprobar, rechazar',
  entidad_tipo VARCHAR(50) NOT NULL COMMENT 'servicios, usuarios, cuentas_cobro, etc.',
  entidad_id INT NOT NULL,
  valores_anteriores JSON COMMENT 'Estado previo del registro',
  valores_nuevos JSON COMMENT 'Estado nuevo del registro',
  detalles TEXT COMMENT 'Explicación adicional del cambio',
  ip_address VARCHAR(45),
  user_agent TEXT,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_entidad_tipo_id (entidad_tipo, entidad_id),
  INDEX idx_accion (accion),
  INDEX idx_fecha_registro (fecha_registro),
  INDEX idx_composite (usuario_id, entidad_tipo, fecha_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoría granular de cambios en el sistema';

-- Vista para auditoría por usuario (últimas 100 acciones)
CREATE OR REPLACE VIEW vw_auditoria_usuario AS
SELECT
  ac.id,
  ac.usuario_id,
  u.nombre_completo,
  ac.accion,
  ac.entidad_tipo,
  ac.entidad_id,
  ac.detalles,
  ac.ip_address,
  ac.fecha_registro,
  TIMESTAMPDIFF(MINUTE, ac.fecha_registro, NOW()) as hace_minutos
FROM auditoria_cambios ac
JOIN usuarios u ON u.id = ac.usuario_id
WHERE ac.fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY ac.fecha_registro DESC
LIMIT 100;

-- Vista para auditoría por entidad
CREATE OR REPLACE VIEW vw_auditoria_entidad AS
SELECT
  ac.id,
  ac.entidad_tipo,
  ac.entidad_id,
  ac.accion,
  u.nombre_completo as usuario_nombre,
  ac.valores_anteriores,
  ac.valores_nuevos,
  ac.fecha_registro
FROM auditoria_cambios ac
JOIN usuarios u ON u.id = ac.usuario_id
ORDER BY ac.fecha_registro DESC;

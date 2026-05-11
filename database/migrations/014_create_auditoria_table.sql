-- Tabla AUDITORIA (RN-16: Logs de auditoría para transiciones de estados)
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    entidad_tipo VARCHAR(100) NOT NULL COMMENT 'Usuario, Servicio, CuentaCobro, etc.',
    entidad_id INT NOT NULL,
    accion VARCHAR(50) NOT NULL COMMENT 'crear, actualizar, eliminar, transicion_estado',
    estado_anterior VARCHAR(255),
    estado_nuevo VARCHAR(255),
    detalles JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_entidad (entidad_tipo, entidad_id),
    INDEX idx_accion (accion),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de auditoría - RN-16';

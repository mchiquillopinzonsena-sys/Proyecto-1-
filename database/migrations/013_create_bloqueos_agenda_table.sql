-- Tabla BLOQUEOS_AGENDA (RN-13/14: Validación de agenda para técnicos)
CREATE TABLE IF NOT EXISTS bloqueos_agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    hora_inicio TIME,
    hora_fin TIME,
    tipo_bloqueo ENUM('no_disponible', 'vacaciones', 'mantenimiento', 'capacitacion') NOT NULL,
    razon VARCHAR(255),
    aprobado_por INT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE RESTRICT,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_tecnico_id (tecnico_id),
    INDEX idx_fecha_inicio (fecha_inicio),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de bloqueos de agenda - RN-13/14';

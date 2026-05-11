-- Tabla TECNICOS (especialización de USUARIOS)
CREATE TABLE IF NOT EXISTS tecnicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    especialidad VARCHAR(100),
    numero_empleado VARCHAR(50) UNIQUE NOT NULL,
    fecha_ingreso DATE NOT NULL,
    certificaciones TEXT,
    disponible TINYINT(1) DEFAULT 1,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_disponible (disponible),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de técnicos especializados';

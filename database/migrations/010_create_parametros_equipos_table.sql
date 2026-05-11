-- Tabla PARAMETROS_EQUIPOS (Equipos y sus parámetros de cotización)
CREATE TABLE IF NOT EXISTS parametros_equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo VARCHAR(255) NOT NULL,
    tipo_equipo VARCHAR(100),
    valor_inspeccion_base DECIMAL(15,2) NOT NULL,
    tiempo_inspeccion_minutos INT,
    complejidad VARCHAR(50),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre_equipo (nombre_equipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de equipos y parámetros de inspección';

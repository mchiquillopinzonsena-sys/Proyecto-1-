-- Secuencias atómicas por tipo y año (RN-06 CC-YYYY-XXXX y números SVC-YYYY-XXXX)
CREATE TABLE IF NOT EXISTS secuencias_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(10) NOT NULL COMMENT 'CC o SVC',
    anio SMALLINT NOT NULL,
    siguiente INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tipo_anio (tipo, anio),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Secuencias documentales por año (concurrencia segura)';

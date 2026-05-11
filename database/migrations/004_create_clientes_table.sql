-- Tabla CLIENTES (especialización de USUARIOS)
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    nombre_empresa VARCHAR(255) NOT NULL,
    nit VARCHAR(50) UNIQUE NOT NULL,
    sector_industrial VARCHAR(100),
    ciudad VARCHAR(100),
    departamento VARCHAR(100),
    pais VARCHAR(100) DEFAULT 'Colombia',
    activo TINYINT(1) DEFAULT 1,
    credito_disponible DECIMAL(15,2) DEFAULT 0,
    dias_credito INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_nit (nit),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de clientes empresariales';

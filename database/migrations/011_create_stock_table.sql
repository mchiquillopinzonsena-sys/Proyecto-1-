-- Tabla STOCK (RN-02: Actualización automática de stock)
CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_articulo VARCHAR(100) UNIQUE NOT NULL,
    nombre_articulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    cantidad_disponible INT NOT NULL DEFAULT 0,
    cantidad_minima INT DEFAULT 0,
    ubicacion_almacen VARCHAR(255),
    precio_unitario DECIMAL(15,2),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo_articulo (codigo_articulo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de stock de artículos - RN-02';

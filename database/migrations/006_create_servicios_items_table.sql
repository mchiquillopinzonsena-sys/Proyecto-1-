-- Tabla SERVICIOS_ITEMS (Detalles de servicios)
CREATE TABLE IF NOT EXISTS servicios_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servicio_id INT NOT NULL,
    numero_item INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
    unidad VARCHAR(50),
    valor_unitario DECIMAL(15,2) NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE RESTRICT,
    INDEX idx_servicio_id (servicio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de items detallados de servicios';

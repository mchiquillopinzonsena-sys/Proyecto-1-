-- Tabla CUENTAS_ITEMS (Items de cuentas de cobro)
CREATE TABLE IF NOT EXISTS cuentas_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_cobro_id INT NOT NULL,
    numero_item INT NOT NULL,
    servicio_id INT,
    descripcion VARCHAR(255) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
    unidad VARCHAR(50),
    valor_unitario DECIMAL(15,2) NOT NULL,
    impuesto DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_cobro_id) REFERENCES cuentas_cobro(id) ON DELETE RESTRICT,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
    INDEX idx_cuenta_cobro_id (cuenta_cobro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de items de cuentas de cobro';

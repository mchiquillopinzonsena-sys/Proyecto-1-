-- Tabla DOCUMENTOS (Almacenamiento de PDFs y QR)
CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento VARCHAR(100) NOT NULL COMMENT 'cuenta_cobro, reporte_termografico',
    entidad_tipo VARCHAR(100),
    entidad_id INT,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_contenido VARCHAR(100),
    tamano_bytes INT,
    qr_codigo VARCHAR(500),
    hash_documento VARCHAR(255),
    generado_por INT,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_tipo_documento (tipo_documento),
    INDEX idx_entidad (entidad_tipo, entidad_id),
    INDEX idx_hash_documento (hash_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de documentos generados (PDFs, QR)';

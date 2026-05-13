<?php
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/database/Database.php';

try {
    $pdo = \Database\Database::getInstance();
    $sql = "CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensaje TEXT NOT NULL,
        leida TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_leida (leida)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones del sistema';";
    
    $pdo->exec($sql);
    echo "Tabla notificaciones creada exitosamente.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

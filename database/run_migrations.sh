#!/bin/bash
# Script para ejecutar todas las migraciones de BD
# Ejecutar: bash database/run_migrations.sh

set -e

echo "=========================================="
echo "🗄️  INICIANDO MIGRACIONES BD - Intérmica"
echo "=========================================="

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-intermica_db}"
MIGRATIONS_DIR="$(dirname "$0")/migrations"

# Crear variable de conexión
if [ -z "$DB_PASS" ]; then
    MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER"
else
    MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER -p$DB_PASS"
fi

echo "📦 Conectando a: $DB_HOST / $DB_NAME"

# Crear tabla de control de migraciones (si no existe)
$MYSQL_CMD << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE $DB_NAME;

CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF

echo "✅ Tabla de migraciones creada/verificada"

# Ejecutar todas las migraciones
BATCH=1
for migration_file in $(ls $MIGRATIONS_DIR/*.sql | sort); do
    migration_name=$(basename "$migration_file")

    # Verificar si ya fue ejecutada
    EXECUTED=$($MYSQL_CMD $DB_NAME -N -e "SELECT COUNT(*) FROM migrations WHERE migration = '$migration_name';" 2>/dev/null || echo "0")

    if [ "$EXECUTED" -eq 0 ]; then
        echo "📄 Ejecutando: $migration_name"

        # Ejecutar migración
        $MYSQL_CMD $DB_NAME < "$migration_file"

        # Registrar en tabla de migraciones
        $MYSQL_CMD $DB_NAME -e "INSERT INTO migrations (migration, batch) VALUES ('$migration_name', $BATCH);"

        echo "   ✅ Completada"
    else
        echo "⏭️  Saltando: $migration_name (ya ejecutada)"
    fi
done

echo ""
echo "=========================================="
echo "✅ TODAS LAS MIGRACIONES COMPLETADAS"
echo "=========================================="

# Verificar tablas creadas
echo ""
echo "📊 Tablas en BD:"
$MYSQL_CMD $DB_NAME -e "SHOW TABLES;" | tail -n +2 | nl

echo ""
echo "📋 Migraciones ejecutadas:"
$MYSQL_CMD $DB_NAME -e "SELECT migration, executed_at FROM migrations ORDER BY batch DESC;" | head -20

echo ""
echo "✨ Base de datos lista para producción"

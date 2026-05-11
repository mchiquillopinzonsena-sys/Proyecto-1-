# 📊 Migraciones de Base de Datos - Intérmica S.A.S

## Estructura de Archivos

```
migrations/
├── 001_create_usuarios_table.sql
├── 002_create_sesiones_jwt_table.sql
├── 003_create_tecnicos_table.sql
├── 004_create_clientes_table.sql
├── 005_create_servicios_table.sql
├── 006_create_servicios_items_table.sql
├── 007_create_cuentas_cobro_table.sql
├── 008_create_cuentas_items_table.sql
├── 009_create_parametros_cotizador_table.sql
├── 010_create_parametros_equipos_table.sql
├── 011_create_stock_table.sql
├── 012_create_movimientos_stock_table.sql
├── 013_create_bloqueos_agenda_table.sql
├── 014_create_auditoria_table.sql
├── 015_create_configuracion_empresa_table.sql
├── 016_create_documentos_table.sql
├── 017_alter_servicios_items_add_stock.sql
├── 018_create_secuencias_documento.sql
├── 019_alter_servicios_idempotencia_rn.sql
└── README_MIGRATIONS.md
```

## 🚀 Instrucciones de Ejecución

### Opción 1: Ejecutar schema.sql completo
```bash
mysql -u root -p < database/sql/schema.sql
```

### Opción 2: Ejecutar migraciones individuales en orden
```bash
mysql -u root -p intermica_db < database/migrations/001_create_usuarios_table.sql
mysql -u root -p intermica_db < database/migrations/002_create_sesiones_jwt_table.sql
# ... continuar con el resto
```

### Opción 3: Desde PHP (recomendado para desarrollo)
```php
<?php
require_once 'backend/app/Database.php';

$migrator = new DatabaseMigrator();
$migrator->runAllMigrations();
?>
```

## 📋 Orden de Ejecución de Migraciones

1. **usuarios** - Base de usuarios del sistema
2. **sesiones_jwt** - Gestión de sesiones
3. **tecnicos** - Técnicos disponibles
4. **clientes** - Información de clientes
5. **servicios** - Servicios solicitados
6. **servicios_items** - Detalles de servicios
7. **cuentas_cobro** - Facturas y cuentas
8. **parametros_cotizador** - Parámetros dinámicos
9. **parametros_equipos** - Catálogo de equipos
10. **stock** - Inventario
11. **movimientos_stock** - Auditoría de movimientos (RN-02)
12. **bloqueos_agenda** - Validación técnicos (RN-13/14)
13. **auditoria** - Log de cambios (RN-16)
14. **configuracion_empresa** - Configuración global

## 🔒 Restricciones de Integridad

Todas las relaciones críticas utilizan **ON DELETE RESTRICT** para proteger la integridad histórica (RN-25):

```sql
FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
```

## 📝 Normalización a 5NF

- ✅ **1NF**: Atributos atómicos
- ✅ **2NF**: Dependencia funcional completa
- ✅ **3NF**: Sin dependencias transitivas
- ✅ **4NF**: Sin dependencias multivaluadas
- ✅ **5NF**: Sin anomalías de join

## 🗑️ Borrado Lógico (RN-23)

Todas las tablas incluyen columna `activo TINYINT(1)` en lugar de eliminaciones físicas:

```sql
SELECT * FROM usuarios WHERE activo = 1; -- Solo registros activos
```

## 🔍 Índices para Performance

Cada tabla incluye índices en:
- Columnas de búsqueda frecuente (email, NIT, estado)
- Claves foráneas
- Rangos de fechas
- Combinaciones de columnas muy consultadas

## 🔄 Triggers Automáticos

Ver `database/sql/triggers.sql` (ejecutar tras migraciones **018**). Incluye:
- Generación atómica de números **SVC-** y **CC-** vía `secuencias_documento`
- Validación de disponibilidad de técnicos (bloqueos de agenda)
- Auditoría en tabla `auditoria` (esquema migración 014)
- **RN-02:** el descuento de stock y `movimientos_stock` los aplica la API (`StockService`), no un trigger en `stock`, para evitar duplicados y registrar `servicio_id`

## 📊 Vistas Útiles

Ver `database/sql/views.sql` para:
- Reportes de servicios completos
- Cuentas vencidas
- Disponibilidad de técnicos
- Movimientos de inventario

## 🛠️ Mantenimiento

### Backup
```bash
mysqldump -u root -p intermica_db > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore
```bash
mysql -u root -p intermica_db < backup_20260511_204746.sql
```

### Verificar integridad
```sql
SELECT COUNT(*) as total_usuarios FROM usuarios;
SELECT COUNT(*) as total_cuentas FROM cuentas_cobro;
```

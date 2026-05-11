# 📚 Migraciones de Base de Datos - Intérmica S.A.S

## Descripción

Este directorio contiene todas las migraciones SQL necesarias para crear la estructura de base de datos de la plataforma Intérmica S.A.S.

## Orden de Ejecución

Las migraciones deben ejecutarse en orden numérico ascendente:

1. **001_create_usuarios_table.sql** - Tabla base de usuarios
2. **002_create_sesiones_jwt_table.sql** - Gestión de sesiones JWT
3. **003_create_tecnicos_table.sql** - Tabla de técnicos
4. **004_create_clientes_table.sql** - Tabla de clientes
5. **005_create_servicios_table.sql** - Tabla de servicios
6. **006_create_servicios_items_table.sql** - Items de servicios
7. **007_create_cuentas_cobro_table.sql** - Cuentas de cobro (RN-06)
8. **008_create_cuentas_items_table.sql** - Items de cuentas
9. **009_create_parametros_cotizador_table.sql** - Parámetros cotizador
10. **010_create_parametros_equipos_table.sql** - Equipos para cotización
11. **011_create_stock_table.sql** - Stock de artículos (RN-02)
12. **012_create_movimientos_stock_table.sql** - Movimientos de stock (RN-02)
13. **013_create_bloqueos_agenda_table.sql** - Bloqueos de agenda (RN-13/14)
14. **014_create_auditoria_table.sql** - Logs de auditoría (RN-16)
15. **015_create_configuracion_empresa_table.sql** - Configuración global
16. **016_create_documentos_table.sql** - Almacenamiento de documentos

## Instalación Manual

```bash
# Acceder a MySQL
mysql -u root -p

# Crear base de datos
CREATE DATABASE IF NOT EXISTS intermica_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Usar la base de datos
USE intermica_db;

# Ejecutar migraciones en orden (copiar contenido de cada archivo)
source 001_create_usuarios_table.sql;
source 002_create_sesiones_jwt_table.sql;
-- ... continuar con las demás
```

## Instalación Automática con Script

```bash
# Desde la carpeta backend/
php database/seeds/MigrationRunner.php
```

## Principios de Diseño

### ✅ 5 Formas Normales (5NF)
- Todas las tablas están normalizadas
- No hay redundancia de datos
- Cada entidad tiene responsabilidad única

### 🔒 Integridad Referencial
- Uso de `ON DELETE RESTRICT` (RN-25) para proteger datos históricos
- Relaciones explícitas entre tablas
- Claves foráneas validadas

### 📝 Borrado Lógico (RN-23)
- Campo `activo` TINYINT(1) en lugar de eliminaciones físicas
- Auditoría completa de cambios
- Recuperación de datos posible

### 📊 Indices para Optimización
- Indices en claves foráneas
- Indices en campos de búsqueda frecuente
- Indices en campos de filtrado por estado y fecha

### 🕐 Timestamps
- `created_at` para fecha de creación
- `updated_at` para fecha de actualización
- Auditoría automática mediante triggers

## Tablas Principales

### USUARIOS
- Gestión centralizada de usuarios
- Roles: admin, tecnico, cliente
- Bloqueo de intentos fallidos de login

### SESIONES_JWT
- Una sesión por dispositivo/navegador
- Tokens con expiración
- Cierre manual de sesiones

### TECNICOS
- Especialización de usuarios
- Disponibilidad y certificaciones
- Asignación de servicios

### CLIENTES
- Empresas cliente
- Crédito disponible
- Días de pago

### SERVICIOS
- Servicios termográficos
- Estados: pendiente, programado, en_proceso, completado, cancelado
- Asignación de técnicos

### CUENTAS_COBRO (RN-06)
- Formato automático: CC-YYYY-XXXX
- Estados: pendiente, parcial, pagada, vencida, cancelada
- Generación automática desde servicios

### STOCK (RN-02)
- Control de inventario
- Movimientos auditados
- Cantidades mínimas

### BLOQUEOS_AGENDA (RN-13/14)
- Validación de disponibilidad técnicos
- Tipos: no_disponible, vacaciones, mantenimiento, capacitacion

### AUDITORIA (RN-16)
- Registro de todas las transiciones de estado
- IP y user agent
- Antes/después de cambios

## Relaciones Críticas

```
usuarios (1) --- (N) sesiones_jwt
usuarios (1) --- (1) tecnicos
usuarios (1) --- (1) clientes
clientes (1) --- (N) servicios
tecnicos (1) --- (N) servicios
servicios (1) --- (N) servicios_items
clientes (1) --- (N) cuentas_cobro
cuentas_cobro (1) --- (N) cuentas_items
tecnicos (1) --- (N) bloqueos_agenda
stock (1) --- (N) movimientos_stock
```

## Seguridad

- ✅ Encriptación de contraseñas con bcrypt
- ✅ JWT con expiración temporal
- ✅ Auditoría completa
- ✅ Restricción de eliminaciones (ON DELETE RESTRICT)
- ✅ Borrado lógico para histórico

## Mantenimiento

```sql
-- Limpiar sesiones expiradas
DELETE FROM sesiones_jwt WHERE fecha_expiracion < NOW() AND activa = 0;

-- Resumen de usuarios por rol
SELECT rol, COUNT(*) FROM usuarios WHERE activo = 1 GROUP BY rol;

-- Auditoría últimos cambios
SELECT * FROM auditoria ORDER BY created_at DESC LIMIT 100;
```

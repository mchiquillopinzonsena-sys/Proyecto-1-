-- ============================================================
-- MIGRATION: Create dynamic RBAC tables
-- Date: 2026-05-12
-- Purpose: Replace hardcoded RBAC with database-driven permissions
-- ============================================================

-- TABLE: PERMISOS (granular permissions)
CREATE TABLE IF NOT EXISTS permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g., servicios.crear, servicios.leer, usuarios.eliminar',
  descripcion TEXT,
  recurso VARCHAR(50) NOT NULL COMMENT 'e.g., servicios, usuarios, cuentas',
  accion VARCHAR(50) NOT NULL COMMENT 'crear, leer, actualizar, eliminar, aprobar',
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recurso_accion (recurso, accion),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de permisos granulares del sistema';

-- TABLE: ROLES (grupos de permisos)
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE COMMENT 'admin, tecnico, cliente, gerente, etc.',
  descripcion TEXT,
  activo TINYINT(1) DEFAULT 1,
  sistema TINYINT(1) DEFAULT 0 COMMENT '1 = roles built-in del sistema',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activo (activo),
  INDEX idx_sistema (sistema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Grupos de permisos (roles) del sistema';

-- TABLE: ROL_PERMISOS (junction table)
CREATE TABLE IF NOT EXISTS rol_permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rol_id INT NOT NULL,
  permiso_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
  UNIQUE KEY uk_rol_permiso (rol_id, permiso_id),
  INDEX idx_rol_id (rol_id),
  INDEX idx_permiso_id (permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Relación entre roles y permisos';

-- TABLE: USUARIO_ROLES (asignación flexible de roles a usuarios)
CREATE TABLE IF NOT EXISTS usuario_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  asignado_por INT,
  razon TEXT,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
  FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  UNIQUE KEY uk_usuario_rol (usuario_id, rol_id),
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_rol_id (rol_id),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Asignación de roles a usuarios (permite múltiples roles por usuario)';

-- ============================================================
-- Seed: Permisos básicos del sistema
-- ============================================================

INSERT INTO permisos (nombre, descripcion, recurso, accion, activo) VALUES
-- Servicios
('servicios.crear', 'Crear servicios', 'servicios', 'crear', 1),
('servicios.leer', 'Ver servicios', 'servicios', 'leer', 1),
('servicios.actualizar', 'Actualizar servicios', 'servicios', 'actualizar', 1),
('servicios.eliminar', 'Eliminar servicios', 'servicios', 'eliminar', 1),
('servicios.cambiar_estado', 'Cambiar estado de servicios', 'servicios', 'cambiar_estado', 1),

-- Usuarios
('usuarios.crear', 'Crear usuarios', 'usuarios', 'crear', 1),
('usuarios.leer', 'Ver usuarios', 'usuarios', 'leer', 1),
('usuarios.actualizar', 'Actualizar usuarios', 'usuarios', 'actualizar', 1),
('usuarios.eliminar', 'Eliminar usuarios', 'usuarios', 'eliminar', 1),

-- Cuentas de Cobro
('cuentas.crear', 'Crear cuentas de cobro', 'cuentas', 'crear', 1),
('cuentas.leer', 'Ver cuentas de cobro', 'cuentas', 'leer', 1),
('cuentas.actualizar', 'Actualizar cuentas de cobro', 'cuentas', 'actualizar', 1),
('cuentas.pagar', 'Registrar pagos', 'cuentas', 'pagar', 1),

-- Stock
('stock.leer', 'Ver stock', 'stock', 'leer', 1),
('stock.actualizar', 'Actualizar stock', 'stock', 'actualizar', 1),

-- Reportes
('reportes.leer', 'Ver reportes', 'reportes', 'leer', 1),
('reportes.exportar', 'Exportar reportes', 'reportes', 'exportar', 1),

-- Admin
('admin.usuarios', 'Administrar usuarios', 'admin', 'usuarios', 1),
('admin.configuracion', 'Administrar configuración', 'admin', 'configuracion', 1);

-- ============================================================
-- Seed: Roles del sistema
-- ============================================================

INSERT INTO roles (nombre, descripcion, activo, sistema) VALUES
('admin', 'Administrador del sistema con acceso total', 1, 1),
('tecnico', 'Técnico especializado en servicios', 1, 1),
('cliente', 'Cliente del sistema', 1, 1),
('gerente', 'Gerente con reportes y aprobaciones', 1, 1);

-- ============================================================
-- Seed: Asignar permisos a roles
-- ============================================================

-- Admin: todos los permisos
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'admin' AND p.activo = 1;

-- Técnico: servicios, stock
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'tecnico'
AND p.nombre IN ('servicios.leer', 'servicios.actualizar', 'servicios.cambiar_estado', 'stock.leer');

-- Cliente: ver sus servicios y cuentas
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'cliente'
AND p.nombre IN ('servicios.leer', 'cuentas.leer');

-- Gerente: todo excepto delete y admin
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'gerente'
AND p.nombre NOT IN ('admin.usuarios', 'admin.configuracion', 'usuarios.eliminar', 'servicios.eliminar')
AND p.activo = 1;

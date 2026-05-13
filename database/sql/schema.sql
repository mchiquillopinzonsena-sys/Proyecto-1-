-- ============================================================
-- INTÉRMICA S.A.S - DATABASE SCHEMA
-- Version: 2.0
-- Created: 2026-05-11
-- Normalized to 5NF with integrity constraints
-- Nota: database/migrations/ es la fuente canónica; este archivo se mantiene alineado para instalación monolítica.
-- ============================================================

CREATE DATABASE IF NOT EXISTS intermica_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE intermica_db;

-- ============================================================
-- TABLE: USUARIOS
-- Purpose: Usuarios del sistema (admin, técnico, cliente)
-- ============================================================
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  nombre_completo VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin', 'tecnico', 'cliente') NOT NULL DEFAULT 'cliente',
  telefono VARCHAR(20),
  direccion TEXT,
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_ultimo_login TIMESTAMP NULL,
  intentos_fallidos INT DEFAULT 0,
  bloqueado_hasta TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_rol (rol),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de usuarios del sistema';

-- ============================================================
-- TABLE: SESIONES_JWT
-- Purpose: Gestión de sesiones y tokens JWT
-- ============================================================
CREATE TABLE sesiones_jwt (
  id INT PRIMARY KEY AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  ip_address VARCHAR(45),
  user_agent TEXT,
  fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_expiracion DATETIME NOT NULL,
  activa TINYINT(1) DEFAULT 1,
  fecha_cierre TIMESTAMP NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_activa (activa),
  INDEX idx_fecha_expiracion (fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: CLIENTES
-- Purpose: Información detallada de clientes
-- ============================================================
CREATE TABLE clientes (
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

-- ============================================================
-- TABLE: TECNICOS
-- Purpose: Información de técnicos disponibles
-- ============================================================
CREATE TABLE tecnicos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  especialidad VARCHAR(100),
  numero_empleado VARCHAR(50) UNIQUE NOT NULL,
  fecha_ingreso DATE NOT NULL,
  certificaciones TEXT,
  disponible TINYINT(1) DEFAULT 1,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_disponible (disponible),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de técnicos especializados';

-- ============================================================
-- TABLE: BLOQUEOS_AGENDA
-- Purpose: RN-13/14 - Validación y bloqueo de agenda técnico
-- ============================================================
CREATE TABLE bloqueos_agenda (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tecnico_id INT NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  hora_inicio TIME,
  hora_fin TIME,
  tipo_bloqueo ENUM('no_disponible', 'vacaciones', 'mantenimiento', 'capacitacion') NOT NULL,
  razon VARCHAR(255),
  aprobado_por INT,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE RESTRICT,
  FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_tecnico_id (tecnico_id),
  INDEX idx_fecha_inicio (fecha_inicio),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de bloqueos de agenda - RN-13/14';

-- ============================================================
-- TABLE: STOCK (debe existir antes de servicios_items — RN-02)
-- ============================================================
CREATE TABLE stock (
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

-- ============================================================
-- TABLE: SECUENCIAS_DOCUMENTO (RN-06 / números SVC — concurrencia)
-- ============================================================
CREATE TABLE secuencias_documento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(10) NOT NULL COMMENT 'CC o SVC',
  anio SMALLINT NOT NULL,
  siguiente INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tipo_anio (tipo, anio),
  INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias documentales por año';

-- ============================================================
-- TABLE: SERVICIOS
-- Purpose: Servicios solicitados por clientes
-- ============================================================
CREATE TABLE servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero_servicio VARCHAR(50) UNIQUE NOT NULL,
  cliente_id INT NOT NULL,
  tecnico_asignado_id INT,
  descripcion TEXT NOT NULL,
  tipo_servicio VARCHAR(100),
  estado ENUM('pendiente', 'programado', 'en_proceso', 'completado', 'cancelado') DEFAULT 'pendiente',
  fecha_solicitud DATE NOT NULL,
  fecha_programada DATE,
  hora_inicio TIME,
  hora_fin TIME,
  ubicacion TEXT,
  valor_estimado DECIMAL(15,2),
  valor_final DECIMAL(15,2),
  observaciones TEXT,
  activo TINYINT(1) DEFAULT 1,
  stock_descuento_aplicado TINYINT(1) NOT NULL DEFAULT 0,
  cuenta_cobro_generada TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
  FOREIGN KEY (tecnico_asignado_id) REFERENCES tecnicos(id) ON DELETE RESTRICT,
  INDEX idx_numero_servicio (numero_servicio),
  INDEX idx_cliente_id (cliente_id),
  INDEX idx_tecnico_id (tecnico_asignado_id),
  INDEX idx_estado (estado),
  INDEX idx_fecha_programada (fecha_programada),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de servicios termográficos';

-- ============================================================
-- TABLE: SERVICIOS_ITEMS
-- Purpose: Detalles de líneas en servicios
-- ============================================================
CREATE TABLE servicios_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  servicio_id INT NOT NULL,
  stock_id INT NULL COMMENT 'Artículo de inventario consumido en la línea',
  numero_item INT NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
  unidad VARCHAR(50),
  valor_unitario DECIMAL(15,2) NOT NULL,
  valor_total DECIMAL(15,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE RESTRICT,
  FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_servicio_id (servicio_id),
  INDEX idx_stock_id (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de items detallados de servicios';

-- ============================================================
-- TABLE: PARAMETROS_COTIZADOR
-- Purpose: Parámetros dinámicos para cotizador inteligente
-- ============================================================
CREATE TABLE parametros_cotizador (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion TEXT,
  tipo_parametro ENUM('porcentaje', 'valor_fijo', 'multiplicador', 'rango') NOT NULL,
  valor_base DECIMAL(12, 4) NOT NULL,
  valor_minimo DECIMAL(12, 4),
  valor_maximo DECIMAL(12, 4),
  activo TINYINT(1) DEFAULT 1,
  fecha_vigencia_inicio DATE,
  fecha_vigencia_fin DATE,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_activo (activo),
  INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: PARAMETROS_EQUIPOS
-- Purpose: Catálogo de equipos y componentes para cotización
-- ============================================================
CREATE TABLE parametros_equipos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL UNIQUE,
  nombre_equipo VARCHAR(150) NOT NULL,
  categoria VARCHAR(100),
  valor_inspeccion DECIMAL(12, 2) NOT NULL,
  tiempo_estimado_horas DECIMAL(5, 2),
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_codigo (codigo),
  INDEX idx_categoria (categoria),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: MOVIMIENTOS_STOCK
-- Purpose: RN-02 - Auditoría de movimientos de inventario
-- ============================================================
CREATE TABLE movimientos_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_id INT NOT NULL,
  tipo_movimiento ENUM('entrada', 'salida', 'ajuste') NOT NULL,
  cantidad INT NOT NULL,
  cantidad_anterior INT NOT NULL,
  cantidad_nueva INT NOT NULL,
  razon VARCHAR(255),
  usuario_id INT,
  servicio_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE RESTRICT,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  INDEX idx_stock_id (stock_id),
  INDEX idx_tipo_movimiento (tipo_movimiento),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de movimientos de stock - RN-02';

-- ============================================================
-- TABLE: CUENTAS_COBRO
-- Purpose: RN-06 - Cuentas de cobro (CC-YYYY-XXXX)
-- ============================================================
CREATE TABLE cuentas_cobro (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(50) UNIQUE NOT NULL COMMENT 'Formato: CC-YYYY-XXXX',
  cliente_id INT NOT NULL,
  fecha_emision DATE NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  fecha_pago DATE,
  estado ENUM('pendiente', 'parcial', 'pagada', 'vencida', 'cancelada') DEFAULT 'pendiente',
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  impuesto_iva DECIMAL(15,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(15,2) DEFAULT 0,
  total DECIMAL(15,2) NOT NULL DEFAULT 0,
  moneda VARCHAR(3) DEFAULT 'COP',
  metodo_pago VARCHAR(100),
  referencia_externa VARCHAR(255),
  notas TEXT,
  usuario_creador_id INT,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
  FOREIGN KEY (usuario_creador_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_numero (numero),
  INDEX idx_cliente_id (cliente_id),
  INDEX idx_estado (estado),
  INDEX idx_fecha_vencimiento (fecha_vencimiento),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de cuentas de cobro - RN-06';

-- ============================================================
-- TABLE: CUENTAS_ITEMS (líneas de cuenta de cobro)
-- ============================================================
CREATE TABLE cuentas_items (
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

-- ============================================================
-- TABLE: AUDITORIA (RN-16)
-- ============================================================
CREATE TABLE auditoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT,
  entidad_tipo VARCHAR(100) NOT NULL COMMENT 'Usuario, Servicio, CuentaCobro, etc.',
  entidad_id INT NOT NULL,
  accion VARCHAR(50) NOT NULL COMMENT 'crear, actualizar, eliminar, transicion_estado',
  estado_anterior VARCHAR(255),
  estado_nuevo VARCHAR(255),
  detalles JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_entidad (entidad_tipo, entidad_id),
  INDEX idx_accion (accion),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de auditoría - RN-16';

-- ============================================================
-- TABLE: CONFIGURACION_EMPRESA
-- Purpose: Parámetros configurables de la empresa
-- ============================================================
CREATE TABLE configuracion_empresa (
  id INT PRIMARY KEY AUTO_INCREMENT,
  clave VARCHAR(100) NOT NULL UNIQUE,
  valor TEXT,
  tipo VARCHAR(50),
  descripcion VARCHAR(255),
  activo TINYINT(1) DEFAULT 1,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- ============================================================
CREATE INDEX idx_servicios_cliente_estado ON servicios(cliente_id, estado);
CREATE INDEX idx_cuentas_cliente_estado ON cuentas_cobro(cliente_id, estado);
CREATE INDEX idx_auditoria_usuario_fecha ON auditoria(usuario_id, created_at);

-- ============================================================
-- RBAC TABLES (requeridas por RBACService.php)
-- roles, permisos, rol_permisos, usuario_roles
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(255),
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_nombre (nombre),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles del sistema RBAC';

CREATE TABLE IF NOT EXISTS permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE COMMENT 'e.g. servicios.leer',
  descripcion VARCHAR(255),
  modulo VARCHAR(100),
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nombre (nombre),
  INDEX idx_modulo (modulo),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permisos atómicos del sistema';

CREATE TABLE IF NOT EXISTS rol_permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rol_id INT NOT NULL,
  permiso_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_rol_permiso (rol_id, permiso_id),
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
  INDEX idx_rol_id (rol_id),
  INDEX idx_permiso_id (permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relación rol-permisos';

CREATE TABLE IF NOT EXISTS usuario_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  asignado_por INT,
  razon VARCHAR(255),
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_rol (usuario_id, rol_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_rol_id (rol_id),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de roles a usuarios';

-- Seeds RBAC base
INSERT IGNORE INTO roles (nombre, descripcion) VALUES
  ('admin',   'Administrador del sistema — acceso total'),
  ('tecnico', 'Técnico de campo'),
  ('cliente', 'Cliente empresarial');

INSERT IGNORE INTO permisos (nombre, modulo, descripcion) VALUES
  ('admin.configuracion',    'admin',      'Configurar parámetros de empresa'),
  ('admin.usuarios',         'admin',      'Gestión completa de usuarios'),
  ('cotizador.leer',         'cotizador',  'Consultar cotizador'),
  ('cotizador.crear',        'cotizador',  'Crear cotizaciones'),
  ('cotizador.actualizar',   'cotizador',  'Editar parámetros del cotizador'),
  ('cuentas.leer',           'cuentas',    'Ver cuentas de cobro'),
  ('cuentas.crear',          'cuentas',    'Generar cuentas de cobro'),
  ('cuentas.actualizar',     'cuentas',    'Editar cuentas de cobro'),
  ('cuentas.pagar',          'cuentas',    'Registrar pagos'),
  ('reportes.leer',          'reportes',   'Ver reportes'),
  ('reportes.exportar',      'reportes',   'Exportar reportes'),
  ('servicios.leer',         'servicios',  'Ver servicios'),
  ('servicios.crear',        'servicios',  'Crear servicios'),
  ('servicios.actualizar',   'servicios',  'Editar servicios'),
  ('servicios.cambiar_estado','servicios', 'Cambiar estado de servicio'),
  ('servicios.eliminar',     'servicios',  'Desactivar servicios'),
  ('stock.leer',             'stock',      'Consultar inventario'),
  ('stock.actualizar',       'stock',      'Modificar stock'),
  ('usuarios.leer',          'usuarios',   'Ver listado de usuarios'),
  ('usuarios.crear',         'usuarios',   'Crear usuarios'),
  ('usuarios.actualizar',    'usuarios',   'Editar usuarios'),
  ('usuarios.eliminar',      'usuarios',   'Desactivar usuarios');

-- Asignar todos los permisos al rol admin
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
  SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'admin';

-- Permisos del técnico
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
  SELECT r.id, p.id FROM roles r
  INNER JOIN permisos p ON p.nombre IN (
    'servicios.leer','servicios.actualizar','servicios.cambiar_estado',
    'stock.leer','cotizador.leer'
  )
  WHERE r.nombre = 'tecnico';

-- Permisos del cliente
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
  SELECT r.id, p.id FROM roles r
  INNER JOIN permisos p ON p.nombre IN (
    'servicios.leer','cuentas.leer','cotizador.leer'
  )
  WHERE r.nombre = 'cliente';

-- ============================================================
-- TABLE: NOTIFICACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS notificaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  mensaje TEXT NOT NULL,
  leida TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_leida (leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones del sistema';

-- ============================================================
-- FIN DEL SCHEMA
-- ============================================================

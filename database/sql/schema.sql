-- ============================================================
-- INTÉRMICA S.A.S - DATABASE SCHEMA
-- Version: 2.0
-- Created: 2026-05-11
-- Normalized to 5NF with integrity constraints
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
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100),
  rol ENUM('admin', 'tecnico', 'cliente') NOT NULL DEFAULT 'cliente',
  password_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(20),
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_rol (rol),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  id INT PRIMARY KEY AUTO_INCREMENT,
  usuario_id INT NOT NULL UNIQUE,
  nombre_empresa VARCHAR(200) NOT NULL,
  nit VARCHAR(20) NOT NULL UNIQUE,
  direccion VARCHAR(300),
  ciudad VARCHAR(100),
  telefono VARCHAR(20),
  email_contacto VARCHAR(255),
  persona_contacto VARCHAR(150),
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_nit (nit),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: TECNICOS
-- Purpose: Información de técnicos disponibles
-- ============================================================
CREATE TABLE tecnicos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  usuario_id INT NOT NULL UNIQUE,
  nombre_completo VARCHAR(150) NOT NULL,
  especialidad VARCHAR(100),
  numero_cedula VARCHAR(20),
  telefono_contacto VARCHAR(20),
  activo TINYINT(1) DEFAULT 1,
  disponible TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
  INDEX idx_disponible (disponible),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: BLOQUEOS_AGENDA
-- Purpose: RN-13/14 - Validación y bloqueo de agenda técnico
-- ============================================================
CREATE TABLE bloqueos_agenda (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tecnico_id INT NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  razon VARCHAR(255),
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE RESTRICT,
  INDEX idx_tecnico_id (tecnico_id),
  INDEX idx_fecha_inicio (fecha_inicio),
  INDEX idx_fecha_fin (fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: SERVICIOS
-- Purpose: Servicios solicitados por clientes
-- ============================================================
CREATE TABLE servicios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  numero_servicio VARCHAR(50) NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  tecnico_asignado_id INT,
  estado ENUM('cotizado', 'aceptado', 'en_proceso', 'completado', 'cancelado') DEFAULT 'cotizado',
  fecha_solicitud DATE NOT NULL,
  fecha_programada DATE,
  fecha_ejecucion DATE,
  descripcion TEXT,
  ubicacion VARCHAR(300),
  costo_total DECIMAL(12, 2),
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
  FOREIGN KEY (tecnico_asignado_id) REFERENCES tecnicos(id) ON DELETE SET NULL,
  INDEX idx_numero_servicio (numero_servicio),
  INDEX idx_cliente_id (cliente_id),
  INDEX idx_estado (estado),
  INDEX idx_tecnico_asignado_id (tecnico_asignado_id),
  INDEX idx_fecha_ejecucion (fecha_ejecucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: SERVICIOS_ITEMS
-- Purpose: Detalles de líneas en servicios
-- ============================================================
CREATE TABLE servicios_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  servicio_id INT NOT NULL,
  numero_item INT NOT NULL,
  descripcion VARCHAR(300) NOT NULL,
  cantidad DECIMAL(10, 2) NOT NULL,
  unidad VARCHAR(50),
  valor_unitario DECIMAL(12, 2) NOT NULL,
  subtotal DECIMAL(12, 2) NOT NULL,
  impuesto DECIMAL(12, 2),
  total DECIMAL(12, 2) NOT NULL,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE RESTRICT,
  UNIQUE KEY uk_servicio_item (servicio_id, numero_item),
  INDEX idx_servicio_id (servicio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- TABLE: STOCK
-- Purpose: Inventario de repuestos y consumibles
-- ============================================================
CREATE TABLE stock (
  id INT PRIMARY KEY AUTO_INCREMENT,
  codigo_interno VARCHAR(50) NOT NULL UNIQUE,
  nombre_producto VARCHAR(200) NOT NULL,
  descripcion TEXT,
  cantidad_disponible INT NOT NULL DEFAULT 0,
  cantidad_minima INT DEFAULT 5,
  unidad_medida VARCHAR(50),
  valor_unitario DECIMAL(12, 2) NOT NULL,
  proveedor VARCHAR(200),
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_codigo_interno (codigo_interno),
  INDEX idx_activo (activo),
  INDEX idx_cantidad_disponible (cantidad_disponible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: MOVIMIENTOS_STOCK
-- Purpose: RN-02 - Auditoría de movimientos de inventario
-- ============================================================
CREATE TABLE movimientos_stock (
  id INT PRIMARY KEY AUTO_INCREMENT,
  stock_id INT NOT NULL,
  tipo_movimiento ENUM('entrada', 'salida', 'ajuste') NOT NULL,
  cantidad INT NOT NULL,
  cantidad_anterior INT NOT NULL,
  cantidad_posterior INT NOT NULL,
  referencia_documento VARCHAR(100),
  razon VARCHAR(255),
  usuario_id INT,
  fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE RESTRICT,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_stock_id (stock_id),
  INDEX idx_tipo_movimiento (tipo_movimiento),
  INDEX idx_fecha_movimiento (fecha_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: CUENTAS_COBRO
-- Purpose: RN-06 - Cuentas de cobro (CC-YYYY-XXXX)
-- ============================================================
CREATE TABLE cuentas_cobro (
  id INT PRIMARY KEY AUTO_INCREMENT,
  numero VARCHAR(50) NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  fecha_emision DATE NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  fecha_pago DATE,
  estado ENUM('pendiente', 'parcial', 'pagada', 'cancelada', 'vencida') DEFAULT 'pendiente',
  subtotal DECIMAL(12, 2) NOT NULL,
  impuesto_iva DECIMAL(12, 2),
  descuento DECIMAL(12, 2) DEFAULT 0,
  total DECIMAL(12, 2) NOT NULL,
  moneda VARCHAR(3) DEFAULT 'COP',
  metodo_pago ENUM('transferencia', 'efectivo', 'cheque', 'tarjeta', 'pendiente'),
  referencia_externa VARCHAR(100),
  notas TEXT,
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  usuario_creador_id INT,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
  FOREIGN KEY (usuario_creador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_numero (numero),
  INDEX idx_cliente_id (cliente_id),
  INDEX idx_estado (estado),
  INDEX idx_fecha_vencimiento (fecha_vencimiento),
  INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: CUENTAS_COBRO_ITEMS
-- Purpose: Detalles de líneas en cuentas de cobro
-- ============================================================
CREATE TABLE cuentas_cobro_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cuenta_cobro_id INT NOT NULL,
  servicio_id INT,
  numero_item INT NOT NULL,
  descripcion VARCHAR(300) NOT NULL,
  cantidad DECIMAL(10, 2) NOT NULL,
  unidad VARCHAR(50),
  valor_unitario DECIMAL(12, 2) NOT NULL,
  subtotal DECIMAL(12, 2) NOT NULL,
  impuesto DECIMAL(12, 2),
  total DECIMAL(12, 2) NOT NULL,
  FOREIGN KEY (cuenta_cobro_id) REFERENCES cuentas_cobro(id) ON DELETE RESTRICT,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  UNIQUE KEY uk_cuenta_item (cuenta_cobro_id, numero_item),
  INDEX idx_cuenta_cobro_id (cuenta_cobro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: AUDITORIA
-- Purpose: RN-16 - Log de transiciones de estados y cambios críticos
-- ============================================================
CREATE TABLE auditoria (
  id INT PRIMARY KEY AUTO_INCREMENT,
  usuario_id INT,
  tabla_afectada VARCHAR(100) NOT NULL,
  registro_id INT,
  accion ENUM('crear', 'modificar', 'eliminar_logico', 'transicion_estado') NOT NULL,
  descripcion TEXT,
  estado_anterior VARCHAR(255),
  estado_nuevo VARCHAR(255),
  valores_anteriores JSON,
  valores_nuevos JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_tabla_afectada (tabla_afectada),
  INDEX idx_accion (accion),
  INDEX idx_fecha_registro (fecha_registro),
  INDEX idx_registro_id (registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
CREATE INDEX idx_auditoria_usuario_fecha ON auditoria(usuario_id, fecha_registro);

-- ============================================================
-- FIN DEL SCHEMA
-- ============================================================

# 🏢 Intérmica S.A.S - Plataforma Operativa Fullstack

**Sistema integral de gestión de servicios termográficos industriales**

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-Development-yellow)

## 📋 Descripción

Plataforma completa para la gestión operativa de Intérmica S.A.S:
- **Frontend**: React 18 con JavaScript, Redux, React Router
- **Backend**: PHP 8.1+ con arquitectura RESTful, JWT, RBAC
- **Base de datos**: MySQL/MariaDB con 5NF, triggers y vistas
- **Integraciones**: Generación de PDFs, QR dinámicos, sistema de auditoría

## 🎯 Características Principales

✅ **Autenticación & Seguridad**
- JWT (JSON Web Tokens) con refresh tokens
- Bcrypt para hash de contraseñas
- Control de acceso basado en roles (RBAC)
- Sistema de sesiones persistente

✅ **Gestión de Servicios**
- Cotizador inteligente con parámetros dinámicos (RN-06)
- Agenda de técnicos con bloqueos (RN-13/14)
- Actualización automática de stock (RN-02)
- Estados y transiciones auditadas (RN-16)

✅ **Cuentas de Cobro**
- Generación automática con formato CC-YYYY-XXXX
- PDFs descargables
- QR dinámico de verificación
- Historial de pagos

✅ **Integridad de Datos**
- Borrado lógico (activo = 0) en lugar de físico (RN-23)
- Restricciones de integridad referencial (RN-25)
- Auditoría completa de cambios (RN-16)

## 🚀 Quick Start

### Prerequisitos
- **PHP** 8.1+ (incluido en XAMPP)
- **MySQL** 5.7+ o MariaDB
- **Node.js** 18+ y npm
- **Composer** 2.0+

### 1. Clonar repositorio

```bash
git clone https://github.com/mchiquillopinzonsena-sys/Proyecto-1-.git
cd Proyecto-1-
```

### 2. Configurar Backend

```bash
cd backend

# Instalar dependencias
composer install

# Copiar archivo de configuración
cp .env.example .env

# Editar .env con credenciales locales
# DB_HOST=localhost
# DB_USER=root
# DB_PASS=(vacío)
# DB_NAME=intermica_db
```

### 3. Crear Base de Datos

```bash
# Iniciar XAMPP
# Abrir phpMyAdmin y crear base de datos 'intermica_db'

# O desde línea de comandos:
mysql -u root -e "CREATE DATABASE intermica_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Ejecutar migraciones
mysql -u root intermica_db < ../database/sql/schema.sql

# Ejecutar seeders
php database/seeds/SeedRunner.php
```

### 4. Configurar Frontend

```bash
cd ../frontend

# Instalar dependencias
npm install

# Copiar variables de entorno
cp .env.example .env.local

# Editar .env.local
# REACT_APP_API_URL=http://localhost:8000/api/v1

# Iniciar servidor de desarrollo
npm start
```

### 5. Acceder a la aplicación

- **Frontend**: http://localhost:3000
- **API**: http://localhost:8000/api/v1
- **phpMyAdmin**: http://localhost/phpmyadmin

## 🔐 Credenciales por Defecto

| Usuario | Email | Contraseña | Rol |
|---------|-------|-----------|-----|
| Admin Sistema | admin@intermica.com | Admin123! | admin |
| Técnico Demo | tecnico@intermica.com | Tecnico123! | tecnico |
| Cliente Demo | cliente@intermica.com | Cliente123! | cliente |

⚠️ **Cambiar credenciales en producción**

## 📁 Estructura de Directorios

```
Proyecto-1-/
├── backend/              # API PHP
│   ├── app/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Middleware/
│   │   ├── Validators/
│   │   ├── Exceptions/
│   │   ├── Helpers/
│   │   └── Enums/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeds/
│   │   └── Database.php
│   ├── config/
│   ├── routes/
│   ├── logs/
│   ├── storage/
│   ├── composer.json
│   └── .env.example
│
├── frontend/             # Aplicación React
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── hooks/
│   │   ├── pages/
│   │   ├── store/
│   │   ├── styles/
│   │   ├── utils/
│   │   ├── __tests__/
│   │   └── App.jsx
│   ├── public/
│   ├── package.json
│   └── .env.example
│
├── database/             # Documentación DB
│   ├── sql/
│   └── documentation/
│
├── docs/                 # Documentación
│   ├── API_REFERENCE.md
│   ├── ARQUITECTURA.md
│   └── ...
│
└── README.md
```

## 📊 Roles y Acceso

### Admin
- `/admin/usuarios` - Gestión de usuarios
- `/admin/parametros` - Configuración del cotizador
- `/admin/reportes` - Reportes y auditoría
- `/admin/configuracion` - Configuración empresa

### Técnico
- `/tecnico/agenda` - Calendario de servicios
- `/tecnico/servicios` - Mis servicios asignados
- `/tecnico/reportes` - Historial de actividades

### Cliente
- `/cliente/servicios` - Cotizaciones
- `/cliente/cuentas` - Mis cuentas de cobro
- `/cliente/perfil` - Mi perfil

## 🔌 Endpoints Principales

```
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh
GET    /api/v1/usuarios
POST   /api/v1/servicios
GET    /api/v1/cuentas/:id
GET    /api/v1/cuentas/:id/pdf
GET    /api/v1/reportes/auditoria
```

**Documentación completa**: [API_REFERENCE.md](docs/API_REFERENCE.md)

## 🧪 Testing

```bash
# Backend
cd backend
composer test

# Frontend
cd frontend
npm test
```

## 📚 Documentación

- [Guía de Instalación](docs/SETUP_LOCAL.md)
- [Referencia API](docs/API_REFERENCE.md)
- [Arquitectura Técnica](docs/ARQUITECTURA.md)
- [Reglas de Negocio](docs/REGLAS_NEGOCIO.md)
- [Seguridad](docs/SEGURIDAD.md)
- [Diagrama ER](database/documentation/DiagramaER.md)

## 🛠️ Stack Tecnológico

### Frontend
- **React** 18.3.1
- **Redux** @reduxjs/toolkit
- **React Router** 6.22.0
- **Axios** 1.6.5
- **React Hook Form** 7.48.0
- **Tailwind CSS** (estilos)

### Backend
- **PHP** 8.1+
- **Firebase JWT** 6.10
- **Monolog** (logging)
- **TCPDF** (generación PDFs)
- **PHP QR Code** (códigos QR)
- **PHPUnit** (testing)

### Base de Datos
- **MySQL** 5.7+ o **MariaDB**
- **5 Formas Normales (5NF)**
- **Triggers** para automaciones
- **Vistas** para reportes

## 🔐 Variables de Entorno

### Backend (.env)
```
# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=intermica_db
DB_PORT=3306

# JWT
JWT_SECRET=your-secret-key-change-in-production
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=604800

# Application
APP_ENV=development
APP_DEBUG=true
APP_LOG_LEVEL=debug
APP_URL=http://localhost:8000

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000

# Email (opcional)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
```

### Frontend (.env.local)
```
REACT_APP_API_URL=http://localhost:8000/api/v1
REACT_APP_ENV=development
REACT_APP_LOG_LEVEL=debug
```

## 📝 Contribuir

1. Crear una rama: `git checkout -b feature/mi-feature`
2. Commits: `git commit -am 'Agregar feature'`
3. Push: `git push origin feature/mi-feature`
4. Pull Request

## 📄 Licencia

MIT License © 2026 Intérmica S.A.S

## 📞 Soporte

Para soporte o reportar bugs: [Issues](https://github.com/mchiquillopinzonsena-sys/Proyecto-1-/issues)

---

**Última actualización**: 2026-05-11

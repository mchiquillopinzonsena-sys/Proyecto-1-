# 🏢 Intérmica S.A.S - Plataforma Operativa Fullstack

Sistema completo de gestión de servicios termográficos con React + PHP + MySQL.

**Estado del Proyecto:** 🚧 En Desarrollo

## 🎯 Descripción General

Plataforma operativa robusta que permite gestionar:
- **Servicios Termográficos**: Cotizaciones, agendamiento y ejecución
- **Gestión de Técnicos**: Agenda inteligente con validación de disponibilidad
- **Generación de Cuentas de Cobro**: Automática con formato CC-YYYY-XXXX
- **Control de Stock**: Actualización automática en transacciones
- **Reportes y Auditoría**: Trazabilidad completa de operaciones
- **Control de Acceso**: JWT + RBAC por roles (Admin, Técnico, Cliente)

## 🚀 Quick Start

### Prerequisitos

- **XAMPP** (PHP 8.1+, MySQL 5.7+)
- **Node.js** 18+ y npm
- **Composer** 2.0+
- **Git**

### Instalación Local

#### 1️⃣ Clonar repositorio

```bash
git clone https://github.com/mchiquillopinzonsena-sys/Proyecto-1-.git
cd Proyecto-1-
```

#### 2️⃣ Backend Setup

```bash
cd backend

# Copiar archivo de configuración
cp .env.example .env

# Editar .env con tus credenciales de base de datos
code .env

# Instalar dependencias PHP
composer install

# Crear base de datos en MySQL
mysql -u root -p < database/schema.sql

# Ejecutar migraciones
php database/migrations/runMigrations.php

# Cargar datos iniciales
php database/seeds/SeedRunner.php
```

#### 3️⃣ Frontend Setup

```bash
cd ../frontend

# Instalar dependencias Node
npm install

# Copiar configuración de entorno
cp .env.example .env.local

# Editar con tu URL de API
echo "REACT_APP_API_URL=http://localhost:8000/api/v1" >> .env.local

# Iniciar servidor de desarrollo
npm start
```

#### 4️⃣ Configurar XAMPP

```bash
# En Windows
Copiar: backend/ → C:\xampp\htdocs\intermica-api

# En Mac/Linux
Copiar: backend/ → /Applications/XAMPP/htdocs/intermica-api
```

- Iniciar Apache y MySQL desde XAMPP Panel
- Acceder a: `http://localhost/intermica-api`

## 📋 Estructura de Roles y Acceso

| Rol | URL | Módulos | Descripción |
|-----|-----|---------|-------------|
| **Admin** | `/admin` | Dashboard, Usuarios, Parámetros, Reportes, Config | Gestión completa del sistema |
| **Técnico** | `/tecnico` | Agenda, Servicios, Registro Trabajo, Actividades | Ejecución de servicios |
| **Cliente** | `/cliente` | Cotizaciones, Cuentas de Cobro, Perfil | Consumidor de servicios |
| **Visitante** | `/login` | Login | Autenticación |

## 🔑 Variables de Entorno

### Backend (.env)

```env
# DATABASE
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=intermica_db
DB_PORT=3306

# JWT
JWT_SECRET=your-super-secret-key-change-in-production-2026
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=604800

# APP
APP_NAME=Intérmica S.A.S
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOG_LEVEL=debug

# CORS
CORS_ORIGIN=http://localhost:3000

# PDF & QR
PDF_OUTPUT_PATH=/storage/pdfs/
QR_OUTPUT_PATH=/storage/qr/
```

### Frontend (.env.local)

```env
REACT_APP_API_URL=http://localhost:8000/api/v1
REACT_APP_ENV=development
REACT_APP_NAME=Intérmica S.A.S
```

## 📁 Estructura del Proyecto

```
Proyecto-1-/
├── backend/                    # API PHP RESTful
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
│   │   └── seeds/
│   ├── config/
│   ├── routes/
│   ├── logs/
│   ├── storage/
│   ├── .env.example
│   ├── composer.json
│   └── index.php
│
├── frontend/                   # App React
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── hooks/
│   │   ├── pages/
│   │   ├── store/
│   │   ├── styles/
│   │   ├── utils/
│   │   └── App.jsx
│   ├── public/
│   ├── .env.example
│   ├── package.json
│   └── vite.config.js
│
├── database/                   # Documentación DB
│   ├── sql/
│   └── documentation/
│
├── docs/                       # Documentación del proyecto
│   ├── API_REFERENCE.md
│   ├── ARQUITECTURA.md
│   ├── REGLAS_NEGOCIO.md
│   ├── SEGURIDAD.md
│   └── postman/
│
├── docker/                     # Docker Compose (opcional)
├── .gitignore
├── .editorconfig
├── README.md
├── TECHNICAL_ARCHITECTURE.md
└── LICENSE
```

## 🧪 Testing

```bash
# Backend tests
cd backend
composer test

# Frontend tests
cd frontend
npm test

# Coverage
npm run test:coverage
```

## 📚 Documentación Detallada

- **[API Reference](docs/API_REFERENCE.md)** - Endpoints, parámetros, respuestas
- **[Arquitectura](docs/ARQUITECTURA.md)** - Diseño del sistema, patrones
- **[Reglas de Negocio](docs/REGLAS_NEGOCIO.md)** - RN-02, RN-06, RN-13/14, RN-16, etc.
- **[Seguridad](docs/SEGURIDAD.md)** - JWT, RBAC, bcrypt, CORS
- **[Setup Local](docs/SETUP_LOCAL.md)** - Guía detallada de instalación

## 🔐 Seguridad

✅ **Implementado:**
- JWT (JSON Web Tokens) para autenticación sin estado
- RBAC (Role-Based Access Control) granular
- Contraseñas hasheadas con bcrypt
- CORS configurado
- SQL Injection prevention (prepared statements)
- XSS protection
- Validación de entrada en cliente y servidor
- Logs de auditoría completos

## 🛠️ Stack Tecnológico

### Backend
- **PHP 8.1+** - Lenguaje principal
- **MySQL 5.7+ / MariaDB** - Base de datos
- **JWT (Firebase)** - Autenticación
- **TCPDF** - Generación de PDFs
- **PHP QR Code** - Códigos QR dinámicos
- **Monolog** - Logging y auditoría

### Frontend
- **React 18+** - UI Framework
- **Redux Toolkit** - State management
- **Axios** - HTTP client
- **React Router v6** - Routing
- **Vite** - Build tool
- **Yup** - Validación de formularios
- **Tailwind CSS** (opcional) - Styling

## 📝 Credenciales por Defecto (CAMBIAR EN PRODUCCIÓN)

Luego de ejecutar seeders:

```
Email: admin@intermica.com
Password: Admin@2026
Rol: Admin

Email: tecnico@intermica.com
Password: Tech@2026
Rol: Técnico

Email: cliente@intermica.com
Password: Client@2026
Rol: Cliente
```

## 🚢 Deployment

Ver documentación de deployment en `docs/DEPLOYMENT.md`

## 📞 Contacto y Soporte

- **Organización:** Intérmica S.A.S
- **Repositorio:** [GitHub](https://github.com/mchiquillopinzonsena-sys/Proyecto-1-)
- **Issues:** [GitHub Issues](https://github.com/mchiquillopinzonsena-sys/Proyecto-1-/issues)

## 📄 Licencia

MIT License © 2026 Intérmica S.A.S

---

**Última actualización:** 2026-05-11

**Mantenedor:** @mchiquillopinzonsena-sys

# 🏢 Intérmica S.A.S - Plataforma Operativa Fullstack

Sistema completo de gestión de servicios termográficos con React + PHP + MySQL.

## 🚀 Quick Start

### Prerequisitos
- **XAMPP** (PHP 8.1+, MySQL 5.7+)
- **Node.js** 18+ y npm
- **Composer** 2.0+

### Instalación Local

#### 1. Clonar repositorio
```bash
git clone https://github.com/mchiquillopinzonsena-sys/Proyecto-1-.git
cd Proyecto-1-
```

#### 2. Backend Setup
```bash
cd backend

# Copiar .env
cp .env.example .env

# Instalar dependencias PHP
composer install

# Crear base de datos
mysql -u root < database/schema.sql

# Ejecutar migraciones
php database/seeds/SeedRunner.php
```

#### 3. Frontend Setup
```bash
cd ../frontend

# Instalar dependencias
npm install

# Configurar variables de entorno
cp .env.example .env.local
# Editar .env.local con: REACT_APP_API_URL=http://localhost:8000/api/v1

# Iniciar servidor desarrollo
npm start
```

#### 4. XAMPP Configuration
- Mover `backend/` a `htdocs/intermica-api`
- Iniciar Apache y MySQL desde XAMPP Panel
- URL: http://localhost/intermica-api
- Frontend: http://localhost:3000

## 📋 Estructura de Roles

| Rol | Acceso | Módulos |
|-----|--------|----------|
| **Admin** | `/admin` | Usuarios, Parámetros, Reportes, Configuración |
| **Técnico** | `/tecnico` | Agenda, Servicios, Registro de Trabajo |
| **Cliente** | `/cliente` | Cotizaciones, Cuentas de Cobro, Perfil |

## 🔑 Variables de Entorno

### Backend (.env)
```
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=intermica_db

JWT_SECRET=your-secret-key-change-in-production
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

APP_ENV=development
APP_LOG_LEVEL=debug
APP_URL=http://localhost

CORS_ORIGIN=http://localhost:3000
```

### Frontend (.env.local)
```
REACT_APP_API_URL=http://localhost:8000/api/v1
REACT_APP_ENV=development
REACT_APP_JWT_KEY=app_jwt_token
```

## 📁 Estructura del Proyecto

```
Proyecto-1-/
├── frontend/          # React + JavaScript
├── backend/           # PHP API RESTful
├── database/          # Migraciones SQL
├── docs/              # Documentación
└── README.md
```

## 🧪 Testing

```bash
# Backend tests
cd backend && composer test

# Frontend tests
cd frontend && npm test
```

## 📚 Documentación Completa

- [Guía de Setup Local](docs/SETUP_LOCAL.md)
- [API Reference](docs/API_REFERENCE.md)
- [Arquitectura Técnica](docs/ARQUITECTURA.md)
- [Reglas de Negocio](docs/REGLAS_NEGOCIO.md)
- [Seguridad](docs/SEGURIDAD.md)

## 🔐 Seguridad

- JWT para autenticación stateless
- RBAC (Role-Based Access Control)
- Bcrypt para hashing de contraseñas
- CORS configurado
- Validación de entrada en todos los endpoints
- Logs de auditoría para transiciones de estado

## 📝 Licencia

MIT License © 2026 Intérmica S.A.S

## 👥 Autor

**mchiquillopinzonsena-sys**

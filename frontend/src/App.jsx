import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { ProtectedRoute } from './components/Layout';
import { LoginPage } from './pages/LoginPage';

// Lazy-load páginas protegidas (se crearán después)
// import { DashboardPage } from './pages/DashboardPage';
// import { ServiciosPage } from './pages/ServiciosPage';
// import { CuentasPage } from './pages/CuentasPage';
// import { UsuariosPage } from './pages/UsuariosPage';
// import { CotizadorPage } from './pages/CotizadorPage';
// import { StockPage } from './pages/StockPage';

/**
 * URL base de la API — configurable vía .env (VITE_API_URL)
 * En dev apunta a http://localhost:8000, en prod a la URL del servidor PHP.
 */
const API_BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

/**
 * Placeholder temporal mientras se crean las páginas protegidas.
 * Reemplazar por el componente real al implementar cada sección.
 */
const PlaceholderPage = ({ title }) => (
  <div style={{ padding: '2rem', textAlign: 'center' }}>
    <h2>{title}</h2>
    <p style={{ color: '#6b7280' }}>Página en construcción.</p>
  </div>
);

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider apiBaseUrl={API_BASE_URL}>
        <Routes>
          {/* Ruta pública */}
          <Route path="/login" element={<LoginPage />} />

          {/* Rutas protegidas — requieren JWT válido */}
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <PlaceholderPage title="Dashboard" />
              </ProtectedRoute>
            }
          />
          <Route
            path="/servicios"
            element={
              <ProtectedRoute>
                <PlaceholderPage title="Servicios" />
              </ProtectedRoute>
            }
          />
          <Route
            path="/cuentas"
            element={
              <ProtectedRoute>
                <PlaceholderPage title="Cuentas de Cobro" />
              </ProtectedRoute>
            }
          />
          <Route
            path="/usuarios"
            element={
              <ProtectedRoute>
                <PlaceholderPage title="Usuarios" />
              </ProtectedRoute>
            }
          />
          <Route
            path="/cotizador"
            element={
              <ProtectedRoute>
                <PlaceholderPage title="Cotizador" />
              </ProtectedRoute>
            }
          />
          <Route
            path="/stock"
            element={
              <ProtectedRoute>
                <PlaceholderPage title="Inventario / Stock" />
              </ProtectedRoute>
            }
          />

          {/* Catch-all: redirige raíz a dashboard */}
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

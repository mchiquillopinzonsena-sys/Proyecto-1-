import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { ProtectedRoute } from './components/Layout';
import { LoginPage } from './pages/LoginPage';

import { DashboardPage } from './pages/DashboardPage';
import { ServiciosPage } from './pages/ServiciosPage';
import { CuentasPage } from './pages/CuentasPage';
import { UsuariosPage } from './pages/UsuariosPage';
import { CotizadorPage } from './pages/CotizadorPage';
import { StockPage } from './pages/StockPage';
import { AgendaPage } from './pages/AgendaPage';

/**
 * URL base de la API — configurable vía .env (VITE_API_URL)
 * En dev apunta a http://localhost:8000, en prod a la URL del servidor PHP.
 */
const API_BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

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
                <DashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/servicios"
            element={
              <ProtectedRoute>
                <ServiciosPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/cuentas"
            element={
              <ProtectedRoute>
                <CuentasPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/usuarios"
            element={
              <ProtectedRoute requiredRole="admin">
                <UsuariosPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/cotizador"
            element={
              <ProtectedRoute>
                <CotizadorPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/stock"
            element={
              <ProtectedRoute>
                <StockPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/agenda"
            element={
              <ProtectedRoute>
                <AgendaPage />
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

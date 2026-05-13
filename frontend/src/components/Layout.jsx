import React from 'react';
import { Navigate, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

/* ── ProtectedRoute ────────────────────────────────────────── */
export const ProtectedRoute = ({ children, requiredRole }) => {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="spinner-wrap">
        <div className="spinner" />
      </div>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  // Si se pasa requiredRole y el usuario no lo tiene → 403
  if (requiredRole && user.role !== requiredRole && user.role !== 'admin') {
    return (
      <MainLayout>
        <div className="empty-state">
          <div className="empty-state-icon">🔒</div>
          <p>No tienes permiso para acceder a esta sección.</p>
        </div>
      </MainLayout>
    );
  }

  return children;
};

/* ── MainLayout ────────────────────────────────────────────── */
export const MainLayout = ({ children }) => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  const navItems = [
    { to: '/dashboard',  label: '🏠 Inicio'    },
    { to: '/servicios',  label: '🔧 Servicios'  },
    { to: '/cuentas',    label: '🧾 Cuentas'    },
    { to: '/cotizador',  label: '📊 Cotizador'  },
    { to: '/stock',      label: '📦 Stock'      },
    ...(user?.role === 'admin' || user?.role === 'tecnico'
      ? [{ to: '/agenda', label: '📅 Agenda' }]
      : []),
    ...(user?.role === 'admin'
      ? [{ to: '/usuarios', label: '👥 Usuarios' }]
      : []),
  ];

  const roleLabel = { admin: 'Admin', tecnico: 'Técnico', cliente: 'Cliente' };

  return (
    <div className="app-shell">
      <nav className="navbar">
        <div className="navbar-inner">
          <div className="navbar-brand">
            🌡️ Intérmica
            <span>S.A.S</span>
          </div>

          <div className="navbar-nav">
            {navItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
              >
                {item.label}
              </NavLink>
            ))}
          </div>

          <div className="navbar-user">
            <span className={`role-badge ${user?.role}`}>
              {roleLabel[user?.role] ?? user?.role}
            </span>
            <span className="user-chip" title={user?.email}>{user?.email}</span>
            <button className="btn btn-ghost btn-sm" onClick={handleLogout}>
              Salir
            </button>
          </div>
        </div>
      </nav>

      <main className="main-content">{children}</main>

      <footer className="page-footer">
        © 2026 Intérmica S.A.S — Sistema de Gestión de Servicios Termográficos
      </footer>
    </div>
  );
};

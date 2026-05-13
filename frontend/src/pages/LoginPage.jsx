import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export const LoginPage = () => {
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [error, setError]       = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const { login } = useAuth();
  const navigate  = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);
    try {
      const ok = await login(email, password);
      if (ok) {
        navigate('/dashboard', { replace: true });
      } else {
        setError('Credenciales inválidas. Verifica tu email y contraseña.');
      }
    } catch (err) {
      setError(err.message || 'Error al conectar con el servidor.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="login-page">
      <div className="login-card">
        <div className="login-brand">
          <h1>🌡️ Intérmica</h1>
          <p>Sistema de Gestión de Servicios Termográficos</p>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label className="form-label" htmlFor="email">Correo electrónico</label>
            <input
              id="email"
              type="email"
              className="form-control"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="tu@email.com"
              required
              autoComplete="email"
            />
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="password">Contraseña</label>
            <input
              id="password"
              type="password"
              className="form-control"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              required
              autoComplete="current-password"
            />
          </div>

          <button
            type="submit"
            className="btn btn-primary"
            style={{ width: '100%', justifyContent: 'center', marginTop: '.5rem' }}
            disabled={isLoading}
          >
            {isLoading ? 'Ingresando…' : 'Ingresar'}
          </button>
        </form>

        {import.meta.env.DEV && (
          <div className="login-dev-hint">
            <strong>Dev:</strong><br />
            admin@intermica.com / Admin123!<br />
            tecnico@intermica.com / Tecnico123!<br />
            cliente@intermica.com / Cliente123!
          </div>
        )}
      </div>
    </div>
  );
};

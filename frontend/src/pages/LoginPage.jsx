import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export const LoginPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      const success = await login(email, password);
      if (success) {
        navigate('/dashboard', { replace: true });
      } else {
        setError('Credenciales inválidas');
      }
    } catch (err) {
      setError(err.message || 'Error en autenticación');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-blue-600">Intérmica</h1>
          <p className="text-gray-600 mt-2">Sistema de Gestión de Servicios</p>
        </div>

        {error && (
          <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded text-red-700">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="mb-4">
            <label className="block text-gray-700 font-medium mb-2">
              Email
            </label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="tu@email.com"
            />
          </div>

          <div className="mb-6">
            <label className="block text-gray-700 font-medium mb-2">
              Contraseña
            </label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="••••••••"
            />
          </div>

          <button
            type="submit"
            disabled={isLoading}
            className="w-full bg-blue-600 text-white font-medium py-2 rounded hover:bg-blue-700 transition disabled:opacity-50"
          >
            {isLoading ? 'Ingresando...' : 'Ingresar'}
          </button>
        </form>

        {process.env.NODE_ENV === 'development' && (
          <div className="mt-6 text-xs text-gray-400 border-t pt-4">
            <p className="font-semibold mb-1">Credenciales de desarrollo:</p>
            <p>admin@intermica.com / Admin123!</p>
            <p>tecnico@intermica.com / Tecnico123!</p>
            <p>cliente@intermica.com / Cliente123!</p>
          </div>
        )}
      </div>
    </div>
  );
};

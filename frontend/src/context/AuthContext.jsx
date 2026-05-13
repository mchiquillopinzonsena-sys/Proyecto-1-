import React, { createContext, useContext, useState, useCallback, ReactNode } from 'react';
import { apiRequest, setAccessToken } from '../api/client';

// Context para autenticación
const AuthContext = createContext(null);

export const AuthProvider = ({ children, apiBaseUrl }) => {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  const login = useCallback(async (email, password) => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await apiRequest(apiBaseUrl, '/api/v1/auth/login', {
        method: 'POST',
        json: { email, password },
      });

      if (response.success) {
        const { access_token, refresh_token } = response.data;
        setAccessToken(access_token);
        sessionStorage.setItem('refresh_token', refresh_token);

        // Decodificar token para obtener info de usuario
        const payload = JSON.parse(atob(access_token.split('.')[1]));
        setUser({ id: payload.sub, role: payload.role, email: payload.email });

        return true;
      }
    } catch (err) {
      setError(err.message || 'Error en autenticación');
      return false;
    } finally {
      setIsLoading(false);
    }
  }, [apiBaseUrl]);

  const logout = useCallback(() => {
    setAccessToken(null);
    sessionStorage.removeItem('refresh_token');
    setUser(null);
  }, []);

  const value = {
    user,
    isLoading,
    error,
    login,
    logout,
    isAuthenticated: !!user,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe ser usado dentro de AuthProvider');
  }
  return context;
};

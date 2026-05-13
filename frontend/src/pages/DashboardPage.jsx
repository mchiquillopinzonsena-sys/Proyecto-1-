import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { useAuth } from '../context/AuthContext';
import { apiRequest, getAccessToken } from '../api/client';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

const ESTADO_BADGE = {
  pendiente: 'badge-yellow',
  programado: 'badge-blue',
  en_proceso: 'badge-blue',
  completado: 'badge-green',
  cancelado: 'badge-gray',
};

function StatCard({ icon, label, value, type }) {
  return (
    <div className={`kpi-card ${type}`}>
      <div className="kpi-icon">{icon}</div>
      <div className="kpi-label">{label}</div>
      <div className="kpi-value">{value ?? '—'}</div>
    </div>
  );
}

export const DashboardPage = () => {
  const { user } = useAuth();
  const [servicios, setServicios] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const res = await apiRequest(API, '/api/v1/servicios', {
          method: 'GET',
          headers: { Authorization: `Bearer ${getAccessToken()}` },
        });
        setServicios(res.data ?? []);
      } catch (e) {
        setError(e.message);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  const byEstado = (estado) => servicios.filter((s) => s.estado === estado).length;

  return (
    <MainLayout>
      <div className="page-header">
        <h1 className="page-title">Bienvenido, {user?.email?.split('@')[0]} 👋</h1>
        <p className="page-subtitle">Resumen del sistema — Intérmica S.A.S</p>
      </div>

      {loading ? (
        <div className="spinner-wrap"><div className="spinner" /></div>
      ) : error ? (
        <div className="alert alert-error">{error}</div>
      ) : (
        <>
          <div className="kpi-grid">
            <StatCard icon="📋" label="Total servicios" value={servicios.length} type="blue" />
            <StatCard icon="⏳" label="Pendientes" value={byEstado('pendiente')} type="yellow" />
            <StatCard icon="⚙️" label="En proceso" value={byEstado('en_proceso')} type="blue" />
            <StatCard icon="✅" label="Completados" value={byEstado('completado')} type="green" />
          </div>

          <div className="card">
            <div className="card-header">
              <span className="card-title">Últimos servicios</span>
            </div>
            <div className="table-wrapper">
              {servicios.length === 0 ? (
                <div className="empty-state">
                  <div className="empty-state-icon">📭</div>
                  <p>No hay servicios registrados.</p>
                </div>
              ) : (
                <table>
                  <thead>
                    <tr>
                      <th>N° Servicio</th>
                      <th>Estado</th>
                      <th>Fecha solicitud</th>
                      <th>Valor estimado</th>
                    </tr>
                  </thead>
                  <tbody>
                    {servicios.slice(0, 10).map((s) => (
                      <tr key={s.id}>
                        <td><strong>{s.numero_servicio}</strong></td>
                        <td>
                          <span className={`badge ${ESTADO_BADGE[s.estado] ?? 'badge-gray'}`}>
                            {s.estado}
                          </span>
                        </td>
                        <td>{s.fecha_solicitud}</td>
                        <td>{s.valor_estimado ? `$ ${Number(s.valor_estimado).toLocaleString('es-CO')}` : '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </>
      )}
    </MainLayout>
  );
};

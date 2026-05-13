import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

const COLORS = ['#1d4ed8', '#f59e0b', '#10b981', '#6b7280', '#ef4444'];

const StatCard = ({ title, value, subtitle, color = 'blue' }) => (
  <div className={`kpi-card kpi-${color}`}>
    <div className="kpi-value">{value}</div>
    <div className="kpi-title">{title}</div>
    {subtitle && <div className="kpi-subtitle">{subtitle}</div>}
  </div>
);

export const DashboardPage = () => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    apiRequest(API, '/api/v1/dashboard', {
      method: 'GET',
      headers: { Authorization: `Bearer ${getAccessToken()}` },
    })
      .then(res => {
        setData(res.data);
        setError(null);
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <MainLayout><div className="spinner-wrap"><div className="spinner" /></div></MainLayout>;
  }

  if (error) {
    return <MainLayout><div className="alert alert-error">{error}</div></MainLayout>;
  }

  if (!data) return null;

  // Preparar datos para gráficos
  const chartData = Object.entries(data.servicios.desglose || {}).map(([key, value]) => ({
    name: key.toUpperCase(),
    value: Number(value)
  }));

  const mockFinanzas = [
    { name: 'Ene', ingresos: 4000 },
    { name: 'Feb', ingresos: 3000 },
    { name: 'Mar', ingresos: 5000 },
    { name: 'Abr', ingresos: 7000 },
    { name: 'May', ingresos: 6500 },
  ];

  return (
    <MainLayout>
      <div className="page-header">
        <h1 className="page-title">📊 Panel de Control</h1>
        <p className="page-subtitle">Métricas y KPIs del sistema en tiempo real</p>
      </div>

      <div className="kpi-grid">
        <StatCard
          title="Total Servicios"
          value={data.servicios.total}
          subtitle="Registrados en el sistema"
          color="blue"
        />
        <StatCard
          title="Servicios Pendientes"
          value={data.servicios.pendientes}
          subtitle="Requieren programación"
          color="yellow"
        />
        <StatCard
          title="Cuentas Vencidas"
          value={data.cuentas_vencidas}
          subtitle="Facturas por cobrar"
          color="red"
        />
        <StatCard
          title="Alertas de Stock"
          value={data.alertas_stock}
          subtitle="Artículos bajo el mínimo"
          color="red"
        />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: '2rem', marginTop: '2rem' }}>
        
        {/* Gráfico de Torta */}
        <div className="card">
          <h3 style={{marginTop:0, color:'#1e3a8a', marginBottom:'1rem'}}>Estado de Servicios</h3>
          <div style={{ width: '100%', height: 300 }}>
            <ResponsiveContainer>
              <PieChart>
                <Pie
                  data={chartData}
                  innerRadius={60}
                  outerRadius={100}
                  paddingAngle={5}
                  dataKey="value"
                  label={({name, percent}) => `${name} ${(percent * 100).toFixed(0)}%`}
                >
                  {chartData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Gráfico de Barras */}
        <div className="card">
          <h3 style={{marginTop:0, color:'#1e3a8a', marginBottom:'1rem'}}>Ingresos Proyectados (Muestra)</h3>
          <div style={{ width: '100%', height: 300 }}>
            <ResponsiveContainer>
              <BarChart data={mockFinanzas}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" />
                <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{fill: '#6b7280'}} />
                <YAxis axisLine={false} tickLine={false} tick={{fill: '#6b7280'}} />
                <Tooltip cursor={{fill: '#f3f4f6'}} />
                <Bar dataKey="ingresos" fill="#10b981" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

      </div>
    </MainLayout>
  );
};

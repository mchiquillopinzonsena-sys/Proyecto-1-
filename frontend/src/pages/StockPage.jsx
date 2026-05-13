import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

export const StockPage = () => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    apiRequest(API, '/api/v1/stock', { method: 'GET', headers: { Authorization: `Bearer ${getAccessToken()}` } })
      .then(r => setItems(r.data ?? []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  return (
    <MainLayout>
      <div className="page-header">
        <h1 className="page-title">📦 Inventario / Stock</h1>
        <p className="page-subtitle">Control de materiales y herramientas</p>
      </div>
      {loading ? <div className="spinner-wrap"><div className="spinner" /></div>
        : error ? <div className="alert alert-error">{error}</div>
          : (
            <div className="card">
              <div className="table-wrapper">
                {items.length === 0
                  ? <div className="empty-state"><div className="empty-state-icon">📦</div><p>Sin artículos.</p></div>
                  : <table>
                    <thead><tr><th>Código</th><th>Artículo</th><th>Disponible</th><th>Mínimo</th><th>Estado</th><th>Precio unit.</th></tr></thead>
                    <tbody>
                      {items.map(i => {
                        const bajo = Number(i.cantidad_disponible) < Number(i.cantidad_minima);
                        return (
                          <tr key={i.id}>
                            <td><code>{i.codigo_articulo}</code></td>
                            <td>{i.nombre_articulo}</td>
                            <td><strong style={{ color: bajo ? 'var(--red-600)' : 'inherit' }}>{i.cantidad_disponible}</strong></td>
                            <td>{i.cantidad_minima}</td>
                            <td><span className={`badge ${bajo ? 'badge-red' : 'badge-green'}`}>{bajo ? 'Stock bajo' : 'OK'}</span></td>
                            <td>{i.precio_unitario ? `$ ${Number(i.precio_unitario).toLocaleString('es-CO')}` : '—'}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                }
              </div>
            </div>
          )}
    </MainLayout>
  );
};

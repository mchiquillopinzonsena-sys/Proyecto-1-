import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

export const CuentasPage = () => {
  const [cuentas, setCuentas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagando, setPagando] = useState(null);
  const [monto, setMonto] = useState('');
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await apiRequest(API, '/api/v1/cuentas', {
        method: 'GET',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
      });
      setCuentas(res.data ?? []);
      setError(null);
    } catch (e) {
      setError(e.status === 404
        ? 'El endpoint GET /api/v1/cuentas aún no está implementado.'
        : e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const handlePDF = (id) =>
    window.open(`${API}/api/v1/cuentas/${id}/pdf`, '_blank');

  const handlePagar = async () => {
    if (!monto || Number(monto) <= 0) return alert('Monto inválido');
    setSaving(true);
    try {
      await apiRequest(API, `/api/v1/cuentas/${pagando.id}/pagar`, {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
        json: { monto: Number(monto), fecha_pago: new Date().toISOString().slice(0, 10) },
      });
      setPagando(null); setMonto('');
      await load();
    } catch (e) { alert(e.message); } finally { setSaving(false); }
  };

  const BADGE = { pendiente: 'badge-yellow', parcial: 'badge-yellow', pagada: 'badge-green', vencida: 'badge-red', cancelada: 'badge-gray' };

  return (
    <MainLayout>
      <div className="page-header">
        <h1 className="page-title">🧾 Cuentas de Cobro</h1>
        <p className="page-subtitle">Facturas y estados de pago</p>
      </div>
      {loading ? <div className="spinner-wrap"><div className="spinner" /></div>
        : error ? <div className="alert alert-error">{error}</div>
          : (
            <div className="card">
              <div className="table-wrapper">
                {cuentas.length === 0
                  ? <div className="empty-state"><div className="empty-state-icon">🧾</div><p>Sin cuentas.</p></div>
                  : <table>
                    <thead><tr><th>N° Cuenta</th><th>Estado</th><th>Emisión</th><th>Vencimiento</th><th>Total</th><th>Acciones</th></tr></thead>
                    <tbody>
                      {cuentas.map((c) => (
                        <tr key={c.id}>
                          <td><strong>{c.numero}</strong></td>
                          <td><span className={`badge ${BADGE[c.estado] ?? 'badge-gray'}`}>{c.estado}</span></td>
                          <td>{c.fecha_emision}</td><td>{c.fecha_vencimiento}</td>
                          <td>$ {Number(c.total ?? 0).toLocaleString('es-CO')}</td>
                          <td style={{ display: 'flex', gap: '.4rem' }}>
                            <button className="btn btn-ghost btn-sm" onClick={() => handlePDF(c.id)}>📄 PDF</button>
                            {c.estado !== 'pagada' && <button className="btn btn-primary btn-sm" onClick={() => { setPagando(c); setMonto(''); }}>💳 Pagar</button>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                }
              </div>
            </div>
          )}
      {pagando && (
        <div className="modal-overlay" onClick={() => setPagando(null)}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <span className="modal-title">Pagar — {pagando.numero}</span>
              <button className="btn btn-ghost btn-icon" onClick={() => setPagando(null)}>✕</button>
            </div>
            <div className="modal-body">
              <div className="form-group">
                <label className="form-label">Monto (COP)</label>
                <input className="form-control" type="number" min="1" value={monto} onChange={e => setMonto(e.target.value)} />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-ghost" onClick={() => setPagando(null)}>Cancelar</button>
              <button className="btn btn-primary" disabled={saving} onClick={handlePagar}>{saving ? 'Guardando…' : 'Confirmar'}</button>
            </div>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

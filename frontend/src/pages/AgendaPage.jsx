import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';
import { useAuth } from '../context/AuthContext';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

const TIPOS_BLOQUEO = ['no_disponible', 'vacaciones', 'mantenimiento', 'capacitacion'];

export const AgendaPage = () => {
  const { user } = useAuth();
  const [bloqueos, setBloqueos] = useState([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState(null);

  const [showModal, setShowModal] = useState(false);
  const [form, setForm]           = useState({ fecha_inicio: '', fecha_fin: '', tipo_bloqueo: 'no_disponible', razon: '', tecnico_id: '' });
  const [saving, setSaving]       = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await apiRequest(API, '/api/v1/agenda', {
        method: 'GET',
        headers: { Authorization: `Bearer ${getAccessToken()}` }
      });
      setBloqueos(res.data ?? []);
      setError(null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      await apiRequest(API, '/api/v1/agenda', {
        method: 'POST',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
        json: form
      });
      setShowModal(false);
      setForm({ fecha_inicio: '', fecha_fin: '', tipo_bloqueo: 'no_disponible', razon: '', tecnico_id: '' });
      await load();
    } catch(e) {
      alert(e.message);
    } finally {
      setSaving(false);
    }
  };

  const isTecnicoOrAdmin = user?.role === 'admin' || user?.role === 'tecnico';

  return (
    <MainLayout>
      <div className="page-header" style={{display:'flex',justifyContent:'space-between',alignItems:'flex-start'}}>
        <div>
          <h1 className="page-title">📅 Agenda y Disponibilidad</h1>
          <p className="page-subtitle">Control de bloqueos de agenda de los técnicos</p>
        </div>
        {isTecnicoOrAdmin && (
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>+ Registrar Bloqueo</button>
        )}
      </div>

      {loading ? <div className="spinner-wrap"><div className="spinner"/></div>
        : error ? <div className="alert alert-error">{error}</div>
        : (
          <div className="card">
            <div className="table-wrapper">
              {bloqueos.length === 0
                ? <div className="empty-state"><div className="empty-state-icon">📅</div><p>No hay bloqueos registrados.</p></div>
                : <table>
                    <thead>
                      <tr>
                        <th>Técnico</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Razón</th>
                      </tr>
                    </thead>
                    <tbody>
                      {bloqueos.map(b => (
                        <tr key={b.id}>
                          <td><strong>{b.numero_empleado}</strong></td>
                          <td><span className="badge badge-gray">{b.tipo_bloqueo}</span></td>
                          <td>{b.fecha_inicio}</td>
                          <td>{b.fecha_fin}</td>
                          <td>{b.razon || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
              }
            </div>
          </div>
        )}

      {/* Modal crear bloqueo */}
      {showModal && (
        <div className="modal-overlay" onClick={() => setShowModal(false)}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <span className="modal-title">Registrar Bloqueo de Agenda</span>
              <button className="btn btn-ghost btn-icon" onClick={() => setShowModal(false)}>✕</button>
            </div>
            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                {user?.role === 'admin' && (
                  <div className="form-group">
                    <label className="form-label">ID Técnico<span className="req">*</span></label>
                    <input className="form-control" type="number" required
                      value={form.tecnico_id} onChange={e => setForm({...form, tecnico_id: e.target.value})} />
                    <span style={{fontSize: '.7rem', color:'var(--gray-500)'}}>Sólo para Admin. Ej: 1 o 2</span>
                  </div>
                )}
                
                <div className="form-row">
                  <div className="form-group">
                    <label className="form-label">Desde<span className="req">*</span></label>
                    <input className="form-control" type="date" required
                      value={form.fecha_inicio} onChange={e => setForm({...form, fecha_inicio: e.target.value})} />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Hasta<span className="req">*</span></label>
                    <input className="form-control" type="date" required
                      value={form.fecha_fin} onChange={e => setForm({...form, fecha_fin: e.target.value})} />
                  </div>
                </div>

                <div className="form-group">
                  <label className="form-label">Tipo de Ausencia<span className="req">*</span></label>
                  <select className="form-control" required
                    value={form.tipo_bloqueo} onChange={e => setForm({...form, tipo_bloqueo: e.target.value})}>
                    {TIPOS_BLOQUEO.map(t => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Razón / Notas</label>
                  <textarea className="form-control" rows="2"
                    value={form.razon} onChange={e => setForm({...form, razon: e.target.value})}></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-ghost" onClick={() => setShowModal(false)}>Cancelar</button>
                <button type="submit" className="btn btn-primary" disabled={saving}>
                  {saving ? 'Guardando…' : 'Registrar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

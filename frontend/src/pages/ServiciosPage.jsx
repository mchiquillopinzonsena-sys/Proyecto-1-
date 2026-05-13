import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { useAuth } from '../context/AuthContext';
import { apiRequest, getAccessToken } from '../api/client';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

const ESTADOS = ['pendiente', 'programado', 'en_proceso', 'completado', 'cancelado'];

const BADGE = {
  pendiente: 'badge-yellow',
  programado: 'badge-blue',
  en_proceso: 'badge-blue',
  completado: 'badge-green',
  cancelado: 'badge-gray',
};

export const ServiciosPage = () => {
  const { user } = useAuth();
  const [servicios, setServicios] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filtro, setFiltro] = useState('');
  const [selected, setSelected] = useState(null);
  const [cambiando, setCambiando] = useState(false);
  const [nuevoEstado, setNuevoEstado] = useState('');

  const [showCrear, setShowCrear] = useState(false);
  const [formSvc, setFormSvc]     = useState({ cliente_id: '', descripcion: '', valor_estimado: '' });
  const [savingSvc, setSavingSvc] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await apiRequest(API, '/api/v1/servicios', {
        method: 'GET',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
      });
      setServicios(res.data ?? []);
      setError(null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const canChangeEstado = user?.role === 'admin' || user?.role === 'tecnico';
  const canCreate = user?.role === 'admin';

  const handleCrearServicio = async (e) => {
    e.preventDefault();
    setSavingSvc(true);
    try {
      await apiRequest(API, '/api/v1/servicios', {
        method: 'POST',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
        json: {
          cliente_id: Number(formSvc.cliente_id),
          descripcion: formSvc.descripcion,
          valor_estimado: Number(formSvc.valor_estimado) || 0
        }
      });
      setShowCrear(false);
      setFormSvc({ cliente_id: '', descripcion: '', valor_estimado: '' });
      await load();
    } catch(e) {
      alert(e.message);
    } finally {
      setSavingSvc(false);
    }
  };

  const handleCambiarEstado = async () => {
    if (!nuevoEstado || !selected) return;
    setCambiando(true);
    try {
      await apiRequest(API, `/api/v1/servicios/${selected.id}/estado`, {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
        json: { estado: nuevoEstado },
      });
      setSelected(null);
      await load();
    } catch (e) {
      alert('Error: ' + e.message);
    } finally {
      setCambiando(false);
    }
  };

  const lista = filtro
    ? servicios.filter((s) => s.estado === filtro)
    : servicios;

  return (
    <MainLayout>
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="page-title">🔧 Servicios</h1>
          <p className="page-subtitle">Gestión de servicios termográficos</p>
        </div>
        {canCreate && (
          <button className="btn btn-primary" onClick={() => setShowCrear(true)}>+ Nuevo Servicio</button>
        )}
      </div>

      <div className="filters-bar">
        <select
          className="filter-select"
          value={filtro}
          onChange={(e) => setFiltro(e.target.value)}
        >
          <option value="">Todos los estados</option>
          {ESTADOS.map((e) => (
            <option key={e} value={e}>{e}</option>
          ))}
        </select>
        <span style={{ color: 'var(--gray-400)', fontSize: '.8rem' }}>
          {lista.length} resultado{lista.length !== 1 ? 's' : ''}
        </span>
      </div>

      {loading ? (
        <div className="spinner-wrap"><div className="spinner" /></div>
      ) : error ? (
        <div className="alert alert-error">{error}</div>
      ) : (
        <div className="card">
          <div className="table-wrapper">
            {lista.length === 0 ? (
              <div className="empty-state">
                <div className="empty-state-icon">📭</div>
                <p>No hay servicios para mostrar.</p>
              </div>
            ) : (
              <table>
                <thead>
                  <tr>
                    <th>N° Servicio</th>
                    <th>Estado</th>
                    <th>Fecha solicitud</th>
                    <th>Fecha programada</th>
                    <th>Valor estimado</th>
                    {canChangeEstado && <th>Acción</th>}
                  </tr>
                </thead>
                <tbody>
                  {lista.map((s) => (
                    <tr key={s.id}>
                      <td><strong>{s.numero_servicio}</strong></td>
                      <td>
                        <span className={`badge ${BADGE[s.estado] ?? 'badge-gray'}`}>
                          {s.estado}
                        </span>
                      </td>
                      <td>{s.fecha_solicitud ?? '—'}</td>
                      <td>{s.fecha_programada ?? '—'}</td>
                      <td>
                        {s.valor_estimado
                          ? `$ ${Number(s.valor_estimado).toLocaleString('es-CO')}`
                          : '—'}
                      </td>
                      {canChangeEstado && (
                        <td>
                          <button
                            className="btn btn-ghost btn-sm"
                            onClick={() => {
                              setSelected(s);
                              setNuevoEstado(s.estado);
                            }}
                          >
                            Cambiar estado
                          </button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* Modal cambiar estado */}
      {selected && (
        <div className="modal-overlay" onClick={() => setSelected(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <span className="modal-title">Cambiar estado — {selected.numero_servicio}</span>
              <button className="btn btn-ghost btn-icon" onClick={() => setSelected(null)}>✕</button>
            </div>
            <div className="modal-body">
              <div className="form-group">
                <label className="form-label">Nuevo estado</label>
                <select
                  className="form-control"
                  value={nuevoEstado}
                  onChange={(e) => setNuevoEstado(e.target.value)}
                >
                  {ESTADOS.map((e) => (
                    <option key={e} value={e}>{e}</option>
                  ))}
                </select>
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-ghost" onClick={() => setSelected(null)}>Cancelar</button>
              <button
                className="btn btn-primary"
                disabled={cambiando || nuevoEstado === selected.estado}
                onClick={handleCambiarEstado}
              >
                {cambiando ? 'Guardando…' : 'Confirmar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal crear servicio */}
      {showCrear && (
        <div className="modal-overlay" onClick={() => setShowCrear(false)}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <span className="modal-title">Nuevo Servicio</span>
              <button className="btn btn-ghost btn-icon" onClick={() => setShowCrear(false)}>✕</button>
            </div>
            <form onSubmit={handleCrearServicio}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">ID del Cliente<span className="req">*</span></label>
                  <input className="form-control" type="number" required
                    value={formSvc.cliente_id} onChange={e => setFormSvc({...formSvc, cliente_id: e.target.value})} />
                  <span style={{fontSize: '.7rem', color:'var(--gray-500)'}}>Ejemplo: 1 o 2 (ID de tabla clientes)</span>
                </div>
                <div className="form-group">
                  <label className="form-label">Descripción<span className="req">*</span></label>
                  <textarea className="form-control" rows="3" required
                    value={formSvc.descripcion} onChange={e => setFormSvc({...formSvc, descripcion: e.target.value})}></textarea>
                </div>
                <div className="form-group">
                  <label className="form-label">Valor Estimado (COP)</label>
                  <input className="form-control" type="number"
                    value={formSvc.valor_estimado} onChange={e => setFormSvc({...formSvc, valor_estimado: e.target.value})} />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-ghost" onClick={() => setShowCrear(false)}>Cancelar</button>
                <button type="submit" className="btn btn-primary" disabled={savingSvc}>
                  {savingSvc ? 'Creando…' : 'Crear'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';
import { useAuth } from '../context/AuthContext';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
const ROLES = ['admin', 'tecnico', 'cliente'];
const ROLE_BADGE = { admin: 'badge-blue', tecnico: 'badge-green', cliente: 'badge-yellow' };

export const UsuariosPage = () => {
  const { user: me } = useAuth();
  const [usuarios, setUsuarios] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ email: '', nombre_completo: '', password: '', rol: 'cliente' });
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await apiRequest(API, '/api/v1/usuarios', {
        method: 'GET', headers: { Authorization: `Bearer ${getAccessToken()}` },
      });
      setUsuarios(res.data ?? []);
      setError(null);
    } catch (e) { setError(e.message); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const handleCrear = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      await apiRequest(API, '/api/v1/usuarios', {
        method: 'POST', headers: { Authorization: `Bearer ${getAccessToken()}` }, json: form,
      });
      setShowForm(false);
      setForm({ email: '', nombre_completo: '', password: '', rol: 'cliente' });
      await load();
    } catch (e) { alert(e.message); }
    finally { setSaving(false); }
  };

  const handleDesactivar = async (id) => {
    if (!confirm('¿Desactivar este usuario?')) return;
    try {
      await apiRequest(API, `/api/v1/usuarios/${id}`, {
        method: 'DELETE', headers: { Authorization: `Bearer ${getAccessToken()}` },
      });
      await load();
    } catch (e) { alert(e.message); }
  };

  return (
    <MainLayout>
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="page-title">👥 Usuarios</h1>
          <p className="page-subtitle">Gestión de accesos al sistema</p>
        </div>
        {me?.role === 'admin' && (
          <button className="btn btn-primary" onClick={() => setShowForm(true)}>+ Nuevo usuario</button>
        )}
      </div>

      {loading ? <div className="spinner-wrap"><div className="spinner" /></div>
        : error ? <div className="alert alert-error">{error}</div>
          : (
            <div className="card">
              <div className="table-wrapper">
                {usuarios.length === 0
                  ? <div className="empty-state"><div className="empty-state-icon">👥</div><p>Sin usuarios.</p></div>
                  : <table>
                    <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th>{me?.role === 'admin' && <th>Acción</th>}</tr></thead>
                    <tbody>
                      {usuarios.map(u => (
                        <tr key={u.id}>
                          <td>{u.nombre_completo}</td>
                          <td>{u.email}</td>
                          <td><span className={`badge ${ROLE_BADGE[u.rol] ?? 'badge-gray'}`}>{u.rol}</span></td>
                          <td><span className={`badge ${u.activo ? 'badge-green' : 'badge-gray'}`}>{u.activo ? 'Activo' : 'Inactivo'}</span></td>
                          {me?.role === 'admin' && (
                            <td>
                              {u.activo && u.id !== me.id && (
                                <button className="btn btn-danger btn-sm" onClick={() => handleDesactivar(u.id)}>Desactivar</button>
                              )}
                            </td>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                }
              </div>
            </div>
          )}

      {/* Modal crear usuario */}
      {showForm && (
        <div className="modal-overlay" onClick={() => setShowForm(false)}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <span className="modal-title">Nuevo usuario</span>
              <button className="btn btn-ghost btn-icon" onClick={() => setShowForm(false)}>✕</button>
            </div>
            <form onSubmit={handleCrear}>
              <div className="modal-body">
                {['email', 'nombre_completo', 'password'].map(field => (
                  <div className="form-group" key={field}>
                    <label className="form-label">{field.replace('_', ' ')}<span className="req">*</span></label>
                    <input className="form-control" required
                      type={field === 'password' ? 'password' : field === 'email' ? 'email' : 'text'}
                      value={form[field]} onChange={e => setForm(p => ({ ...p, [field]: e.target.value }))} />
                  </div>
                ))}
                <div className="form-group">
                  <label className="form-label">Rol</label>
                  <select className="form-control" value={form.rol} onChange={e => setForm(p => ({ ...p, rol: e.target.value }))}>
                    {ROLES.map(r => <option key={r} value={r}>{r}</option>)}
                  </select>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>Cancelar</button>
                <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Creando…' : 'Crear'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

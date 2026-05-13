import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';
import { useAuth } from '../context/AuthContext';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

const INITIAL_FORM = {
  codigo_articulo: '',
  nombre_articulo: '',
  descripcion: '',
  cantidad_disponible: 0,
  cantidad_minima: 0,
  precio_unitario: ''
};

export const StockPage = () => {
  const { user } = useAuth();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState(INITIAL_FORM);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await apiRequest(API, '/api/v1/stock', {
        method: 'GET',
        headers: { Authorization: `Bearer ${getAccessToken()}` }
      });
      setItems(res.data ?? []);
      setError(null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const canManage = user?.role === 'admin' || user?.role === 'tecnico';

  const handleOpenCreate = () => {
    setForm(INITIAL_FORM);
    setEditingId(null);
    setShowModal(true);
  };

  const handleOpenEdit = (item) => {
    setForm({
      codigo_articulo: item.codigo_articulo,
      nombre_articulo: item.nombre_articulo,
      descripcion: item.descripcion || '',
      cantidad_disponible: item.cantidad_disponible,
      cantidad_minima: item.cantidad_minima,
      precio_unitario: item.precio_unitario || ''
    });
    setEditingId(item.id);
    setShowModal(true);
  };

  const handleDelete = async (id) => {
    if (!confirm('¿Seguro que deseas eliminar este artículo de la tienda/inventario?')) return;
    try {
      await apiRequest(API, `/api/v1/stock/${id}`, {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
        json: { activo: false }
      });
      await load();
    } catch(e) {
      alert(e.message);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload = {
        ...form,
        cantidad_disponible: Number(form.cantidad_disponible),
        cantidad_minima: Number(form.cantidad_minima),
        precio_unitario: form.precio_unitario ? Number(form.precio_unitario) : null
      };

      if (editingId) {
        // En PATCH, omitimos codigo_articulo porque el backend no deja cambiarlo o lo ignorará
        const { codigo_articulo, ...patchPayload } = payload;
        await apiRequest(API, `/api/v1/stock/${editingId}`, {
          method: 'PATCH',
          headers: { Authorization: `Bearer ${getAccessToken()}` },
          json: patchPayload
        });
      } else {
        await apiRequest(API, '/api/v1/stock', {
          method: 'POST',
          headers: { Authorization: `Bearer ${getAccessToken()}` },
          json: payload
        });
      }
      setShowModal(false);
      await load();
    } catch (e) {
      alert(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <MainLayout>
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="page-title">📦 Inventario / Tienda</h1>
          <p className="page-subtitle">Control de materiales, herramientas y productos</p>
        </div>
        {canManage && (
          <button className="btn btn-primary" onClick={handleOpenCreate}>+ Nuevo Artículo</button>
        )}
      </div>

      {loading ? <div className="spinner-wrap"><div className="spinner" /></div>
        : error ? <div className="alert alert-error">{error}</div>
          : (
            <div className="card">
              <div className="table-wrapper">
                {items.length === 0
                  ? <div className="empty-state"><div className="empty-state-icon">📦</div><p>Sin artículos.</p></div>
                  : <table>
                    <thead>
                      <tr>
                        <th>Código</th>
                        <th>Artículo</th>
                        <th>Disponible</th>
                        <th>Mínimo</th>
                        <th>Estado</th>
                        <th>Precio unit.</th>
                        {canManage && <th>Acciones</th>}
                      </tr>
                    </thead>
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
                            {canManage && (
                              <td style={{ display: 'flex', gap: '.4rem' }}>
                                <button className="btn btn-ghost btn-sm" onClick={() => handleOpenEdit(i)}>Editar</button>
                                <button className="btn btn-danger btn-sm" onClick={() => handleDelete(i.id)}>Borrar</button>
                              </td>
                            )}
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                }
              </div>
            </div>
          )}

      {/* Modal CRUD */}
      {showModal && (
        <div className="modal-overlay" onClick={() => setShowModal(false)}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <span className="modal-title">{editingId ? 'Editar Artículo' : 'Nuevo Artículo'}</span>
              <button className="btn btn-ghost btn-icon" onClick={() => setShowModal(false)}>✕</button>
            </div>
            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                <div className="form-row">
                  <div className="form-group">
                    <label className="form-label">Código (SKU)<span className="req">*</span></label>
                    <input className="form-control" required disabled={!!editingId}
                      value={form.codigo_articulo} onChange={e => setForm({...form, codigo_articulo: e.target.value})} />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Precio Unitario (COP)</label>
                    <input className="form-control" type="number"
                      value={form.precio_unitario} onChange={e => setForm({...form, precio_unitario: e.target.value})} />
                  </div>
                </div>

                <div className="form-group">
                  <label className="form-label">Nombre del Artículo<span className="req">*</span></label>
                  <input className="form-control" required
                    value={form.nombre_articulo} onChange={e => setForm({...form, nombre_articulo: e.target.value})} />
                </div>

                <div className="form-group">
                  <label className="form-label">Descripción</label>
                  <textarea className="form-control" rows="2"
                    value={form.descripcion} onChange={e => setForm({...form, descripcion: e.target.value})}></textarea>
                </div>

                <div className="form-row">
                  <div className="form-group">
                    <label className="form-label">Cant. Disponible<span className="req">*</span></label>
                    <input className="form-control" type="number" required min="0" disabled={!!editingId}
                      value={form.cantidad_disponible} onChange={e => setForm({...form, cantidad_disponible: e.target.value})} />
                    {!!editingId && <span style={{fontSize:'.7rem', color:'var(--gray-500)'}}>Usa movimientos de stock para cambiar esto.</span>}
                  </div>
                  <div className="form-group">
                    <label className="form-label">Stock Mínimo</label>
                    <input className="form-control" type="number" min="0"
                      value={form.cantidad_minima} onChange={e => setForm({...form, cantidad_minima: e.target.value})} />
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-ghost" onClick={() => setShowModal(false)}>Cancelar</button>
                <button type="submit" className="btn btn-primary" disabled={saving}>
                  {saving ? 'Guardando…' : 'Guardar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

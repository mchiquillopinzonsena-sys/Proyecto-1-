import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

export const CotizadorPage = () => {
  const [equipos, setEquipos] = useState([]);
  const [lineas, setLineas] = useState([]);
  const [resultado, setResultado] = useState(null);
  const [loading, setLoading] = useState(true);
  const [calculando, setCalculando] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    Promise.all([
      apiRequest(API, '/api/v1/cotizador/equipos', { method: 'GET', headers: { Authorization: `Bearer ${getAccessToken()}` } }),
    ]).then(([eq]) => { setEquipos(eq.data ?? []); })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  const addLinea = () => setLineas(prev => [...prev, { equipo_id: '', cantidad: 1 }]);
  const removeLinea = (i) => setLineas(prev => prev.filter((_, idx) => idx !== i));
  const updateLinea = (i, field, val) =>
    setLineas(prev => prev.map((l, idx) => idx === i ? { ...l, [field]: val } : l));

  const handleCotizar = async () => {
    const payload = lineas
      .filter(l => l.equipo_id)
      .map(l => ({ equipo_id: Number(l.equipo_id), cantidad: Number(l.cantidad) }));
    if (!payload.length) return alert('Agrega al menos un equipo.');
    setCalculando(true);
    try {
      const res = await apiRequest(API, '/api/v1/cotizador/cotizar', {
        method: 'POST',
        headers: { Authorization: `Bearer ${getAccessToken()}` },
        json: { equipos: payload },
      });
      setResultado(res.data);
    } catch (e) { alert(e.message); }
    finally { setCalculando(false); }
  };

  return (
    <MainLayout>
      <div className="page-header">
        <h1 className="page-title">📊 Cotizador</h1>
        <p className="page-subtitle">Simulador de cotizaciones termográficas</p>
      </div>
      {loading ? <div className="spinner-wrap"><div className="spinner" /></div>
        : error ? <div className="alert alert-error">{error}</div>
          : (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
              {/* Selector de equipos */}
              <div className="card">
                <div className="card-header">
                  <span className="card-title">Equipos a inspeccionar</span>
                  <button className="btn btn-primary btn-sm" onClick={addLinea}>+ Agregar</button>
                </div>
                <div className="card-body">
                  {lineas.length === 0
                    ? <p style={{ color: 'var(--gray-400)', textAlign: 'center', padding: '1rem' }}>Agrega equipos para cotizar.</p>
                    : lineas.map((l, i) => (
                      <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 80px 32px', gap: '.5rem', marginBottom: '.5rem', alignItems: 'center' }}>
                        <select className="form-control" value={l.equipo_id} onChange={e => updateLinea(i, 'equipo_id', e.target.value)}>
                          <option value="">Seleccionar equipo…</option>
                          {equipos.map(eq => <option key={eq.id} value={eq.id}>{eq.nombre_equipo}</option>)}
                        </select>
                        <input className="form-control" type="number" min="1" value={l.cantidad}
                          onChange={e => updateLinea(i, 'cantidad', e.target.value)} placeholder="Cant." />
                        <button className="btn btn-danger btn-icon btn-sm" onClick={() => removeLinea(i)}>✕</button>
                      </div>
                    ))
                  }
                  {lineas.length > 0 && (
                    <button className="btn btn-primary" style={{ width: '100%', marginTop: '1rem', justifyContent: 'center' }}
                      disabled={calculando} onClick={handleCotizar}>
                      {calculando ? 'Calculando…' : '🧮 Calcular cotización'}
                    </button>
                  )}
                </div>
              </div>

              {/* Resultado */}
              <div className="card">
                <div className="card-header"><span className="card-title">Resultado</span></div>
                <div className="card-body">
                  {!resultado
                    ? <div className="empty-state"><div className="empty-state-icon">📊</div><p>El resultado aparecerá aquí.</p></div>
                    : <>
                      {resultado.lineas?.map((l, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '.35rem 0', borderBottom: '1px solid var(--gray-100)', fontSize: '.875rem' }}>
                          <span>{l.nombre_equipo} ×{l.cantidad}</span>
                          <strong>$ {Number(l.subtotal_linea).toLocaleString('es-CO')}</strong>
                        </div>
                      ))}
                      <div style={{ marginTop: '1rem', display: 'grid', gap: '.25rem', fontSize: '.875rem' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                          <span>Subtotal:</span>
                          <span>$ {Number(resultado.subtotal_equipos).toLocaleString('es-CO')}</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                          <span>IVA 19%:</span>
                          <span>$ {Number(resultado.iva_19).toLocaleString('es-CO')}</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: '1rem', borderTop: '2px solid var(--blue-500)', paddingTop: '.5rem', marginTop: '.5rem' }}>
                          <span>TOTAL:</span>
                          <span style={{ color: 'var(--blue-700)' }}>$ {Number(resultado.total).toLocaleString('es-CO')}</span>
                        </div>
                      </div>
                    </>
                  }
                </div>
              </div>
            </div>
          )}
    </MainLayout>
  );
};

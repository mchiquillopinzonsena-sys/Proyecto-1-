import React, { useEffect, useState } from 'react';
import { MainLayout } from '../components/Layout';
import { apiRequest, getAccessToken } from '../api/client';

const API = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

export const CuentasPage = () => {
  const [cuentas, setCuentas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagando, setPagando] = useState(null);
  
  // Payment Simulation State
  const [monto, setMonto] = useState('');
  const [card, setCard] = useState({ number: '', name: '', expiry: '', cvc: '' });
  const [step, setStep] = useState('form'); // form -> processing -> success

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
    window.open(`${API}/api/v1/cuentas/${id}/pdf?token=${getAccessToken()}`, '_blank');

  const iniciarPago = (c) => {
    setPagando(c);
    setMonto(c.total); // Set total as default
    setCard({ number: '', name: '', expiry: '', cvc: '' });
    setStep('form');
  };

  const handleSimularPago = async (e) => {
    e.preventDefault();
    if (!monto || Number(monto) <= 0) return alert('Monto inválido');
    if (!card.number || !card.name || !card.expiry || !card.cvc) return alert('Completa los datos de la tarjeta');
    
    setStep('processing');

    // Simulate network delay / bank processing (2.5 seconds)
    setTimeout(async () => {
      try {
        await apiRequest(API, `/api/v1/cuentas/${pagando.id}/pagar`, {
          method: 'PATCH',
          headers: { Authorization: `Bearer ${getAccessToken()}` },
          json: { monto: Number(monto), fecha_pago: new Date().toISOString().slice(0, 10), metodo_pago: 'Tarjeta Crédito Simulada' },
        });
        setStep('success');
      } catch (e) { 
        alert('Error en el pago: ' + e.message);
        setStep('form');
      }
    }, 2500);
  };

  const cerrarModal = async () => {
    const wasSuccess = step === 'success';
    setPagando(null);
    setStep('form');
    if (wasSuccess) await load();
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
                          <td><span className={`badge ${BADGE[c.estado] ?? 'badge-gray'}`}>{c.estado.toUpperCase()}</span></td>
                          <td>{c.fecha_emision}</td><td>{c.fecha_vencimiento}</td>
                          <td>$ {Number(c.total ?? 0).toLocaleString('es-CO')}</td>
                          <td style={{ display: 'flex', gap: '.4rem' }}>
                            <button className="btn btn-ghost btn-sm" onClick={() => handlePDF(c.id)}>📄 Descargar PDF</button>
                            {c.estado !== 'pagada' && <button className="btn btn-primary btn-sm" onClick={() => iniciarPago(c)}>💳 Pagar en línea</button>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                }
              </div>
            </div>
          )}

      {/* Modal de Simulación de Pagos */}
      {pagando && (
        <div className="modal-overlay" onClick={step !== 'processing' ? cerrarModal : null}>
          <div className="modal" onClick={e => e.stopPropagation()} style={{ maxWidth: '450px' }}>
            
            {step === 'form' && (
              <>
                <div className="modal-header">
                  <span className="modal-title">Pasarela de Pagos Segura</span>
                  <button className="btn btn-ghost btn-icon" onClick={cerrarModal}>✕</button>
                </div>
                <form onSubmit={handleSimularPago}>
                  <div className="modal-body">
                    <div style={{ background: '#f3f4f6', padding: '15px', borderRadius: '8px', marginBottom: '20px', textAlign: 'center' }}>
                      <p style={{ margin: 0, fontSize: '0.85rem', color: '#6b7280' }}>Total a pagar</p>
                      <h2 style={{ margin: '5px 0 0', color: '#1e3a8a' }}>$ {Number(monto).toLocaleString('es-CO')} COP</h2>
                      <p style={{ margin: '5px 0 0', fontSize: '0.8rem', fontWeight: 'bold' }}>Factura: {pagando.numero}</p>
                    </div>

                    <div className="form-group">
                      <label className="form-label">Número de Tarjeta</label>
                      <input className="form-control" type="text" required placeholder="0000 0000 0000 0000" maxLength="19"
                        value={card.number} onChange={e => setCard({...card, number: e.target.value})} />
                    </div>
                    <div className="form-group">
                      <label className="form-label">Nombre en la tarjeta</label>
                      <input className="form-control" type="text" required placeholder="JUAN PEREZ"
                        value={card.name} onChange={e => setCard({...card, name: e.target.value})} />
                    </div>
                    <div className="form-row">
                      <div className="form-group">
                        <label className="form-label">Expiración (MM/AA)</label>
                        <input className="form-control" type="text" required placeholder="12/28" maxLength="5"
                          value={card.expiry} onChange={e => setCard({...card, expiry: e.target.value})} />
                      </div>
                      <div className="form-group">
                        <label className="form-label">CVC</label>
                        <input className="form-control" type="text" required placeholder="123" maxLength="4"
                          value={card.cvc} onChange={e => setCard({...card, cvc: e.target.value})} />
                      </div>
                    </div>
                  </div>
                  <div className="modal-footer" style={{ borderTop: 'none', justifyContent: 'center' }}>
                    <button type="submit" className="btn btn-primary" style={{ width: '100%', padding: '12px', fontSize: '1rem' }}>
                      🔒 Procesar Pago Seguro
                    </button>
                  </div>
                </form>
              </>
            )}

            {step === 'processing' && (
              <div className="modal-body" style={{ textAlign: 'center', padding: '40px 20px' }}>
                <div className="spinner" style={{ width: '50px', height: '50px', borderWidth: '4px', margin: '0 auto 20px' }}></div>
                <h3 style={{ color: '#1f2937' }}>Procesando tu pago...</h3>
                <p style={{ color: '#6b7280' }}>Conectando con la entidad bancaria. Por favor, no cierres esta ventana.</p>
              </div>
            )}

            {step === 'success' && (
              <div className="modal-body" style={{ textAlign: 'center', padding: '40px 20px' }}>
                <div style={{ fontSize: '60px', color: '#10b981', marginBottom: '10px' }}>✅</div>
                <h3 style={{ color: '#111827', margin: '0 0 10px' }}>¡Pago Exitoso!</h3>
                <p style={{ color: '#4b5563', marginBottom: '20px' }}>
                  El pago de <strong>$ {Number(monto).toLocaleString('es-CO')}</strong> ha sido procesado y la factura <strong>{pagando.numero}</strong> está al día.
                </p>
                <button className="btn btn-primary" style={{ width: '100%' }} onClick={cerrarModal}>Volver a mis cuentas</button>
              </div>
            )}

          </div>
        </div>
      )}
    </MainLayout>
  );
};

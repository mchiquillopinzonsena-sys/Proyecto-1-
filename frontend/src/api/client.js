/**
 * Cliente HTTP centralizado para la API Intérmica.
 * - Adjunta Bearer desde sessionStorage (clave configurable).
 * - Propaga errores con status y cuerpo parseado.
 */

const TOKEN_KEY = 'intermica_access_token';

export function getAccessToken() {
  return sessionStorage.getItem(TOKEN_KEY) || '';
}

export function setAccessToken(token) {
  if (token) {
    sessionStorage.setItem(TOKEN_KEY, token);
  } else {
    sessionStorage.removeItem(TOKEN_KEY);
  }
}

/**
 * @param {string} path - Ruta relativa, ej. "/api/v1/servicios"
 * @param {RequestInit & { json?: object }} options
 * @returns {Promise<{ success: boolean, data?: unknown, message?: string, status: number, errors?: object }>}
 */
export async function apiRequest(baseUrl, path, options = {}) {
  const { json, headers: extraHeaders, ...rest } = options;
  const headers = new Headers(extraHeaders || {});
  if (!headers.has('Content-Type') && json !== undefined) {
    headers.set('Content-Type', 'application/json');
  }
  const token = getAccessToken();
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  const url = `${baseUrl.replace(/\/$/, '')}${path.startsWith('/') ? path : `/${path}`}`;
  const res = await fetch(url, {
    ...rest,
    headers,
    body: json !== undefined ? JSON.stringify(json) : rest.body,
  });
  let body;
  const ct = res.headers.get('Content-Type') || '';
  if (ct.includes('application/json')) {
    body = await res.json();
  } else {
    body = { message: await res.text() };
  }
  if (!res.ok) {
    const err = new Error(body.message || res.statusText || 'Error de API');
    err.status = res.status;
    err.body = body;
    throw err;
  }
  return body;
}

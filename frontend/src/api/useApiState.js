import { useState, useCallback } from 'react';

/**
 * Estado estándar para llamadas a la API: carga, error y datos.
 *
 * @param {() => Promise<unknown>} executor - Función que ejecuta la petición y devuelve `data`
 * @returns {{
 *   data: unknown,
 *   loading: boolean,
 *   error: Error | null,
 *   execute: (...args: unknown[]) => Promise<unknown>,
 *   reset: () => void
 * }}
 */
export function useApiState(executor) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const execute = useCallback(
    async (...args) => {
      setLoading(true);
      setError(null);
      try {
        const result = await executor(...args);
        setData(result);
        return result;
      } catch (e) {
        const err = e instanceof Error ? e : new Error(String(e));
        setError(err);
        throw err;
      } finally {
        setLoading(false);
      }
    },
    [executor],
  );

  const reset = useCallback(() => {
    setData(null);
    setError(null);
    setLoading(false);
  }, []);

  return { data, loading, error, execute, reset };
}

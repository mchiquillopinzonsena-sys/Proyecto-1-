import React, { useState, useEffect } from 'react';
import { apiRequest, getAccessToken } from '../api/client';

/**
 * Componente de tabla reutilizable
 * Muestra datos en formato tabla con paginación básica
 */
export const DataTable = ({
  columns,
  data,
  onRowClick,
  loading = false,
  error = null,
}) => {
  if (loading) {
    return (
      <div className="flex justify-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded p-4 text-red-700">
        Error: {error}
      </div>
    );
  }

  if (!data || data.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
        No hay datos para mostrar
      </div>
    );
  }

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full bg-white border border-gray-200 rounded-lg">
        <thead className="bg-gray-100 border-b">
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                className="px-6 py-3 text-left text-sm font-semibold text-gray-700"
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((row, idx) => (
            <tr
              key={row.id || idx}
              onClick={() => onRowClick?.(row)}
              className="border-b hover:bg-gray-50 cursor-pointer transition"
            >
              {columns.map((col) => (
                <td key={col.key} className="px-6 py-4 text-sm text-gray-700">
                  {col.render ? col.render(row[col.key], row) : row[col.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

/**
 * Componente de formulario reutilizable
 */
export const Form = ({
  fields,
  onSubmit,
  submitText = 'Guardar',
  isLoading = false,
}) => {
  const [values, setValues] = useState({});
  const [errors, setErrors] = useState({});

  const handleChange = (e) => {
    const { name, value } = e.target;
    setValues((prev) => ({ ...prev, [name]: value }));
    // Limpiar error del campo
    if (errors[name]) {
      setErrors((prev) => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await onSubmit(values);
    } catch (err) {
      if (err.body?.errors) {
        setErrors(err.body.errors);
      }
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {fields.map((field) => (
        <div key={field.name}>
          <label className="block text-gray-700 font-medium mb-1">
            {field.label}
            {field.required && <span className="text-red-500">*</span>}
          </label>
          {field.type === 'textarea' ? (
            <textarea
              name={field.name}
              value={values[field.name] || ''}
              onChange={handleChange}
              required={field.required}
              className="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder={field.placeholder}
              rows={4}
            />
          ) : field.type === 'select' ? (
            <select
              name={field.name}
              value={values[field.name] || ''}
              onChange={handleChange}
              required={field.required}
              className="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Seleccionar...</option>
              {field.options?.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          ) : (
            <input
              type={field.type || 'text'}
              name={field.name}
              value={values[field.name] || ''}
              onChange={handleChange}
              required={field.required}
              className="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder={field.placeholder}
            />
          )}
          {errors[field.name] && (
            <p className="text-red-500 text-sm mt-1">
              {errors[field.name].join(', ')}
            </p>
          )}
        </div>
      ))}

      <button
        type="submit"
        disabled={isLoading}
        className="w-full bg-blue-600 text-white font-medium py-2 rounded hover:bg-blue-700 transition disabled:opacity-50"
      >
        {isLoading ? 'Procesando...' : submitText}
      </button>
    </form>
  );
};

/**
 * Hook para obtener datos de la API
 */
export const useFetch = (path, apiBaseUrl) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        const response = await apiRequest(apiBaseUrl, path, {
          method: 'GET',
        });
        if (response.success) {
          setData(response.data);
          setError(null);
        }
      } catch (err) {
        setError(err.message);
        setData(null);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [path, apiBaseUrl]);

  return { data, loading, error };
};

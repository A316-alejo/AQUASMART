import api from '../api/axios';

// GET /ponds devuelve { data: [...], links, meta } (paginado de Laravel).
export const listar = (params) => api.get('/ponds', { params }).then((r) => r.data);
export const opciones = () => api.get('/ponds/options').then((r) => r.data.data);
export const obtener = (id) => api.get(`/ponds/${id}`).then((r) => r.data.data);
export const crear = (datos) => api.post('/ponds', datos).then((r) => r.data.data);
export const actualizar = (id, datos) => api.put(`/ponds/${id}`, datos).then((r) => r.data.data);
// Los estanques nunca se eliminan: se activan o desactivan.
export const activar = (id) => api.patch(`/ponds/${id}/activar`).then((r) => r.data.data);
export const desactivar = (id) => api.patch(`/ponds/${id}/desactivar`).then((r) => r.data.data);

// Extrae el mensaje general y los errores por campo de una respuesta 4xx de Laravel.
export const leerError = (err, porDefecto = 'No se pudo completar la operación') => ({
  message: err.response?.data?.message || porDefecto,
  fields: err.response?.data?.errors || {},
});

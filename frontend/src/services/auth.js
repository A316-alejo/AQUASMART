import api from '../api/axios';

export const registrar = (datos) => api.post('/auth/register', datos).then((r) => r.data);
export const iniciarSesion = (email, password) => api.post('/auth/login', { email, password }).then((r) => r.data); // { token }
export const obtenerPerfil = () => api.get('/auth/me').then((r) => r.data.data);
export const actualizarPerfil = (datos) => api.put('/auth/me', datos).then((r) => r.data.data);
export const cerrarSesion = () => api.post('/auth/logout');

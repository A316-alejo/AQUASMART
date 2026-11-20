import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { leerError } from '../services/estanques';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setEnviando(true);
    try {
      await login(form.email, form.password);
      navigate('/perfil');
    } catch (err) {
      const { message, fields } = leerError(err, 'No se pudo iniciar sesión');
      setError(fields.email?.[0] || message);
    } finally {
      setEnviando(false);
    }
  };

  return (
    <form className="card form" onSubmit={onSubmit}>
      <h1>Iniciar sesión</h1>
      {error && <p className="error">{error}</p>}
      <label>Correo
        <input name="email" type="email" value={form.email} onChange={onChange} required autoFocus />
      </label>
      <label>Contraseña
        <input name="password" type="password" value={form.password} onChange={onChange} required />
      </label>
      <button className="btn primary" disabled={enviando}>{enviando ? 'Ingresando...' : 'Ingresar'}</button>
      <p>¿No tienes cuenta? <Link to="/register">Regístrate</Link></p>
    </form>
  );
}

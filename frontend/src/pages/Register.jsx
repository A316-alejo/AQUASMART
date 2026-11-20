import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { registrar } from '../services/auth';
import { leerError } from '../services/estanques';

export default function Register() {
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', password: '' });
  const [error, setError] = useState('');
  const [campos, setCampos] = useState({});

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setCampos({});
    try {
      await registrar(form); // no devuelve token: después se va a login
      navigate('/login');
    } catch (err) {
      const { message, fields } = leerError(err, 'No se pudo registrar');
      setError(message);
      setCampos(fields);
    }
  };

  const fieldError = (name) => (campos[name] ? <small className="error">{campos[name][0]}</small> : null);

  return (
    <form className="form" onSubmit={onSubmit}>
      <h1>Crear cuenta</h1>
      {error && <p className="error">{error}</p>}
      <label>Nombre
        <input name="name" value={form.name} onChange={onChange} required />
        {fieldError('name')}
      </label>
      <label>Correo
        <input name="email" type="email" value={form.email} onChange={onChange} required />
        {fieldError('email')}
      </label>
      <label>Contraseña (mínimo 8 caracteres)
        <input name="password" type="password" value={form.password} onChange={onChange} minLength={8} required />
        {fieldError('password')}
      </label>
      <button className="btn">Registrarme</button>
      <p>¿Ya tienes cuenta? <Link to="/login">Ingresa</Link></p>
    </form>
  );
}

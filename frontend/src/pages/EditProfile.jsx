import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { actualizarPerfil } from '../services/auth';
import { leerError } from '../services/estanques';

export default function EditProfile() {
  const { user, setUser } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: user.name, email: user.email });
  const [error, setError] = useState('');

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      setUser(await actualizarPerfil(form));
      navigate('/perfil');
    } catch (err) {
      const { message, fields } = leerError(err, 'No se pudo actualizar');
      setError(fields.email?.[0] || fields.name?.[0] || message);
    }
  };

  return (
    <form className="form" onSubmit={onSubmit}>
      <h1>Editar perfil</h1>
      {error && <p className="error">{error}</p>}
      <label>Nombre<input name="name" value={form.name} onChange={onChange} required /></label>
      <label>Correo<input name="email" type="email" value={form.email} onChange={onChange} required /></label>
      <div className="row">
        <button className="btn">Guardar cambios</button>
        <button type="button" className="btn" onClick={() => navigate('/perfil')}>Cancelar</button>
      </div>
    </form>
  );
}

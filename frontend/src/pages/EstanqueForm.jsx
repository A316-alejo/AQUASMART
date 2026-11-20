import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { obtener, crear, actualizar, opciones, leerError } from '../services/estanques';

const vacio = { farm_id: '', code: '', name: '', type: '', surface_m2: '', volume_m3: '', depth_m: '', is_active: true };

export default function EstanqueForm() {
  const { id } = useParams(); // undefined si es "nuevo"
  const navigate = useNavigate();
  const [form, setForm] = useState(vacio);
  const [opts, setOpts] = useState({ farms: [], types: {} });
  const [finca, setFinca] = useState('');
  const [error, setError] = useState('');
  const [campos, setCampos] = useState({});
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    opciones().then(setOpts).catch((err) => setError(leerError(err).message));
  }, []);

  useEffect(() => {
    if (!id) return;
    obtener(id)
      .then((p) => {
        setFinca(p.farm?.name ?? '');
        setForm({
          farm_id: p.farm?.id ?? '', code: p.code, name: p.name, type: p.type,
          surface_m2: p.surface_m2 ?? '', volume_m3: p.volume_m3 ?? '', depth_m: p.depth_m ?? '', is_active: p.is_active,
        });
      })
      .catch((err) => setError(leerError(err, 'No se pudo cargar el estanque').message));
  }, [id]);

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setCampos({});
    setEnviando(true);
    // Los campos numéricos vacíos se envían como null (son opcionales).
    const numero = (v) => (v === '' ? null : v);
    const datos = {
      code: form.code, name: form.name, type: form.type,
      surface_m2: numero(form.surface_m2), volume_m3: numero(form.volume_m3), depth_m: numero(form.depth_m),
    };
    try {
      if (id) await actualizar(id, datos); // la finca y el estado no se cambian al editar
      else await crear({ ...datos, farm_id: form.farm_id, is_active: form.is_active });
      navigate('/estanques');
    } catch (err) {
      const { message, fields } = leerError(err, 'Error al guardar');
      setError(message);
      setCampos(fields);
    } finally {
      setEnviando(false);
    }
  };

  const fieldError = (name) => (campos[name] ? <small className="error">{campos[name][0]}</small> : null);

  return (
    <form className="card form" onSubmit={onSubmit}>
      <h1>{id ? 'Editar estanque' : 'Nuevo estanque'}</h1>
      {error && <p className="error">{error}</p>}

      {id ? (
        <label>Finca<input value={finca} disabled /></label>
      ) : (
        <label>Finca
          <select name="farm_id" value={form.farm_id} onChange={onChange} required>
            <option value="">Selecciona una finca</option>
            {opts.farms.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
          </select>
          {opts.farms.length === 0 && (
            <small className="error">No tienes fincas disponibles. Un administrador debe asignarte una finca o darte un rol con permisos.</small>
          )}
          {fieldError('farm_id')}
        </label>
      )}

      <label>Código<input name="code" value={form.code} onChange={onChange} maxLength={20} required />{fieldError('code')}</label>
      <label>Nombre<input name="name" value={form.name} onChange={onChange} required />{fieldError('name')}</label>
      <label>Tipo
        <select name="type" value={form.type} onChange={onChange} required>
          <option value="">Selecciona un tipo</option>
          {Object.entries(opts.types).map(([valor, etiqueta]) => <option key={valor} value={valor}>{etiqueta}</option>)}
        </select>
        {fieldError('type')}
      </label>
      <label>Superficie (m²)<input name="surface_m2" value={form.surface_m2} onChange={onChange} inputMode="decimal" />{fieldError('surface_m2')}</label>
      <label>Volumen (m³)<input name="volume_m3" value={form.volume_m3} onChange={onChange} inputMode="decimal" />{fieldError('volume_m3')}</label>
      <label>Profundidad (m)<input name="depth_m" value={form.depth_m} onChange={onChange} inputMode="decimal" />{fieldError('depth_m')}</label>

      {!id && (
        <label className="check">
          <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /> Activo
        </label>
      )}

      <div className="row">
        <button className="btn primary" disabled={enviando}>{enviando ? 'Guardando...' : id ? 'Guardar cambios' : 'Crear'}</button>
        <button type="button" className="btn" onClick={() => navigate('/estanques')}>Cancelar</button>
      </div>
    </form>
  );
}

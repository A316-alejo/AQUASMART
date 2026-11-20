import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listar, activar, desactivar, leerError } from '../services/estanques';

export default function EstanquesList() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [filtros, setFiltros] = useState({ q: '', status: '' });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const cargar = useCallback(() => {
    setLoading(true);
    const params = { page, ...(filtros.q && { q: filtros.q }), ...(filtros.status && { status: filtros.status }) };
    listar(params)
      .then((res) => { setItems(res.data); setMeta(res.meta); setError(''); })
      .catch((err) => setError(leerError(err, 'No se pudo cargar la lista').message))
      .finally(() => setLoading(false));
  }, [page, filtros]);

  useEffect(() => { cargar(); }, [cargar]);

  const cambiarEstado = async (p) => {
    const accion = p.is_active ? desactivar : activar;
    if (!window.confirm(`¿${p.is_active ? 'Desactivar' : 'Activar'} el estanque ${p.code}?`)) return;
    try {
      const actualizado = await accion(p.id);
      setItems((prev) => prev.map((x) => (x.id === p.id ? actualizado : x)));
    } catch (err) {
      window.alert(leerError(err).message);
    }
  };

  const onFiltro = (e) => { setPage(1); setFiltros({ ...filtros, [e.target.name]: e.target.value }); };

  return (
    <div>
      <h1>Estanques</h1>
      <div className="filters">
        <input name="q" placeholder="Buscar..." value={filtros.q} onChange={onFiltro} />
        <select name="status" value={filtros.status} onChange={onFiltro}>
          <option value="">Todos</option>
          <option value="active">Activos</option>
          <option value="inactive">Inactivos</option>
        </select>
      </div>
      {error && <p className="error">{error}</p>}
      {loading ? <p className="muted">Cargando...</p> : (
        <table>
          <thead>
            <tr><th>Código</th><th>Nombre</th><th>Finca</th><th>Tipo</th><th>Superficie (m²)</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            {items.length === 0 && <tr><td colSpan="7" className="muted">Sin resultados</td></tr>}
            {items.map((p) => (
              <tr key={p.id}>
                <td>{p.code}</td>
                <td>{p.name}</td>
                <td>{p.farm?.name}</td>
                <td>{p.type_label}</td>
                <td>{p.surface_m2 ?? '—'}</td>
                <td><span className={`tag ${p.is_active ? 'ok' : 'off'}`}>{p.is_active ? 'Activo' : 'Inactivo'}</span></td>
                <td className="actions">
                  <Link to={`/estanques/${p.id}/editar`}>Editar</Link>
                  <button className="btn" onClick={() => cambiarEstado(p)}>{p.is_active ? 'Desactivar' : 'Activar'}</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {meta && meta.last_page > 1 && (
        <div className="pager">
          <button className="btn" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Anterior</button>
          <span className="muted">Página {meta.current_page} de {meta.last_page}</span>
          <button className="btn" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Siguiente →</button>
        </div>
      )}
    </div>
  );
}

import { Routes, Route, Link, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import Register from './pages/Register';
import Profile from './pages/Profile';
import EditProfile from './pages/EditProfile';
import EstanquesList from './pages/EstanquesList';
import EstanqueForm from './pages/EstanqueForm';

export default function App() {
  const { user, logout } = useAuth();
  return (
    <>
      <header className="topbar">
        <strong>AquaSmart</strong>
        {user ? (
          <>
            <nav>
              <Link to="/perfil">Mi perfil</Link>
              <Link to="/estanques">Estanques</Link>
              <Link to="/estanques/nuevo">Nuevo estanque</Link>
            </nav>
            <button className="btn" onClick={logout}>Salir</button>
          </>
        ) : (
          <nav>
            <Link to="/login">Ingresar</Link>
            <Link to="/register">Registrarse</Link>
          </nav>
        )}
      </header>
      <main>
        <Routes>
          <Route path="/" element={<Navigate to="/perfil" replace />} />

          {/* Públicas */}
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />

          {/* Protegidas: sin sesión redirigen a /login */}
          <Route element={<ProtectedRoute />}>
            <Route path="/perfil" element={<Profile />} />
            <Route path="/perfil/editar" element={<EditProfile />} />
            <Route path="/estanques" element={<EstanquesList />} />
            <Route path="/estanques/nuevo" element={<EstanqueForm />} />
            <Route path="/estanques/:id/editar" element={<EstanqueForm />} />
          </Route>

          <Route path="*" element={<p>Página no encontrada</p>} />
        </Routes>
      </main>
    </>
  );
}

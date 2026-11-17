import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { Carregando } from '../components/ui';
import { useAuth, type Perfil } from './AuthContext';

/** Exige login (e, opcionalmente, um dos perfis). Força a troca de senha quando obrigatória. */
export function RotaProtegida({ perfis }: { perfis?: Perfil[] }) {
  const { usuario, carregando } = useAuth();
  const local = useLocation();

  if (carregando) return <Carregando />;
  if (!usuario) return <Navigate to="/entrar" replace state={{ de: local.pathname }} />;
  if (usuario.troca_senha_obrigatoria && local.pathname !== '/trocar-senha') {
    return <Navigate to="/trocar-senha" replace />;
  }
  if (perfis && !perfis.includes(usuario.perfil)) return <Navigate to="/" replace />;
  return <Outlet />;
}

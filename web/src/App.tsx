import { Navigate, Route, Routes } from 'react-router-dom';
import { RotaProtegida } from './auth/RotaProtegida';
import { Layout } from './components/Layout';
import { PaginaLogin } from './pages/acesso/Login';
import { PaginaRecuperarSenha, PaginaRedefinirSenha } from './pages/acesso/RecuperarSenha';
import { PaginaTrocarSenha } from './pages/acesso/TrocarSenha';
import { PaginaInicio } from './pages/inicio/Inicio';
import { PaginaUsuarios } from './pages/usuarios/Usuarios';

export function App() {
  return (
    <Routes>
      <Route path="/entrar" element={<PaginaLogin />} />
      <Route path="/recuperar-senha" element={<PaginaRecuperarSenha />} />
      <Route path="/redefinir-senha" element={<PaginaRedefinirSenha />} />

      <Route element={<RotaProtegida />}>
        <Route element={<Layout />}>
          <Route path="/trocar-senha" element={<PaginaTrocarSenha />} />
          <Route element={<RotaProtegida perfis={['admin', 'professora']} />}>
            <Route path="/" element={<PaginaInicio />} />
          </Route>
          <Route element={<RotaProtegida perfis={['admin']} />}>
            <Route path="/usuarios" element={<PaginaUsuarios />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

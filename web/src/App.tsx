import { Navigate, Route, Routes } from 'react-router-dom';
import { RotaProtegida } from './auth/RotaProtegida';
import { Layout } from './components/Layout';
import { PaginaLogin } from './pages/acesso/Login';
import { PaginaRecuperarSenha, PaginaRedefinirSenha } from './pages/acesso/RecuperarSenha';
import { PaginaTrocarSenha } from './pages/acesso/TrocarSenha';
import { PaginaAlunos } from './pages/alunos/Alunos';
import { PaginaFicha } from './pages/alunos/Ficha';
import { PaginaFormularioAluno } from './pages/alunos/FormularioAluno';
import { PaginaAuditoria } from './pages/auditoria/Auditoria';
import { PaginaAulas } from './pages/aulas/Aulas';
import { PaginaFormularioAula } from './pages/aulas/FormularioAula';
import { PaginaFinanceiro } from './pages/financeiro/Financeiro';
import { PaginaLicoesDoAluno } from './pages/progresso/LicoesDoAluno';
import { PaginaMetodoDetalhe } from './pages/progresso/MetodoDetalhe';
import { PaginaMetodos } from './pages/progresso/Metodos';
import { PaginaListas } from './pages/listas/Listas';
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
            <Route path="/alunos" element={<PaginaAlunos />} />
            <Route path="/alunos/novo" element={<PaginaFormularioAluno />} />
            <Route path="/alunos/:id" element={<PaginaFicha />} />
            <Route path="/alunos/:id/editar" element={<PaginaFormularioAluno />} />
            <Route path="/aulas" element={<PaginaAulas />} />
            <Route path="/aulas/nova" element={<PaginaFormularioAula />} />
            <Route path="/aulas/:id/editar" element={<PaginaFormularioAula />} />
            <Route path="/financeiro" element={<PaginaFinanceiro />} />
            <Route path="/metodos" element={<PaginaMetodos />} />
            <Route path="/metodos/:id" element={<PaginaMetodoDetalhe />} />
            <Route path="/alunos/:id/metodos/:metodoId" element={<PaginaLicoesDoAluno />} />
            <Route path="/listas" element={<PaginaListas />} />
          </Route>
          <Route element={<RotaProtegida perfis={['admin']} />}>
            <Route path="/usuarios" element={<PaginaUsuarios />} />
            <Route path="/auditoria" element={<PaginaAuditoria />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

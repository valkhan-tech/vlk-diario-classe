import { NavLink, Outlet } from 'react-router-dom';
import { useAuth, type Perfil } from '../auth/AuthContext';
import { Botao } from './ui';

interface ItemMenu { para: string; rotulo: string; perfis: Perfil[]; fim?: boolean }

const OPERACAO: Perfil[] = ['admin', 'professora'];

/** Itens de navegação; cada entrega acrescenta os seus. */
export const MENU: ItemMenu[] = [
  { para: '/', rotulo: 'Início', perfis: OPERACAO, fim: true },
  { para: '/alunos', rotulo: 'Alunos', perfis: OPERACAO },
  { para: '/aulas', rotulo: 'Aulas', perfis: OPERACAO },
  { para: '/financeiro', rotulo: 'Financeiro', perfis: OPERACAO },
  { para: '/listas', rotulo: 'Listas', perfis: OPERACAO },
  { para: '/usuarios', rotulo: 'Usuários', perfis: ['admin'] },
];

export function Layout() {
  const { usuario, sair } = useAuth();
  const itens = MENU.filter((i) => usuario && i.perfis.includes(usuario.perfil));

  const classe = ({ isActive }: { isActive: boolean }) =>
    `whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium ${isActive ? 'bg-marca-100 text-marca-700' : 'text-slate-600 hover:bg-slate-100'}`;

  return (
    <div className="min-h-screen">
      <a href="#conteudo" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:p-2">
        Ir para o conteúdo
      </a>
      <header className="nao-imprimir sticky top-0 z-30 border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-2">
          <span className="text-lg font-bold text-marca-700">🎹 Diário de Classe</span>
          <div className="flex items-center gap-3">
            <span className="hidden text-sm text-slate-600 sm:inline">{usuario?.nome}</span>
            {usuario?.perfil === 'admin' && (
              <a className="hidden text-sm font-medium text-marca-700 underline sm:inline" href="/api/docs" target="_blank" rel="noreferrer">
                Docs da API
              </a>
            )}
            <NavLink to="/trocar-senha" className="text-sm text-slate-600 underline">Senha</NavLink>
            <Botao variante="secundario" onClick={() => void sair()}>Sair</Botao>
          </div>
        </div>
        <nav aria-label="Principal" className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-3 pb-2">
          {itens.map((i) => (
            <NavLink key={i.para} to={i.para} end={i.fim} className={classe}>{i.rotulo}</NavLink>
          ))}
        </nav>
      </header>
      <main id="conteudo" className="mx-auto max-w-6xl px-4 py-5">
        <Outlet />
      </main>
    </div>
  );
}

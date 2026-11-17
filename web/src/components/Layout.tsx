import { useQuery } from '@tanstack/react-query';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import type { PainelInicio } from '../api/tipos';
import { useAuth, type Perfil } from '../auth/AuthContext';
import { Icone, type NomeIcone } from './icones';
import { Avatar, Botao, Modal, cx } from './ui';

interface ItemMenu { para: string; rotulo: string; icone: NomeIcone; perfis: Perfil[]; fim?: boolean; principal?: boolean; grupo?: string }

const OPERACAO: Perfil[] = ['admin', 'professora'];

/** Itens de navegação. Os `principal` ficam na barra inferior do celular; o resto vai em "Mais". */
export const MENU: ItemMenu[] = [
  { para: '/', rotulo: 'Início', icone: 'home', perfis: OPERACAO, fim: true, principal: true },
  { para: '/alunos', rotulo: 'Alunos', icone: 'users', perfis: OPERACAO, principal: true },
  { para: '/aulas', rotulo: 'Aulas', icone: 'calendario', perfis: OPERACAO, principal: true },
  { para: '/financeiro', rotulo: 'Financeiro', icone: 'carteira', perfis: OPERACAO, principal: true },
  { para: '/metodos', rotulo: 'Métodos', icone: 'livro', perfis: OPERACAO, principal: true },
  { para: '/listas', rotulo: 'Listas', icone: 'lista', perfis: OPERACAO, grupo: 'Cadastros' },
  { para: '/usuarios', rotulo: 'Usuários', icone: 'users', perfis: ['admin'], grupo: 'Administração' },
  { para: '/auditoria', rotulo: 'Auditoria', icone: 'escudo', perfis: ['admin'], grupo: 'Administração' },
];

/** Painel flutuante simples (notificações, menu do usuário): fecha com Esc ou clique fora. */
function Flutuante({ aberto, aoFechar, children, rotulo, largura = 'md:w-96' }: { aberto: boolean; aoFechar: () => void; children: ReactNode; rotulo: string; largura?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!aberto) return;
    const fora = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node) && !(e.target as HTMLElement).closest('[data-gatilho]')) aoFechar(); };
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') aoFechar(); };
    document.addEventListener('mousedown', fora);
    document.addEventListener('keydown', esc);
    return () => { document.removeEventListener('mousedown', fora); document.removeEventListener('keydown', esc); };
  }, [aberto, aoFechar]);
  if (!aberto) return null;
  return (
    <div ref={ref} role="dialog" aria-label={rotulo}
      className={cx('surgir fixed inset-x-3 top-[4.5rem] z-50 max-h-[75vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl md:absolute md:inset-x-auto md:right-0 md:top-14', largura)}>
      {children}
    </div>
  );
}

const TOM_NOTIF = { erro: 'bg-red-100 text-red-700', info: 'bg-sky-100 text-sky-700', ok: 'bg-emerald-100 text-emerald-700' } as const;
const ICONE_NOTIF = { erro: 'alerta', info: 'calendario', ok: 'check' } as const;

export function Layout() {
  const { usuario, sair } = useAuth();
  const navegar = useNavigate();
  const local = useLocation();
  const [notifAberto, setNotifAberto] = useState(false);
  const [perfilAberto, setPerfilAberto] = useState(false);
  const [maisAberto, setMaisAberto] = useState(false);
  const [busca, setBusca] = useState('');

  const operacional = !!usuario && OPERACAO.includes(usuario.perfil);
  const painel = useQuery({
    queryKey: ['inicio'],
    queryFn: async () => (await api.get<{ dados: PainelInicio }>('/inicio')).dados,
    enabled: operacional,
    refetchInterval: 300_000,
  });
  const notificacoes = painel.data?.notificacoes ?? [];
  const semLancar = painel.data ? painel.data.kpis.aulas_hoje - painel.data.kpis.aulas_hoje_lancadas : 0;

  const itens = MENU.filter((i) => usuario && i.perfis.includes(usuario.perfil));
  const principais = itens.filter((i) => i.principal);
  const secundarios = itens.filter((i) => !i.principal);
  const ehAdmin = usuario?.perfil === 'admin';

  useEffect(() => { setNotifAberto(false); setPerfilAberto(false); setMaisAberto(false); }, [local.pathname]);

  const buscar = (e: FormEvent) => {
    e.preventDefault();
    navegar(busca.trim() ? `/alunos?q=${encodeURIComponent(busca.trim())}` : '/alunos');
  };

  const linkLateral = ({ isActive }: { isActive: boolean }) =>
    cx('relative flex min-h-11 items-center gap-3 rounded-2xl px-3.5 text-[15px] md:justify-center lg:justify-start',
      isActive ? 'bg-white/20 font-semibold text-white before:absolute before:-left-3.5 before:bottom-2.5 before:top-2.5 before:w-1.5 before:rounded-r-md before:bg-ambar-500 before:content-[""]' : 'text-white/85 hover:bg-white/10 hover:text-white');

  const linkInferior = ({ isActive }: { isActive: boolean }) =>
    cx('relative flex min-h-[3.5rem] flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 text-[11px] font-medium', isActive ? 'bg-white/20 text-white' : 'text-white/80');

  return (
    <div className="min-h-screen md:grid md:grid-cols-[84px_1fr] lg:grid-cols-[236px_1fr]">
      <a href="#conteudo" className="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-[70] focus:rounded-lg focus:bg-white focus:p-2">Ir para o conteúdo</a>

      {/* Menu lateral colorido (tablet e computador) */}
      <aside className="app-lateral nao-imprimir sticky top-0 hidden h-screen flex-col px-3.5 pb-4 pt-5 text-white md:flex" style={{ background: 'var(--sb-bg)' }}>
        <div className="mb-5 flex items-center gap-2.5 px-1.5 md:justify-center lg:justify-start">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/15"><Icone nome="piano" className="h-6 w-6" /></span>
          <span className="hidden font-titulo text-xl font-semibold leading-tight lg:block">Diário de Classe</span>
        </div>
        <nav aria-label="Principal" className="flex flex-1 flex-col gap-1 overflow-y-auto">
          {principais.map((i) => (
            <NavLink key={i.para} to={i.para} end={i.fim} className={linkLateral} title={i.rotulo}>
              <Icone nome={i.icone} />
              <span className="hidden lg:inline">{i.rotulo}</span>
              {i.para === '/aulas' && semLancar > 0 && <span className="ml-auto hidden rounded-full bg-ambar-500 px-2 text-[11px] font-bold text-slate-900 lg:inline">{semLancar}</span>}
            </NavLink>
          ))}
          {['Cadastros', 'Administração'].map((g) => {
            const grupo = secundarios.filter((i) => i.grupo === g);
            if (!grupo.length) return null;
            return (
              <div key={g} className="mt-2 flex flex-col gap-1">
                <div className="hidden px-3.5 pb-1 pt-3 text-[11px] uppercase tracking-[.09em] text-white/55 lg:block">{g}</div>
                {grupo.map((i) => (
                  <NavLink key={i.para} to={i.para} className={linkLateral} title={i.rotulo}>
                    <Icone nome={i.icone} /><span className="hidden lg:inline">{i.rotulo}</span>
                  </NavLink>
                ))}
              </div>
            );
          })}
        </nav>
        <div className="mt-3 flex items-center gap-2.5 rounded-2xl bg-black/15 p-2.5 md:justify-center lg:justify-start">
          <Avatar nome={usuario?.nome ?? '?'} tamanho="p" />
          <div className="hidden min-w-0 flex-1 text-sm lg:block"><div className="truncate font-medium">{usuario?.nome}</div><div className="text-xs text-white/70">{usuario?.perfil === 'admin' ? 'Administrador' : 'Professora'}</div></div>
          <button type="button" aria-label="Sair" onClick={() => void sair()} className="hidden h-10 w-10 place-items-center rounded-full border border-white/30 hover:bg-white/10 lg:grid"><Icone nome="sair" /></button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-col">
        {/* Barra superior */}
        <header className="nao-imprimir sticky top-0 z-40 flex items-center gap-3 border-b border-slate-200/70 bg-[color-mix(in_srgb,var(--bg)_90%,transparent)] px-4 py-2.5 backdrop-blur md:px-7">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white md:hidden" style={{ background: 'var(--sb-bg)' }}><Icone nome="piano" /></span>
          <form onSubmit={buscar} role="search" className="relative min-w-0 max-w-md flex-1">
            <Icone nome="busca" className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
            <input type="search" value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar aluno…" aria-label="Buscar aluno"
              className="w-full rounded-full border border-slate-200 bg-white pl-11 pr-4 text-base placeholder:text-slate-400 focus:border-marca-600 focus:outline-none focus:ring-4 focus:ring-marca-600/15" />
          </form>
          <span className="hidden flex-1 md:block" />
          <div className="relative">
            <button type="button" data-gatilho aria-label={`Notificações${notificacoes.length ? ` (${notificacoes.length})` : ''}`} aria-expanded={notifAberto} onClick={() => { setNotifAberto((a) => !a); setPerfilAberto(false); }}
              className="relative grid h-11 w-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-800 hover:bg-marca-50">
              <Icone nome="sino" />
              {notificacoes.length > 0 && <span className="absolute right-2.5 top-2.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-red-600" />}
            </button>
            <Flutuante aberto={notifAberto} aoFechar={() => setNotifAberto(false)} rotulo="Notificações">
              <h2 className="mb-2 font-titulo text-lg font-semibold">Notificações</h2>
              {notificacoes.length === 0 ? <p className="py-6 text-center text-sm text-slate-500">Tudo em dia. Nenhuma pendência.</p> : (
                <ul className="divide-y divide-slate-100">
                  {notificacoes.map((n, i) => (
                    <li key={i}>
                      <button type="button" onClick={() => navegar(n.link)} className="flex w-full items-start gap-3 py-3 text-left hover:bg-slate-50">
                        <span className={cx('grid h-9 w-9 shrink-0 place-items-center rounded-xl', TOM_NOTIF[n.tipo as keyof typeof TOM_NOTIF] ?? TOM_NOTIF.info)}><Icone nome={ICONE_NOTIF[n.tipo as keyof typeof ICONE_NOTIF] ?? 'info'} className="h-[18px] w-[18px]" /></span>
                        <span className="min-w-0 text-sm"><b className="block text-slate-900">{n.titulo}</b><span className="text-slate-500">{n.detalhe}</span></span>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </Flutuante>
          </div>
          <div className="relative">
            <button type="button" data-gatilho aria-label="Menu do usuário" aria-expanded={perfilAberto} onClick={() => { setPerfilAberto((a) => !a); setNotifAberto(false); }}
              className="flex items-center gap-2.5 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-1 hover:bg-marca-50 lg:pr-4">
              <Avatar nome={usuario?.nome ?? '?'} tamanho="p" />
              <span className="hidden text-left text-sm leading-tight lg:block"><b className="block">{usuario?.nome}</b><small className="text-slate-500">{usuario?.perfil === 'admin' ? 'Administrador' : 'Professora'}</small></span>
            </button>
            <Flutuante aberto={perfilAberto} aoFechar={() => setPerfilAberto(false)} rotulo="Menu do usuário" largura="md:w-64">
              <div className="mb-2 border-b border-slate-100 pb-3"><b className="block">{usuario?.nome}</b><span className="text-sm text-slate-500">{usuario?.email}</span></div>
              <NavLink to="/trocar-senha" className="flex min-h-11 items-center gap-3 rounded-xl px-2 text-sm hover:bg-slate-50"><Icone nome="chave" />Trocar senha</NavLink>
              {ehAdmin && <a href="/api/docs" target="_blank" rel="noreferrer" className="flex min-h-11 items-center gap-3 rounded-xl px-2 text-sm hover:bg-slate-50"><Icone nome="escudo" />Documentação da API</a>}
              <button type="button" onClick={() => void sair()} className="flex min-h-11 w-full items-center gap-3 rounded-xl px-2 text-left text-sm text-red-700 hover:bg-red-50"><Icone nome="sair" />Sair</button>
            </Flutuante>
          </div>
        </header>

        <main id="conteudo" className="app-conteudo mx-auto w-full max-w-[1280px] px-4 pb-[calc(6.5rem+var(--safe-b))] pt-3 md:px-7 md:pb-12">
          <Outlet />
        </main>
      </div>

      {/* Barra inferior (celular) */}
      <nav aria-label="Principal" className="app-barra-inferior nao-imprimir fixed inset-x-0 bottom-0 z-40 flex gap-1 px-2 pt-1.5 pb-[calc(0.375rem+var(--safe-b))] md:hidden" style={{ background: 'var(--sb-bg)' }}>
        {principais.slice(0, 4).map((i) => (
          <NavLink key={i.para} to={i.para} end={i.fim} className={linkInferior}>
            <Icone nome={i.icone} />{i.rotulo}
            {i.para === '/aulas' && semLancar > 0 && <span className="absolute right-3 top-1.5 h-2.5 w-2.5 rounded-full bg-ambar-500" />}
          </NavLink>
        ))}
        <button type="button" onClick={() => setMaisAberto(true)} className={linkInferior({ isActive: false })}><Icone nome="menu" />Mais</button>
      </nav>

      <Modal aberto={maisAberto} aoFechar={() => setMaisAberto(false)} titulo="Mais opções">
        <nav aria-label="Mais opções" className="flex flex-col gap-1">
          {[...principais.slice(4), ...secundarios].map((i) => (
            <NavLink key={i.para} to={i.para} className="flex min-h-12 items-center gap-3 rounded-2xl px-3 text-base hover:bg-slate-50"><Icone nome={i.icone} />{i.rotulo}</NavLink>
          ))}
          <NavLink to="/trocar-senha" className="flex min-h-12 items-center gap-3 rounded-2xl px-3 text-base hover:bg-slate-50"><Icone nome="chave" />Trocar senha</NavLink>
          {ehAdmin && <a href="/api/docs" target="_blank" rel="noreferrer" className="flex min-h-12 items-center gap-3 rounded-2xl px-3 text-base hover:bg-slate-50"><Icone nome="escudo" />Documentação da API</a>}
        </nav>
        <Botao variante="secundario" className="mt-4 w-full" onClick={() => void sair()}><Icone nome="sair" />Sair</Botao>
      </Modal>
    </div>
  );
}

import * as Dialog from '@radix-ui/react-dialog';
import * as Tabs from '@radix-ui/react-tabs';
import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Icone, type NomeIcone } from './icones';

export const cx = (...c: (string | false | undefined | null)[]) => c.filter(Boolean).join(' ');

/* ---------- Botões ---------- */
type PropsBotao = ComponentProps<'button'> & {
  variante?: 'primario' | 'secundario' | 'perigo' | 'fantasma' | 'destaque';
  carregando?: boolean;
  compacto?: boolean;
};

export function Botao({ variante = 'primario', carregando, compacto, className, children, disabled, ...resto }: PropsBotao) {
  const base = 'inline-flex items-center justify-center gap-2 rounded-full px-5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';
  const estilos = {
    primario: 'bg-marca-600 text-white hover:bg-marca-700',
    secundario: 'border border-slate-200 bg-white text-slate-800 hover:bg-marca-50',
    perigo: 'bg-red-600 text-white hover:bg-red-700',
    fantasma: 'text-marca-700 hover:bg-marca-50',
    destaque: 'bg-ambar-500 text-slate-900 hover:brightness-95',
  }[variante];
  return (
    <button className={cx(base, estilos, compacto && '!min-h-9 !px-3.5 text-[13px]', className)} disabled={disabled || carregando} {...resto}>
      {carregando ? 'Aguarde…' : children}
    </button>
  );
}

/* ---------- Campos ---------- */
type PropsCampo = { rotulo: string; erro?: string; dica?: string; className?: string };

function Rotulo({ rotulo, erro, dica, id, children, className }: PropsCampo & { id: string; children: ReactNode }) {
  return (
    <div className={cx('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className="text-[13px] font-semibold text-slate-500">{rotulo}</label>
      {children}
      {dica && !erro && <span className="text-xs text-slate-500">{dica}</span>}
      {erro && <span role="alert" className="text-xs text-red-600">{erro}</span>}
    </div>
  );
}

const estiloEntrada = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-base text-slate-900 placeholder:text-slate-400 focus:border-marca-600 focus:outline-none focus:ring-4 focus:ring-marca-600/15 aria-[invalid=true]:border-red-600 aria-[invalid=true]:bg-red-50';

export function Campo({ rotulo, erro, dica, className, id, ...resto }: PropsCampo & ComponentProps<'input'>) {
  const idFinal = id ?? `campo-${resto.name ?? rotulo}`;
  return (
    <Rotulo id={idFinal} rotulo={rotulo} erro={erro} dica={dica} className={className}>
      <input id={idFinal} aria-invalid={!!erro} className={estiloEntrada} {...resto} />
    </Rotulo>
  );
}

export function Selecao({ rotulo, erro, dica, className, id, children, ...resto }: PropsCampo & ComponentProps<'select'>) {
  const idFinal = id ?? `sel-${resto.name ?? rotulo}`;
  return (
    <Rotulo id={idFinal} rotulo={rotulo} erro={erro} dica={dica} className={className}>
      <select id={idFinal} aria-invalid={!!erro} className={estiloEntrada} {...resto}>{children}</select>
    </Rotulo>
  );
}

export function AreaTexto({ rotulo, erro, dica, className, id, ...resto }: PropsCampo & ComponentProps<'textarea'>) {
  const idFinal = id ?? `txt-${resto.name ?? rotulo}`;
  return (
    <Rotulo id={idFinal} rotulo={rotulo} erro={erro} dica={dica} className={className}>
      <textarea id={idFinal} rows={3} aria-invalid={!!erro} className={estiloEntrada} {...resto} />
    </Rotulo>
  );
}

export function Marcador({ rotulo, className, ...resto }: { rotulo: string } & ComponentProps<'input'>) {
  return (
    <label className={cx('flex min-h-11 items-center gap-3 text-sm text-slate-800', className)}>
      <input type="checkbox" className="h-5 w-5 rounded border-slate-300 accent-[var(--color-marca-600)]" {...resto} />
      {rotulo}
    </label>
  );
}

/** Interruptor (liga/desliga) acessível: é um checkbox com role=switch. */
export function Interruptor({ rotulo, className, ...resto }: { rotulo: string } & ComponentProps<'input'>) {
  return (
    <label className={cx('flex min-h-11 cursor-pointer items-center gap-3 text-sm text-slate-800', className)}>
      <input type="checkbox" role="switch" className="peer sr-only" {...resto} />
      <span className="relative h-[26px] w-[46px] shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-offset-2 peer-focus-visible:outline-marca-500 after:absolute after:left-[3px] after:top-[3px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all after:content-[''] peer-checked:after:left-[23px]" />
      {rotulo}
    </label>
  );
}

/** Controle segmentado (opções mutuamente exclusivas em formato de pílulas). */
export function Segmentos<T extends string>({ opcoes, valor, aoMudar, rotulo }: { opcoes: { valor: T; rotulo: string }[]; valor: T; aoMudar: (v: T) => void; rotulo: string }) {
  return (
    <div role="group" aria-label={rotulo} className="inline-flex flex-wrap gap-1 rounded-full bg-slate-100 p-1">
      {opcoes.map((o) => (
        <button key={o.valor} type="button" aria-pressed={valor === o.valor} onClick={() => aoMudar(o.valor)}
          className={cx('!min-h-9 rounded-full px-4 text-[13px] font-semibold', valor === o.valor ? 'bg-white text-marca-700 shadow-sm' : 'text-slate-500')}>
          {o.rotulo}
        </button>
      ))}
    </div>
  );
}

/* ---------- Estrutura ---------- */
export function Cartao({ titulo, acoes, children, className }: { titulo?: string; acoes?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={cx('imprimir-pagina min-w-0 rounded-[22px] border border-slate-200 bg-white p-4 shadow-[var(--shadow-cartao)] sm:p-5', className)}>
      {(titulo || acoes) && (
        <header className="mb-3.5 flex flex-wrap items-center justify-between gap-2">
          {titulo && <h2 className="text-lg font-semibold text-slate-900">{titulo}</h2>}
          {acoes && <div className="nao-imprimir flex flex-wrap gap-2">{acoes}</div>}
        </header>
      )}
      {children}
    </section>
  );
}

export function Titulo({ children, acoes, trilha }: { children: ReactNode; acoes?: ReactNode; trilha?: ReactNode }) {
  return (
    <div className="mb-5 mt-1 flex flex-wrap items-end justify-between gap-3">
      <div className="min-w-0">
        {trilha && <div className="mb-0.5 text-[13px] text-slate-500">{trilha}</div>}
        <h1 className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[26px] font-semibold leading-tight text-slate-900 sm:text-3xl">{children}</h1>
      </div>
      {acoes && <div className="nao-imprimir flex flex-wrap gap-2">{acoes}</div>}
    </div>
  );
}

const ICONE_AVISO: Record<string, NomeIcone> = { erro: 'alerta', alerta: 'alerta', info: 'info', sucesso: 'check' };

export function Aviso({ tipo = 'erro', children }: { tipo?: 'erro' | 'sucesso' | 'info' | 'alerta'; children: ReactNode }) {
  const estilos = {
    erro: 'border-red-200 bg-red-100 text-red-800',
    sucesso: 'border-emerald-200 bg-emerald-100 text-emerald-900',
    info: 'border-sky-200 bg-sky-100 text-sky-800',
    alerta: 'border-amber-300 bg-amber-100 text-amber-900',
  }[tipo];
  return (
    <div role={tipo === 'erro' ? 'alert' : 'status'} className={cx('flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm', estilos)}>
      <Icone nome={ICONE_AVISO[tipo]} className="mt-0.5 h-5 w-5" />
      <div className="min-w-0">{children}</div>
    </div>
  );
}

export function Carregando({ texto = 'Carregando…' }: { texto?: string }) {
  return (
    <div role="status" className="space-y-2.5 py-6" aria-label={texto}>
      <span className="sr-only">{texto}</span>
      <div className="h-4 w-2/3 animate-pulse rounded bg-slate-200" />
      <div className="h-4 animate-pulse rounded bg-slate-200" />
      <div className="h-4 w-1/2 animate-pulse rounded bg-slate-200" />
    </div>
  );
}

export function Vazio({ children, icone = 'nota', acao }: { children: ReactNode; icone?: NomeIcone; acao?: ReactNode }) {
  return (
    <div className="px-4 py-9 text-center text-sm text-slate-500">
      <div className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-marca-100 text-marca-600"><Icone nome={icone} className="h-8 w-8" /></div>
      <p>{children}</p>
      {acao && <div className="mt-3">{acao}</div>}
    </div>
  );
}

type TomBadge = 'neutro' | 'verde' | 'amarelo' | 'vermelho' | 'azul' | 'roxo';
export function Badge({ tom = 'neutro', children }: { tom?: TomBadge; children: ReactNode }) {
  const estilos: Record<TomBadge, string> = {
    neutro: 'bg-slate-100 text-slate-600',
    verde: 'bg-emerald-100 text-emerald-700',
    amarelo: 'bg-amber-100 text-amber-800',
    vermelho: 'bg-red-100 text-red-700',
    azul: 'bg-sky-100 text-sky-700',
    roxo: 'bg-marca-100 text-marca-700', // hinário / destaque de marca
  };
  return (
    <span className={cx('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold before:h-[7px] before:w-[7px] before:rounded-full before:bg-current before:content-[""]', estilos[tom])}>
      {children}
    </span>
  );
}

/* ---------- Avatar, anel e barra ---------- */
const CORES_NOTAS = ['bg-nota-1', 'bg-nota-2', 'bg-nota-3 !text-slate-900', 'bg-nota-4', 'bg-nota-5', 'bg-nota-6', 'bg-nota-7'];

export function iniciais(nome: string): string {
  const partes = nome.trim().split(/\s+/).filter(Boolean);
  if (partes.length === 0) return '?';
  return ((partes[0][0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase();
}

export function Avatar({ nome, tamanho = 'm', className }: { nome: string; tamanho?: 'p' | 'm' | 'g'; className?: string }) {
  const soma = [...nome].reduce((a, c) => a + c.charCodeAt(0), 0);
  const medidas = { p: 'h-9 w-9 text-xs', m: 'h-11 w-11 text-sm', g: 'h-20 w-20 text-2xl' }[tamanho];
  return (
    <span aria-hidden="true" className={cx('inline-grid shrink-0 place-items-center rounded-full font-bold text-white', CORES_NOTAS[soma % CORES_NOTAS.length], medidas, className)}>
      {iniciais(nome)}
    </span>
  );
}

export function Anel({ valor, tamanho = 96, cor = 'var(--color-marca-600)', children, rotulo }: { valor: number | null; tamanho?: number; cor?: string; children?: ReactNode; rotulo: string }) {
  const v = valor ?? 0;
  return (
    <div role="img" aria-label={`${rotulo}: ${valor === null ? 'sem dados' : `${valor}%`}`} className="anel" style={{ width: tamanho, height: tamanho, ['--v' as string]: v, ['--cor' as string]: cor }}>
      <span className="font-titulo font-semibold leading-none text-slate-900" style={{ fontSize: Math.round(tamanho * 0.22) }}>
        {children ?? (valor === null ? '—' : <>{String(valor).replace('.', ',')}<small className="ml-0.5 font-sans text-[0.6em] font-medium text-slate-500">%</small></>)}
      </span>
    </div>
  );
}

export function Barra({ valor, cor = 'var(--color-marca-600)', rotulo }: { valor: number | null; cor?: string; rotulo?: string }) {
  const v = Math.max(0, Math.min(100, valor ?? 0));
  return (
    <div role="progressbar" aria-label={rotulo} aria-valuemin={0} aria-valuemax={100} aria-valuenow={v} className="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
      <div className="h-full rounded-full" style={{ width: `${v}%`, background: cor }} />
    </div>
  );
}

/* ---------- Tabelas (viram cartões no celular) ---------- */
export function Tabela({ cabecalhos, children, rotulo }: { cabecalhos: string[]; children: ReactNode; rotulo: string }) {
  const ref = useRef<HTMLTableElement>(null);
  // Copia o texto do cabeçalho para cada célula (data-rotulo): no celular a linha vira um cartão "rótulo: valor".
  useLayoutEffect(() => {
    const t = ref.current;
    if (!t) return;
    const titulos = [...t.querySelectorAll('thead th')].map((th) => th.textContent ?? '');
    t.querySelectorAll('tbody > tr').forEach((tr) => {
      let col = 0;
      tr.querySelectorAll(':scope > td').forEach((td) => {
        const celula = td as HTMLTableCellElement;
        const span = celula.colSpan || 1;
        celula.dataset.rotulo = span > 1 ? '' : (titulos[col] ?? '');
        celula.classList.toggle('col-acoes', span === 1 && (titulos[col] ?? '') === '');
        col += span;
      });
    });
  });
  return (
    <div className="-mx-1 overflow-x-auto px-1 sm:mx-0 sm:px-0">
      <table ref={ref} className="tb-resp w-full min-w-[34rem] text-left text-sm sm:min-w-[34rem]" aria-label={rotulo}>
        <thead>
          <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
            {cabecalhos.map((c, i) => (
              <th key={`${c}-${i}`} scope="col" className="px-3 py-2.5 font-semibold">{c}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">{children}</tbody>
      </table>
    </div>
  );
}

export const Td = ({ className, ...r }: ComponentProps<'td'>) => <td className={cx('px-3 py-3 align-middle', className)} {...r} />;

/* ---------- Modal (vira "bottom sheet" no celular) e abas ---------- */
export function Modal({ aberto, aoFechar, titulo, children }: { aberto: boolean; aoFechar: () => void; titulo: string; children: ReactNode }) {
  return (
    <Dialog.Root open={aberto} onOpenChange={(a) => !a && aoFechar()}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-40 bg-slate-900/50" />
        <Dialog.Content
          aria-describedby={undefined}
          className={cx(
            'surgir fixed z-50 max-h-[92vh] overflow-y-auto bg-white p-5 pb-[calc(1.25rem+var(--safe-b))] shadow-2xl',
            'inset-x-0 bottom-0 rounded-t-3xl',
            'sm:inset-auto sm:left-1/2 sm:top-1/2 sm:w-[calc(100vw-2rem)] sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-3xl sm:pb-5',
          )}
        >
          <div aria-hidden="true" className="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-200 sm:hidden" />
          <div className="mb-3 flex items-start justify-between gap-3">
            <Dialog.Title className="font-titulo text-xl font-semibold text-slate-900">{titulo}</Dialog.Title>
            <Dialog.Close className="nao-imprimir -mr-2 -mt-1 grid h-11 w-11 place-items-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="Fechar">
              <Icone nome="x" />
            </Dialog.Close>
          </div>
          {children}
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

export function Confirmar({ aberto, aoFechar, aoConfirmar, titulo, texto, rotuloConfirmar = 'Confirmar' }: {
  aberto: boolean; aoFechar: () => void; aoConfirmar: () => void; titulo: string; texto: string; rotuloConfirmar?: string;
}) {
  return (
    <Modal aberto={aberto} aoFechar={aoFechar} titulo={titulo}>
      <p className="mb-5 text-sm text-slate-600">{texto}</p>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Botao variante="secundario" onClick={aoFechar}>Cancelar</Botao>
        <Botao variante="perigo" onClick={() => { aoConfirmar(); aoFechar(); }}>{rotuloConfirmar}</Botao>
      </div>
    </Modal>
  );
}

export function Abas({ abas, padrao }: { abas: { id: string; rotulo: string; conteudo: ReactNode }[]; padrao?: string }) {
  return (
    <Tabs.Root defaultValue={padrao ?? abas[0].id}>
      <Tabs.List className="nao-imprimir -mx-4 mb-5 flex gap-1 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0" aria-label="Seções">
        {abas.map((a) => (
          <Tabs.Trigger
            key={a.id}
            value={a.id}
            className="-mb-px whitespace-nowrap border-b-[3px] border-transparent px-4 text-sm font-semibold text-slate-500 hover:text-slate-800 data-[state=active]:border-marca-600 data-[state=active]:text-marca-700"
          >
            {a.rotulo}
          </Tabs.Trigger>
        ))}
      </Tabs.List>
      {abas.map((a) => (
        <Tabs.Content key={a.id} value={a.id} className="surgir">{a.conteudo}</Tabs.Content>
      ))}
    </Tabs.Root>
  );
}

/** Foca o primeiro campo ao montar (formulários em modais). */
export function useFocoInicial<T extends HTMLElement>() {
  const ref = useRef<T>(null);
  useEffect(() => { ref.current?.focus(); }, []);
  return ref;
}

/* ---------- Notificações rápidas (toasts) ---------- */
type TipoToast = 'ok' | 'erro' | 'alerta' | 'info';
interface ToastItem { id: number; texto: string; tipo: TipoToast }
const ContextoToast = createContext<(texto: string, tipo?: TipoToast) => void>(() => {});

export function ToastProvider({ children }: { children: ReactNode }) {
  const [itens, setItens] = useState<ToastItem[]>([]);
  const proximo = useRef(1);
  const fechar = useCallback((id: number) => setItens((l) => l.filter((t) => t.id !== id)), []);
  const avisar = useCallback((texto: string, tipo: TipoToast = 'ok') => {
    const id = proximo.current++;
    setItens((l) => [...l, { id, texto, tipo }]);
    window.setTimeout(() => fechar(id), 5200);
  }, [fechar]);
  const valor = useMemo(() => avisar, [avisar]);
  const cor = { ok: 'border-emerald-600', erro: 'border-red-600', alerta: 'border-ambar-500', info: 'border-sky-700' };
  return (
    <ContextoToast.Provider value={valor}>
      {children}
      <div aria-live="polite" className="nao-imprimir pointer-events-none fixed inset-x-3 bottom-[calc(5.5rem+var(--safe-b))] z-[60] flex flex-col items-center gap-2 sm:inset-x-auto sm:bottom-5 sm:right-5 sm:items-end lg:bottom-5">
        {itens.map((t) => (
          <div key={t.id} role={t.tipo === 'erro' ? 'alert' : 'status'} className={cx('surgir pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-2xl border-l-[5px] bg-slate-900 px-4 py-3 text-sm text-white shadow-xl', cor[t.tipo])}>
            <span className="min-w-0 flex-1">{t.texto}</span>
            <button type="button" aria-label="Fechar aviso" onClick={() => fechar(t.id)} className="!min-h-8 shrink-0 text-lg leading-none opacity-70 hover:opacity-100">×</button>
          </div>
        ))}
      </div>
    </ContextoToast.Provider>
  );
}

export function useToast() {
  return useContext(ContextoToast);
}

import * as Dialog from '@radix-ui/react-dialog';
import * as Tabs from '@radix-ui/react-tabs';
import type { ComponentProps, ReactNode } from 'react';
import { useEffect, useRef } from 'react';

const cx = (...c: (string | false | undefined | null)[]) => c.filter(Boolean).join(' ');

/* ---------- Botões ---------- */
type PropsBotao = ComponentProps<'button'> & { variante?: 'primario' | 'secundario' | 'perigo' | 'fantasma'; carregando?: boolean };

export function Botao({ variante = 'primario', carregando, className, children, disabled, ...resto }: PropsBotao) {
  const base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed';
  const estilos = {
    primario: 'bg-marca-600 text-white hover:bg-marca-700',
    secundario: 'bg-white text-slate-800 border border-slate-300 hover:bg-slate-50',
    perigo: 'bg-red-600 text-white hover:bg-red-700',
    fantasma: 'text-marca-700 hover:bg-marca-50',
  }[variante];
  return (
    <button className={cx(base, estilos, className)} disabled={disabled || carregando} {...resto}>
      {carregando ? 'Aguarde…' : children}
    </button>
  );
}

/* ---------- Campos ---------- */
type PropsCampo = { rotulo: string; erro?: string; dica?: string; className?: string };

function Rotulo({ rotulo, erro, dica, id, children, className }: PropsCampo & { id: string; children: ReactNode }) {
  return (
    <div className={cx('flex flex-col gap-1', className)}>
      <label htmlFor={id} className="text-sm font-medium text-slate-700">{rotulo}</label>
      {children}
      {dica && !erro && <span className="text-xs text-slate-500">{dica}</span>}
      {erro && <span role="alert" className="text-xs text-red-600">{erro}</span>}
    </div>
  );
}

const estiloEntrada = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base';

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
    <label className={cx('flex items-center gap-3 text-sm text-slate-800', className)}>
      <input type="checkbox" className="h-5 w-5 rounded border-slate-300" {...resto} />
      {rotulo}
    </label>
  );
}

/* ---------- Estrutura ---------- */
export function Cartao({ titulo, acoes, children, className }: { titulo?: string; acoes?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={cx('imprimir-pagina rounded-xl border border-slate-200 bg-white p-4 shadow-sm', className)}>
      {(titulo || acoes) && (
        <header className="mb-3 flex flex-wrap items-center justify-between gap-2">
          {titulo && <h2 className="text-base font-semibold text-slate-900">{titulo}</h2>}
          {acoes && <div className="nao-imprimir flex gap-2">{acoes}</div>}
        </header>
      )}
      {children}
    </section>
  );
}

export function Titulo({ children, acoes }: { children: ReactNode; acoes?: ReactNode }) {
  return (
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{children}</h1>
      {acoes && <div className="nao-imprimir flex flex-wrap gap-2">{acoes}</div>}
    </div>
  );
}

export function Aviso({ tipo = 'erro', children }: { tipo?: 'erro' | 'sucesso' | 'info' | 'alerta'; children: ReactNode }) {
  const estilos = {
    erro: 'border-red-200 bg-red-50 text-red-800',
    sucesso: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    info: 'border-sky-200 bg-sky-50 text-sky-800',
    alerta: 'border-amber-300 bg-amber-50 text-amber-900',
  }[tipo];
  return (
    <div role={tipo === 'erro' ? 'alert' : 'status'} className={cx('rounded-lg border px-3 py-2 text-sm', estilos)}>
      {children}
    </div>
  );
}

export function Carregando({ texto = 'Carregando…' }: { texto?: string }) {
  return <p role="status" className="py-6 text-center text-sm text-slate-500">{texto}</p>;
}

export function Vazio({ children }: { children: ReactNode }) {
  return <p className="py-6 text-center text-sm text-slate-500">{children}</p>;
}

type TomBadge = 'neutro' | 'verde' | 'amarelo' | 'vermelho' | 'azul' | 'roxo';
export function Badge({ tom = 'neutro', children }: { tom?: TomBadge; children: ReactNode }) {
  const estilos: Record<TomBadge, string> = {
    neutro: 'bg-slate-100 text-slate-700',
    verde: 'bg-emerald-100 text-emerald-800',
    amarelo: 'bg-amber-100 text-amber-800',
    vermelho: 'bg-red-100 text-red-800',
    azul: 'bg-sky-100 text-sky-800',
    roxo: 'bg-violet-100 text-violet-800',
  };
  return <span className={cx('inline-block rounded-full px-2 py-0.5 text-xs font-medium', estilos[tom])}>{children}</span>;
}

export function Tabela({ cabecalhos, children, rotulo }: { cabecalhos: string[]; children: ReactNode; rotulo: string }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[32rem] text-left text-sm" aria-label={rotulo}>
        <thead>
          <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
            {cabecalhos.map((c) => (
              <th key={c} scope="col" className="px-2 py-2 font-medium">{c}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">{children}</tbody>
      </table>
    </div>
  );
}

export const Td = ({ className, ...r }: ComponentProps<'td'>) => <td className={cx('px-2 py-2 align-middle', className)} {...r} />;

/* ---------- Modal e abas (Radix) ---------- */
export function Modal({ aberto, aoFechar, titulo, children }: { aberto: boolean; aoFechar: () => void; titulo: string; children: ReactNode }) {
  return (
    <Dialog.Root open={aberto} onOpenChange={(a) => !a && aoFechar()}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-40 bg-slate-900/50" />
        <Dialog.Content
          aria-describedby={undefined}
          className="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100vw-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl bg-white p-5 shadow-xl"
        >
          <Dialog.Title className="mb-3 text-lg font-semibold">{titulo}</Dialog.Title>
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
      <p className="mb-4 text-sm text-slate-700">{texto}</p>
      <div className="flex justify-end gap-2">
        <Botao variante="secundario" onClick={aoFechar}>Cancelar</Botao>
        <Botao variante="perigo" onClick={() => { aoConfirmar(); aoFechar(); }}>{rotuloConfirmar}</Botao>
      </div>
    </Modal>
  );
}

export function Abas({ abas, padrao }: { abas: { id: string; rotulo: string; conteudo: ReactNode }[]; padrao?: string }) {
  return (
    <Tabs.Root defaultValue={padrao ?? abas[0].id}>
      <Tabs.List className="nao-imprimir mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Seções">
        {abas.map((a) => (
          <Tabs.Trigger
            key={a.id}
            value={a.id}
            className="whitespace-nowrap border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-600 data-[state=active]:border-marca-600 data-[state=active]:text-marca-700"
          >
            {a.rotulo}
          </Tabs.Trigger>
        ))}
      </Tabs.List>
      {abas.map((a) => (
        <Tabs.Content key={a.id} value={a.id}>{a.conteudo}</Tabs.Content>
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

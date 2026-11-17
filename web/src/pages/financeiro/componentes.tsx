import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { useLista } from '../../api/hooks';
import { ESTADO_COBRANCA, TOM_ESTADO, type Cobranca, type EstadoCobranca, type ResumoFinanceiro } from '../../api/tipos';
import { Icone } from '../../components/icones';
import { AreaTexto, Aviso, Badge, Botao, Campo, Cartao, Modal, Selecao, useToast } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { hoje, moeda } from '../../lib/format';

export const BadgeEstado = ({ estado, excedente = 0 }: { estado: EstadoCobranca; excedente?: number }) => (
  <>
    <Badge tom={TOM_ESTADO[estado]}>{ESTADO_COBRANCA[estado]}</Badge>
    {excedente > 0 && (
      <span className="ml-1 text-xs font-medium text-amber-700" title="Pago a mais que o valor da cobrança">+{moeda(excedente)} a mais</span>
    )}
  </>
);

/** Invalida tudo que depende de cobranças/pagamentos. */
export function useInvalidarFinanceiro() {
  const cliente = useQueryClient();
  return () => {
    for (const k of ['cobrancas', 'ficha-financeira', 'dashboard-financeiro']) void cliente.invalidateQueries({ queryKey: [k] });
  };
}

export function ResumoCards({ resumo }: { resumo: ResumoFinanceiro }) {
  const itens: { r: string; v: string; nota: 1 | 3 | 4 | 6; icone: 'carteira' | 'check' | 'relogio' | 'alerta' }[] = [
    { r: 'Previsto', v: moeda(resumo.previsto), nota: 6, icone: 'carteira' },
    { r: 'Recebido', v: moeda(resumo.recebido), nota: 4, icone: 'check' },
    { r: 'Em aberto', v: moeda(resumo.aberto), nota: 3, icone: 'relogio' },
    { r: 'Atrasado', v: moeda(resumo.atrasado), nota: 1, icone: 'alerta' },
  ];
  return (
    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
      {itens.map((i) => (
        <div key={i.r} className="flex items-center gap-3 rounded-[22px] border border-slate-200 bg-white p-3.5 shadow-[var(--shadow-cartao)] sm:p-4">
          <span className="hidden h-11 w-11 shrink-0 place-items-center rounded-2xl sm:grid" style={{ background: `color-mix(in srgb, var(--color-nota-${i.nota}) 18%, white)`, color: i.nota === 3 ? '#8a6a00' : `var(--color-nota-${i.nota})` }}>
            <Icone nome={i.icone} />
          </span>
          <div className="min-w-0"><p className="text-xs text-slate-500">{i.r}</p><p className="truncate font-titulo text-xl font-semibold sm:text-2xl">{i.v}</p></div>
        </div>
      ))}
    </div>
  );
}

/* ---------- Pagamento ---------- */
const esquemaPagamento = z.object({
  valor: z.coerce.number({ error: 'Informe o valor.' }).positive('Informe um valor maior que zero.'),
  data_pagamento: z.string().min(1, 'Informe a data.'),
  forma_pagamento_id: z.preprocess((v) => (v === '' ? null : v), z.coerce.number().nullable()),
  observacoes: z.preprocess((v) => (v === '' ? null : v), z.string().nullable()),
});
type EntradaPagamento = z.input<typeof esquemaPagamento>;
type SaidaPagamento = z.output<typeof esquemaPagamento>;

export function ModalPagamento({ cobranca, aoFechar }: { cobranca: Cobranca | null; aoFechar: () => void }) {
  const formas = useLista('forma_pagamento');
  const invalidar = useInvalidarFinanceiro();
  const avisar = useToast();
  const [erro, setErro] = useState<string | null>(null);
  const { register, handleSubmit, formState, reset, control } = useForm<EntradaPagamento, unknown, SaidaPagamento>({
    resolver: zodResolver(esquemaPagamento),
    values: cobranca ? { valor: cobranca.saldo, data_pagamento: hoje(), forma_pagamento_id: '', observacoes: '' } : undefined,
  });
  const digitado = Number(useWatch({ control, name: 'valor' }) ?? 0);
  const excesso = cobranca ? Math.round((digitado - cobranca.saldo) * 100) / 100 : 0;
  const salvar = useMutation({
    mutationFn: (d: SaidaPagamento) => api.post(`/cobrancas/${cobranca!.id}/pagamentos`, d),
    onSuccess: () => { invalidar(); reset(); setErro(null); aoFechar(); avisar(excesso > 0 ? `Pagamento registrado, com excedente de ${moeda(excesso)}.` : 'Pagamento registrado.', excesso > 0 ? 'alerta' : 'ok'); },
    onError: (e) => setErro(mensagemDeErro(e)),
  });
  return (
    <Modal aberto={!!cobranca} aoFechar={aoFechar} titulo="Registrar pagamento">
      {cobranca && (
        <form onSubmit={handleSubmit((d) => salvar.mutate(d))} noValidate className="flex flex-col gap-3">
          <p className="text-sm text-slate-600">
            <b>{cobranca.aluno}</b> — competência {cobranca.competencia} · saldo {moeda(cobranca.saldo)}
          </p>
          {erro && <Aviso>{erro}</Aviso>}
          <Campo rotulo="Valor pago (R$)" type="number" step="0.01" min="0.01"
            dica="Pagamento parcial é permitido; valor acima do saldo também é aceito (com alerta)." erro={formState.errors.valor?.message} {...register('valor')} />
          {excesso > 0 && (
            <Aviso tipo="alerta">
              <b>Atenção:</b> o valor informado é {moeda(excesso)} maior que o saldo ({moeda(cobranca.saldo)}). O pagamento será aceito e o
              excedente de {moeda(excesso)} ficará registrado nesta cobrança.
            </Aviso>
          )}
          <Campo rotulo="Data do pagamento" type="date" erro={formState.errors.data_pagamento?.message} {...register('data_pagamento')} />
          <Selecao rotulo="Forma de pagamento" {...register('forma_pagamento_id')}>
            <option value="">—</option>
            {formas.data?.map((f) => <option key={f.id} value={f.id}>{f.valor}</option>)}
          </Selecao>
          <AreaTexto rotulo="Observações" {...register('observacoes')} />
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Botao type="button" variante="secundario" onClick={aoFechar}>Cancelar</Botao>
            <Botao type="submit" carregando={salvar.isPending}>Confirmar pagamento</Botao>
          </div>
        </form>
      )}
    </Modal>
  );
}

/* ---------- Edição da cobrança ---------- */
const esquemaCobranca = z.object({
  valor: z.coerce.number({ error: 'Informe o valor.' }).min(0),
  desconto: z.coerce.number({ error: 'Informe o desconto.' }).min(0),
  vencimento: z.string().min(1, 'Informe o vencimento.'),
  observacoes: z.preprocess((v) => (v === '' ? null : v), z.string().nullable()),
});
type EntradaCobranca = z.input<typeof esquemaCobranca>;
type SaidaCobranca = z.output<typeof esquemaCobranca>;

export function ModalCobranca({ cobranca, aoFechar }: { cobranca: Cobranca | null; aoFechar: () => void }) {
  const invalidar = useInvalidarFinanceiro();
  const avisar = useToast();
  const [erro, setErro] = useState<string | null>(null);
  const { register, handleSubmit, formState } = useForm<EntradaCobranca, unknown, SaidaCobranca>({
    resolver: zodResolver(esquemaCobranca),
    values: cobranca ? { valor: cobranca.valor, desconto: cobranca.desconto, vencimento: cobranca.vencimento, observacoes: cobranca.observacoes ?? '' } : undefined,
  });
  const concluir = () => { invalidar(); setErro(null); aoFechar(); avisar('Cobrança atualizada.', 'ok'); };
  const salvar = useMutation({
    mutationFn: (d: SaidaCobranca) => api.put(`/cobrancas/${cobranca!.id}`, d),
    onSuccess: concluir,
    onError: (e) => setErro(mensagemDeErro(e)),
  });
  const situacao = useMutation({
    mutationFn: (s: 'aberta' | 'cancelada') => api.put(`/cobrancas/${cobranca!.id}`, { situacao: s }),
    onSuccess: concluir,
    onError: (e) => setErro(mensagemDeErro(e)),
  });
  const cancelada = cobranca?.situacao === 'cancelada';
  return (
    <Modal aberto={!!cobranca} aoFechar={aoFechar} titulo="Ajustar cobrança">
      {cobranca && (
        <form onSubmit={handleSubmit((d) => salvar.mutate(d))} noValidate className="flex flex-col gap-3">
          <p className="text-sm text-slate-600"><b>{cobranca.aluno}</b> — competência {cobranca.competencia}</p>
          {erro && <Aviso>{erro}</Aviso>}
          <div className="grid gap-3 sm:grid-cols-2">
            <Campo rotulo="Valor (R$)" type="number" step="0.01" min="0" erro={formState.errors.valor?.message} {...register('valor')} />
            <Campo rotulo="Desconto (R$)" type="number" step="0.01" min="0" erro={formState.errors.desconto?.message} {...register('desconto')} />
            <Campo rotulo="Vencimento" type="date" erro={formState.errors.vencimento?.message} className="sm:col-span-2" {...register('vencimento')} />
          </div>
          <AreaTexto rotulo="Observações" {...register('observacoes')} />
          <div className="flex flex-wrap justify-between gap-2">
            <Botao type="button" variante={cancelada ? 'secundario' : 'perigo'} carregando={situacao.isPending}
              onClick={() => situacao.mutate(cancelada ? 'aberta' : 'cancelada')}>
              {cancelada ? 'Reabrir cobrança' : 'Cancelar cobrança'}
            </Botao>
            <div className="flex gap-2">
              <Botao type="button" variante="secundario" onClick={aoFechar}>Fechar</Botao>
              <Botao type="submit" carregando={salvar.isPending}>Salvar</Botao>
            </div>
          </div>
        </form>
      )}
    </Modal>
  );
}

export function SemCobrancas({ children }: { children: React.ReactNode }) {
  return <Cartao><p className="py-4 text-center text-sm text-slate-500">{children}</p></Cartao>;
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ESTAGIO, NIVEL, SITUACAO_METODO, STATUS_TECNICA,
  type Aluno, type EstagioValor, type FichaProgresso, type Metodo, type MetodoDoAluno, type SituacaoMetodo, type StatusTecnica,
} from '../../api/tipos';
import { Icone } from '../../components/icones';
import { Anel, Aviso, Badge, Barra, Botao, Carregando, Cartao, Interruptor, Modal, Selecao, Vazio, useToast } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { data, percentual } from '../../lib/format';

const tomSituacao = (s: SituacaoMetodo) => (s === 'concluido' ? 'verde' : s === 'pausado' ? 'amarelo' : 'azul') as 'verde' | 'amarelo' | 'azul';

function CartaoMetodo({ alunoId, m, aoMudar, aoFalhar }: { alunoId: number; m: MetodoDoAluno; aoMudar: (msg: string) => void; aoFalhar: (e: unknown) => void }) {
  const situacao = useMutation({
    mutationFn: (s: SituacaoMetodo) => api.put(`/alunos/${alunoId}/metodos/${m.metodo_id}`, { situacao: s }),
    onSuccess: (_r, s) => aoMudar(`${m.metodo}: ${SITUACAO_METODO[s].toLowerCase()}.`),
    onError: aoFalhar,
  });
  const remover = useMutation({
    mutationFn: () => api.delete(`/alunos/${alunoId}/metodos/${m.metodo_id}`),
    onSuccess: () => aoMudar(`${m.metodo} removido do aluno.`),
    onError: aoFalhar,
  });
  const cor = m.tipo === 'hinario' ? 'var(--color-nota-3)' : m.situacao === 'concluido' ? 'var(--color-emerald-600)' : 'var(--color-marca-600)';
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4">
      <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <Link className="font-semibold text-slate-900 hover:underline" to={`/alunos/${alunoId}/metodos/${m.metodo_id}`}>{m.metodo}</Link>
          {m.tipo === 'hinario' && <Badge tom="roxo">Hinário</Badge>}
          <Badge tom={tomSituacao(m.situacao)}>{SITUACAO_METODO[m.situacao]}</Badge>
          <span className="text-xs text-slate-500">{m.nivel ? `nível ${NIVEL[m.nivel]}` : 'sem nível'}</span>
        </div>
        <b>{percentual(m.media)}</b>
      </div>
      <Barra valor={m.media} cor={cor} rotulo={`Progresso em ${m.metodo}`} />
      <p className="mt-2 text-[13px] text-slate-500">
        {m.aprovadas} de {m.total_licoes} lições aprovadas
        {m.licao_atual && <> · atual: <b className="text-slate-900">{m.licao_atual.ordem}. {m.licao_atual.titulo}</b>{m.licao_atual.status === 'reprovado' && ` (reprovada ${m.licao_atual.tentativas}× desde ${data(m.licao_atual.desde)})`}</>}
      </p>
      {m.concluir_sugerido && (
        <div className="mt-3"><Aviso tipo="info">Todas as lições foram aprovadas. <b>Deseja marcar o método como concluído?</b></Aviso></div>
      )}
      <div className="nao-imprimir mt-3 flex flex-wrap gap-1.5">
        <Link to={`/alunos/${alunoId}/metodos/${m.metodo_id}`}><Botao variante="secundario" compacto>Lições</Botao></Link>
        {m.situacao !== 'concluido' && <Botao variante={m.concluir_sugerido ? 'primario' : 'fantasma'} compacto onClick={() => situacao.mutate('concluido')}>Concluir</Botao>}
        {m.situacao === 'iniciado' && <Botao variante="fantasma" compacto onClick={() => situacao.mutate('pausado')}>Pausar</Botao>}
        {m.situacao !== 'iniciado' && <Botao variante="fantasma" compacto onClick={() => situacao.mutate('iniciado')}>{m.situacao === 'pausado' ? 'Retomar' : 'Reabrir'}</Botao>}
        <Botao variante="fantasma" compacto onClick={() => remover.mutate()}>Remover</Botao>
      </div>
    </div>
  );
}

export function AbaProgresso({ aluno }: { aluno: Aluno }) {
  const cliente = useQueryClient();
  const avisar = useToast();
  const [erro, setErro] = useState<string | null>(null);
  const [associando, setAssociando] = useState(false);
  const [metodoEscolhido, setMetodoEscolhido] = useState('');

  const consulta = useQuery({
    queryKey: ['ficha-progresso', aluno.id],
    queryFn: async () => (await api.get<{ dados: FichaProgresso }>(`/alunos/${aluno.id}/ficha-progresso`)).dados,
  });
  const metodos = useQuery({
    queryKey: ['metodos', 'ativos'],
    queryFn: async () => (await api.get<{ dados: Metodo[] }>('/metodos?ativos=1')).dados,
    enabled: associando,
  });

  const refrescar = (mensagem?: string) => {
    setErro(null);
    for (const k of ['ficha-progresso', 'inicio', 'alunos']) void cliente.invalidateQueries({ queryKey: [k] });
    if (mensagem) avisar(mensagem, 'ok');
  };
  const falhou = (e: unknown) => setErro(mensagemDeErro(e));

  const salvar = useMutation({
    mutationFn: ({ corpo }: { corpo: Record<string, unknown>; aviso: string }) => api.put(`/alunos/${aluno.id}/progresso`, corpo),
    onSuccess: (_r, v) => refrescar(v.aviso),
    onError: falhou,
  });
  const associar = useMutation({
    mutationFn: () => api.post(`/alunos/${aluno.id}/metodos`, { metodo_id: Number(metodoEscolhido) }),
    onSuccess: () => { setAssociando(false); setMetodoEscolhido(''); refrescar('Método associado (Iniciado).'); },
    onError: falhou,
  });

  if (consulta.isLoading) return <Carregando />;
  const f = consulta.data;
  if (!f) return <Aviso>Não foi possível carregar a ficha de progresso.</Aviso>;
  const disponiveis = metodos.data?.filter((m) => !f.metodos.some((x) => x.metodo_id === m.id)) ?? [];

  return (
    <div className="flex flex-col gap-4 sm:gap-5">
      {erro && <Aviso>{erro}</Aviso>}
      <div className="grid gap-4 sm:gap-5 lg:grid-cols-[2fr_1fr]">
        <Cartao titulo="Estágio e progresso" acoes={<Badge>{f.estagio.origem === 'manual' ? 'definido pela professora' : 'calculado'}</Badge>}>
          <div className="flex flex-wrap items-center gap-x-8 gap-y-4">
            <Anel valor={f.progresso_geral} tamanho={132} rotulo="Progresso geral" />
            <div className="min-w-[14rem] flex-1">
              <div className="text-[13px] text-slate-500">Estágio atual</div>
              <div className="font-titulo text-3xl font-semibold">{f.estagio.rotulo ?? 'Sem estágio'}</div>
              <p className="mb-3 mt-1 text-[13px] text-slate-500">
                {f.estagio.origem === 'manual' && 'Definido pela professora.'}
                {f.estagio.origem === 'calculado' && 'Nível do método mais alto em andamento ou concluído.'}
                {f.estagio.origem === null && 'Associe métodos com nível ou defina o estágio manualmente.'}
                {f.estagio.calculado && f.estagio.origem === 'manual' && ` Calculado: ${ESTAGIO[f.estagio.calculado]}.`}
              </p>
              <Selecao rotulo="Estágio definido pela professora" value={f.estagio.manual ?? ''}
                onChange={(e) => salvar.mutate({ corpo: { estagio_manual: e.target.value === '' ? null : e.target.value }, aviso: 'Estágio atualizado.' })}>
                <option value="">Usar o calculado{f.estagio.calculado ? ` (${ESTAGIO[f.estagio.calculado]})` : ''}</option>
                {(Object.entries(ESTAGIO) as [EstagioValor, string][]).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
              </Selecao>
            </div>
          </div>
          <p className="mt-3 text-xs text-slate-500">Progresso geral = média ponderada (pelo nº de lições) dos métodos em andamento ou concluídos; métodos pausados ficam de fora.</p>
        </Cartao>

        <Cartao titulo="Apto para culto">
          <div className="flex flex-col">
            <Interruptor rotulo="Culto de jovens" checked={f.aptidao.jovens} onChange={(e) => salvar.mutate({ corpo: { apto_jovens: e.target.checked }, aviso: e.target.checked ? 'Marcado como apto (jovens).' : 'Marcação de jovens removida.' })} />
            <Interruptor rotulo="Culto adulto" checked={f.aptidao.adulto} onChange={(e) => salvar.mutate({ corpo: { apto_adulto: e.target.checked }, aviso: e.target.checked ? 'Marcado como apto (adulto).' : 'Marcação de adulto removida.' })} />
            <Interruptor rotulo="Oficialização" checked={f.aptidao.oficializacao} onChange={(e) => salvar.mutate({ corpo: { apto_oficializacao: e.target.checked }, aviso: e.target.checked ? 'Marcado para oficialização.' : 'Marcação de oficialização removida.' })} />
          </div>
          <p className="mt-2 text-xs text-slate-500">Decisão da professora, sem regra rígida. Cada marcação fica registrada no log.</p>
        </Cartao>
      </div>

      <div className="grid gap-4 sm:gap-5 lg:grid-cols-[2fr_1fr]">
        <Cartao titulo="Métodos e hinário" acoes={<Botao compacto onClick={() => setAssociando(true)}><Icone nome="mais" className="h-4 w-4" />Associar método</Botao>}>
          {!f.metodos.length ? <Vazio icone="livro">Nenhum método associado a este aluno.</Vazio> : (
            <div className="flex flex-col gap-3">
              {f.metodos.map((m) => <CartaoMetodo key={m.metodo_id} alunoId={aluno.id} m={m} aoMudar={refrescar} aoFalhar={falhou} />)}
            </div>
          )}
        </Cartao>

        <div className="flex flex-col gap-4 sm:gap-5">
          <Cartao titulo="Técnica">
            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
              {([['m1', 'Mão 1'], ['m2', 'Mão 2'], ['ped', 'Pedal']] as const).map(([chave, rotulo]) => (
                <Selecao key={chave} rotulo={rotulo} value={f.tecnica[chave]} onChange={(e) => salvar.mutate({ corpo: { [`tecnica_${chave}`]: e.target.value as StatusTecnica }, aviso: 'Técnica atualizada.' })}>
                  {Object.entries(STATUS_TECNICA).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                </Selecao>
              ))}
            </div>
          </Cartao>
          <Cartao titulo="Frequência">
            <div className="flex items-center gap-4">
              <Anel valor={f.frequencia.frequencia} tamanho={84} cor="var(--color-nota-5)" rotulo="Frequência" />
              <p className="text-[13px] text-slate-500">{f.frequencia.aulas} aulas · {f.frequencia.presentes} presenças<br />{f.frequencia.faltas_aluno} faltas · {f.frequencia.faltas_professora} da professora</p>
            </div>
          </Cartao>
        </div>
      </div>

      <Modal aberto={associando} aoFechar={() => setAssociando(false)} titulo="Associar método ao aluno">
        <Selecao rotulo="Método ou hinário" value={metodoEscolhido} onChange={(e) => setMetodoEscolhido(e.target.value)}>
          <option value="">Escolha…</option>
          {disponiveis.map((m) => <option key={m.id} value={m.id}>{m.nome}{m.nivel ? ` — ${NIVEL[m.nivel]}` : ''}</option>)}
        </Selecao>
        {metodos.isSuccess && !disponiveis.length && <p className="mt-2 text-sm text-slate-500">Todos os métodos ativos já estão associados. Cadastre outro em “Métodos”.</p>}
        <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Botao variante="secundario" onClick={() => setAssociando(false)}>Cancelar</Botao>
          <Botao disabled={!metodoEscolhido} carregando={associar.isPending} onClick={() => associar.mutate()}>Associar</Botao>
        </div>
      </Modal>
    </div>
  );
}

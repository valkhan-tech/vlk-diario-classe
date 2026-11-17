import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ESTAGIO, NIVEL, SITUACAO_METODO, STATUS_TECNICA,
  type Aluno, type EstagioValor, type FichaProgresso, type Metodo, type MetodoDoAluno, type SituacaoMetodo, type StatusTecnica,
} from '../../api/tipos';
import { Aviso, Badge, Botao, Carregando, Cartao, Marcador, Modal, Selecao, Vazio } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { data, percentual } from '../../lib/format';

const tomSituacao = (s: SituacaoMetodo) => (s === 'concluido' ? 'verde' : s === 'pausado' ? 'amarelo' : 'azul') as 'verde' | 'amarelo' | 'azul';

function Barra({ valor }: { valor: number | null }) {
  const v = valor ?? 0;
  return (
    <div role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={v} className="h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
      <div className="h-full rounded-full bg-marca-600" style={{ width: `${v}%` }} />
    </div>
  );
}

function CartaoMetodo({ alunoId, m, aoMudar, aoFalhar }: { alunoId: number; m: MetodoDoAluno; aoMudar: () => void; aoFalhar: (e: unknown) => void }) {
  const situacao = useMutation({
    mutationFn: (s: SituacaoMetodo) => api.put(`/alunos/${alunoId}/metodos/${m.metodo_id}`, { situacao: s }),
    onSuccess: aoMudar,
    onError: aoFalhar,
  });
  const remover = useMutation({
    mutationFn: () => api.delete(`/alunos/${alunoId}/metodos/${m.metodo_id}`),
    onSuccess: aoMudar,
    onError: aoFalhar,
  });
  return (
    <div className="rounded-lg border border-slate-200 p-3">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <Link className="font-semibold text-marca-700 underline" to={`/alunos/${alunoId}/metodos/${m.metodo_id}`}>{m.metodo}</Link>
          {m.tipo === 'hinario' && <Badge tom="roxo">Hinário</Badge>}
          <Badge tom={tomSituacao(m.situacao)}>{SITUACAO_METODO[m.situacao]}</Badge>
          <span className="text-xs text-slate-500">{m.nivel ? `nível ${NIVEL[m.nivel]}` : 'sem nível'}</span>
        </div>
        <span className="text-sm font-medium">{percentual(m.media)}</span>
      </div>
      <Barra valor={m.media} />
      <p className="mt-1 text-xs text-slate-600">
        {m.aprovadas} de {m.total_licoes} lições aprovadas
        {m.licao_atual && <> · lição atual: <b>{m.licao_atual.ordem}. {m.licao_atual.titulo}</b>{m.licao_atual.status === 'reprovado' && ` (reprovada ${m.licao_atual.tentativas}× desde ${data(m.licao_atual.desde)})`}</>}
      </p>
      {m.concluir_sugerido && (
        <div className="mt-2"><Aviso tipo="info">Todas as lições foram aprovadas. Deseja marcar o método como concluído?</Aviso></div>
      )}
      <div className="nao-imprimir mt-2 flex flex-wrap gap-1">
        <Link to={`/alunos/${alunoId}/metodos/${m.metodo_id}`}><Botao variante="secundario">Lições</Botao></Link>
        {m.situacao !== 'concluido' && <Botao variante={m.concluir_sugerido ? 'primario' : 'fantasma'} onClick={() => situacao.mutate('concluido')}>Concluir</Botao>}
        {m.situacao === 'iniciado' && <Botao variante="fantasma" onClick={() => situacao.mutate('pausado')}>Pausar</Botao>}
        {m.situacao !== 'iniciado' && <Botao variante="fantasma" onClick={() => situacao.mutate('iniciado')}>{m.situacao === 'pausado' ? 'Retomar' : 'Reabrir'}</Botao>}
        <Botao variante="fantasma" onClick={() => remover.mutate()}>Remover</Botao>
      </div>
    </div>
  );
}

export function AbaProgresso({ aluno }: { aluno: Aluno }) {
  const cliente = useQueryClient();
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

  const refrescar = () => {
    setErro(null);
    for (const k of ['ficha-progresso', 'inicio']) void cliente.invalidateQueries({ queryKey: [k] });
  };
  const falhou = (e: unknown) => setErro(mensagemDeErro(e));

  const salvar = useMutation({
    mutationFn: (corpo: Record<string, unknown>) => api.put(`/alunos/${aluno.id}/progresso`, corpo),
    onSuccess: refrescar,
    onError: falhou,
  });
  const associar = useMutation({
    mutationFn: () => api.post(`/alunos/${aluno.id}/metodos`, { metodo_id: Number(metodoEscolhido) }),
    onSuccess: () => { setAssociando(false); setMetodoEscolhido(''); refrescar(); },
    onError: falhou,
  });

  if (consulta.isLoading) return <Carregando />;
  const f = consulta.data;
  if (!f) return <Aviso>Não foi possível carregar a ficha de progresso.</Aviso>;
  const disponiveis = metodos.data?.filter((m) => !f.metodos.some((x) => x.metodo_id === m.id)) ?? [];

  return (
    <div className="flex flex-col gap-4">
      {erro && <Aviso>{erro}</Aviso>}
      <div className="grid gap-4 lg:grid-cols-2">
        <Cartao titulo="Estágio">
          <p className="text-2xl font-bold text-marca-700">{f.estagio.rotulo ?? 'Sem estágio'}</p>
          <p className="mb-3 text-xs text-slate-500">
            {f.estagio.origem === 'manual' && 'Definido pela professora.'}
            {f.estagio.origem === 'calculado' && 'Calculado a partir do nível dos métodos em andamento ou concluídos.'}
            {f.estagio.origem === null && 'Associe métodos com nível ou defina o estágio manualmente.'}
            {f.estagio.calculado && f.estagio.origem === 'manual' && ` Calculado: ${ESTAGIO[f.estagio.calculado]}.`}
          </p>
          <Selecao rotulo="Estágio definido pela professora" value={f.estagio.manual ?? ''}
            onChange={(e) => salvar.mutate({ estagio_manual: e.target.value === '' ? null : e.target.value })}>
            <option value="">Usar o calculado{f.estagio.calculado ? ` (${ESTAGIO[f.estagio.calculado]})` : ''}</option>
            {(Object.entries(ESTAGIO) as [EstagioValor, string][]).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
          </Selecao>
        </Cartao>

        <Cartao titulo="Progresso geral">
          <p className="text-3xl font-bold text-marca-700">{percentual(f.progresso_geral)}</p>
          <Barra valor={f.progresso_geral} />
          <p className="mt-2 text-xs text-slate-500">Média ponderada (pelo nº de lições) dos métodos em andamento ou concluídos; métodos pausados ficam de fora.</p>
          <p className="mt-2 text-sm text-slate-600">Frequência: <b>{percentual(f.frequencia.frequencia)}</b> ({f.frequencia.aulas} aulas)</p>
        </Cartao>

        <Cartao titulo="Apto para culto (decisão da professora)">
          <div className="flex flex-col gap-3">
            <Marcador rotulo="Culto de jovens" checked={f.aptidao.jovens} onChange={(e) => salvar.mutate({ apto_jovens: e.target.checked })} />
            <Marcador rotulo="Culto adulto" checked={f.aptidao.adulto} onChange={(e) => salvar.mutate({ apto_adulto: e.target.checked })} />
            <Marcador rotulo="Oficialização" checked={f.aptidao.oficializacao} onChange={(e) => salvar.mutate({ apto_oficializacao: e.target.checked })} />
          </div>
          <p className="mt-2 text-xs text-slate-500">Sem regra rígida: se a professora marcou, está apto. Cada marcação fica registrada no log.</p>
        </Cartao>

        <Cartao titulo="Técnica">
          <div className="grid gap-3 sm:grid-cols-3">
            {([['m1', 'Mão 1'], ['m2', 'Mão 2'], ['ped', 'Pedal']] as const).map(([chave, rotulo]) => (
              <Selecao key={chave} rotulo={rotulo} value={f.tecnica[chave]} onChange={(e) => salvar.mutate({ [`tecnica_${chave}`]: e.target.value as StatusTecnica })}>
                {Object.entries(STATUS_TECNICA).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
              </Selecao>
            ))}
          </div>
        </Cartao>
      </div>

      <Cartao titulo="Métodos e hinário" acoes={<Botao onClick={() => setAssociando(true)}>Associar método</Botao>}>
        {!f.metodos.length ? <Vazio>Nenhum método associado a este aluno.</Vazio> : (
          <div className="flex flex-col gap-3">
            {f.metodos.map((m) => <CartaoMetodo key={m.metodo_id} alunoId={aluno.id} m={m} aoMudar={refrescar} aoFalhar={falhou} />)}
          </div>
        )}
      </Cartao>

      <Modal aberto={associando} aoFechar={() => setAssociando(false)} titulo="Associar método ao aluno">
        <Selecao rotulo="Método ou hinário" value={metodoEscolhido} onChange={(e) => setMetodoEscolhido(e.target.value)}>
          <option value="">Escolha…</option>
          {disponiveis.map((m) => <option key={m.id} value={m.id}>{m.nome}{m.nivel ? ` — ${NIVEL[m.nivel]}` : ''}</option>)}
        </Selecao>
        {metodos.isSuccess && !disponiveis.length && <p className="mt-2 text-sm text-slate-500">Todos os métodos ativos já estão associados. Cadastre outro em “Métodos”.</p>}
        <div className="mt-4 flex justify-end gap-2">
          <Botao variante="secundario" onClick={() => setAssociando(false)}>Cancelar</Botao>
          <Botao disabled={!metodoEscolhido} carregando={associar.isPending} onClick={() => associar.mutate()}>Associar</Botao>
        </div>
      </Modal>
    </div>
  );
}

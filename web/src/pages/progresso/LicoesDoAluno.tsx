import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { SITUACAO_METODO, STATUS_LICAO, type LicaoDoAluno, type Metodo, type SituacaoMetodo, type StatusLicao } from '../../api/tipos';
import { Aviso, Badge, Botao, Campo, Carregando, Cartao, Marcador, Modal, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { data as formatarData, hoje } from '../../lib/format';

interface Resposta {
  metodo: Metodo;
  vinculo: { situacao: SituacaoMetodo; data_inicio: string; data_situacao: string } | null;
  licoes: LicaoDoAluno[];
}

interface Tentativa { id: number; status: StatusLicao; data_tentativa: string; observacoes: string | null }

const TOM = { pendente: 'neutro', aprovado: 'verde', reprovado: 'vermelho' } as const;

export function PaginaLicoesDoAluno() {
  const { id, metodoId } = useParams();
  const cliente = useQueryClient();
  const [dataTentativa, setDataTentativa] = useState(hoje());
  const [busca, setBusca] = useState('');
  const [soPendentes, setSoPendentes] = useState(false);
  const [historico, setHistorico] = useState<LicaoDoAluno | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);

  const consulta = useQuery({
    queryKey: ['licoes-aluno', id, metodoId],
    queryFn: async () => (await api.get<{ dados: Resposta }>(`/alunos/${id}/metodos/${metodoId}/licoes`)).dados,
  });
  const tentativas = useQuery({
    queryKey: ['tentativas', id, historico?.id],
    queryFn: async () => (await api.get<{ dados: Tentativa[] }>(`/alunos/${id}/licoes/${historico!.id}/avaliacoes`)).dados,
    enabled: !!historico,
  });

  const refrescar = () => {
    for (const k of ['licoes-aluno', 'ficha-progresso', 'tentativas', 'inicio']) void cliente.invalidateQueries({ queryKey: [k] });
  };

  const avaliar = useMutation({
    mutationFn: ({ licaoId, status }: { licaoId: number; status: StatusLicao }) =>
      api.post<{ dados: { fora_de_ordem: boolean; concluir_sugerido: boolean }[] }>(`/alunos/${id}/avaliacoes`, {
        avaliacoes: [{ licao_id: licaoId, status, data_tentativa: dataTentativa }],
      }),
    onSuccess: (r) => {
      setErro(null);
      const x = r.dados[0];
      setAviso(
        [x.fora_de_ordem ? 'Atenção: esta lição estava fora da ordem sugerida (foi registrada mesmo assim).' : null,
          x.concluir_sugerido ? 'Todas as lições estão aprovadas: considere marcar o método como concluído na ficha.' : null]
          .filter(Boolean).join(' ') || null,
      );
      refrescar();
    },
    onError: (e) => setErro(mensagemDeErro(e)),
  });
  const excluir = useMutation({
    mutationFn: (tentativaId: number) => api.delete(`/avaliacoes/${tentativaId}`),
    onSuccess: refrescar,
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  const filtradas = useMemo(() => {
    const termo = busca.trim().toLowerCase();
    return (consulta.data?.licoes ?? []).filter((l) =>
      (!soPendentes || l.status !== 'aprovado') && (!termo || l.titulo.toLowerCase().includes(termo) || String(l.ordem) === termo));
  }, [consulta.data, busca, soPendentes]);

  if (consulta.isLoading) return <Carregando />;
  const r = consulta.data;
  if (!r) return <Aviso>Método não encontrado.</Aviso>;
  const aprovadas = r.licoes.filter((l) => l.status === 'aprovado').length;

  return (
    <>
      <Titulo acoes={<Link to={`/alunos/${id}`}><Botao variante="secundario">← Voltar à ficha</Botao></Link>}>
        {r.metodo.nome} {r.vinculo && <Badge tom="azul">{SITUACAO_METODO[r.vinculo.situacao]}</Badge>}
      </Titulo>
      <p className="mb-3 text-sm text-slate-600">
        {aprovadas} de {r.licoes.length} lições aprovadas. A sequência é uma orientação: dá para avaliar fora de ordem.
        {!r.vinculo && ' Ao avaliar, o método é associado ao aluno automaticamente.'}
      </p>
      {erro && <div className="mb-3"><Aviso>{erro}</Aviso></div>}
      {aviso && <div className="mb-3"><Aviso tipo="info">{aviso}</Aviso></div>}

      <Cartao>
        <div className="mb-3 flex flex-wrap items-end gap-3">
          <Campo rotulo="Data da tentativa" type="date" value={dataTentativa} onChange={(e) => e.target.value && setDataTentativa(e.target.value)} />
          <Campo rotulo="Buscar lição" type="search" value={busca} onChange={(e) => setBusca(e.target.value)} />
          <Marcador rotulo="Só as não aprovadas" checked={soPendentes} onChange={(e) => setSoPendentes(e.target.checked)} />
        </div>
        {!filtradas.length ? <Vazio>Nenhuma lição encontrada.</Vazio> : (
          <Tabela rotulo="Lições do aluno" cabecalhos={['#', 'Lição', 'Status', 'Desde', 'Tent.', 'Tempo', 'Avaliar']}>
            {filtradas.map((l) => (
              <tr key={l.id} className={l.atual ? 'bg-marca-50' : ''}>
                <Td className="w-12 font-mono text-slate-500">{l.ordem}</Td>
                <Td className="font-medium">{l.titulo}{l.atual && <Badge tom="azul"> atual</Badge>}</Td>
                <Td><Badge tom={TOM[l.status]}>{STATUS_LICAO[l.status]}</Badge></Td>
                <Td>{formatarData(l.desde)}</Td>
                <Td>{l.tentativas || '—'}</Td>
                <Td>{l.tempo_dias !== null ? `${l.tempo_dias} d` : '—'}</Td>
                <Td>
                  <div className="flex flex-wrap gap-1">
                    <Botao variante="fantasma" onClick={() => avaliar.mutate({ licaoId: l.id, status: 'aprovado' })}>Aprovar</Botao>
                    <Botao variante="fantasma" onClick={() => avaliar.mutate({ licaoId: l.id, status: 'reprovado' })}>Reprovar</Botao>
                    {l.tentativas > 0 && <Botao variante="fantasma" onClick={() => setHistorico(l)}>Histórico</Botao>}
                  </div>
                </Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>

      <Modal aberto={!!historico} aoFechar={() => setHistorico(null)} titulo={historico ? `Tentativas — ${historico.ordem}. ${historico.titulo}` : ''}>
        {tentativas.isLoading ? <Carregando /> : (
          <ul className="divide-y divide-slate-100">
            {tentativas.data?.map((t) => (
              <li key={t.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                <span>{formatarData(t.data_tentativa)} — <Badge tom={TOM[t.status]}>{STATUS_LICAO[t.status]}</Badge></span>
                <Botao variante="fantasma" onClick={() => excluir.mutate(t.id)}>Excluir</Botao>
              </li>
            ))}
          </ul>
        )}
        <p className="mt-3 text-xs text-slate-500">Cada tentativa guarda a data real. A data do status é a da primeira de uma sequência igual (reprovações seguidas mantêm a data inicial). Excluir corrige um lançamento errado e fica no log.</p>
      </Modal>
    </>
  );
}

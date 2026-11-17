import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { NIVEL, type ItemAvaliacao, type LicaoDoAluno, type Metodo, type StatusLicao } from '../../api/tipos';
import { Cartao, Selecao } from '../../components/ui';
import { api } from '../../lib/api';

interface Props {
  alunoId: number;
  /** Chamado a cada mudança com as lições marcadas (o formulário da aula as envia ao salvar). */
  aoMudar: (itens: ItemAvaliacao[]) => void;
}

/** Avaliação de lições dentro da tela de lançar aula. A sequência é só orientação: tudo pode ser marcado. */
export function AvaliacaoNaAula({ alunoId, aoMudar }: Props) {
  const [metodoId, setMetodoId] = useState('');
  const [marcadas, setMarcadas] = useState<Record<number, StatusLicao>>({});
  const [todas, setTodas] = useState(false);

  const metodos = useQuery({
    queryKey: ['metodos', 'ativos'],
    queryFn: async () => (await api.get<{ dados: Metodo[] }>('/metodos?ativos=1')).dados,
  });
  const licoes = useQuery({
    queryKey: ['licoes-aluno', String(alunoId), metodoId],
    queryFn: async () => (await api.get<{ dados: { licoes: LicaoDoAluno[] } }>(`/alunos/${alunoId}/metodos/${metodoId}/licoes`)).dados.licoes,
    enabled: !!metodoId,
  });

  useEffect(() => {
    setMarcadas({});
    setMetodoId('');
  }, [alunoId]);

  useEffect(() => {
    aoMudar(Object.entries(marcadas).map(([licao, status]) => ({ licao_id: Number(licao), status })));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [marcadas]);

  const visiveis = useMemo(() => {
    const lista = licoes.data ?? [];
    if (todas) return lista;
    const indiceAtual = Math.max(0, lista.findIndex((l) => l.atual));
    return lista.slice(Math.max(0, indiceAtual - 1), indiceAtual + 9);
  }, [licoes.data, todas]);

  const alternar = (id: number, status: StatusLicao) =>
    setMarcadas((atual) => {
      const novo = { ...atual };
      if (novo[id] === status) delete novo[id];
      else novo[id] = status;
      return novo;
    });

  return (
    <Cartao titulo="Avaliar lições nesta aula (opcional)">
      <p className="mb-3 text-xs text-slate-500">Presença não gera progresso: marque aqui só as lições que o aluno apresentou. A data da tentativa é a data da aula.</p>
      <Selecao rotulo="Método ou hinário" value={metodoId} onChange={(e) => { setMetodoId(e.target.value); }}>
        <option value="">Escolha para avaliar…</option>
        {metodos.data?.map((m) => <option key={m.id} value={m.id}>{m.nome}{m.nivel ? ` — ${NIVEL[m.nivel]}` : ''}</option>)}
      </Selecao>
      {metodoId && licoes.data && (
        <>
          <ul className="mt-3 divide-y divide-slate-100">
            {visiveis.map((l) => (
              <li key={l.id} className={`flex flex-wrap items-center justify-between gap-2 py-2 ${l.atual ? 'bg-marca-50' : ''}`}>
                <span className="text-sm">
                  <span className="mr-2 font-mono text-slate-500">{l.ordem}</span>
                  <b>{l.titulo}</b>
                  <span className="ml-2 text-xs text-slate-500">
                    {l.status === 'aprovado' ? '✔ aprovada' : l.status === 'reprovado' ? `✖ reprovada (${l.tentativas}×)` : l.atual ? '← atual' : ''}
                  </span>
                </span>
                <span className="flex gap-1" role="group" aria-label={`Avaliação da lição ${l.ordem}`}>
                  {(['aprovado', 'reprovado'] as const).map((s) => (
                    <button
                      key={s}
                      type="button"
                      aria-pressed={marcadas[l.id] === s}
                      onClick={() => alternar(l.id, s)}
                      className={`rounded-lg border px-3 py-1 text-sm ${marcadas[l.id] === s ? (s === 'aprovado' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-red-600 bg-red-600 text-white') : 'border-slate-300 bg-white'}`}
                    >
                      {s === 'aprovado' ? 'Aprovado' : 'Reprovado'}
                    </button>
                  ))}
                </span>
              </li>
            ))}
          </ul>
          {licoes.data.length > visiveis.length && (
            <button type="button" className="mt-2 text-sm text-marca-700 underline" onClick={() => setTodas(true)}>Mostrar todas as {licoes.data.length} lições</button>
          )}
        </>
      )}
      {Object.keys(marcadas).length > 0 && <p className="mt-3 text-sm font-medium text-marca-700">{Object.keys(marcadas).length} lição(ões) serão registradas ao salvar a aula.</p>}
    </Cartao>
  );
}

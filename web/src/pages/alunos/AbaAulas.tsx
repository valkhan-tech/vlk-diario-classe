import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { PRESENCA, TOM_PRESENCA, type Aluno, type Aula, type ResumoFrequencia } from '../../api/tipos';
import { Badge, Botao, Carregando, Cartao, Tabela, Td, Vazio } from '../../components/ui';
import { api } from '../../lib/api';
import { data, percentual } from '../../lib/format';

export function AbaAulas({ aluno }: { aluno: Aluno }) {
  const consulta = useQuery({
    queryKey: ['aluno-aulas', aluno.id],
    queryFn: () => api.get<{ dados: Aula[]; resumo: ResumoFrequencia }>(`/alunos/${aluno.id}/aulas`),
  });
  if (consulta.isLoading) return <Carregando />;
  const r = consulta.data?.resumo;
  return (
    <div className="flex flex-col gap-4">
      {r && (
        <Cartao titulo="Frequência">
          <p className="text-3xl font-bold text-marca-700">{percentual(r.frequencia)}</p>
          <p className="text-sm text-slate-600">
            {r.aulas} aulas · {r.presentes} presenças · {r.faltas_aluno} faltas do aluno · {r.faltas_professora} faltas da professora · {r.feriados} feriados/eventos
          </p>
          <p className="mt-1 text-xs text-slate-500">Faltas da professora e feriados não entram no cálculo.</p>
        </Cartao>
      )}
      <Cartao titulo="Histórico de aulas" acoes={<Link to={`/aulas/nova?aluno=${aluno.id}`}><Botao>Lançar aula</Botao></Link>}>
        {!consulta.data?.dados.length ? <Vazio>Nenhuma aula lançada.</Vazio> : (
          <Tabela rotulo="Aulas do aluno" cabecalhos={['Data', 'Presença', 'Conteúdo', 'Lição de casa', '']}>
            {consulta.data.dados.map((a) => (
              <tr key={a.id}>
                <Td>{data(a.data)}</Td>
                <Td><Badge tom={TOM_PRESENCA[a.presenca]}>{PRESENCA[a.presenca]}</Badge></Td>
                <Td className="max-w-xs">{a.conteudo ?? '—'}</Td>
                <Td className="max-w-xs">{a.licao_casa ?? '—'}</Td>
                <Td><Link className="text-marca-700 underline" to={`/aulas/${a.id}/editar`}>Corrigir</Link></Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>
    </div>
  );
}

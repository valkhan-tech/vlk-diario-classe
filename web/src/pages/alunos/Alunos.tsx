import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLista } from '../../api/hooks';
import { SITUACAO_ALUNO, type Aluno, type SituacaoAluno } from '../../api/tipos';
import { Badge, Botao, Campo, Carregando, Cartao, Selecao, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api, qs } from '../../lib/api';
import { DIAS_SEMANA, moeda } from '../../lib/format';

export const tomSituacao = (s: SituacaoAluno) => (s === 'ativo' ? 'verde' : s === 'pausado' ? 'amarelo' : 'neutro') as 'verde' | 'amarelo' | 'neutro';

export function PaginaAlunos() {
  const [busca, setBusca] = useState('');
  const [instrumento, setInstrumento] = useState('');
  const [situacao, setSituacao] = useState('');
  const instrumentos = useLista('instrumento');
  const consulta = useQuery({
    queryKey: ['alunos', busca, instrumento, situacao],
    queryFn: async () =>
      (await api.get<{ dados: Aluno[] }>(`/alunos${qs({ q: busca, instrumento_id: instrumento, situacao })}`)).dados,
  });

  return (
    <>
      <Titulo acoes={<Link to="/alunos/novo"><Botao>Novo aluno</Botao></Link>}>Alunos</Titulo>
      <Cartao className="mb-4">
        <div className="grid gap-3 sm:grid-cols-3">
          <Campo rotulo="Buscar por nome" type="search" value={busca} onChange={(e) => setBusca(e.target.value)} />
          <Selecao rotulo="Instrumento" value={instrumento} onChange={(e) => setInstrumento(e.target.value)}>
            <option value="">Todos</option>
            {instrumentos.data?.map((i) => <option key={i.id} value={i.id}>{i.valor}</option>)}
          </Selecao>
          <Selecao rotulo="Situação" value={situacao} onChange={(e) => setSituacao(e.target.value)} dica="Encerrados ficam ocultos, salvo se escolhidos aqui.">
            <option value="">Ativos e pausados</option>
            {Object.entries(SITUACAO_ALUNO).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
          </Selecao>
        </div>
      </Cartao>
      <Cartao>
        {consulta.isLoading ? <Carregando /> : !consulta.data?.length ? <Vazio>Nenhum aluno encontrado.</Vazio> : (
          <Tabela rotulo="Alunos" cabecalhos={['Aluno', 'Instrumento', 'Horário', 'Mensalidade', 'Situação']}>
            {consulta.data.map((a) => (
              <tr key={a.id} className="hover:bg-slate-50">
                <Td className="font-medium"><Link className="text-marca-700 underline" to={`/alunos/${a.id}`}>{a.nome}</Link></Td>
                <Td>{a.instrumento ?? '—'}</Td>
                <Td>{a.dia_semana !== null ? `${DIAS_SEMANA[a.dia_semana]} ${a.hora_aula ?? ''}` : '—'}</Td>
                <Td>{moeda(a.valor_mensalidade)} <span className="text-xs text-slate-500">dia {a.dia_vencimento}</span></Td>
                <Td><Badge tom={tomSituacao(a.situacao)}>{SITUACAO_ALUNO[a.situacao]}</Badge></Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>
    </>
  );
}

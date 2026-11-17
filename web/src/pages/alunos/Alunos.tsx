import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useLista } from '../../api/hooks';
import { SITUACAO_ALUNO, type Aluno, type SituacaoAluno } from '../../api/tipos';
import { Icone } from '../../components/icones';
import { Avatar, Badge, Barra, Botao, Campo, Carregando, Cartao, Segmentos, Selecao, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api, qs } from '../../lib/api';
import { DIAS_SEMANA, moeda, percentual } from '../../lib/format';

export const tomSituacao = (s: SituacaoAluno) => (s === 'ativo' ? 'verde' : s === 'pausado' ? 'amarelo' : 'neutro') as 'verde' | 'amarelo' | 'neutro';
const tomEstagio = (v: string | null | undefined) => (!v ? 'neutro' : v.startsWith('apto') || v === 'oficializado' ? 'roxo' : 'azul') as 'neutro' | 'roxo' | 'azul';

function horario(a: Aluno): string {
  return a.dia_semana !== null ? `${DIAS_SEMANA[a.dia_semana]} ${a.hora_aula ?? ''}`.trim() : '—';
}

export function PaginaAlunos() {
  const [params] = useSearchParams();
  const [busca, setBusca] = useState(params.get('q') ?? '');
  const [instrumento, setInstrumento] = useState('');
  const [situacao, setSituacao] = useState('');
  const [vista, setVista] = useState<'tabela' | 'cartoes'>('tabela');
  const instrumentos = useLista('instrumento');
  const consulta = useQuery({
    queryKey: ['alunos', busca, instrumento, situacao],
    queryFn: async () =>
      (await api.get<{ dados: Aluno[] }>(`/alunos${qs({ q: busca, instrumento_id: instrumento, situacao })}`)).dados,
  });
  const lista = consulta.data ?? [];

  return (
    <>
      <Titulo trilha="Cadastros" acoes={<Link to="/alunos/novo"><Botao><Icone nome="mais" />Novo aluno</Botao></Link>}>Alunos</Titulo>

      <Cartao className="mb-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto] lg:items-end">
          <Campo rotulo="Buscar por nome" type="search" value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Digite o nome…" />
          <Selecao rotulo="Instrumento" value={instrumento} onChange={(e) => setInstrumento(e.target.value)}>
            <option value="">Todos</option>
            {instrumentos.data?.map((i) => <option key={i.id} value={i.id}>{i.valor}</option>)}
          </Selecao>
          <Selecao rotulo="Situação" value={situacao} onChange={(e) => setSituacao(e.target.value)} dica="Encerrados ficam ocultos, salvo se escolhidos aqui.">
            <option value="">Ativos e pausados</option>
            {Object.entries(SITUACAO_ALUNO).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
          </Selecao>
          <div className="hidden lg:block"><Segmentos rotulo="Visualização" valor={vista} aoMudar={setVista} opcoes={[{ valor: 'tabela', rotulo: 'Tabela' }, { valor: 'cartoes', rotulo: 'Cartões' }]} /></div>
        </div>
      </Cartao>

      <Cartao>
        {consulta.isLoading ? <Carregando /> : !lista.length ? (
          <Vazio icone="users" acao={<Link to="/alunos/novo"><Botao>Novo aluno</Botao></Link>}>Nenhum aluno encontrado. Ajuste os filtros ou cadastre um novo aluno.</Vazio>
        ) : vista === 'cartoes' ? (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {lista.map((a) => (
              <Link key={a.id} to={`/alunos/${a.id}`} className="rounded-[22px] border border-slate-200 bg-white p-4 transition hover:border-marca-500 hover:shadow-md">
                <div className="flex items-center gap-3"><Avatar nome={a.nome} tamanho="m" /><div className="min-w-0"><b className="block truncate">{a.nome}</b><span className="text-[13px] text-slate-500">{a.instrumento ?? '—'} · {horario(a)}</span></div></div>
                <div className="mb-1.5 mt-4 flex justify-between text-[13px]"><span className="text-slate-500">Progresso geral</span><b>{percentual(a.progresso_geral ?? null)}</b></div>
                <Barra valor={a.progresso_geral ?? 0} rotulo="Progresso geral" />
                <div className="mt-3 flex flex-wrap gap-2"><Badge tom={tomEstagio(a.estagio?.valor)}>{a.estagio?.rotulo ?? 'Sem estágio'}</Badge><Badge tom={tomSituacao(a.situacao)}>{SITUACAO_ALUNO[a.situacao]}</Badge></div>
              </Link>
            ))}
          </div>
        ) : (
          <Tabela rotulo="Alunos" cabecalhos={['Aluno', 'Instrumento', 'Horário', 'Estágio', 'Mensalidade', 'Situação']}>
            {lista.map((a) => (
              <tr key={a.id}>
                <Td>
                  <Link to={`/alunos/${a.id}`} className="flex items-center gap-3 font-semibold hover:underline">
                    <Avatar nome={a.nome} />
                    <span className="text-left"><span className="block">{a.nome}</span>{a.faixa_etaria && <small className="block font-normal text-slate-500">{a.faixa_etaria}</small>}</span>
                  </Link>
                </Td>
                <Td>{a.instrumento ?? '—'}</Td>
                <Td>{horario(a)}</Td>
                <Td><Badge tom={tomEstagio(a.estagio?.valor)}>{a.estagio?.rotulo ?? 'Sem estágio'}</Badge></Td>
                <Td className="tabular-nums">{moeda(a.valor_mensalidade)} <small className="text-slate-500">dia {a.dia_vencimento}</small></Td>
                <Td><Badge tom={tomSituacao(a.situacao)}>{SITUACAO_ALUNO[a.situacao]}</Badge></Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>
    </>
  );
}

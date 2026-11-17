import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { Link } from 'react-router-dom';
import { PRESENCA, TOM_PRESENCA, type PainelInicio } from '../../api/tipos';
import { useAuth } from '../../auth/AuthContext';
import { Aviso, Badge, Botao, Carregando, Cartao, Titulo, Vazio } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { data, moeda } from '../../lib/format';

const GraficoEstagios = lazy(() => import('./GraficoEstagios'));

function Kpi({ rotulo, valor, detalhe, para, tom = 'text-slate-900' }: { rotulo: string; valor: string | number; detalhe?: string; para?: string; tom?: string }) {
  const conteudo = (
    <div className="h-full rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-marca-500">
      <p className="text-xs uppercase tracking-wide text-slate-500">{rotulo}</p>
      <p className={`text-3xl font-bold ${tom}`}>{valor}</p>
      {detalhe && <p className="text-xs text-slate-500">{detalhe}</p>}
    </div>
  );
  return para ? <Link to={para}>{conteudo}</Link> : conteudo;
}

export function PaginaInicio() {
  const { usuario } = useAuth();
  const consulta = useQuery({ queryKey: ['inicio'], queryFn: async () => (await api.get<{ dados: PainelInicio }>('/inicio')).dados });
  const p = consulta.data;

  return (
    <>
      <Titulo>Olá, {usuario?.nome}</Titulo>
      {consulta.isLoading && <Carregando />}
      {consulta.error && <Aviso>{mensagemDeErro(consulta.error)}</Aviso>}
      {p && (
        <div className="flex flex-col gap-4">
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Kpi rotulo="Alunos ativos" valor={p.kpis.alunos_ativos} para="/alunos" />
            <Kpi rotulo="Aulas hoje" valor={p.kpis.aulas_hoje} detalhe={`${p.kpis.aulas_hoje_lancadas} já lançada(s)`} para="/aulas" />
            <Kpi rotulo="Cobranças atrasadas" valor={p.kpis.cobrancas_atrasadas} detalhe={moeda(p.kpis.valor_atrasado)} tom="text-red-700" para="/financeiro" />
            <Kpi rotulo="Aptos para culto" valor={p.kpis.aptos_culto} detalhe="marcados pela professora" tom="text-emerald-700" />
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <Cartao titulo={`Agenda de hoje — ${data(p.agenda.data)}`} acoes={<Link to="/aulas/nova"><Botao variante="secundario">Lançar aula</Botao></Link>}>
              {!p.agenda.itens.length ? <Vazio>Nenhum aluno com horário hoje.</Vazio> : (
                <ul className="divide-y divide-slate-100">
                  {p.agenda.itens.map((i) => (
                    <li key={i.aluno_id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                      <span><span className="mr-2 font-mono text-slate-500">{i.hora ?? '--:--'}</span><b>{i.aluno}</b></span>
                      {i.presenca
                        ? <Badge tom={TOM_PRESENCA[i.presenca]}>{PRESENCA[i.presenca]}</Badge>
                        : <Link to={`/aulas/nova?aluno=${i.aluno_id}`}><Botao variante="fantasma">Lançar</Botao></Link>}
                    </li>
                  ))}
                </ul>
              )}
            </Cartao>

            <Cartao titulo="Alunos por estágio (ativos)">
              <Suspense fallback={<Carregando texto="Carregando gráfico…" />}>
                <GraficoEstagios dados={p.por_estagio} />
              </Suspense>
            </Cartao>
          </div>
        </div>
      )}
    </>
  );
}

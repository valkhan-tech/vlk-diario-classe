import { useQuery } from '@tanstack/react-query';
import { format, subMonths } from 'date-fns';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { DashboardFinanceiro } from '../../api/tipos';
import { Aviso, Botao, Campo, Carregando, Cartao, Tabela, Td, Vazio } from '../../components/ui';
import { api, baixar, mensagemDeErro, qs } from '../../lib/api';
import { mesAtual, moeda, percentual } from '../../lib/format';
import { ResumoCards } from './componentes';

const CORES = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6'];

const EXPORTACOES = [
  { tipo: 'cobrancas', rotulo: 'Cobranças do período' },
  { tipo: 'resumo-mensal', rotulo: 'Resumo mensal (gráficos)' },
  { tipo: 'inadimplencia', rotulo: 'Inadimplência' },
  { tipo: 'recebimentos-forma', rotulo: 'Recebimentos por forma' },
];

export function PainelDashboard() {
  const [de, setDe] = useState(format(subMonths(new Date(), 5), 'yyyy-MM'));
  const [ate, setAte] = useState(mesAtual());
  const [erro, setErro] = useState<string | null>(null);
  const consulta = useQuery({
    queryKey: ['dashboard-financeiro', de, ate],
    queryFn: async () => (await api.get<{ dados: DashboardFinanceiro }>(`/dashboards/financeiro${qs({ de, ate })}`)).dados,
  });
  const d = consulta.data;

  const exportar = (tipo: string) =>
    baixar(`/exportacoes/${tipo}.xlsx${qs({ de, ate })}`, `${tipo}-${de}-a-${ate}.xlsx`).catch((e) => setErro(mensagemDeErro(e)));

  return (
    <div className="flex flex-col gap-4">
      <Cartao>
        <div className="flex flex-wrap items-end gap-3">
          <Campo rotulo="De" type="month" value={de} onChange={(e) => e.target.value && setDe(e.target.value)} />
          <Campo rotulo="Até" type="month" value={ate} onChange={(e) => e.target.value && setAte(e.target.value)} />
          <div className="flex flex-wrap gap-2">
            {EXPORTACOES.map((x) => (
              <Botao key={x.tipo} variante="secundario" onClick={() => void exportar(x.tipo)}>⬇ {x.rotulo}</Botao>
            ))}
          </div>
        </div>
        <p className="mt-2 text-xs text-slate-500">Excel formatado, com totais e gráficos. Período máximo: 24 meses.</p>
      </Cartao>

      {erro && <Aviso>{erro}</Aviso>}
      {consulta.isLoading && <Carregando />}
      {consulta.error && <Aviso>{mensagemDeErro(consulta.error)}</Aviso>}

      {d && (
        <>
          <ResumoCards resumo={{ quantidade: d.kpis.cobrancas, previsto: d.kpis.previsto, recebido: d.kpis.recebido, aberto: d.kpis.aberto, atrasado: d.kpis.atrasado, por_estado: d.kpis.por_estado }} />
          <p className="text-sm text-slate-600">
            Inadimplência (atrasado ÷ previsto): <b className="text-red-700">{percentual(d.kpis.inadimplencia)}</b>
          </p>

          <div className="grid gap-4 lg:grid-cols-2">
            <Cartao titulo="Previsto × recebido por competência">
              <div role="img" aria-label="Gráfico de barras de previsto e recebido por competência" style={{ width: '100%', height: 280 }}>
                <ResponsiveContainer>
                  <BarChart data={d.por_mes}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="competencia" />
                    <YAxis tickFormatter={(v: number) => `${Math.round(v)}`} />
                    <Tooltip formatter={(v) => moeda(Number(v))} />
                    <Legend />
                    <Bar dataKey="previsto" name="Previsto" fill="#4f46e5" />
                    <Bar dataKey="recebido" name="Recebido" fill="#10b981" />
                    <Bar dataKey="atrasado" name="Atrasado" fill="#ef4444" />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Cartao>

            <Cartao titulo="Recebimentos por forma de pagamento">
              {d.por_forma.length === 0 ? <Vazio>Sem pagamentos no período.</Vazio> : (
                <div role="img" aria-label="Gráfico de pizza de recebimentos por forma de pagamento" style={{ width: '100%', height: 280 }}>
                  <ResponsiveContainer>
                    <PieChart>
                      <Pie data={d.por_forma} dataKey="valor" nameKey="forma" outerRadius={95} label={(p) => String(p.name)}>
                        {d.por_forma.map((_, i) => <Cell key={i} fill={CORES[i % CORES.length]} />)}
                      </Pie>
                      <Tooltip formatter={(v) => moeda(Number(v))} />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
              )}
            </Cartao>

            <Cartao titulo="Envelhecimento do atraso (saldo)">
              <div role="img" aria-label="Gráfico de barras do saldo atrasado por faixa de dias" style={{ width: '100%', height: 240 }}>
                <ResponsiveContainer>
                  <BarChart data={d.envelhecimento}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="faixa" />
                    <YAxis />
                    <Tooltip formatter={(v) => moeda(Number(v))} />
                    <Bar dataKey="valor" name="Saldo atrasado" fill="#ef4444" />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Cartao>

            <Cartao titulo="Quem mais deve">
              {d.ranking.length === 0 ? <Vazio>Nenhum atraso no período. 🎉</Vazio> : (
                <Tabela rotulo="Ranking de atrasos" cabecalhos={['Aluno', 'Cobranças', 'Saldo', 'Maior atraso']}>
                  {d.ranking.map((r) => (
                    <tr key={r.aluno_id}>
                      <Td className="font-medium"><Link className="text-marca-700 underline" to={`/alunos/${r.aluno_id}`}>{r.aluno}</Link></Td>
                      <Td>{r.cobrancas}</Td>
                      <Td>{moeda(r.saldo)}</Td>
                      <Td>{r.maior_atraso} dias</Td>
                    </tr>
                  ))}
                </Tabela>
              )}
            </Cartao>
          </div>
        </>
      )}
    </div>
  );
}

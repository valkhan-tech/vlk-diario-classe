import { useMutation, useQuery } from '@tanstack/react-query';
import { lazy, Suspense, useState } from 'react';
import { Link } from 'react-router-dom';
import { ESTADO_COBRANCA, type Cobranca, type EstadoCobranca, type ResumoFinanceiro } from '../../api/tipos';
import { Abas, Avatar, Botao, Campo, Carregando, Cartao, Selecao, Tabela, Td, Titulo, Vazio, useToast } from '../../components/ui';
import { api, mensagemDeErro, qs } from '../../lib/api';
import { data, mesAtual, moeda } from '../../lib/format';
import { BadgeEstado, ModalCobranca, ModalPagamento, ResumoCards, useInvalidarFinanceiro } from './componentes';

const PainelDashboard = lazy(() => import('./Dashboard').then((m) => ({ default: m.PainelDashboard })));

function VisaoDoMes() {
  const [competencia, setCompetencia] = useState(mesAtual());
  const [estado, setEstado] = useState<EstadoCobranca | ''>('');
  const [pagar, setPagar] = useState<Cobranca | null>(null);
  const [editar, setEditar] = useState<Cobranca | null>(null);
  const avisar = useToast();
  const invalidar = useInvalidarFinanceiro();

  const consulta = useQuery({
    queryKey: ['cobrancas', competencia, estado],
    queryFn: () => api.get<{ dados: Cobranca[]; resumo: ResumoFinanceiro }>(`/cobrancas${qs({ competencia, estado })}`),
  });
  const gerar = useMutation({
    mutationFn: () => api.post<{ dados: { criadas: number; existentes: number; ignorados: number } }>('/cobrancas/gerar', { competencia }),
    onSuccess: (r) => {
      invalidar();
      avisar(`${r.dados.criadas} cobrança(s) criada(s); ${r.dados.existentes} já existiam; ${r.dados.ignorados} ignorada(s).`, 'ok');
    },
    onError: (e) => avisar(mensagemDeErro(e), 'erro'),
  });

  return (
    <div className="flex flex-col gap-4">
      <Cartao>
        <div className="grid gap-3 sm:flex sm:flex-wrap sm:items-end">
          <Campo rotulo="Competência" type="month" value={competencia} onChange={(e) => e.target.value && setCompetencia(e.target.value)} />
          <Selecao rotulo="Estado" value={estado} onChange={(e) => setEstado(e.target.value as EstadoCobranca | '')}>
            <option value="">Todos</option>
            {Object.entries(ESTADO_COBRANCA).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
          </Selecao>
          <Botao variante="secundario" className="w-full sm:w-auto" carregando={gerar.isPending} onClick={() => gerar.mutate()}>Gerar cobranças da competência</Botao>
        </div>
        <p className="mt-2 text-xs text-slate-500">Só alunos ativos, sem proporcional. Rodar de novo não duplica. O sistema também gera sozinho todo dia (cron).</p>
      </Cartao>
      {consulta.data && <ResumoCards resumo={consulta.data.resumo} />}
      <Cartao>
        {consulta.isLoading ? <Carregando /> : !consulta.data?.dados.length ? <Vazio icone="carteira">Nenhuma cobrança nesta competência. Use “Gerar cobranças da competência”.</Vazio> : (
          <Tabela rotulo="Cobranças" cabecalhos={['Aluno', 'Vencimento', 'Líquido', 'Pago', 'Saldo', 'Estado', '']}>
            {consulta.data.dados.map((c) => (
              <tr key={c.id}>
                <Td><Link className="flex items-center gap-2.5 font-semibold hover:underline" to={`/alunos/${c.aluno_id}`}><Avatar nome={c.aluno ?? '?'} tamanho="p" />{c.aluno}</Link></Td>
                <Td>{data(c.vencimento)}{c.dias_atraso > 0 && <span className="ml-1 text-xs text-red-600">({c.dias_atraso}d)</span>}</Td>
                <Td className="tabular-nums">{moeda(c.valor_liquido)}</Td>
                <Td className="tabular-nums">{moeda(c.pago)}</Td>
                <Td className="tabular-nums">{moeda(c.saldo)}</Td>
                <Td><BadgeEstado estado={c.estado} excedente={c.excedente} /></Td>
                <Td>
                  <div className="flex gap-1">
                    {c.saldo > 0 && c.situacao === 'aberta' && <Botao variante="fantasma" onClick={() => setPagar(c)}>Receber</Botao>}
                    <Botao variante="fantasma" onClick={() => setEditar(c)}>Ajustar</Botao>
                  </div>
                </Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>
      <ModalPagamento cobranca={pagar} aoFechar={() => setPagar(null)} />
      <ModalCobranca cobranca={editar} aoFechar={() => setEditar(null)} />
    </div>
  );
}

export function PaginaFinanceiro() {
  return (
    <>
      <Titulo trilha="Controle">Financeiro</Titulo>
      <Abas abas={[
        { id: 'mes', rotulo: 'Cobranças do mês', conteudo: <VisaoDoMes /> },
        { id: 'dashboard', rotulo: 'Dashboard', conteudo: <Suspense fallback={<Carregando />}><PainelDashboard /></Suspense> },
      ]} />
    </>
  );
}

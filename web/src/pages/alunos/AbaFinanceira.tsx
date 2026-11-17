import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useLista } from '../../api/hooks';
import type { Aluno, Cobranca, FichaFinanceira } from '../../api/tipos';
import { Aviso, Botao, Carregando, Cartao, Confirmar, Tabela, Td, Vazio } from '../../components/ui';
import { api, baixar, mensagemDeErro } from '../../lib/api';
import { data, moeda } from '../../lib/format';
import { BadgeEstado, ModalCobranca, ModalPagamento, ResumoCards, useInvalidarFinanceiro } from '../financeiro/componentes';

export function AbaFinanceira({ aluno }: { aluno: Aluno }) {
  useLista('forma_pagamento');
  const [pagar, setPagar] = useState<Cobranca | null>(null);
  const [editar, setEditar] = useState<Cobranca | null>(null);
  const [estornar, setEstornar] = useState<number | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const invalidar = useInvalidarFinanceiro();
  const consulta = useQuery({
    queryKey: ['ficha-financeira', aluno.id],
    queryFn: async () => (await api.get<{ dados: FichaFinanceira }>(`/alunos/${aluno.id}/ficha-financeira`)).dados,
  });

  if (consulta.isLoading) return <Carregando />;
  const ficha = consulta.data;
  if (!ficha) return <Aviso>Não foi possível carregar a ficha financeira.</Aviso>;

  const fazerEstorno = async (id: number) => {
    try { await api.delete(`/pagamentos/${id}`); invalidar(); } catch (e) { setErro(mensagemDeErro(e)); }
  };

  return (
    <div className="flex flex-col gap-4">
      {erro && <Aviso>{erro}</Aviso>}
      <ResumoCards resumo={ficha.resumo} />
      <Cartao
        titulo="Cobranças e pagamentos"
        acoes={<Botao variante="secundario" onClick={() => void baixar(`/exportacoes/ficha-financeira.xlsx?aluno_id=${aluno.id}`, `ficha-financeira-${aluno.id}.xlsx`).catch((e) => setErro(mensagemDeErro(e)))}>Exportar Excel</Botao>}
      >
        <p className="mb-3 text-sm text-slate-600">
          Mensalidade {moeda(ficha.aluno.valor_mensalidade)} · vence todo dia {ficha.aluno.dia_vencimento}.
        </p>
        {!ficha.cobrancas.length ? <Vazio>Nenhuma cobrança ainda. Elas são geradas pelo sistema para alunos ativos.</Vazio> : (
          <Tabela rotulo="Cobranças do aluno" cabecalhos={['Competência', 'Vencimento', 'Líquido', 'Pago', 'Saldo', 'Estado', '']}>
            {ficha.cobrancas.map((c) => (
              <>
                <tr key={c.id}>
                  <Td className="font-medium">{c.competencia}</Td>
                  <Td>{data(c.vencimento)}</Td>
                  <Td>{moeda(c.valor_liquido)}</Td>
                  <Td>{moeda(c.pago)}</Td>
                  <Td>{moeda(c.saldo)}</Td>
                  <Td><BadgeEstado estado={c.estado} excedente={c.excedente} /></Td>
                  <Td>
                    <div className="nao-imprimir flex gap-1">
                      {c.saldo > 0 && c.situacao === 'aberta' && <Botao variante="fantasma" onClick={() => setPagar(c)}>Receber</Botao>}
                      <Botao variante="fantasma" onClick={() => setEditar(c)}>Ajustar</Botao>
                    </div>
                  </Td>
                </tr>
                {c.pagamentos?.map((p) => (
                  <tr key={`p${p.id}`} className="bg-slate-50 text-xs text-slate-600">
                    <Td className="pl-6" colSpan={2}>↳ pago em {data(p.data_pagamento)}{p.forma ? ` (${p.forma})` : ''}</Td>
                    <Td colSpan={2}>{moeda(p.valor)}</Td>
                    <Td colSpan={3}><button type="button" className="nao-imprimir text-red-700 underline" onClick={() => setEstornar(p.id)}>Estornar</button></Td>
                  </tr>
                ))}
              </>
            ))}
          </Tabela>
        )}
      </Cartao>
      <ModalPagamento cobranca={pagar} aoFechar={() => setPagar(null)} />
      <ModalCobranca cobranca={editar} aoFechar={() => setEditar(null)} />
      <Confirmar aberto={estornar !== null} aoFechar={() => setEstornar(null)} titulo="Estornar pagamento"
        texto="O pagamento será removido (exclusão lógica, mantida no log) e a cobrança volta a refletir o saldo." rotuloConfirmar="Estornar"
        aoConfirmar={() => estornar !== null && void fazerEstorno(estornar)} />
    </div>
  );
}

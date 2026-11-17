import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import type { EntradaLog, ResultadoVerificacao } from '../../api/tipos';
import { Aviso, Badge, Botao, Campo, Carregando, Cartao, Modal, Selecao, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api, mensagemDeErro, qs } from '../../lib/api';
import { dataHora } from '../../lib/format';

interface Pagina { dados: EntradaLog[]; total: number; pagina: number; limite: number; tabelas: string[] }

const TOM = { INSERT: 'verde', UPDATE: 'azul', DELETE: 'vermelho' } as const;

function Detalhe({ id }: { id: number }) {
  const entrada = useQuery({ queryKey: ['auditoria', id], queryFn: async () => (await api.get<{ dados: EntradaLog }>(`/auditoria/${id}`)).dados });
  const verificar = useMutation({ mutationFn: () => api.get<{ dados: ResultadoVerificacao }>(`/auditoria/${id}/verificar`) });
  const v = verificar.data?.dados;
  const e = entrada.data;
  if (!e) return <Carregando />;
  return (
    <div className="flex flex-col gap-3 text-sm">
      <dl className="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1">
        <dt className="text-slate-500">Quando</dt><dd>{dataHora(e.quando)}</dd>
        <dt className="text-slate-500">Quem</dt><dd>{e.usuario ?? '—'} {e.perfil && <span className="text-slate-500">({e.perfil})</span>}</dd>
        <dt className="text-slate-500">Operação</dt><dd><Badge tom={TOM[e.operacao]}>{e.operacao}</Badge> {e.tabela} #{e.registro_id}</dd>
        <dt className="text-slate-500">Origem</dt><dd className="break-all">{e.origem}</dd>
        <dt className="text-slate-500">Controller</dt><dd className="break-all">{e.controller ?? '—'}</dd>
        <dt className="text-slate-500">IP</dt><dd>{e.ip ?? '—'}</dd>
        <dt className="text-slate-500">Fingerprint</dt><dd className="break-all font-mono text-xs">{e.fingerprint}</dd>
      </dl>
      <div>
        <p className="mb-1 font-medium">Registro após a ação</p>
        <pre className="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{JSON.stringify(e.registro, null, 2)}</pre>
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <Botao variante="secundario" carregando={verificar.isPending} onClick={() => verificar.mutate()}>Verificar fingerprint</Botao>
        {verificar.error && <Aviso>{mensagemDeErro(verificar.error)}</Aviso>}
      </div>
      {v && (
        <div className="flex flex-col gap-2">
          <Aviso tipo={v.integro ? 'sucesso' : 'erro'}>
            {v.integro ? 'O JSON gravado confere com o fingerprint registrado (entrada íntegra).' : 'ATENÇÃO: o JSON gravado NÃO confere com o fingerprint — esta entrada do log foi alterada.'}
          </Aviso>
          {v.ultima_do_registro ? (
            <Aviso tipo={v.confere_com_banco ? 'sucesso' : 'erro'}>
              {v.registro_existe === false
                ? 'O registro não existe mais na tabela.'
                : v.confere_com_banco
                  ? 'A linha atual da tabela confere com este fingerprint.'
                  : 'ATENÇÃO: a linha atual da tabela é diferente deste registro — foi alterada fora do sistema.'}
            </Aviso>
          ) : (
            <Aviso tipo="info">Há entradas mais recentes para este registro; a comparação com a linha atual só vale para a última.</Aviso>
          )}
        </div>
      )}
    </div>
  );
}

export function PaginaAuditoria() {
  const [filtros, setFiltros] = useState({ tabela: '', operacao: '', registro_id: '', de: '', ate: '' });
  const [pagina, setPagina] = useState(1);
  const [aberto, setAberto] = useState<number | null>(null);
  const consulta = useQuery({
    queryKey: ['auditoria', filtros, pagina],
    queryFn: () => api.get<Pagina>(`/auditoria${qs({ ...filtros, pagina })}`),
  });
  const mudar = (campo: keyof typeof filtros, valor: string) => { setFiltros((f) => ({ ...f, [campo]: valor })); setPagina(1); };
  const r = consulta.data;
  const totalPaginas = r ? Math.max(1, Math.ceil(r.total / r.limite)) : 1;

  return (
    <>
      <Titulo>Auditoria</Titulo>
      <Cartao className="mb-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <Selecao rotulo="Tabela" value={filtros.tabela} onChange={(e) => mudar('tabela', e.target.value)}>
            <option value="">Todas</option>
            {r?.tabelas.map((t) => <option key={t} value={t}>{t}</option>)}
          </Selecao>
          <Selecao rotulo="Operação" value={filtros.operacao} onChange={(e) => mudar('operacao', e.target.value)}>
            <option value="">Todas</option>
            <option value="INSERT">INSERT</option><option value="UPDATE">UPDATE</option><option value="DELETE">DELETE</option>
          </Selecao>
          <Campo rotulo="ID do registro" type="number" min="1" value={filtros.registro_id} onChange={(e) => mudar('registro_id', e.target.value)} />
          <Campo rotulo="De" type="date" value={filtros.de} onChange={(e) => mudar('de', e.target.value)} />
          <Campo rotulo="Até" type="date" value={filtros.ate} onChange={(e) => mudar('ate', e.target.value)} />
        </div>
      </Cartao>
      <Cartao>
        {consulta.isLoading ? <Carregando /> : !r?.dados.length ? <Vazio>Nenhuma entrada encontrada.</Vazio> : (
          <>
            <Tabela rotulo="Log de auditoria" cabecalhos={['Quando', 'Quem', 'Operação', 'Registro', 'Origem', '']}>
              {r.dados.map((l) => (
                <tr key={l.id}>
                  <Td className="whitespace-nowrap">{dataHora(l.quando)}</Td>
                  <Td>{l.usuario ?? '—'}</Td>
                  <Td><Badge tom={TOM[l.operacao]}>{l.operacao}</Badge></Td>
                  <Td>{l.tabela} #{l.registro_id}</Td>
                  <Td className="max-w-xs truncate text-xs text-slate-600" title={l.origem}>{l.origem}</Td>
                  <Td><Botao variante="fantasma" onClick={() => setAberto(l.id)}>Detalhes</Botao></Td>
                </tr>
              ))}
            </Tabela>
            <div className="mt-3 flex items-center justify-between text-sm text-slate-600">
              <span>{r.total} entrada(s)</span>
              <span className="flex items-center gap-2">
                <Botao variante="secundario" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>Anterior</Botao>
                <span>Página {pagina} de {totalPaginas}</span>
                <Botao variante="secundario" disabled={pagina >= totalPaginas} onClick={() => setPagina(pagina + 1)}>Próxima</Botao>
              </span>
            </div>
          </>
        )}
      </Cartao>
      <Modal aberto={aberto !== null} aoFechar={() => setAberto(null)} titulo="Entrada de auditoria">
        {aberto !== null && <Detalhe id={aberto} />}
      </Modal>
    </>
  );
}

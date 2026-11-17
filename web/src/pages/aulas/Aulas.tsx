import { useQuery } from '@tanstack/react-query';
import { addMonths, eachDayOfInterval, endOfMonth, format, getDay, parseISO, startOfMonth } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { PRESENCA, TOM_PRESENCA, type Aula, type Calendario, type ItemAgenda, type Presenca } from '../../api/tipos';
import { Icone } from '../../components/icones';
import { Abas, Avatar, Badge, Botao, Carregando, Cartao, Modal, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api } from '../../lib/api';
import { data as formatarData, hoje, mesAtual } from '../../lib/format';

const COR_PRESENCA: Record<Presenca, string> = {
  presente: 'var(--color-emerald-600)', falta_aluno_justificada: 'var(--color-nota-2)', falta_aluno_sem_aviso: 'var(--color-red-600)',
  falta_professora: 'var(--color-sky-700)', feriado_evento: 'var(--color-nota-3)',
};

function Hoje() {
  const consulta = useQuery({
    queryKey: ['agenda', hoje()],
    queryFn: async () => (await api.get<{ dados: { data: string; itens: ItemAgenda[] } }>(`/agenda/hoje?data=${hoje()}`)).dados,
  });
  return (
    <Cartao titulo={`Aulas de hoje — ${formatarData(hoje())}`}>
      {consulta.isLoading ? <Carregando /> : !consulta.data?.itens.length ? <Vazio icone="calendario">Nenhum aluno com horário cadastrado para hoje.</Vazio> : (
        <ul className="divide-y divide-slate-100">
          {consulta.data.itens.map((i) => (
            <li key={i.aluno_id} className="flex flex-wrap items-center gap-3 py-3">
              <span className="w-12 shrink-0 font-mono text-sm tabular-nums text-slate-500">{i.hora ?? '--:--'}</span>
              <Avatar nome={i.aluno} />
              <Link className="min-w-0 flex-1 truncate font-semibold hover:underline" to={`/alunos/${i.aluno_id}`}>{i.aluno}</Link>
              {i.presenca ? (
                <div className="flex items-center gap-2">
                  <Badge tom={TOM_PRESENCA[i.presenca]}>{PRESENCA[i.presenca]}</Badge>
                  <Link to={`/aulas/${i.aula_id}/editar`}><Botao variante="fantasma" compacto>Corrigir</Botao></Link>
                </div>
              ) : (
                <Link to={`/aulas/nova?aluno=${i.aluno_id}&data=${hoje()}`}><Botao compacto>Lançar aula</Botao></Link>
              )}
            </li>
          ))}
        </ul>
      )}
    </Cartao>
  );
}

function VisaoCalendario() {
  const [mes, setMes] = useState(mesAtual());
  const [dia, setDia] = useState<string | null>(null);
  const consulta = useQuery({
    queryKey: ['calendario', mes],
    queryFn: async () => (await api.get<{ dados: Calendario }>(`/calendario?mes=${mes}`)).dados,
  });
  const inicio = startOfMonth(parseISO(`${mes}-01`));
  const dias = eachDayOfInterval({ start: inicio, end: endOfMonth(inicio) });
  const deslocar = (n: number) => setMes(format(addMonths(inicio, n), 'yyyy-MM'));
  const r = consulta.data?.resumo;
  const aulasDoDia = dia ? consulta.data?.dias[dia] ?? [] : [];

  return (
    <Cartao>
      <div className="mb-3 flex items-center justify-between gap-2">
        <Botao variante="secundario" className="!w-11 !px-0" onClick={() => deslocar(-1)} aria-label="Mês anterior">‹</Botao>
        <h2 className="text-center text-lg font-semibold capitalize">{format(inicio, "MMMM 'de' yyyy", { locale: ptBR })}</h2>
        <Botao variante="secundario" className="!w-11 !px-0" onClick={() => deslocar(1)} aria-label="Próximo mês">›</Botao>
      </div>
      {r && (
        <p className="mb-3 text-center text-sm text-slate-500">
          {r.aulas} aulas · {r.presentes} presenças · {r.faltas_aluno} faltas do aluno · {r.faltas_professora} da professora
        </p>
      )}
      <div className="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-slate-500 sm:gap-1.5">
        {['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].map((d, i) => <div key={i} className="py-1">{d}</div>)}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1 sm:gap-1.5">
        {Array.from({ length: getDay(inicio) }).map((_, i) => <div key={`v${i}`} />)}
        {dias.map((d) => {
          const chave = format(d, 'yyyy-MM-dd');
          const aulas = consulta.data?.dias[chave] ?? [];
          const ehHoje = chave === hoje();
          return (
            <button
              key={chave}
              type="button"
              onClick={() => setDia(chave)}
              aria-label={`${format(d, 'dd/MM')}: ${aulas.length} aula(s)`}
              className={`flex min-h-[3.4rem] flex-col items-center justify-between rounded-xl border bg-white p-1 text-sm hover:bg-marca-50 sm:min-h-[4.6rem] sm:items-start sm:p-2 ${ehHoje ? 'border-2 border-marca-600' : 'border-slate-200'}`}
            >
              <span className={ehHoje ? 'font-bold text-marca-700' : ''}>{format(d, 'd')}</span>
              <span className="flex flex-wrap justify-center gap-[3px] sm:justify-start">
                {aulas.slice(0, 6).map((a) => <span key={a.id} className="h-2 w-2 rounded-full" style={{ background: COR_PRESENCA[a.presenca] }} />)}
              </span>
            </button>
          );
        })}
      </div>
      <p className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
        {(Object.keys(COR_PRESENCA) as Presenca[]).map((p) => (
          <span key={p} className="inline-flex items-center gap-1.5"><i className="h-2.5 w-2.5 rounded-full" style={{ background: COR_PRESENCA[p] }} />{PRESENCA[p]}</span>
        ))}
      </p>
      <Modal aberto={!!dia} aoFechar={() => setDia(null)} titulo={dia ? `Dia ${formatarData(dia)}` : ''}>
        {aulasDoDia.length === 0 ? <Vazio icone="calendario">Sem aulas lançadas neste dia.</Vazio> : (
          <ul className="mb-4 divide-y divide-slate-100">
            {aulasDoDia.map((a) => (
              <li key={a.id} className="flex flex-wrap items-center gap-3 py-2.5 text-sm">
                <Avatar nome={a.aluno} tamanho="p" />
                <span className="min-w-0 flex-1 truncate font-semibold">{a.aluno}</span>
                <Badge tom={TOM_PRESENCA[a.presenca]}>{PRESENCA[a.presenca]}</Badge>
                <Link className="font-medium text-marca-700 underline" to={`/aulas/${a.id}/editar`}>Corrigir</Link>
              </li>
            ))}
          </ul>
        )}
        <Link to={`/aulas/nova?data=${dia ?? ''}`}><Botao className="w-full sm:w-auto">Lançar aula neste dia</Botao></Link>
      </Modal>
    </Cartao>
  );
}

function Recentes() {
  const consulta = useQuery({
    queryKey: ['aulas', 'recentes'],
    queryFn: async () => (await api.get<{ dados: Aula[] }>('/aulas?limite=30')).dados,
  });
  return (
    <Cartao titulo="Últimas aulas lançadas">
      {consulta.isLoading ? <Carregando /> : !consulta.data?.length ? <Vazio icone="nota">Nenhuma aula lançada ainda.</Vazio> : (
        <Tabela rotulo="Últimas aulas" cabecalhos={['Data', 'Aluno', 'Presença', 'Conteúdo', '']}>
          {consulta.data.map((a) => (
            <tr key={a.id}>
              <Td>{formatarData(a.data)}</Td>
              <Td className="font-semibold">{a.aluno}</Td>
              <Td><Badge tom={TOM_PRESENCA[a.presenca]}>{PRESENCA[a.presenca]}</Badge></Td>
              <Td className="max-w-xs truncate">{a.conteudo ?? '—'}</Td>
              <Td><Link className="font-medium text-marca-700 underline" to={`/aulas/${a.id}/editar`}>Corrigir</Link></Td>
            </tr>
          ))}
        </Tabela>
      )}
    </Cartao>
  );
}

export function PaginaAulas() {
  return (
    <>
      <Titulo trilha="Rotina" acoes={<Link to="/aulas/nova"><Botao><Icone nome="mais" />Lançar aula</Botao></Link>}>Aulas</Titulo>
      <Abas abas={[
        { id: 'hoje', rotulo: 'Hoje', conteudo: <Hoje /> },
        { id: 'calendario', rotulo: 'Calendário', conteudo: <VisaoCalendario /> },
        { id: 'recentes', rotulo: 'Últimas aulas', conteudo: <Recentes /> },
      ]} />
    </>
  );
}

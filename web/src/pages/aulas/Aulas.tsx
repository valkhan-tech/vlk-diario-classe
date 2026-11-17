import { useQuery } from '@tanstack/react-query';
import { addMonths, eachDayOfInterval, endOfMonth, format, getDay, parseISO, startOfMonth } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { PRESENCA, TOM_PRESENCA, type Aula, type Calendario, type ItemAgenda } from '../../api/tipos';
import { Abas, Badge, Botao, Carregando, Cartao, Modal, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api } from '../../lib/api';
import { data as formatarData, hoje, mesAtual } from '../../lib/format';

function Hoje() {
  const consulta = useQuery({
    queryKey: ['agenda', hoje()],
    queryFn: async () => (await api.get<{ dados: { data: string; itens: ItemAgenda[] } }>(`/agenda/hoje?data=${hoje()}`)).dados,
  });
  return (
    <Cartao titulo={`Aulas de hoje — ${formatarData(hoje())}`}>
      {consulta.isLoading ? <Carregando /> : !consulta.data?.itens.length ? <Vazio>Nenhum aluno com horário cadastrado para hoje.</Vazio> : (
        <ul className="divide-y divide-slate-100">
          {consulta.data.itens.map((i) => (
            <li key={i.aluno_id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <div>
                <span className="mr-3 font-mono text-sm text-slate-500">{i.hora ?? '--:--'}</span>
                <Link className="font-medium text-marca-700 underline" to={`/alunos/${i.aluno_id}`}>{i.aluno}</Link>
              </div>
              {i.presenca ? (
                <div className="flex items-center gap-2">
                  <Badge tom={TOM_PRESENCA[i.presenca]}>{PRESENCA[i.presenca]}</Badge>
                  <Link to={`/aulas/${i.aula_id}/editar`}><Botao variante="fantasma">Corrigir</Botao></Link>
                </div>
              ) : (
                <Link to={`/aulas/nova?aluno=${i.aluno_id}&data=${hoje()}`}><Botao>Lançar aula</Botao></Link>
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
      <div className="mb-3 flex items-center justify-between">
        <Botao variante="secundario" onClick={() => deslocar(-1)} aria-label="Mês anterior">‹</Botao>
        <h2 className="text-base font-semibold capitalize">{format(inicio, "MMMM 'de' yyyy", { locale: ptBR })}</h2>
        <Botao variante="secundario" onClick={() => deslocar(1)} aria-label="Próximo mês">›</Botao>
      </div>
      {r && (
        <p className="mb-3 text-sm text-slate-600">
          {r.aulas} aulas · {r.presentes} presenças · {r.faltas_aluno} faltas do aluno · {r.faltas_professora} da professora
        </p>
      )}
      <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-500">
        {['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].map((d, i) => <div key={i}>{d}</div>)}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1">
        {Array.from({ length: getDay(inicio) }).map((_, i) => <div key={`v${i}`} />)}
        {dias.map((d) => {
          const chave = format(d, 'yyyy-MM-dd');
          const aulas = consulta.data?.dias[chave] ?? [];
          return (
            <button
              key={chave}
              type="button"
              onClick={() => setDia(chave)}
              aria-label={`${format(d, 'dd/MM')}: ${aulas.length} aula(s)`}
              className="flex min-h-[3.5rem] flex-col items-center rounded-lg border border-slate-200 bg-white p-1 text-sm hover:bg-marca-50"
            >
              <span>{format(d, 'd')}</span>
              <span className="mt-1 flex flex-wrap justify-center gap-0.5">
                {aulas.slice(0, 6).map((a) => (
                  <span
                    key={a.id}
                    className={`h-2 w-2 rounded-full ${{ presente: 'bg-emerald-500', falta_aluno_justificada: 'bg-amber-500', falta_aluno_sem_aviso: 'bg-red-500', falta_professora: 'bg-sky-500', feriado_evento: 'bg-slate-400' }[a.presenca]}`}
                  />
                ))}
              </span>
            </button>
          );
        })}
      </div>
      <p className="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
        <span>● verde: presente</span><span>● amarelo: falta justificada</span><span>● vermelho: sem aviso</span><span>● azul: falta da professora</span>
      </p>
      <Modal aberto={!!dia} aoFechar={() => setDia(null)} titulo={dia ? `Dia ${formatarData(dia)}` : ''}>
        {aulasDoDia.length === 0 ? <Vazio>Sem aulas lançadas neste dia.</Vazio> : (
          <ul className="mb-3 divide-y divide-slate-100">
            {aulasDoDia.map((a) => (
              <li key={a.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                <span className="font-medium">{a.aluno}</span>
                <span className="flex items-center gap-2">
                  <Badge tom={TOM_PRESENCA[a.presenca]}>{PRESENCA[a.presenca]}</Badge>
                  <Link className="text-marca-700 underline" to={`/aulas/${a.id}/editar`}>Corrigir</Link>
                </span>
              </li>
            ))}
          </ul>
        )}
        <Link to={`/aulas/nova?data=${dia ?? ''}`}><Botao>Lançar aula neste dia</Botao></Link>
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
      {consulta.isLoading ? <Carregando /> : !consulta.data?.length ? <Vazio>Nenhuma aula lançada ainda.</Vazio> : (
        <Tabela rotulo="Últimas aulas" cabecalhos={['Data', 'Aluno', 'Presença', 'Conteúdo', '']}>
          {consulta.data.map((a) => (
            <tr key={a.id}>
              <Td>{formatarData(a.data)}</Td>
              <Td className="font-medium">{a.aluno}</Td>
              <Td><Badge tom={TOM_PRESENCA[a.presenca]}>{PRESENCA[a.presenca]}</Badge></Td>
              <Td className="max-w-xs truncate">{a.conteudo ?? '—'}</Td>
              <Td><Link className="text-marca-700 underline" to={`/aulas/${a.id}/editar`}>Corrigir</Link></Td>
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
      <Titulo acoes={<Link to="/aulas/nova"><Botao>Lançar aula</Botao></Link>}>Aulas</Titulo>
      <Abas abas={[
        { id: 'hoje', rotulo: 'Hoje', conteudo: <Hoje /> },
        { id: 'calendario', rotulo: 'Calendário', conteudo: <VisaoCalendario /> },
        { id: 'recentes', rotulo: 'Últimas aulas', conteudo: <Recentes /> },
      ]} />
    </>
  );
}

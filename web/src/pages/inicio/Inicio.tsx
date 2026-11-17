import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { PRESENCA, TOM_PRESENCA, type PainelInicio } from '../../api/tipos';
import { useAuth } from '../../auth/AuthContext';
import { ArteMusical } from '../../components/ArteMusical';
import { Icone, type NomeIcone } from '../../components/icones';
import { Anel, Aviso, Avatar, Badge, Barra, Botao, Cartao, Titulo, Vazio, cx } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { data, moeda } from '../../lib/format';

const COR_ESTAGIO: Record<string, string> = {
  iniciante: 'var(--color-nota-4)', basico: 'var(--color-nota-5)', intermediario: 'var(--color-nota-6)', avancado: 'var(--color-nota-7)',
  apto_culto_jovens: 'var(--color-nota-3)', apto_culto_adulto: 'var(--color-nota-2)', oficializado: 'var(--color-nota-1)', sem_estagio: 'var(--color-slate-300)',
};

function saudacao(): string {
  const h = new Date().getHours();
  return h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
}

function Kpi({ rotulo, valor, detalhe, icone, nota, para }: { rotulo: string; valor: string | number; detalhe?: string; icone: NomeIcone; nota: 1 | 2 | 3 | 4 | 5 | 6 | 7; para?: string }) {
  const corpo = (
    <div className="flex h-full items-center gap-3.5 rounded-[22px] border border-slate-200 bg-white p-4 shadow-[var(--shadow-cartao)] transition hover:border-marca-500">
      <span className="grid h-[52px] w-[52px] shrink-0 place-items-center rounded-2xl" style={{ background: `color-mix(in srgb, var(--color-nota-${nota}) 18%, white)`, color: nota === 3 ? '#8a6a00' : `var(--color-nota-${nota})` }}>
        <Icone nome={icone} className="h-[26px] w-[26px]" />
      </span>
      <div className="min-w-0">
        <small className="block text-[13px] text-slate-500">{rotulo}</small>
        <strong className="block font-titulo text-3xl font-semibold leading-tight">{valor}</strong>
        {detalhe && <em className="block text-xs not-italic text-slate-500">{detalhe}</em>}
      </div>
    </div>
  );
  return para ? <Link to={para} className="block">{corpo}</Link> : corpo;
}

export function PaginaInicio() {
  const { usuario } = useAuth();
  const consulta = useQuery({ queryKey: ['inicio'], queryFn: async () => (await api.get<{ dados: PainelInicio }>('/inicio')).dados });
  const p = consulta.data;
  const primeiroNome = (usuario?.nome ?? '').split(' ')[0];
  const proxima = p?.agenda.itens.find((i) => i.aula_id === null) ?? p?.agenda.itens[0];
  const percentRecebido = p && p.mes.previsto > 0 ? Math.round((p.mes.recebido / p.mes.previsto) * 100) : null;
  const total = Math.max(1, ...(p?.por_estagio.map((e) => e.quantidade) ?? [1]));

  return (
    <>
      <Titulo trilha={new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())}
        acoes={<Link to="/aulas/nova"><Botao><Icone nome="mais" />Lançar aula</Botao></Link>}>Início</Titulo>

      {consulta.isLoading && <div className="h-48 animate-pulse rounded-[22px] bg-slate-100" role="status" aria-label="Carregando" />}
      {consulta.error && <Aviso>{mensagemDeErro(consulta.error)}</Aviso>}

      {p && (
        <div className="flex flex-col gap-4 sm:gap-5">
          <section className="relative grid items-center gap-4 overflow-hidden rounded-[22px] p-5 sm:p-7 md:grid-cols-[1.3fr_1fr]" style={{ background: 'linear-gradient(115deg, #f6d9b8, #f2c4a0)', color: '#4a2a18' }}>
            <div>
              <h2 className="font-titulo text-[28px] font-semibold leading-tight sm:text-4xl">{saudacao()}, {primeiroNome}!</h2>
              <p className="mt-1.5 text-[15px]">
                {p.kpis.aulas_hoje === 0
                  ? 'Hoje não há aulas na agenda.'
                  : <>Você tem <b>{p.kpis.aulas_hoje} {p.kpis.aulas_hoje === 1 ? 'aula' : 'aulas'}</b> hoje{proxima && <> e a próxima é com <b>{proxima.aluno}</b>{proxima.hora ? <> às <b>{proxima.hora}</b></> : null}</>}.</>}
              </p>
              <div className="mt-3.5 flex flex-wrap gap-2 text-sm">
                {proxima?.hora && <span className="inline-flex items-center gap-1.5 rounded-full bg-white/60 px-3 py-1.5"><Icone nome="relogio" className="h-4 w-4" />Próxima: {proxima.hora}</span>}
                {p.kpis.cobrancas_atrasadas > 0 && <Link to="/financeiro" className="inline-flex items-center gap-1.5 rounded-full bg-white/60 px-3 py-1.5"><Icone nome="alerta" className="h-4 w-4" />{p.kpis.cobrancas_atrasadas} cobranças vencidas</Link>}
                {p.kpis.conclusoes_pendentes > 0 && <span className="inline-flex items-center gap-1.5 rounded-full bg-white/60 px-3 py-1.5"><Icone nome="check" className="h-4 w-4" />{p.kpis.conclusoes_pendentes} conclusões a confirmar</span>}
              </div>
            </div>
            <ArteMusical className="hidden w-full max-w-[330px] justify-self-end text-marca-600 md:block" />
          </section>

          <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <Kpi rotulo="Alunos ativos" valor={p.kpis.alunos_ativos} icone="users" nota={6} para="/alunos" />
            <Kpi rotulo="Aulas hoje" valor={p.kpis.aulas_hoje} detalhe={`${p.kpis.aulas_hoje_lancadas} já lançada(s)`} icone="calendario" nota={5} para="/aulas" />
            <Kpi rotulo="Atrasadas" valor={p.kpis.cobrancas_atrasadas} detalhe={moeda(p.kpis.valor_atrasado)} icone="alerta" nota={1} para="/financeiro" />
            <Kpi rotulo="Aptos p/ culto" valor={p.kpis.aptos_culto} detalhe="marcados pela professora" icone="check" nota={4} />
          </div>

          <div className="grid gap-4 sm:gap-5 lg:grid-cols-[7fr_5fr]">
            <Cartao titulo={`Agenda de hoje — ${data(p.agenda.data)}`} acoes={<Link to="/aulas"><Botao variante="fantasma" compacto>Calendário <Icone nome="seta" className="h-4 w-4" /></Botao></Link>}>
              {!p.agenda.itens.length ? <Vazio icone="calendario">Nenhum aluno com horário hoje.</Vazio> : (
                <ul className="divide-y divide-slate-100">
                  {p.agenda.itens.map((i) => (
                    <li key={i.aluno_id} className="flex items-center gap-3 py-3">
                      <span className="w-12 shrink-0 font-mono text-sm tabular-nums text-slate-500">{i.hora ?? '--:--'}</span>
                      <Avatar nome={i.aluno} />
                      <div className="min-w-0 flex-1"><Link to={`/alunos/${i.aluno_id}`} className="block truncate font-semibold hover:underline">{i.aluno}</Link></div>
                      {i.presenca
                        ? <Badge tom={TOM_PRESENCA[i.presenca]}>{PRESENCA[i.presenca]}</Badge>
                        : <Link to={`/aulas/nova?aluno=${i.aluno_id}`}><Botao compacto>Lançar</Botao></Link>}
                    </li>
                  ))}
                </ul>
              )}
            </Cartao>

            <Cartao titulo="Alunos por estágio" acoes={<Badge>ativos</Badge>}>
              <div className="flex flex-col gap-3">
                {p.por_estagio.map((e) => (
                  <div key={e.estagio} className="grid grid-cols-[minmax(0,8.5rem)_1fr_1.5rem] items-center gap-3 text-sm sm:grid-cols-[9.5rem_1fr_1.5rem]">
                    <span className="truncate">{e.rotulo}</span>
                    <Barra valor={(e.quantidade / total) * 100} cor={COR_ESTAGIO[e.estagio]} rotulo={`${e.rotulo}: ${e.quantidade}`} />
                    <b className="text-right">{e.quantidade}</b>
                  </div>
                ))}
              </div>
            </Cartao>
          </div>

          <div className="grid gap-4 sm:gap-5 md:grid-cols-3">
            <Cartao titulo="Frequência do mês">
              <div className="flex items-center gap-4">
                <Anel valor={p.mes.frequencia} tamanho={104} cor="var(--color-nota-5)" rotulo="Frequência do mês" />
                <p className="text-sm text-slate-500">{p.mes.aulas} aulas lançadas. Faltas da professora e feriados ficam fora do cálculo.</p>
              </div>
            </Cartao>
            <Cartao titulo="Recebido no mês">
              <div className="flex items-center gap-4">
                <Anel valor={percentRecebido} tamanho={104} cor="var(--color-nota-4)" rotulo="Recebido no mês" />
                <p className="text-sm text-slate-500"><b className="text-slate-900">{moeda(p.mes.recebido)}</b> de {moeda(p.mes.previsto)} previstos.{p.mes.vencido > 0 && <><br />{moeda(p.mes.vencido)} vencidos.</>}</p>
              </div>
            </Cartao>
            <Cartao titulo="Aniversariantes" acoes={<Badge tom="roxo">esta semana</Badge>}>
              {!p.aniversariantes.length ? <p className="py-3 text-sm text-slate-500">Ninguém faz aniversário nos próximos 7 dias.</p> : (
                <ul className="divide-y divide-slate-100">
                  {p.aniversariantes.map((a) => (
                    <li key={a.aluno_id} className="flex items-center gap-3 py-2.5">
                      <Avatar nome={a.aluno} tamanho="p" />
                      <div className="min-w-0"><Link to={`/alunos/${a.aluno_id}`} className="block truncate text-sm font-semibold hover:underline">{a.aluno}</Link>
                        <small className={cx('text-slate-500', a.hoje && 'font-semibold text-marca-700')}>{a.hoje ? 'hoje' : data(a.data).slice(0, 5)} · {a.idade} anos</small></div>
                    </li>
                  ))}
                </ul>
              )}
            </Cartao>
          </div>
        </div>
      )}
    </>
  );
}

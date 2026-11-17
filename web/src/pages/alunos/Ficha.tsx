import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { SITUACAO_ALUNO, type Aluno, type FichaProgresso } from '../../api/tipos';
import { Icone } from '../../components/icones';
import { Abas, Anel, Aviso, Avatar, Badge, Botao, Cartao, Carregando, Confirmar, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { DIAS_SEMANA, data, hoje, moeda } from '../../lib/format';
import { AbaAulas } from './AbaAulas';
import { AbaFinanceira } from './AbaFinanceira';
import { AbaProgresso } from './AbaProgresso';
import { tomSituacao } from './Alunos';

function Linha({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:gap-4">
      <dt className="w-44 shrink-0 text-sm text-slate-500">{rotulo}</dt>
      <dd className="text-sm font-medium text-slate-900">{children}</dd>
    </div>
  );
}

function Cadastro({ aluno }: { aluno: Aluno }) {
  return (
    <Cartao acoes={<Link to={`/alunos/${aluno.id}/editar`}><Botao variante="secundario" compacto><Icone nome="editar" className="h-4 w-4" />Editar dados</Botao></Link>}>
      <dl className="divide-y divide-slate-100">
        <Linha rotulo="Instrumento">{aluno.instrumento ?? '—'}</Linha>
        <Linha rotulo="Nascimento">{data(aluno.data_nascimento)} {aluno.faixa_etaria && <span className="text-slate-500">({aluno.faixa_etaria})</span>}</Linha>
        <Linha rotulo="Contato">{aluno.contato ?? '—'}</Linha>
        <Linha rotulo="Responsável">{aluno.responsavel_nome ?? '—'}</Linha>
        <Linha rotulo="Aula">{aluno.dia_semana !== null ? `${DIAS_SEMANA[aluno.dia_semana]} ${aluno.hora_aula ?? ''}` : '—'}</Linha>
        <Linha rotulo="Mensalidade">{moeda(aluno.valor_mensalidade)} — vence todo dia {aluno.dia_vencimento}</Linha>
        <Linha rotulo="Início">{data(aluno.data_inicio)} {aluno.meses_ativos !== null && <span className="text-slate-500">({aluno.meses_ativos} meses)</span>}</Linha>
        <Linha rotulo="Observações"><span className="whitespace-pre-wrap font-normal">{aluno.observacoes ?? '—'}</span></Linha>
      </dl>
    </Cartao>
  );
}

export function PaginaFicha() {
  const { id } = useParams();
  const navegar = useNavigate();
  const cliente = useQueryClient();
  const [excluir, setExcluir] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const consulta = useQuery({
    queryKey: ['aluno', id],
    queryFn: async () => (await api.get<{ dados: Aluno }>(`/alunos/${id}`)).dados,
  });
  // mesma consulta da aba Progresso (compartilha o cache)
  const progresso = useQuery({
    queryKey: ['ficha-progresso', Number(id)],
    queryFn: async () => (await api.get<{ dados: FichaProgresso }>(`/alunos/${id}/ficha-progresso`)).dados,
    enabled: !!consulta.data,
  });
  const remover = useMutation({
    mutationFn: () => api.delete(`/alunos/${id}`),
    onSuccess: () => { void cliente.invalidateQueries({ queryKey: ['alunos'] }); navegar('/alunos', { replace: true }); },
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  if (consulta.isLoading) return <Carregando />;
  if (!consulta.data) return <Aviso>Aluno não encontrado.</Aviso>;
  const aluno = consulta.data;
  const f = progresso.data;
  const digitos = (aluno.contato ?? '').replace(/\D/g, '');
  const whatsapp = digitos.length >= 10 ? `https://wa.me/${digitos.startsWith('55') ? digitos : `55${digitos}`}` : null;

  return (
    <>
      <Titulo
        trilha={<Link to="/alunos" className="text-marca-700 hover:underline">Alunos</Link>}
        acoes={
          <>
            <Botao variante="secundario" onClick={() => window.print()}><Icone nome="imprimir" />Imprimir</Botao>
            <Botao variante="perigo" onClick={() => setExcluir(true)}><Icone nome="lixeira" />Excluir</Botao>
          </>
        }
      >
        {aluno.nome}
      </Titulo>
      {erro && <div className="mb-3"><Aviso>{erro}</Aviso></div>}
      <p className="mb-2 hidden text-sm text-slate-600 print:block">Ficha de {aluno.nome} — impressa em {data(hoje())}</p>

      <Cartao className="mb-5">
        <div className="flex flex-wrap items-center gap-x-6 gap-y-4">
          <Avatar nome={aluno.nome} tamanho="g" />
          <div className="min-w-[14rem] flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tom={tomSituacao(aluno.situacao)}>{SITUACAO_ALUNO[aluno.situacao]}</Badge>
              {f?.estagio.rotulo && <Badge tom="azul">{f.estagio.rotulo}</Badge>}
            </div>
            <p className="mt-2 text-[15px] text-slate-600">
              {aluno.instrumento ?? 'Sem instrumento'}
              {aluno.dia_semana !== null && <> · {DIAS_SEMANA[aluno.dia_semana]} {aluno.hora_aula}</>}
              {aluno.meses_ativos !== null && <> · {aluno.meses_ativos} meses de aulas</>}
              {aluno.faixa_etaria && <> · {aluno.faixa_etaria}</>}
            </p>
            <div className="nao-imprimir mt-3 flex flex-wrap gap-2">
              {digitos.length >= 8 && <a href={`tel:${digitos}`}><Botao variante="secundario" compacto><Icone nome="tel" className="h-4 w-4" />Ligar</Botao></a>}
              {whatsapp && <a href={whatsapp} target="_blank" rel="noreferrer"><Botao variante="secundario" compacto>WhatsApp</Botao></a>}
              <Link to={`/aulas/nova?aluno=${aluno.id}`}><Botao compacto><Icone nome="mais" className="h-4 w-4" />Lançar aula</Botao></Link>
              <Link to={`/alunos/${aluno.id}/editar`}><Botao variante="secundario" compacto><Icone nome="editar" className="h-4 w-4" />Editar</Botao></Link>
            </div>
          </div>
          {f && (
            <div className="flex items-center gap-4">
              <div className="text-center"><Anel valor={f.progresso_geral} tamanho={84} rotulo="Progresso geral" /><small className="mt-1 block text-xs text-slate-500">progresso</small></div>
              <div className="text-center"><Anel valor={f.frequencia.frequencia} tamanho={84} cor="var(--color-nota-5)" rotulo="Frequência" /><small className="mt-1 block text-xs text-slate-500">frequência</small></div>
            </div>
          )}
        </div>
      </Cartao>

      <Abas
        padrao="progresso"
        abas={[
          { id: 'cadastro', rotulo: 'Cadastro', conteudo: <Cadastro aluno={aluno} /> },
          { id: 'progresso', rotulo: 'Progresso', conteudo: <AbaProgresso aluno={aluno} /> },
          { id: 'aulas', rotulo: 'Aulas', conteudo: <AbaAulas aluno={aluno} /> },
          { id: 'financeira', rotulo: 'Financeira', conteudo: <AbaFinanceira aluno={aluno} /> },
        ]}
      />
      <Confirmar aberto={excluir} aoFechar={() => setExcluir(false)} titulo="Excluir aluno"
        texto={`Excluir ${aluno.nome}? O registro some das listas (exclusão lógica, mantida no log).`}
        rotuloConfirmar="Excluir" aoConfirmar={() => remover.mutate()} />
    </>
  );
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { SITUACAO_ALUNO, type Aluno } from '../../api/tipos';
import { Abas, Aviso, Badge, Botao, Carregando, Cartao, Confirmar, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { DIAS_SEMANA, data, moeda } from '../../lib/format';
import { AbaAulas } from './AbaAulas';
import { AbaFinanceira } from './AbaFinanceira';
import { AbaProgresso } from './AbaProgresso';
import { tomSituacao } from './Alunos';

function Linha({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-1.5 sm:flex-row sm:gap-4">
      <dt className="w-44 shrink-0 text-sm text-slate-500">{rotulo}</dt>
      <dd className="text-sm font-medium text-slate-900">{children}</dd>
    </div>
  );
}

function Cadastro({ aluno }: { aluno: Aluno }) {
  const digitos = (aluno.contato ?? '').replace(/\D/g, '');
  const whatsapp = digitos.length >= 10 ? `https://wa.me/${digitos.startsWith('55') ? digitos : `55${digitos}`}` : null;
  return (
    <Cartao>
      <dl className="divide-y divide-slate-100">
        <Linha rotulo="Instrumento">{aluno.instrumento ?? '—'}</Linha>
        <Linha rotulo="Nascimento">{data(aluno.data_nascimento)} {aluno.faixa_etaria && <span className="text-slate-500">({aluno.faixa_etaria})</span>}</Linha>
        <Linha rotulo="Contato">
          {aluno.contato ?? '—'}
          {digitos.length >= 8 && (
            <span className="nao-imprimir ml-3 inline-flex gap-3">
              <a className="text-marca-700 underline" href={`tel:${digitos}`}>Ligar</a>
              {whatsapp && <a className="text-marca-700 underline" href={whatsapp} target="_blank" rel="noreferrer">WhatsApp</a>}
            </span>
          )}
        </Linha>
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
  const remover = useMutation({
    mutationFn: () => api.delete(`/alunos/${id}`),
    onSuccess: () => { void cliente.invalidateQueries({ queryKey: ['alunos'] }); navegar('/alunos', { replace: true }); },
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  if (consulta.isLoading) return <Carregando />;
  if (!consulta.data) return <Aviso>Aluno não encontrado.</Aviso>;
  const aluno = consulta.data;

  return (
    <>
      <Titulo
        acoes={
          <>
            <Botao variante="secundario" onClick={() => window.print()}>Imprimir</Botao>
            <Link to={`/alunos/${aluno.id}/editar`}><Botao variante="secundario">Editar</Botao></Link>
            <Botao variante="perigo" onClick={() => setExcluir(true)}>Excluir</Botao>
          </>
        }
      >
        {aluno.nome} <Badge tom={tomSituacao(aluno.situacao)}>{SITUACAO_ALUNO[aluno.situacao]}</Badge>
      </Titulo>
      {erro && <div className="mb-3"><Aviso>{erro}</Aviso></div>}
      <Abas
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

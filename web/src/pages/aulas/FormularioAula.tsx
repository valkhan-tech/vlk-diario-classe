import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import { useLista } from '../../api/hooks';
import { PRESENCA, type Aluno, type Aula, type ItemAvaliacao } from '../../api/tipos';
import { AreaTexto, Aviso, Botao, Campo, Carregando, Cartao, Selecao, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { data as formatarData, hoje } from '../../lib/format';
import { AvaliacaoNaAula } from '../progresso/AvaliacaoNaAula';

const vazioParaNulo = (v: unknown) => (v === '' || v === undefined || Number.isNaN(v) ? null : v);

const esquema = z.object({
  aluno_id: z.coerce.number({ error: 'Escolha o aluno.' }).positive('Escolha o aluno.'),
  data: z.string().min(1, 'Informe a data.'),
  presenca: z.enum(['presente', 'falta_aluno_justificada', 'falta_aluno_sem_aviso', 'falta_professora', 'feriado_evento']),
  motivo: z.preprocess(vazioParaNulo, z.string().max(255).nullable()),
  tipo_aula_id: z.preprocess(vazioParaNulo, z.coerce.number().nullable()),
  conteudo: z.preprocess(vazioParaNulo, z.string().nullable()),
  hino: z.preprocess(vazioParaNulo, z.string().max(150).nullable()),
  licao_casa: z.preprocess(vazioParaNulo, z.string().nullable()),
  video_url: z.preprocess(vazioParaNulo, z.string().max(255).nullable()),
  observacoes: z.preprocess(vazioParaNulo, z.string().nullable()),
});
type Dados = z.input<typeof esquema>;
type Saida = z.output<typeof esquema>;

export function PaginaFormularioAula() {
  const { id } = useParams();
  const editando = !!id;
  const [params] = useSearchParams();
  const navegar = useNavigate();
  const cliente = useQueryClient();
  const tipos = useLista('tipo_aula');
  const [erro, setErro] = useState<string | null>(null);
  const [avaliacoes, setAvaliacoes] = useState<ItemAvaliacao[]>([]);

  const alunos = useQuery({
    queryKey: ['alunos', '', '', ''],
    queryFn: async () => (await api.get<{ dados: Aluno[] }>('/alunos')).dados,
  });
  const aula = useQuery({
    queryKey: ['aula', id],
    queryFn: async () => (await api.get<{ dados: Aula }>(`/aulas/${id}`)).dados,
    enabled: editando,
  });

  const { register, handleSubmit, control, formState } = useForm<Dados, unknown, Saida>({
    resolver: zodResolver(esquema),
    values: aula.data
      ? {
          aluno_id: aula.data.aluno_id, data: aula.data.data, presenca: aula.data.presenca, motivo: aula.data.motivo ?? '',
          tipo_aula_id: aula.data.tipo_aula_id ?? '', conteudo: aula.data.conteudo ?? '', hino: aula.data.hino ?? '',
          licao_casa: aula.data.licao_casa ?? '', video_url: aula.data.video_url ?? '', observacoes: aula.data.observacoes ?? '',
        }
      : undefined,
    defaultValues: { aluno_id: params.get('aluno') ?? '', data: params.get('data') || hoje(), presenca: 'presente' } as Dados,
  });

  const alunoId = Number(useWatch({ control, name: 'aluno_id' }));
  const dataAula = String(useWatch({ control, name: 'data' }) ?? '');
  const presenca = useWatch({ control, name: 'presenca' });

  const anterior = useQuery({
    queryKey: ['aula-anterior', alunoId, dataAula],
    queryFn: async () => (await api.get<{ dados: Aula | null }>(`/alunos/${alunoId}/aulas/anterior?antes=${dataAula}`)).dados,
    enabled: alunoId > 0 && /^\d{4}-\d{2}-\d{2}$/.test(dataAula),
  });

  const salvar = useMutation({
    mutationFn: async (dados: Saida) => {
      const r = editando ? await api.put<{ dados: Aula }>(`/aulas/${id}`, dados) : await api.post<{ dados: Aula }>('/aulas', dados);
      if (avaliacoes.length > 0) {
        await api.post(`/alunos/${r.dados.aluno_id}/avaliacoes`, { avaliacoes, data: dados.data, aula_id: r.dados.id });
      }
      return r;
    },
    onSuccess: (r) => {
      for (const k of ['aulas', 'agenda', 'calendario', 'aula', 'aluno-aulas', 'ficha-progresso', 'licoes-aluno', 'inicio']) void cliente.invalidateQueries({ queryKey: [k] });
      navegar(`/alunos/${r.dados.aluno_id}`);
    },
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  const excluir = useMutation({
    mutationFn: () => api.delete(`/aulas/${id}`),
    onSuccess: () => {
      for (const k of ['aulas', 'agenda', 'calendario', 'aluno-aulas']) void cliente.invalidateQueries({ queryKey: [k] });
      navegar('/aulas');
    },
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  if (editando && aula.isLoading) return <Carregando />;
  const e = formState.errors;
  const ehFalta = presenca !== 'presente';

  return (
    <>
      <Titulo acoes={editando && <Botao variante="perigo" onClick={() => { if (window.confirm('Excluir esta aula?')) excluir.mutate(); }}>Excluir</Botao>}>
        {editando ? 'Corrigir aula' : 'Lançar aula'}
      </Titulo>
      <form onSubmit={handleSubmit((d) => { setErro(null); salvar.mutate(d); })} noValidate className="flex flex-col gap-4">
        {erro && <Aviso>{erro}</Aviso>}
        <Cartao>
          <div className="grid gap-3 sm:grid-cols-2">
            <Selecao rotulo="Aluno" erro={e.aluno_id?.message} {...register('aluno_id')}>
              <option value="">Escolha…</option>
              {alunos.data?.map((a) => <option key={a.id} value={a.id}>{a.nome}</option>)}
            </Selecao>
            <Campo rotulo="Data" type="date" erro={e.data?.message} {...register('data')} />
            <Selecao rotulo="Presença" erro={e.presenca?.message} className="sm:col-span-2" {...register('presenca')}>
              {Object.entries(PRESENCA).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
            </Selecao>
            {ehFalta && <Campo rotulo="Motivo" erro={e.motivo?.message} className="sm:col-span-2" {...register('motivo')} />}
          </div>
          {presenca === 'falta_aluno_justificada' && (
            <p className="mt-2 text-xs text-slate-500">Dica: se houver reposição, anote a data nas observações.</p>
          )}
        </Cartao>

        {anterior.data && (
          <Aviso tipo="info">
            <b>Última aula ({formatarData(anterior.data.data)}):</b>{' '}
            {anterior.data.licao_casa ? `lição de casa — ${anterior.data.licao_casa}` : 'sem lição de casa registrada.'}
          </Aviso>
        )}

        <Cartao titulo="Conteúdo da aula">
          <div className="grid gap-3 sm:grid-cols-2">
            <Selecao rotulo="Tipo de aula" {...register('tipo_aula_id')}>
              <option value="">—</option>
              {tipos.data?.map((t) => <option key={t.id} value={t.id}>{t.valor}</option>)}
            </Selecao>
            <Campo rotulo="Hino" erro={e.hino?.message} {...register('hino')} />
            <AreaTexto rotulo="Conteúdo trabalhado" className="sm:col-span-2" {...register('conteudo')} />
            <AreaTexto rotulo="Lição de casa" className="sm:col-span-2" {...register('licao_casa')} />
            <Campo rotulo="Vídeo (link)" type="url" className="sm:col-span-2" erro={e.video_url?.message} {...register('video_url')} />
            <AreaTexto rotulo="Observações" className="sm:col-span-2" {...register('observacoes')} />
          </div>
        </Cartao>

        {alunoId > 0 && presenca === 'presente' && <AvaliacaoNaAula alunoId={alunoId} aoMudar={setAvaliacoes} />}

        <div className="flex justify-end gap-2">
          <Botao type="button" variante="secundario" onClick={() => navegar(-1)}>Cancelar</Botao>
          <Botao type="submit" carregando={salvar.isPending}>Salvar aula</Botao>
        </div>
      </form>
    </>
  );
}

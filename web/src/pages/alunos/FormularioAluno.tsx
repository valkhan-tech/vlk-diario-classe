import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';
import { useLista } from '../../api/hooks';
import { SITUACAO_ALUNO, type Aluno } from '../../api/tipos';
import { AreaTexto, Aviso, Botao, Campo, Carregando, Cartao, Selecao, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { DIAS_SEMANA } from '../../lib/format';

const vazioParaNulo = (v: unknown) => (v === '' || v === undefined || Number.isNaN(v) ? null : v);

const esquema = z.object({
  nome: z.string().min(1, 'Informe o nome.').max(150),
  data_nascimento: z.preprocess(vazioParaNulo, z.string().nullable()),
  instrumento_id: z.preprocess(vazioParaNulo, z.coerce.number().nullable()),
  contato: z.preprocess(vazioParaNulo, z.string().max(150).nullable()),
  responsavel_nome: z.preprocess(vazioParaNulo, z.string().max(150).nullable()),
  dia_semana: z.preprocess(vazioParaNulo, z.coerce.number().min(0).max(6).nullable()),
  hora_aula: z.preprocess(vazioParaNulo, z.string().nullable()),
  valor_mensalidade: z.coerce.number({ error: 'Informe a mensalidade.' }).min(0, 'Informe 0 ou mais.'),
  dia_vencimento: z.coerce.number({ error: 'Informe o dia.' }).int().min(1, 'Entre 1 e 31.').max(31, 'Entre 1 e 31.'),
  data_inicio: z.preprocess(vazioParaNulo, z.string().nullable()),
  situacao: z.enum(['ativo', 'pausado', 'encerrado']),
  observacoes: z.preprocess(vazioParaNulo, z.string().nullable()),
});
type Dados = z.input<typeof esquema>;
type Saida = z.output<typeof esquema>;

export function PaginaFormularioAluno() {
  const { id } = useParams();
  const editando = !!id;
  const navegar = useNavigate();
  const cliente = useQueryClient();
  const instrumentos = useLista('instrumento');
  const [erro, setErro] = useState<string | null>(null);

  const aluno = useQuery({
    queryKey: ['aluno', id],
    queryFn: async () => (await api.get<{ dados: Aluno }>(`/alunos/${id}`)).dados,
    enabled: editando,
  });

  const { register, handleSubmit, formState } = useForm<Dados, unknown, Saida>({
    resolver: zodResolver(esquema),
    values: aluno.data
      ? {
          nome: aluno.data.nome, data_nascimento: aluno.data.data_nascimento ?? '', instrumento_id: aluno.data.instrumento_id ?? '',
          contato: aluno.data.contato ?? '', responsavel_nome: aluno.data.responsavel_nome ?? '', dia_semana: aluno.data.dia_semana ?? '',
          hora_aula: aluno.data.hora_aula ?? '', valor_mensalidade: aluno.data.valor_mensalidade, dia_vencimento: aluno.data.dia_vencimento,
          data_inicio: aluno.data.data_inicio ?? '', situacao: aluno.data.situacao, observacoes: aluno.data.observacoes ?? '',
        }
      : undefined,
    defaultValues: { situacao: 'ativo', dia_vencimento: 10, valor_mensalidade: 0 } as Dados,
  });

  const salvar = useMutation({
    mutationFn: (dados: Saida) => {
      const corpo = editando ? dados : (({ situacao: _s, ...r }) => r)(dados);
      return editando ? api.put<{ dados: Aluno }>(`/alunos/${id}`, corpo) : api.post<{ dados: Aluno }>('/alunos', corpo);
    },
    onSuccess: (r) => {
      void cliente.invalidateQueries({ queryKey: ['alunos'] });
      void cliente.invalidateQueries({ queryKey: ['aluno'] });
      navegar(`/alunos/${r.dados.id}`);
    },
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  if (editando && aluno.isLoading) return <Carregando />;
  const e = formState.errors;

  return (
    <>
      <Titulo>{editando ? 'Editar aluno' : 'Novo aluno'}</Titulo>
      <form onSubmit={handleSubmit((d) => { setErro(null); salvar.mutate(d); })} noValidate className="flex flex-col gap-4">
        {erro && <Aviso>{erro}</Aviso>}
        <Cartao titulo="Dados pessoais">
          <div className="grid gap-3 sm:grid-cols-2">
            <Campo rotulo="Nome" erro={e.nome?.message} className="sm:col-span-2" {...register('nome')} />
            <Campo rotulo="Data de nascimento" type="date" erro={e.data_nascimento?.message} {...register('data_nascimento')} />
            <Selecao rotulo="Instrumento" erro={e.instrumento_id?.message} {...register('instrumento_id')}>
              <option value="">—</option>
              {instrumentos.data?.map((i) => <option key={i.id} value={i.id}>{i.valor}</option>)}
            </Selecao>
            <Campo rotulo="Contato (telefone/WhatsApp)" erro={e.contato?.message} {...register('contato')} />
            <Campo rotulo="Responsável" erro={e.responsavel_nome?.message} {...register('responsavel_nome')} />
          </div>
        </Cartao>
        <Cartao titulo="Aulas e cobrança">
          <div className="grid gap-3 sm:grid-cols-2">
            <Selecao rotulo="Dia da aula" {...register('dia_semana')}>
              <option value="">—</option>
              {DIAS_SEMANA.map((d, i) => <option key={d} value={i}>{d}</option>)}
            </Selecao>
            <Campo rotulo="Horário da aula" type="time" {...register('hora_aula')} />
            <Campo rotulo="Mensalidade (R$)" type="number" step="0.01" min="0" erro={e.valor_mensalidade?.message} {...register('valor_mensalidade')} />
            <Campo rotulo="Dia de vencimento" type="number" min="1" max="31" erro={e.dia_vencimento?.message}
              dica="Fixo todo mês. Dias 29–31 em meses curtos vencem no último dia." {...register('dia_vencimento')} />
            <Campo rotulo="Início das aulas" type="date" {...register('data_inicio')} />
            {editando && (
              <Selecao rotulo="Situação" erro={e.situacao?.message} dica="Só alunos Ativos geram cobrança mensal." {...register('situacao')}>
                {Object.entries(SITUACAO_ALUNO).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
              </Selecao>
            )}
          </div>
        </Cartao>
        <Cartao titulo="Observações">
          <AreaTexto rotulo="Observações" {...register('observacoes')} />
        </Cartao>
        <div className="flex justify-end gap-2">
          <Botao type="button" variante="secundario" onClick={() => navegar(-1)}>Cancelar</Botao>
          <Botao type="submit" carregando={salvar.isPending}>Salvar</Botao>
        </div>
      </form>
    </>
  );
}

import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router-dom';
import { z } from 'zod';
import { useLista } from '../../api/hooks';
import { NIVEL, type Metodo } from '../../api/tipos';
import { Icone } from '../../components/icones';
import { Aviso, Badge, Botao, Campo, Carregando, Cartao, Modal, Selecao, Titulo, Vazio, useToast } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';

const vazio = (v: unknown) => (v === '' || v === undefined ? null : v);

const esquema = z.object({
  nome: z.string().min(1, 'Informe o nome.').max(150),
  tipo: z.enum(['metodo', 'hinario']),
  nivel: z.preprocess(vazio, z.enum(['iniciante', 'basico', 'intermediario', 'avancado']).nullable()),
  instrumento_id: z.preprocess(vazio, z.coerce.number().nullable()),
});
type Entrada = z.input<typeof esquema>;
type Saida = z.output<typeof esquema>;

export function FormularioMetodo({ metodo, aoSalvar, aoCancelar }: { metodo?: Metodo; aoSalvar: (m: Metodo) => void; aoCancelar: () => void }) {
  const instrumentos = useLista('instrumento');
  const [erro, setErro] = useState<string | null>(null);
  const { register, handleSubmit, formState } = useForm<Entrada, unknown, Saida>({
    resolver: zodResolver(esquema),
    defaultValues: metodo
      ? { nome: metodo.nome, tipo: metodo.tipo, nivel: metodo.nivel ?? '', instrumento_id: metodo.instrumento_id ?? '' }
      : { tipo: 'metodo', nivel: '', instrumento_id: '' },
  });
  const salvar = useMutation({
    mutationFn: (d: Saida) => (metodo ? api.put<{ dados: Metodo }>(`/metodos/${metodo.id}`, d) : api.post<{ dados: Metodo }>('/metodos', d)),
    onSuccess: (r) => aoSalvar(r.dados),
    onError: (e) => setErro(mensagemDeErro(e)),
  });
  const e = formState.errors;
  return (
    <form onSubmit={handleSubmit((d) => { setErro(null); salvar.mutate(d); })} noValidate className="flex flex-col gap-3">
      {erro && <Aviso>{erro}</Aviso>}
      <Campo rotulo="Nome" erro={e.nome?.message} {...register('nome')} />
      <div className="grid gap-3 sm:grid-cols-2">
        <Selecao rotulo="Tipo" {...register('tipo')}>
          <option value="metodo">Método</option>
          <option value="hinario">Hinário</option>
        </Selecao>
        <Selecao rotulo="Nível" dica="Dá o nome do estágio do aluno." {...register('nivel')}>
          <option value="">Sem nível</option>
          {Object.entries(NIVEL).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
        </Selecao>
        <Selecao rotulo="Instrumento" dica="Em branco = vale para qualquer instrumento." className="sm:col-span-2" {...register('instrumento_id')}>
          <option value="">Qualquer</option>
          {instrumentos.data?.map((i) => <option key={i.id} value={i.id}>{i.valor}</option>)}
        </Selecao>
      </div>
      <div className="flex justify-end gap-2">
        <Botao type="button" variante="secundario" onClick={aoCancelar}>Cancelar</Botao>
        <Botao type="submit" carregando={salvar.isPending}>Salvar</Botao>
      </div>
    </form>
  );
}

const ORDEM_NIVEL = { iniciante: 1, basico: 2, intermediario: 3, avancado: 4 } as const;
const NOTA_NIVEL = { iniciante: 4, basico: 5, intermediario: 6, avancado: 7 } as const;

export function PaginaMetodos() {
  const cliente = useQueryClient();
  const avisar = useToast();
  const [novo, setNovo] = useState(false);
  const consulta = useQuery({ queryKey: ['metodos'], queryFn: async () => (await api.get<{ dados: Metodo[] }>('/metodos')).dados });

  return (
    <>
      <Titulo trilha="Pedagógico" acoes={<Botao onClick={() => setNovo(true)}><Icone nome="mais" />Novo método</Botao>}>Métodos e hinários</Titulo>
      {consulta.isLoading ? <Carregando /> : !consulta.data?.length ? (
        <Cartao><Vazio icone="livro" acao={<Botao onClick={() => setNovo(true)}>Novo método</Botao>}>Nenhum método cadastrado.</Vazio></Cartao>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-3">
          {consulta.data.map((m) => {
            const nota = m.nivel ? NOTA_NIVEL[m.nivel] : 3;
            return (
              <Link key={m.id} to={`/metodos/${m.id}`} className="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[var(--shadow-cartao)] transition hover:border-marca-500">
                <div className="flex items-center justify-between gap-2">
                  <span className="grid h-12 w-12 place-items-center rounded-2xl" style={{ background: `color-mix(in srgb, var(--color-nota-${nota}) 18%, white)`, color: nota === 3 ? '#8a6a00' : `var(--color-nota-${nota})` }}>
                    <Icone nome={m.tipo === 'hinario' ? 'nota' : 'livro'} className="h-6 w-6" />
                  </span>
                  {m.ativo ? <Badge tom="verde">Ativo</Badge> : <Badge>Inativo</Badge>}
                </div>
                <h3 className="mt-3 text-lg font-semibold">{m.nome}</h3>
                <p className="text-[13px] text-slate-500">{m.instrumento ?? 'Qualquer instrumento'} · {m.total_licoes} {m.tipo === 'hinario' ? 'hinos' : 'lições'}</p>
                <div className="mb-2 mt-4 flex gap-1.5" aria-hidden="true">
                  {[1, 2, 3, 4].map((n) => <i key={n} className="h-2 flex-1 rounded" style={{ background: m.nivel && n <= ORDEM_NIVEL[m.nivel] ? 'var(--color-marca-600)' : 'var(--color-slate-200)' }} />)}
                </div>
                <div className="flex flex-wrap gap-2">
                  {m.tipo === 'hinario' && <Badge tom="roxo">Hinário</Badge>}
                  <Badge tom={m.nivel ? 'azul' : 'neutro'}>{m.nivel ? NIVEL[m.nivel] : 'Sem nível'}</Badge>
                </div>
              </Link>
            );
          })}
        </div>
      )}
      <Modal aberto={novo} aoFechar={() => setNovo(false)} titulo="Novo método">
        <FormularioMetodo aoCancelar={() => setNovo(false)} aoSalvar={() => { setNovo(false); avisar('Método cadastrado.', 'ok'); void cliente.invalidateQueries({ queryKey: ['metodos'] }); }} />
      </Modal>
    </>
  );
}

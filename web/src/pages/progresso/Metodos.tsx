import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router-dom';
import { z } from 'zod';
import { useLista } from '../../api/hooks';
import { NIVEL, type Metodo } from '../../api/tipos';
import { Aviso, Badge, Botao, Campo, Carregando, Cartao, Modal, Selecao, Tabela, Td, Titulo, Vazio } from '../../components/ui';
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

export function PaginaMetodos() {
  const cliente = useQueryClient();
  const [novo, setNovo] = useState(false);
  const consulta = useQuery({ queryKey: ['metodos'], queryFn: async () => (await api.get<{ dados: Metodo[] }>('/metodos')).dados });

  return (
    <>
      <Titulo acoes={<Botao onClick={() => setNovo(true)}>Novo método</Botao>}>Métodos e hinários</Titulo>
      <Cartao>
        {consulta.isLoading ? <Carregando /> : !consulta.data?.length ? <Vazio>Nenhum método cadastrado.</Vazio> : (
          <Tabela rotulo="Métodos" cabecalhos={['Nome', 'Tipo', 'Nível', 'Instrumento', 'Lições', 'Situação']}>
            {consulta.data.map((m) => (
              <tr key={m.id} className="hover:bg-slate-50">
                <Td className="font-medium"><Link className="text-marca-700 underline" to={`/metodos/${m.id}`}>{m.nome}</Link></Td>
                <Td>{m.tipo === 'hinario' ? <Badge tom="roxo">Hinário</Badge> : 'Método'}</Td>
                <Td>{m.nivel ? NIVEL[m.nivel] : '—'}</Td>
                <Td>{m.instrumento ?? 'Qualquer'}</Td>
                <Td>{m.total_licoes}</Td>
                <Td>{m.ativo ? <Badge tom="verde">Ativo</Badge> : <Badge>Inativo</Badge>}</Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>
      <Modal aberto={novo} aoFechar={() => setNovo(false)} titulo="Novo método">
        <FormularioMetodo aoCancelar={() => setNovo(false)} aoSalvar={() => { setNovo(false); void cliente.invalidateQueries({ queryKey: ['metodos'] }); }} />
      </Modal>
    </>
  );
}

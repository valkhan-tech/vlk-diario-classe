import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import type { Usuario } from '../../auth/AuthContext';
import { Aviso, Badge, Botao, Campo, Carregando, Cartao, Confirmar, Modal, Selecao, Tabela, Td, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { dataHora } from '../../lib/format';

type UsuarioLinha = Usuario & { ultimo_login_em: string | null };

const PERFIS = { admin: 'Administrador', professora: 'Professora', responsavel: 'Responsável' } as const;

const esquemaNovo = z.object({
  nome: z.string().min(1, 'Informe o nome.').max(150),
  email: z.string().email('Informe um e-mail válido.'),
  perfil: z.enum(['admin', 'professora', 'responsavel']),
  senha: z.string().min(8, 'Mínimo de 8 caracteres.').regex(/\d/, 'Inclua um número.').regex(/\p{L}/u, 'Inclua uma letra.'),
});
type DadosNovo = z.infer<typeof esquemaNovo>;

function FormularioUsuario({ usuario, aoSalvar, aoCancelar }: { usuario?: UsuarioLinha; aoSalvar: () => void; aoCancelar: () => void }) {
  const editando = !!usuario;
  const esquema = editando ? esquemaNovo.omit({ senha: true }) : esquemaNovo;
  const { register, handleSubmit, formState } = useForm<DadosNovo>({
    resolver: zodResolver(esquema) as never,
    defaultValues: usuario ? { nome: usuario.nome, email: usuario.email, perfil: usuario.perfil } : { perfil: 'professora' },
  });
  const [erro, setErro] = useState<string | null>(null);

  const enviar = handleSubmit(async (dados) => {
    setErro(null);
    try {
      if (usuario) await api.put(`/usuarios/${usuario.id}`, { nome: dados.nome, email: dados.email, perfil: dados.perfil });
      else await api.post('/usuarios', dados);
      aoSalvar();
    } catch (e) {
      setErro(mensagemDeErro(e));
    }
  });

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-3">
      {erro && <Aviso>{erro}</Aviso>}
      <Campo rotulo="Nome" erro={formState.errors.nome?.message} {...register('nome')} />
      <Campo rotulo="E-mail" type="email" erro={formState.errors.email?.message} {...register('email')} />
      <Selecao rotulo="Perfil" erro={formState.errors.perfil?.message} {...register('perfil')}>
        {Object.entries(PERFIS).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
      </Selecao>
      {!editando && (
        <Campo rotulo="Senha provisória" type="password" autoComplete="new-password"
          dica="A pessoa será obrigada a trocá-la no primeiro acesso." erro={formState.errors.senha?.message} {...register('senha')} />
      )}
      <div className="flex justify-end gap-2">
        <Botao type="button" variante="secundario" onClick={aoCancelar}>Cancelar</Botao>
        <Botao type="submit" carregando={formState.isSubmitting}>Salvar</Botao>
      </div>
    </form>
  );
}

function FormularioSenha({ usuario, aoSalvar, aoCancelar }: { usuario: UsuarioLinha; aoSalvar: () => void; aoCancelar: () => void }) {
  const { register, handleSubmit, formState } = useForm<{ senha: string }>({
    resolver: zodResolver(z.object({ senha: esquemaNovo.shape.senha })),
  });
  const [erro, setErro] = useState<string | null>(null);
  const enviar = handleSubmit(async ({ senha }) => {
    setErro(null);
    try { await api.post(`/usuarios/${usuario.id}/senha`, { senha }); aoSalvar(); } catch (e) { setErro(mensagemDeErro(e)); }
  });
  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-3">
      <p className="text-sm text-slate-600">As sessões de <b>{usuario.nome}</b> serão encerradas e a troca de senha será exigida no próximo acesso.</p>
      {erro && <Aviso>{erro}</Aviso>}
      <Campo rotulo="Nova senha provisória" type="password" autoComplete="new-password" erro={formState.errors.senha?.message} {...register('senha')} />
      <div className="flex justify-end gap-2">
        <Botao type="button" variante="secundario" onClick={aoCancelar}>Cancelar</Botao>
        <Botao type="submit" carregando={formState.isSubmitting}>Definir senha</Botao>
      </div>
    </form>
  );
}

export function PaginaUsuarios() {
  const cliente = useQueryClient();
  const consulta = useQuery({ queryKey: ['usuarios'], queryFn: () => api.get<{ dados: UsuarioLinha[] }>('/usuarios') });
  const [modal, setModal] = useState<{ tipo: 'novo' | 'editar' | 'senha'; usuario?: UsuarioLinha } | null>(null);
  const [excluir, setExcluir] = useState<UsuarioLinha | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  const atualizar = () => { setModal(null); void cliente.invalidateQueries({ queryKey: ['usuarios'] }); };
  const alternarAtivo = useMutation({
    mutationFn: (u: UsuarioLinha) => api.put(`/usuarios/${u.id}`, { ativo: !u.ativo }),
    onSuccess: atualizar,
    onError: (e) => setErro(mensagemDeErro(e)),
  });
  const remover = useMutation({
    mutationFn: (u: UsuarioLinha) => api.delete(`/usuarios/${u.id}`),
    onSuccess: atualizar,
    onError: (e) => setErro(mensagemDeErro(e)),
  });

  return (
    <>
      <Titulo acoes={<Botao onClick={() => setModal({ tipo: 'novo' })}>Novo usuário</Botao>}>Usuários</Titulo>
      {erro && <div className="mb-3"><Aviso>{erro}</Aviso></div>}
      <Cartao>
        {consulta.isLoading ? <Carregando /> : (
          <Tabela rotulo="Usuários" cabecalhos={['Nome', 'E-mail', 'Perfil', 'Situação', 'Último acesso', 'Ações']}>
            {consulta.data?.dados.map((u) => (
              <tr key={u.id}>
                <Td className="font-medium">{u.nome}</Td>
                <Td>{u.email}</Td>
                <Td>{PERFIS[u.perfil]}</Td>
                <Td>{u.ativo ? <Badge tom="verde">Ativo</Badge> : <Badge tom="vermelho">Inativo</Badge>}</Td>
                <Td>{dataHora(u.ultimo_login_em)}</Td>
                <Td>
                  <div className="flex flex-wrap gap-1">
                    <Botao variante="fantasma" onClick={() => setModal({ tipo: 'editar', usuario: u })}>Editar</Botao>
                    <Botao variante="fantasma" onClick={() => setModal({ tipo: 'senha', usuario: u })}>Senha</Botao>
                    <Botao variante="fantasma" onClick={() => alternarAtivo.mutate(u)}>{u.ativo ? 'Desativar' : 'Ativar'}</Botao>
                    <Botao variante="fantasma" onClick={() => setExcluir(u)}>Excluir</Botao>
                  </div>
                </Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>

      <Modal aberto={modal?.tipo === 'novo'} aoFechar={() => setModal(null)} titulo="Novo usuário">
        <FormularioUsuario aoSalvar={atualizar} aoCancelar={() => setModal(null)} />
      </Modal>
      <Modal aberto={modal?.tipo === 'editar'} aoFechar={() => setModal(null)} titulo="Editar usuário">
        {modal?.usuario && <FormularioUsuario usuario={modal.usuario} aoSalvar={atualizar} aoCancelar={() => setModal(null)} />}
      </Modal>
      <Modal aberto={modal?.tipo === 'senha'} aoFechar={() => setModal(null)} titulo="Definir senha">
        {modal?.usuario && <FormularioSenha usuario={modal.usuario} aoSalvar={atualizar} aoCancelar={() => setModal(null)} />}
      </Modal>
      <Confirmar aberto={!!excluir} aoFechar={() => setExcluir(null)} titulo="Excluir usuário"
        texto={`Excluir ${excluir?.nome ?? ''}? O acesso será removido.`} rotuloConfirmar="Excluir"
        aoConfirmar={() => excluir && remover.mutate(excluir)} />
    </>
  );
}

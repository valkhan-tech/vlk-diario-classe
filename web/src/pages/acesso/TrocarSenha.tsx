import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext';
import { Aviso, Botao, Campo, Cartao, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';

const esquema = z
  .object({
    atual: z.string().min(1, 'Informe a senha atual.'),
    nova: z.string().min(8, 'Mínimo de 8 caracteres.').regex(/\d/, 'Inclua ao menos um número.').regex(/\p{L}/u, 'Inclua ao menos uma letra.'),
    confirmacao: z.string(),
  })
  .refine((d) => d.nova === d.confirmacao, { path: ['confirmacao'], message: 'As senhas não conferem.' });
type Dados = z.infer<typeof esquema>;

export function PaginaTrocarSenha() {
  const { usuario, recarregar } = useAuth();
  const navegar = useNavigate();
  const { register, handleSubmit, formState, reset } = useForm<Dados>({ resolver: zodResolver(esquema) });
  const [erro, setErro] = useState<string | null>(null);
  const [ok, setOk] = useState(false);

  const enviar = handleSubmit(async ({ atual, nova }) => {
    setErro(null);
    try {
      await api.post('/senha/trocar', { atual, nova });
      setOk(true);
      reset();
      await recarregar();
      if (usuario?.troca_senha_obrigatoria) navegar('/', { replace: true });
    } catch (e) {
      setErro(mensagemDeErro(e));
    }
  });

  return (
    <div className="mx-auto max-w-md">
      <Titulo>Trocar senha</Titulo>
      {usuario?.troca_senha_obrigatoria && <div className="mb-3"><Aviso tipo="info">Defina uma nova senha para continuar.</Aviso></div>}
      <Cartao>
        <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
          {erro && <Aviso>{erro}</Aviso>}
          {ok && <Aviso tipo="sucesso">Senha alterada com sucesso.</Aviso>}
          <Campo rotulo="Senha atual" type="password" autoComplete="current-password" erro={formState.errors.atual?.message} {...register('atual')} />
          <Campo rotulo="Nova senha" type="password" autoComplete="new-password" erro={formState.errors.nova?.message} {...register('nova')} />
          <Campo rotulo="Repita a nova senha" type="password" autoComplete="new-password" erro={formState.errors.confirmacao?.message} {...register('confirmacao')} />
          <Botao type="submit" carregando={formState.isSubmitting}>Salvar</Botao>
        </form>
      </Cartao>
    </div>
  );
}

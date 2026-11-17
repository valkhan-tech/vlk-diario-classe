import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import { Aviso, Botao, Campo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';

export function PaginaRecuperarSenha() {
  const esquema = z.object({ email: z.string().email('Informe um e-mail válido.') });
  const { register, handleSubmit, formState } = useForm<{ email: string }>({ resolver: zodResolver(esquema) });
  const [enviado, setEnviado] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const enviar = handleSubmit(async ({ email }) => {
    setErro(null);
    try {
      await api.post('/senha/recuperar', { email });
      setEnviado(true);
    } catch (e) {
      setErro(mensagemDeErro(e));
    }
  });

  return (
    <div className="mx-auto mt-16 w-full max-w-sm px-4">
      <h1 className="mb-4 text-center text-xl font-bold">Recuperar senha</h1>
      {enviado ? (
        <Aviso tipo="sucesso">Se o e-mail estiver cadastrado, você receberá um link para definir uma nova senha.</Aviso>
      ) : (
        <form onSubmit={enviar} noValidate className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          {erro && <Aviso>{erro}</Aviso>}
          <Campo rotulo="E-mail" type="email" erro={formState.errors.email?.message} {...register('email')} />
          <Botao type="submit" carregando={formState.isSubmitting}>Enviar link</Botao>
        </form>
      )}
      <Link to="/entrar" className="mt-4 block text-center text-sm text-marca-700 underline">Voltar ao login</Link>
    </div>
  );
}

const esquemaNova = z
  .object({
    senha: z.string().min(8, 'Mínimo de 8 caracteres.').regex(/\d/, 'Inclua ao menos um número.').regex(/\p{L}/u, 'Inclua ao menos uma letra.'),
    confirmacao: z.string(),
  })
  .refine((d) => d.senha === d.confirmacao, { path: ['confirmacao'], message: 'As senhas não conferem.' });
type DadosNova = z.infer<typeof esquemaNova>;

export function PaginaRedefinirSenha() {
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const { register, handleSubmit, formState } = useForm<DadosNova>({ resolver: zodResolver(esquemaNova) });
  const [pronto, setPronto] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const enviar = handleSubmit(async ({ senha }) => {
    setErro(null);
    try {
      await api.post('/senha/redefinir', { token, senha });
      setPronto(true);
    } catch (e) {
      setErro(mensagemDeErro(e));
    }
  });

  return (
    <div className="mx-auto mt-16 w-full max-w-sm px-4">
      <h1 className="mb-4 text-center text-xl font-bold">Definir nova senha</h1>
      {pronto ? (
        <Aviso tipo="sucesso">Senha alterada. <Link to="/entrar" className="underline">Entrar</Link></Aviso>
      ) : (
        <form onSubmit={enviar} noValidate className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          {!token && <Aviso>Link inválido.</Aviso>}
          {erro && <Aviso>{erro}</Aviso>}
          <Campo rotulo="Nova senha" type="password" autoComplete="new-password" erro={formState.errors.senha?.message} {...register('senha')} />
          <Campo rotulo="Repita a nova senha" type="password" autoComplete="new-password" erro={formState.errors.confirmacao?.message} {...register('confirmacao')} />
          <Botao type="submit" disabled={!token} carregando={formState.isSubmitting}>Salvar senha</Botao>
        </form>
      )}
    </div>
  );
}

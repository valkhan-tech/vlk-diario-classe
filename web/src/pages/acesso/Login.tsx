import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext';
import { Aviso, Botao, Campo } from '../../components/ui';
import { mensagemDeErro } from '../../lib/api';

const esquema = z.object({
  email: z.string().min(1, 'Informe o e-mail.'),
  senha: z.string().min(1, 'Informe a senha.'),
});
type Dados = z.infer<typeof esquema>;

export function PaginaLogin() {
  const { usuario, entrar } = useAuth();
  const navegar = useNavigate();
  const local = useLocation() as { state?: { de?: string } };
  const [erro, setErro] = useState<string | null>(null);
  const { register, handleSubmit, formState } = useForm<Dados>({ resolver: zodResolver(esquema) });

  if (usuario) return <Navigate to={usuario.troca_senha_obrigatoria ? '/trocar-senha' : '/'} replace />;

  const enviar = handleSubmit(async (dados) => {
    setErro(null);
    try {
      const u = await entrar(dados.email, dados.senha);
      navegar(u.troca_senha_obrigatoria ? '/trocar-senha' : (local.state?.de ?? '/'), { replace: true });
    } catch (e) {
      setErro(mensagemDeErro(e));
    }
  });

  return (
    <div className="mx-auto mt-16 w-full max-w-sm px-4">
      <h1 className="mb-1 text-center text-2xl font-bold text-marca-700">🎹 Diário de Classe</h1>
      <p className="mb-6 text-center text-sm text-slate-600">Entre com seu e-mail e senha.</p>
      <form onSubmit={enviar} noValidate className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        {erro && <Aviso>{erro}</Aviso>}
        <Campo rotulo="E-mail" type="email" autoComplete="username" erro={formState.errors.email?.message} {...register('email')} />
        <Campo rotulo="Senha" type="password" autoComplete="current-password" erro={formState.errors.senha?.message} {...register('senha')} />
        <Botao type="submit" carregando={formState.isSubmitting}>Entrar</Botao>
        <Link to="/recuperar-senha" className="text-center text-sm text-marca-700 underline">Esqueci minha senha</Link>
      </form>
    </div>
  );
}

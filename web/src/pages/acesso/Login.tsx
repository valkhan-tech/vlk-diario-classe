import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext';
import { PautaFundo } from '../../components/ArteMusical';
import { Icone } from '../../components/icones';
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
    <div className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
      <div className="relative hidden flex-col justify-center gap-5 overflow-hidden p-16 text-white lg:flex" style={{ background: 'var(--sb-bg)' }}>
        <div className="flex items-center gap-3 font-titulo text-2xl font-semibold">
          <span className="grid h-11 w-11 place-items-center rounded-xl bg-white/15"><Icone nome="piano" className="h-6 w-6" /></span>Diário de Classe
        </div>
        <h2 className="max-w-md font-titulo text-5xl font-semibold leading-[1.08]">Cada aula, uma nota a mais na partitura.</h2>
        <p className="max-w-sm text-lg text-white/85">Acompanhe o progresso dos alunos, as lições aprovadas e o financeiro da turma em um só lugar.</p>
        <PautaFundo className="absolute -bottom-2 -right-10 w-[560px] opacity-20" />
      </div>

      <div className="grid place-items-center px-5 py-10">
        <div className="w-full max-w-sm">
          <div className="mb-8 flex flex-col items-center gap-3 text-center lg:hidden">
            <span className="grid h-14 w-14 place-items-center rounded-2xl text-white" style={{ background: 'var(--sb-bg)' }}><Icone nome="piano" className="h-7 w-7" /></span>
            <span className="font-titulo text-2xl font-semibold text-marca-700">Diário de Classe</span>
          </div>
          <h1 className="font-titulo text-3xl font-semibold">Entrar</h1>
          <p className="mb-6 mt-1 text-slate-500">Use seu e-mail e senha.</p>
          <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
            {erro && <Aviso>{erro}</Aviso>}
            <Campo rotulo="E-mail" type="email" autoComplete="username" inputMode="email" erro={formState.errors.email?.message} {...register('email')} />
            <Campo rotulo="Senha" type="password" autoComplete="current-password" erro={formState.errors.senha?.message} {...register('senha')} />
            <Botao type="submit" carregando={formState.isSubmitting}>Entrar</Botao>
            <Link to="/recuperar-senha" className="py-2 text-center text-sm font-medium text-marca-700 underline">Esqueci minha senha</Link>
          </form>
        </div>
      </div>
    </div>
  );
}

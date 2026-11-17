import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api, EVENTO_NAO_AUTENTICADO, EVENTO_TROCA_SENHA, ErroApi } from '../lib/api';

export type Perfil = 'admin' | 'professora' | 'responsavel';

export interface Usuario {
  id: number;
  nome: string;
  email: string;
  perfil: Perfil;
  ativo: boolean;
  troca_senha_obrigatoria: boolean;
}

interface AuthValor {
  usuario: Usuario | null;
  carregando: boolean;
  entrar: (email: string, senha: string) => Promise<Usuario>;
  sair: () => Promise<void>;
  recarregar: () => Promise<void>;
}

const AuthContext = createContext<AuthValor | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [carregando, setCarregando] = useState(true);
  const cliente = useQueryClient();

  const recarregar = useCallback(async () => {
    try {
      const r = await api.get<{ usuario: Usuario }>('/eu');
      setUsuario(r.usuario);
    } catch (e) {
      if (!(e instanceof ErroApi) || e.status !== 401) {
        // 403 de troca obrigatória ainda mostra o usuário em /eu (rota liberada), então só falhas reais chegam aqui
      }
      setUsuario(null);
    } finally {
      setCarregando(false);
    }
  }, []);

  useEffect(() => { void recarregar(); }, [recarregar]);

  useEffect(() => {
    const aoExpirar = () => { setUsuario(null); cliente.clear(); };
    const aoExigirTroca = () => { void recarregar(); };
    window.addEventListener(EVENTO_NAO_AUTENTICADO, aoExpirar);
    window.addEventListener(EVENTO_TROCA_SENHA, aoExigirTroca);
    return () => {
      window.removeEventListener(EVENTO_NAO_AUTENTICADO, aoExpirar);
      window.removeEventListener(EVENTO_TROCA_SENHA, aoExigirTroca);
    };
  }, [cliente, recarregar]);

  const entrar = useCallback(async (email: string, senha: string) => {
    const r = await api.post<{ usuario: Usuario }>('/sessao', { email, senha });
    setUsuario(r.usuario);
    return r.usuario;
  }, []);

  const sair = useCallback(async () => {
    try { await api.delete('/sessao'); } finally { setUsuario(null); cliente.clear(); }
  }, [cliente]);

  const valor = useMemo(() => ({ usuario, carregando, entrar, sair, recarregar }), [usuario, carregando, entrar, sair, recarregar]);
  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValor {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth fora do AuthProvider');
  return ctx;
}

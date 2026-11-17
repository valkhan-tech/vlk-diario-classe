import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider } from '../../auth/AuthContext';
import { PaginaLogin } from './Login';

function montar() {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <MemoryRouter>
        <AuthProvider>
          <PaginaLogin />
        </AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

afterEach(() => vi.restoreAllMocks());

describe('Login', () => {
  it('mostra erros de validação sem chamar a API de sessão', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ title: 'Autenticação necessária.' }), { status: 401 }));
    vi.stubGlobal('fetch', fetchMock);
    montar();
    await userEvent.click(await screen.findByRole('button', { name: 'Entrar' }));
    expect(await screen.findByText('Informe o e-mail.')).toBeInTheDocument();
    expect(screen.getByText('Informe a senha.')).toBeInTheDocument();
    expect(fetchMock.mock.calls.filter(([url]) => String(url).endsWith('/sessao'))).toHaveLength(0);
  });

  it('exibe a mensagem da API quando as credenciais são inválidas', async () => {
    const fetchMock = vi.fn().mockImplementation((url: string) => {
      if (String(url).endsWith('/sessao')) {
        return Promise.resolve(new Response(JSON.stringify({ title: 'E-mail ou senha inválidos.' }), { status: 401 }));
      }
      return Promise.resolve(new Response(JSON.stringify({ title: 'Autenticação necessária.' }), { status: 401 }));
    });
    vi.stubGlobal('fetch', fetchMock);
    montar();
    await userEvent.type(await screen.findByLabelText('E-mail'), 'a@b.com');
    await userEvent.type(screen.getByLabelText('Senha'), 'errada123');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('E-mail ou senha inválidos.'));
  });
});

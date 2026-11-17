// Cliente HTTP da API (mesma origem: /api/v1). Cookie de sessão HttpOnly + cabeçalho anti-CSRF.

export class ErroApi extends Error {
  constructor(
    public status: number,
    mensagem: string,
    public erros?: Record<string, string>,
    public codigo?: string,
  ) {
    super(mensagem);
  }
}

export const EVENTO_NAO_AUTENTICADO = 'ddc:nao-autenticado';
export const EVENTO_TROCA_SENHA = 'ddc:troca-senha';

type Metodo = 'GET' | 'POST' | 'PUT' | 'DELETE';

async function requisitar<T>(metodo: Metodo, caminho: string, corpo?: unknown): Promise<T> {
  const resposta = await fetch(`/api/v1${caminho}`, {
    method: metodo,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(corpo !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    body: corpo !== undefined ? JSON.stringify(corpo) : undefined,
  });

  if (resposta.status === 204) return undefined as T;

  const texto = await resposta.text();
  const json = texto ? JSON.parse(texto) : {};

  if (!resposta.ok) {
    if (resposta.status === 401 && caminho !== '/sessao') {
      window.dispatchEvent(new Event(EVENTO_NAO_AUTENTICADO));
    }
    if (resposta.status === 403 && json.codigo === 'troca_senha_obrigatoria') {
      window.dispatchEvent(new Event(EVENTO_TROCA_SENHA));
    }
    throw new ErroApi(resposta.status, json.title ?? 'Erro inesperado.', json.erros, json.codigo);
  }
  return json as T;
}

export const api = {
  get: <T>(caminho: string) => requisitar<T>('GET', caminho),
  post: <T>(caminho: string, corpo?: unknown) => requisitar<T>('POST', caminho, corpo ?? {}),
  put: <T>(caminho: string, corpo?: unknown) => requisitar<T>('PUT', caminho, corpo ?? {}),
  delete: <T = void>(caminho: string) => requisitar<T>('DELETE', caminho),
};

/** Monta querystring ignorando valores vazios. */
export function qs(params: Record<string, string | number | boolean | null | undefined>): string {
  const partes = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
  return partes.length ? `?${partes.join('&')}` : '';
}

/** Baixa um arquivo (ex.: .xlsx) mmantendo a sessão. */
export async function baixar(caminho: string, nomeSugerido: string): Promise<void> {
  const resposta = await fetch(`/api/v1${caminho}`, { credentials: 'same-origin' });
  if (!resposta.ok) throw new ErroApi(resposta.status, 'Não foi possível gerar o arquivo.');
  const blob = await resposta.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = nomeSugerido;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function mensagemDeErro(e: unknown): string {
  if (e instanceof ErroApi) {
    if (e.erros) return Object.values(e.erros).join(' ');
    return e.message;
  }
  return 'Erro inesperado.';
}

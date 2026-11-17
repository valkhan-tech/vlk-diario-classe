import { format, parseISO } from 'date-fns';

export function moeda(valor: number | string | null | undefined): string {
  const n = typeof valor === 'string' ? Number(valor) : (valor ?? 0);
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

/** Aceita 'AAAA-MM-DD' ou 'AAAA-MM-DD HH:MM:SS'. */
export function data(valor: string | null | undefined): string {
  if (!valor) return '—';
  return format(parseISO(valor.replace(' ', 'T')), 'dd/MM/yyyy');
}

export function dataHora(valor: string | null | undefined): string {
  if (!valor) return '—';
  return format(parseISO(valor.replace(' ', 'T')), 'dd/MM/yyyy HH:mm');
}

export function hoje(): string {
  return format(new Date(), 'yyyy-MM-dd');
}

export function mesAtual(): string {
  return format(new Date(), 'yyyy-MM');
}

export function percentual(valor: number | null | undefined): string {
  if (valor === null || valor === undefined) return '—';
  return `${valor.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

export const DIAS_SEMANA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

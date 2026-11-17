import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api';
import type { ItemLista, TipoLista } from './tipos';

/** Itens ativos de uma lista (para selects). */
export function useLista(tipo: TipoLista) {
  return useQuery({
    queryKey: ['listas', tipo, 'ativos'],
    queryFn: async () => (await api.get<{ dados: ItemLista[] }>(`/listas/${tipo}?ativos=1`)).dados,
    staleTime: 60_000,
  });
}

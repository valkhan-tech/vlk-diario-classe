import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import type { ItemLista, TipoLista } from '../../api/tipos';
import { Abas, Aviso, Badge, Botao, Campo, Carregando, Cartao, Confirmar, Tabela, Td, Titulo } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';

const TIPOS: { id: TipoLista; rotulo: string }[] = [
  { id: 'instrumento', rotulo: 'Instrumentos' },
  { id: 'tipo_aula', rotulo: 'Tipos de aula' },
  { id: 'forma_pagamento', rotulo: 'Formas de pagamento' },
];

function EditorLista({ tipo }: { tipo: TipoLista }) {
  const cliente = useQueryClient();
  const consulta = useQuery({
    queryKey: ['listas', tipo, 'todos'],
    queryFn: async () => (await api.get<{ dados: ItemLista[] }>(`/listas/${tipo}`)).dados,
  });
  const [novo, setNovo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [excluir, setExcluir] = useState<ItemLista | null>(null);

  const refrescar = () => void cliente.invalidateQueries({ queryKey: ['listas', tipo] });
  const falhou = (e: unknown) => setErro(mensagemDeErro(e));

  const criar = useMutation({
    mutationFn: () => api.post(`/listas/${tipo}`, { valor: novo.trim(), ordem: (consulta.data?.length ?? 0) + 1 }),
    onSuccess: () => { setNovo(''); setErro(null); refrescar(); },
    onError: falhou,
  });
  const alternar = useMutation({
    mutationFn: (i: ItemLista) => api.put(`/listas/${tipo}/${i.id}`, { ativo: !i.ativo }),
    onSuccess: () => { setErro(null); refrescar(); },
    onError: falhou,
  });
  const remover = useMutation({
    mutationFn: (i: ItemLista) => api.delete(`/listas/${tipo}/${i.id}`),
    onSuccess: () => { setErro(null); refrescar(); },
    onError: falhou,
  });

  return (
    <Cartao>
      {erro && <div className="mb-3"><Aviso>{erro}</Aviso></div>}
      <form
        className="mb-4 flex flex-wrap items-end gap-2"
        onSubmit={(e) => { e.preventDefault(); if (novo.trim()) criar.mutate(); }}
      >
        <Campo rotulo="Novo item" value={novo} onChange={(e) => setNovo(e.target.value)} className="min-w-[14rem] flex-1" maxLength={100} />
        <Botao type="submit" carregando={criar.isPending}>Adicionar</Botao>
      </form>
      {consulta.isLoading ? <Carregando /> : (
        <Tabela rotulo="Itens" cabecalhos={['Item', 'Situação', 'Ações']}>
          {consulta.data?.map((i) => (
            <tr key={i.id}>
              <Td className="font-medium">{i.valor}</Td>
              <Td>{i.ativo ? <Badge tom="verde">Ativo</Badge> : <Badge>Inativo</Badge>}</Td>
              <Td>
                <Botao variante="fantasma" onClick={() => alternar.mutate(i)}>{i.ativo ? 'Desativar' : 'Ativar'}</Botao>
                <Botao variante="fantasma" onClick={() => setExcluir(i)}>Excluir</Botao>
              </Td>
            </tr>
          ))}
        </Tabela>
      )}
      <Confirmar aberto={!!excluir} aoFechar={() => setExcluir(null)} titulo="Excluir item"
        texto={`Excluir "${excluir?.valor ?? ''}"? Itens em uso não podem ser excluídos (desative-os).`}
        rotuloConfirmar="Excluir" aoConfirmar={() => excluir && remover.mutate(excluir)} />
    </Cartao>
  );
}

export function PaginaListas() {
  return (
    <>
      <Titulo>Listas</Titulo>
      <Abas abas={TIPOS.map((t) => ({ id: t.id, rotulo: t.rotulo, conteudo: <EditorLista tipo={t.id} /> }))} />
    </>
  );
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { NIVEL, type Licao, type Metodo } from '../../api/tipos';
import { Aviso, Badge, Botao, Campo, Carregando, Cartao, Confirmar, Modal, Tabela, Td, Titulo, Vazio } from '../../components/ui';
import { api, mensagemDeErro } from '../../lib/api';
import { FormularioMetodo } from './Metodos';

export function PaginaMetodoDetalhe() {
  const { id } = useParams();
  const navegar = useNavigate();
  const cliente = useQueryClient();
  const [editando, setEditando] = useState(false);
  const [excluindo, setExcluindo] = useState(false);
  const [colando, setColando] = useState(false);
  const [texto, setTexto] = useState('');
  const [titulo, setTitulo] = useState('');
  const [editarLicao, setEditarLicao] = useState<Licao | null>(null);
  const [tituloEdicao, setTituloEdicao] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);

  const metodo = useQuery({ queryKey: ['metodo', id], queryFn: async () => (await api.get<{ dados: Metodo }>(`/metodos/${id}`)).dados });
  const licoes = useQuery({ queryKey: ['licoes', id], queryFn: async () => (await api.get<{ dados: Licao[] }>(`/metodos/${id}/licoes`)).dados });

  const refrescar = () => {
    for (const k of ['metodo', 'metodos', 'licoes']) void cliente.invalidateQueries({ queryKey: [k] });
  };
  const falhou = (e: unknown) => setErro(mensagemDeErro(e));

  const adicionar = useMutation({
    mutationFn: () => api.post(`/metodos/${id}/licoes`, { titulo: titulo.trim() }),
    onSuccess: () => { setTitulo(''); setErro(null); refrescar(); },
    onError: falhou,
  });
  const colar = useMutation({
    mutationFn: () => api.post<{ dados: { criadas: number } }>(`/metodos/${id}/licoes/lote`, { texto }),
    onSuccess: (r) => { setColando(false); setTexto(''); setErro(null); setAviso(`${r.dados.criadas} lição(ões) criada(s).`); refrescar(); },
    onError: falhou,
  });
  const reordenar = useMutation({
    mutationFn: (ids: number[]) => api.put(`/metodos/${id}/licoes/ordem`, { ids }),
    onSuccess: refrescar,
    onError: falhou,
  });
  const salvarLicao = useMutation({
    mutationFn: () => api.put(`/licoes/${editarLicao!.id}`, { titulo: tituloEdicao.trim() }),
    onSuccess: () => { setEditarLicao(null); refrescar(); },
    onError: falhou,
  });
  const removerLicao = useMutation({
    mutationFn: (l: Licao) => api.delete(`/licoes/${l.id}`),
    onSuccess: () => { setErro(null); refrescar(); },
    onError: falhou,
  });
  const alternarAtivo = useMutation({
    mutationFn: () => api.put(`/metodos/${id}`, { ativo: !metodo.data!.ativo }),
    onSuccess: refrescar,
    onError: falhou,
  });
  const excluir = useMutation({
    mutationFn: () => api.delete(`/metodos/${id}`),
    onSuccess: () => { refrescar(); navegar('/metodos', { replace: true }); },
    onError: falhou,
  });

  if (metodo.isLoading) return <Carregando />;
  const m = metodo.data;
  if (!m) return <Aviso>Método não encontrado.</Aviso>;
  const lista = licoes.data ?? [];

  const mover = (indice: number, delta: number) => {
    const ids = lista.map((l) => l.id);
    const alvo = indice + delta;
    if (alvo < 0 || alvo >= ids.length) return;
    [ids[indice], ids[alvo]] = [ids[alvo], ids[indice]];
    reordenar.mutate(ids);
  };

  return (
    <>
      <Titulo
        acoes={
          <>
            <Botao variante="secundario" onClick={() => setEditando(true)}>Editar</Botao>
            <Botao variante="secundario" onClick={() => alternarAtivo.mutate()}>{m.ativo ? 'Desativar' : 'Ativar'}</Botao>
            <Botao variante="perigo" onClick={() => setExcluindo(true)}>Excluir</Botao>
          </>
        }
      >
        {m.nome} {m.tipo === 'hinario' && <Badge tom="roxo">Hinário</Badge>}
      </Titulo>
      <p className="mb-4 text-sm text-slate-600">
        Nível: <b>{m.nivel ? NIVEL[m.nivel] : 'sem nível'}</b> · Instrumento: <b>{m.instrumento ?? 'qualquer'}</b> · {m.total_licoes} lição(ões)
      </p>
      {erro && <div className="mb-3"><Aviso>{erro}</Aviso></div>}
      {aviso && <div className="mb-3"><Aviso tipo="sucesso">{aviso}</Aviso></div>}

      <Cartao titulo="Lições (em ordem)" acoes={<Botao variante="secundario" onClick={() => setColando(true)}>Colar lista</Botao>}>
        <form className="mb-4 flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); if (titulo.trim()) adicionar.mutate(); }}>
          <Campo rotulo="Nova lição (no fim da lista)" value={titulo} onChange={(e) => setTitulo(e.target.value)} className="min-w-[14rem] flex-1" maxLength={255} />
          <Botao type="submit" carregando={adicionar.isPending}>Adicionar</Botao>
        </form>
        {licoes.isLoading ? <Carregando /> : !lista.length ? <Vazio>Nenhuma lição. Adicione uma a uma ou cole uma lista (uma por linha).</Vazio> : (
          <Tabela rotulo="Lições do método" cabecalhos={['#', 'Título', 'Ações']}>
            {lista.map((l, i) => (
              <tr key={l.id}>
                <Td className="w-12 font-mono text-slate-500">{l.ordem}</Td>
                <Td className="font-medium">{l.titulo}</Td>
                <Td>
                  <div className="flex flex-wrap gap-1">
                    <Botao variante="fantasma" aria-label={`Subir lição ${l.ordem}`} disabled={i === 0} onClick={() => mover(i, -1)}>↑</Botao>
                    <Botao variante="fantasma" aria-label={`Descer lição ${l.ordem}`} disabled={i === lista.length - 1} onClick={() => mover(i, 1)}>↓</Botao>
                    <Botao variante="fantasma" onClick={() => { setEditarLicao(l); setTituloEdicao(l.titulo); }}>Editar</Botao>
                    <Botao variante="fantasma" onClick={() => removerLicao.mutate(l)}>Excluir</Botao>
                  </div>
                </Td>
              </tr>
            ))}
          </Tabela>
        )}
      </Cartao>

      <Modal aberto={editando} aoFechar={() => setEditando(false)} titulo="Editar método">
        <FormularioMetodo metodo={m} aoCancelar={() => setEditando(false)} aoSalvar={() => { setEditando(false); refrescar(); }} />
      </Modal>
      <Modal aberto={colando} aoFechar={() => setColando(false)} titulo="Colar lista de lições">
        <p className="mb-2 text-sm text-slate-600">Uma lição por linha; linhas vazias são ignoradas. Entram no fim da sequência.</p>
        <textarea aria-label="Lista de lições" rows={10} className="mb-3 w-full rounded-lg border border-slate-300 p-2 font-mono text-sm" value={texto} onChange={(e) => setTexto(e.target.value)} />
        <div className="flex justify-end gap-2">
          <Botao variante="secundario" onClick={() => setColando(false)}>Cancelar</Botao>
          <Botao carregando={colar.isPending} onClick={() => colar.mutate()}>Criar lições</Botao>
        </div>
      </Modal>
      <Modal aberto={!!editarLicao} aoFechar={() => setEditarLicao(null)} titulo={`Editar lição ${editarLicao?.ordem ?? ''}`}>
        <Campo rotulo="Título" value={tituloEdicao} onChange={(e) => setTituloEdicao(e.target.value)} maxLength={255} />
        <div className="mt-3 flex justify-end gap-2">
          <Botao variante="secundario" onClick={() => setEditarLicao(null)}>Cancelar</Botao>
          <Botao carregando={salvarLicao.isPending} onClick={() => salvarLicao.mutate()}>Salvar</Botao>
        </div>
      </Modal>
      <Confirmar aberto={excluindo} aoFechar={() => setExcluindo(false)} titulo="Excluir método"
        texto="Excluir este método e suas lições? Bloqueado se houver alunos associados." rotuloConfirmar="Excluir" aoConfirmar={() => excluir.mutate()} />
    </>
  );
}

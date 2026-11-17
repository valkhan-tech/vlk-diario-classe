// Tipos das respostas da API (mantidos à mão; a fonte de verdade é /api/docs).

export type SituacaoAluno = 'ativo' | 'pausado' | 'encerrado';

export interface Aluno {
  id: number;
  nome: string;
  data_nascimento: string | null;
  faixa_etaria: string | null;
  instrumento_id: number | null;
  instrumento: string | null;
  contato: string | null;
  responsavel_nome: string | null;
  dia_semana: number | null;
  hora_aula: string | null;
  valor_mensalidade: number;
  dia_vencimento: number;
  data_inicio: string | null;
  meses_ativos: number | null;
  situacao: SituacaoAluno;
  observacoes: string | null;
}

export type TipoLista = 'instrumento' | 'tipo_aula' | 'forma_pagamento';

export interface ItemLista {
  id: number;
  tipo: TipoLista;
  valor: string;
  ordem: number;
  ativo: boolean;
}

export const SITUACAO_ALUNO: Record<SituacaoAluno, string> = {
  ativo: 'Ativo',
  pausado: 'Pausado',
  encerrado: 'Encerrado',
};

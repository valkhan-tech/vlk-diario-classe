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

export type Presenca = 'presente' | 'falta_aluno_justificada' | 'falta_aluno_sem_aviso' | 'falta_professora' | 'feriado_evento';

export const PRESENCA: Record<Presenca, string> = {
  presente: 'Presente',
  falta_aluno_justificada: 'Falta — aluno (justificada)',
  falta_aluno_sem_aviso: 'Falta — aluno (sem aviso)',
  falta_professora: 'Falta — professora',
  feriado_evento: 'Feriado / evento',
};

export const TOM_PRESENCA: Record<Presenca, 'verde' | 'amarelo' | 'vermelho' | 'azul' | 'neutro'> = {
  presente: 'verde',
  falta_aluno_justificada: 'amarelo',
  falta_aluno_sem_aviso: 'vermelho',
  falta_professora: 'azul',
  feriado_evento: 'neutro',
};

export interface Aula {
  id: number;
  aluno_id: number;
  aluno: string | null;
  data: string;
  presenca: Presenca;
  motivo: string | null;
  tipo_aula_id: number | null;
  tipo_aula: string | null;
  metodo_id: number | null;
  conteudo: string | null;
  hino: string | null;
  licao_casa: string | null;
  video_url: string | null;
  observacoes: string | null;
}

export interface ResumoFrequencia {
  aulas: number;
  presentes: number;
  faltas_aluno: number;
  faltas_professora: number;
  feriados: number;
  frequencia: number | null;
}

export interface ItemAgenda {
  aluno_id: number;
  aluno: string;
  hora: string | null;
  aula_id: number | null;
  presenca: Presenca | null;
}

export interface Calendario {
  mes: string;
  resumo: Omit<ResumoFrequencia, 'frequencia'>;
  dias: Record<string, { id: number; aluno_id: number; aluno: string; presenca: Presenca }[]>;
}

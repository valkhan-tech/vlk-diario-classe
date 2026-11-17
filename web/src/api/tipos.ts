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

export type EstadoCobranca = 'pendente' | 'parcial' | 'atrasado' | 'pago' | 'cancelada';

export const ESTADO_COBRANCA: Record<EstadoCobranca, string> = {
  pendente: 'Pendente',
  parcial: 'Parcial',
  atrasado: 'Atrasado',
  pago: 'Pago',
  cancelada: 'Cancelada',
};

export const TOM_ESTADO: Record<EstadoCobranca, 'amarelo' | 'azul' | 'vermelho' | 'verde' | 'neutro'> = {
  pendente: 'amarelo',
  parcial: 'azul',
  atrasado: 'vermelho',
  pago: 'verde',
  cancelada: 'neutro',
};

export interface Pagamento {
  id: number;
  cobranca_id: number;
  valor: number;
  data_pagamento: string;
  forma_pagamento_id: number | null;
  forma: string | null;
  observacoes: string | null;
}

export interface Cobranca {
  id: number;
  aluno_id: number;
  aluno: string | null;
  competencia: string;
  valor: number;
  desconto: number;
  valor_liquido: number;
  pago: number;
  saldo: number;
  excedente: number;
  vencimento: string;
  dias_atraso: number;
  situacao: 'aberta' | 'cancelada';
  estado: EstadoCobranca;
  observacoes: string | null;
  pagamentos?: Pagamento[];
}

export interface ResumoFinanceiro {
  quantidade: number;
  previsto: number;
  recebido: number;
  aberto: number;
  atrasado: number;
  excedente?: number;
  por_estado: Record<EstadoCobranca, number>;
}

export interface FichaFinanceira {
  aluno: { id: number; nome: string; valor_mensalidade: number; dia_vencimento: number; situacao: SituacaoAluno };
  cobrancas: Cobranca[];
  resumo: ResumoFinanceiro;
}

export interface DashboardFinanceiro {
  periodo: { de: string; ate: string };
  kpis: { previsto: number; recebido: number; aberto: number; atrasado: number; inadimplencia: number; cobrancas: number; por_estado: Record<EstadoCobranca, number> };
  por_mes: { competencia: string; previsto: number; recebido: number; aberto: number; atrasado: number }[];
  por_forma: { forma: string; quantidade: number; valor: number }[];
  envelhecimento: { faixa: string; quantidade: number; valor: number }[];
  ranking: { aluno_id: number; aluno: string; cobrancas: number; saldo: number; maior_atraso: number }[];
}

export type Nivel = 'iniciante' | 'basico' | 'intermediario' | 'avancado';
export const NIVEL: Record<Nivel, string> = { iniciante: 'Iniciante', basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };

export type EstagioValor = Nivel | 'apto_culto_jovens' | 'apto_culto_adulto' | 'oficializado';
export const ESTAGIO: Record<EstagioValor, string> = {
  ...NIVEL,
  apto_culto_jovens: 'Apto — Culto Jovens',
  apto_culto_adulto: 'Apto — Culto Adulto',
  oficializado: 'Oficializado',
};

export type SituacaoMetodo = 'iniciado' | 'pausado' | 'concluido';
export const SITUACAO_METODO: Record<SituacaoMetodo, string> = { iniciado: 'Iniciado', pausado: 'Pausado', concluido: 'Concluído' };

export type StatusLicao = 'pendente' | 'aprovado' | 'reprovado';
export const STATUS_LICAO: Record<StatusLicao, string> = { pendente: 'Pendente', aprovado: 'Aprovado', reprovado: 'Reprovado' };

export type StatusTecnica = 'nao_iniciado' | 'em_andamento' | 'concluido';
export const STATUS_TECNICA: Record<StatusTecnica, string> = { nao_iniciado: 'Não iniciado', em_andamento: 'Em andamento', concluido: 'Concluído' };

export interface Metodo {
  id: number;
  nome: string;
  tipo: 'metodo' | 'hinario';
  nivel: Nivel | null;
  instrumento_id: number | null;
  instrumento: string | null;
  ativo: boolean;
  observacoes: string | null;
  total_licoes: number;
}

export interface Licao {
  id: number;
  metodo_id: number;
  ordem: number;
  titulo: string;
  observacoes: string | null;
}

export interface LicaoDoAluno extends Licao {
  status: StatusLicao;
  desde: string | null;
  tentativas: number;
  tempo_dias: number | null;
  atual: boolean;
}

export interface LicaoAtual {
  id: number;
  ordem: number;
  titulo: string;
  status: StatusLicao;
  desde: string | null;
  tentativas: number;
}

export interface MetodoDoAluno {
  metodo_id: number;
  metodo: string;
  tipo: 'metodo' | 'hinario';
  nivel: Nivel | null;
  situacao: SituacaoMetodo;
  data_inicio: string;
  data_situacao: string;
  total_licoes: number;
  aprovadas: number;
  media: number | null;
  concluir_sugerido: boolean;
  licao_atual: LicaoAtual | null;
}

export interface FichaProgresso {
  aluno: { id: number; nome: string; instrumento: string | null; situacao: SituacaoAluno };
  estagio: { valor: EstagioValor | null; origem: 'manual' | 'calculado' | null; rotulo: string | null; calculado: EstagioValor | null; manual: EstagioValor | null };
  progresso_geral: number | null;
  aptidao: { jovens: boolean; adulto: boolean; oficializacao: boolean };
  tecnica: { m1: StatusTecnica; m2: StatusTecnica; ped: StatusTecnica };
  metodos: MetodoDoAluno[];
  frequencia: ResumoFrequencia;
}

export interface ResultadoAvaliacao {
  avaliacao_id: number;
  licao_id: number;
  status: StatusLicao;
  data_tentativa: string;
  fora_de_ordem: boolean;
  licao_atual: LicaoAtual | null;
  concluir_sugerido: boolean;
}

export interface ItemAvaliacao {
  licao_id: number;
  status: StatusLicao;
}

export interface PainelInicio {
  kpis: { alunos_ativos: number; aulas_hoje: number; aulas_hoje_lancadas: number; cobrancas_atrasadas: number; valor_atrasado: number; aptos_culto: number };
  agenda: { data: string; itens: ItemAgenda[] };
  por_estagio: { estagio: string; rotulo: string; quantidade: number }[];
}

export interface EntradaLog {
  id: number;
  quando: string;
  usuario_id: number | null;
  usuario: string | null;
  perfil: string | null;
  operacao: 'INSERT' | 'UPDATE' | 'DELETE';
  tabela: string;
  registro_id: number;
  origem: string;
  controller: string | null;
  fingerprint: string;
  ip: string | null;
  user_agent?: string | null;
  registro?: Record<string, unknown>;
}

export interface ResultadoVerificacao {
  integro: boolean;
  fingerprint_registrado: string;
  fingerprint_calculado: string;
  ultima_do_registro: boolean;
  confere_com_banco: boolean | null;
  fingerprint_atual: string | null;
  registro_existe: boolean | null;
}

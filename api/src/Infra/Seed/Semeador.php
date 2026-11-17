<?php

declare(strict_types=1);

namespace App\Infra\Seed;

use App\Aplicacao\Acesso\UsuarioServico;
use App\Aplicacao\Aulas\AulaServico;
use App\Aplicacao\Cadastros\AlunoServico;
use App\Aplicacao\Cadastros\ListaServico;
use App\Aplicacao\Financeiro\CobrancaServico;
use App\Aplicacao\Financeiro\GeracaoCobrancas;
use App\Aplicacao\Financeiro\PagamentoServico;
use App\Aplicacao\Progresso\MetodoServico;
use App\Aplicacao\Progresso\ProgressoServico;
use App\Infra\Auditoria\ContextoOperacao;
use App\Infra\Banco\Conexao;
use App\Infra\Relogio;
use App\Infra\Repositorios\UsuarioRepositorio;
use DateTimeImmutable;

/**
 * Dados de demonstração para desenvolvimento e testes iniciais.
 * Grava SEMPRE pelos serviços da aplicação (as regras valem e o sys_log é gerado, com fingerprint).
 * Determinístico: mesma semente → mesmos dados (as datas são relativas a "hoje").
 */
final class Semeador
{
    public const SEMENTE = 2026;

    public const USUARIOS = [
        ['Administrador', 'admin@ddc.local', 'Admin@123', 'admin'],
        ['Helena Martins', 'professora@ddc.local', 'Professora@123', 'professora'],
        ['Responsável Teste', 'responsavel@ddc.local', 'Responsavel@123', 'responsavel'],
    ];

    private const NOMES = [
        'Ana Beatriz Lima', 'Bruno Carvalho', 'Carla Mendes', 'Davi Oliveira', 'Eduarda Souza', 'Felipe Ramos', 'Gabriela Alves',
        'Heitor Costa', 'Isabela Nunes', 'João Pedro Rocha', 'Karina Moreira', 'Lucas Teixeira', 'Mariana Duarte', 'Nicolas Barros',
        'Olívia Pinto', 'Pedro Henrique Dias',
    ];

    /** [nome, nível, instrumento (ou null), tipo, quantidade de lições] */
    private const METODOS = [
        ['Apostila própria — Teoria', 'iniciante', null, 'metodo', 15],
        ['Faber Vol.1', 'basico', 'Piano', 'metodo', 30],
        ['Flauta doce — Básico', 'basico', 'Flauta', 'metodo', 15],
        ['Faber Vol.2', 'intermediario', 'Piano', 'metodo', 30],
        ['Hanon', 'intermediario', 'Piano', 'metodo', 20],
        ['Czerny Op.599', 'avancado', 'Piano', 'metodo', 25],
        ['Hinário de Jovens', null, null, 'hinario', 50],
        ['Hinário Oficial', null, null, 'hinario', 430],
    ];

    private const TEMAS = ['Postura e respiração', 'Escala de Dó maior', 'Leitura rítmica', 'Pausas e compassos', 'Intervalos', 'Acordes de três notas', 'Dedilhado', 'Legato e staccato', 'Dinâmica', 'Arpejos'];

    public function __construct(
        private readonly Conexao $db,
        private readonly ContextoOperacao $contexto,
        private readonly Relogio $relogio,
        private readonly UsuarioServico $usuarios,
        private readonly UsuarioRepositorio $usuariosRepo,
        private readonly ListaServico $listas,
        private readonly AlunoServico $alunos,
        private readonly AulaServico $aulas,
        private readonly MetodoServico $metodos,
        private readonly ProgressoServico $progresso,
        private readonly GeracaoCobrancas $geracao,
        private readonly CobrancaServico $cobrancas,
        private readonly PagamentoServico $pagamentos,
    ) {
    }

    /** @return array<string,int> contagens do que foi criado */
    public function executar(bool $limpar = false): array
    {
        $existentes = (int) $this->db->escalar('SELECT COUNT(*) FROM usuarios');
        if ($existentes > 0 && !$limpar) {
            throw new \RuntimeException('O banco já tem dados. Rode com --limpar para apagar tudo (exceto sys_migracao) e semear de novo.');
        }
        if ($limpar) {
            $this->limpar();
        }
        mt_srand(self::SEMENTE);

        $admin = $this->criarUsuarios();
        $this->contexto->usuarioId = (int) $admin['id'];
        $this->contexto->perfil = 'admin';
        $this->contexto->origem = 'seed: api/bin/seed.php';
        $this->contexto->controller = 'seed';

        $hoje = $this->relogio->hoje();
        $ids = $this->criarListas();
        $alunos = $this->criarAlunos($ids, $hoje);
        $metodos = $this->criarMetodos($ids);

        // cobranças antigas enquanto todos estavam ativos; depois pausa/encerra e gera as mais recentes
        for ($i = 5; $i >= 3; $i--) {
            $this->geracao->gerar($hoje->modify("-{$i} months"));
        }
        $this->ajustarSituacoes($alunos);
        for ($i = 2; $i >= 0; $i--) {
            $this->geracao->gerar($hoje->modify("-{$i} months"));
        }
        $this->registrarPagamentos($ids, $hoje);
        $this->ajustesFinanceiros($hoje);

        $this->associarMetodos($alunos, $metodos, $hoje);
        $this->lancarAulasEAvaliacoes($alunos, $ids, $hoje);
        $this->progressoManual($alunos);

        return $this->contagens();
    }

    /** Apaga os dados (menos sys_migracao) para semear de novo. */
    private function limpar(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->consultar('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $l) {
            if ($l['t'] !== 'sys_migracao') {
                $pdo->exec('TRUNCATE TABLE `' . $l['t'] . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array<string,mixed> o administrador */
    private function criarUsuarios(): array
    {
        $admin = [];
        $this->contexto->definirSistema('seed: api/bin/seed.php');
        foreach (self::USUARIOS as [$nome, $email, $senha, $perfil]) {
            $u = $this->usuarios->criar(['nome' => $nome, 'email' => $email, 'senha' => $senha, 'perfil' => $perfil]);
            $this->usuariosRepo->atualizar((int) $u['id'], ['troca_senha_obrigatoria' => 0]); // senhas de teste já são as definitivas
            if ($perfil === 'admin') {
                $admin = $u;
            }
        }
        // usuário inativo, para testar a tela de usuários
        $inativo = $this->usuarios->criar(['nome' => 'Usuário Inativo', 'email' => 'inativo@ddc.local', 'senha' => 'Inativo@123', 'perfil' => 'professora']);
        $this->usuariosRepo->atualizar((int) $inativo['id'], ['ativo' => 0, 'troca_senha_obrigatoria' => 0]);
        return $admin;
    }

    /** @return array<string,array<string,int>> tipo => [valor => id] */
    private function criarListas(): array
    {
        $dados = [
            'instrumento' => ['Piano', 'Órgão', 'Flauta', 'Piano e Órgão', 'Piano e Flauta'],
            'tipo_aula' => ['Teórica', 'Prática', 'Mista', 'Musicalização', 'Avaliação'],
            'forma_pagamento' => ['Pix', 'Dinheiro', 'Transferência', 'Outro'],
        ];
        $ids = [];
        foreach ($dados as $tipo => $valores) {
            foreach ($valores as $i => $valor) {
                $ids[$tipo][$valor] = (int) $this->listas->criar($tipo, ['valor' => $valor, 'ordem' => $i + 1])['id'];
            }
        }
        return $ids;
    }

    /**
     * @param array<string,array<string,int>> $ids
     * @return list<array<string,mixed>>
     */
    private function criarAlunos(array $ids, DateTimeImmutable $hoje): array
    {
        // idade em anos para cobrir todas as faixas etárias
        $idades = [6, 10, 15, 16, 25, 40, 14, 31, 12, 19, 7, 45, 17, 28, 9, 13];
        $instrumentos = ['Piano', 'Órgão', 'Flauta', 'Piano', 'Piano e Órgão', 'Piano', 'Flauta', 'Órgão', 'Piano', 'Piano e Flauta', 'Piano', 'Órgão', 'Piano', 'Piano', 'Flauta', 'Piano'];
        $vencimentos = [5, 10, 15, 28, 30, 31, 10, 5, 15, 28, 10, 30, 31, 5, 15, 10];
        $mensalidades = [120, 150, 180, 200, 150, 180, 120, 200, 150, 180, 120, 200, 150, 180, 120, 150];
        $alunos = [];
        foreach (self::NOMES as $i => $nome) {
            $idade = $idades[$i];
            $nasc = $hoje->modify("-{$idade} years")->modify('-' . mt_rand(10, 300) . ' days');
            $inicio = $hoje->modify('-' . mt_rand(60, 420) . ' days');
            $primeiro = explode(' ', $nome)[0];
            $dados = [
                'nome' => $nome,
                'data_nascimento' => $nasc->format('Y-m-d'),
                'instrumento_id' => $ids['instrumento'][$instrumentos[$i]],
                'contato' => sprintf('(85) 9%04d-%04d', 8000 + $i, 1000 + $i * 7),
                'dia_semana' => [2, 3, 4, 6, 6, 5, 2, 3, 4, 6, 5, 2, 3, 4, 6, 5][$i],
                'hora_aula' => sprintf('%02d:%s', 8 + ($i % 8), $i % 2 === 0 ? '00' : '30'),
                'valor_mensalidade' => $mensalidades[$i],
                'dia_vencimento' => $vencimentos[$i],
                'data_inicio' => $inicio->format('Y-m-d'),
                'observacoes' => $i % 3 === 0 ? "{$primeiro} é muito dedicado(a). Meta: culto." : null,
            ];
            if ($idade < 18) {
                $dados['responsavel_nome'] = 'Responsável de ' . $primeiro;
            }
            $alunos[] = $this->alunos->criar($dados);
        }
        return $alunos;
    }

    /** @param list<array<string,mixed>> $alunos */
    private function ajustarSituacoes(array $alunos): void
    {
        // dois pausados e dois encerrados (os últimos da lista)
        foreach ([[12, 'pausado'], [13, 'pausado'], [14, 'encerrado'], [15, 'encerrado']] as [$i, $situacao]) {
            $this->alunos->atualizar((int) $alunos[$i]['id'], ['situacao' => $situacao]);
        }
    }

    /**
     * @param array<string,array<string,int>> $ids
     * @return array<string,array{id:int,licoes:list<array<string,mixed>>,nivel:?string,tipo:string}>
     */
    private function criarMetodos(array $ids): array
    {
        $saida = [];
        foreach (self::METODOS as [$nome, $nivel, $instrumento, $tipo, $quantidade]) {
            $m = $this->metodos->criar(array_filter([
                'nome' => $nome,
                'tipo' => $tipo,
                'nivel' => $nivel,
                'instrumento_id' => $instrumento !== null ? $ids['instrumento'][$instrumento] : null,
            ], static fn (mixed $v): bool => $v !== null));
            $linhas = [];
            for ($n = 1; $n <= $quantidade; $n++) {
                $linhas[] = $tipo === 'hinario' ? "Hino {$n}" : "Lição {$n} — " . self::TEMAS[($n - 1) % count(self::TEMAS)];
            }
            $this->metodos->colarLista((int) $m['id'], implode("\n", $linhas));
            $saida[$nome] = ['id' => (int) $m['id'], 'licoes' => $this->metodos->licoes((int) $m['id']), 'nivel' => $nivel, 'tipo' => $tipo];
        }
        return $saida;
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param array<string,array{id:int,licoes:list<array<string,mixed>>,nivel:?string,tipo:string}> $metodos
     */
    private function associarMetodos(array $alunos, array $metodos, DateTimeImmutable $hoje): void
    {
        $nomes = array_keys($metodos);
        foreach ($alunos as $i => $a) {
            $instrumento = (string) $a['instrumento'];
            $ehFlauta = str_contains($instrumento, 'Flauta');
            $ehTeclado = str_contains($instrumento, 'Piano') || str_contains($instrumento, 'Órgão');
            $doInstrumento = [];
            foreach (self::METODOS as [$nomeMetodo, , $instrumentoMetodo, $tipoMetodo]) {
                $serve = $tipoMetodo === 'metodo'
                    && ($instrumentoMetodo === null || ($instrumentoMetodo === 'Flauta' && $ehFlauta) || ($instrumentoMetodo === 'Piano' && $ehTeclado));
                // os alunos 1 e 2 recebem a apostila mais adiante (concluída / pausada)
                if ($serve && !($i <= 2 && $nomeMetodo === 'Apostila própria — Teoria' && $i >= 1)) {
                    $doInstrumento[] = $nomeMetodo;
                }
            }
            // 1 a 2 métodos (a posição no catálogo varia com o aluno) + hinário para alguns
            $escolhidos = [$doInstrumento[$i % count($doInstrumento)]];
            if ($i % 3 === 0) {
                $escolhidos[] = $doInstrumento[($i + 2) % count($doInstrumento)];
            }
            if ($i % 2 === 0) {
                $escolhidos[] = 'Hinário de Jovens';
            }
            if ($i % 5 === 0) {
                $escolhidos[] = 'Hinário Oficial';
            }
            foreach (array_unique($escolhidos) as $nome) {
                $inicio = $hoje->modify('-' . mt_rand(40, 200) . ' days')->format('Y-m-d');
                $this->progresso->associar((int) $a['id'], $metodos[$nome]['id'], $inicio);
            }
        }

        // Ana (aluno 0): concluiu a apostila de teoria; uma situação "pausado" para variedade
        $apostila = $metodos['Apostila própria — Teoria'];
        $alunoConcluido = (int) $alunos[1]['id'];
        $this->progresso->associar($alunoConcluido, $apostila['id'], $hoje->modify('-150 days')->format('Y-m-d'));
        $itens = [];
        foreach ($apostila['licoes'] as $n => $l) {
            $itens[] = ['licao_id' => (int) $l['id'], 'status' => 'aprovado', 'data_tentativa' => $hoje->modify('-' . (140 - $n * 6) . ' days')->format('Y-m-d')];
        }
        $this->progresso->avaliar($alunoConcluido, $itens);
        $this->progresso->alterarSituacao($alunoConcluido, $apostila['id'], 'concluido', $hoje->modify('-45 days')->format('Y-m-d'));

        $this->progresso->associar((int) $alunos[2]['id'], $apostila['id'], $hoje->modify('-100 days')->format('Y-m-d'));
        $this->progresso->alterarSituacao((int) $alunos[2]['id'], $apostila['id'], 'pausado', $hoje->modify('-30 days')->format('Y-m-d'));

        // hinário: avanço em lote, em datas passadas
        $jovens = $metodos['Hinário de Jovens']['licoes'];
        foreach ([0 => 20, 2 => 35, 4 => 12] as $i => $quantos) {
            $itensHinos = [];
            foreach (array_slice($jovens, 0, $quantos) as $n => $l) {
                $itensHinos[] = ['licao_id' => (int) $l['id'], 'status' => 'aprovado', 'data_tentativa' => $hoje->modify('-' . (120 - $n * 2) . ' days')->format('Y-m-d')];
            }
            $this->progresso->avaliar((int) $alunos[$i]['id'], $itensHinos);
        }
        $oficial = $metodos['Hinário Oficial']['licoes'];
        $itensOficial = [];
        foreach (array_slice($oficial, 0, 60) as $n => $l) {
            $itensOficial[] = ['licao_id' => (int) $l['id'], 'status' => 'aprovado', 'data_tentativa' => $hoje->modify('-' . (110 - (int) ($n * 1.5)) . ' days')->format('Y-m-d')];
        }
        $this->progresso->avaliar((int) $alunos[0]['id'], $itensOficial);
    }

    /**
     * Aulas semanais das últimas 13 semanas; em parte delas avalia a lição atual (com aula_id).
     *
     * @param list<array<string,mixed>> $alunos
     * @param array<string,array<string,int>> $ids
     */
    private function lancarAulasEAvaliacoes(array $alunos, array $ids, DateTimeImmutable $hoje): void
    {
        $tipos = array_values($ids['tipo_aula']);
        foreach ($alunos as $i => $a) {
            $situacaoFinal = $i >= 14 ? 'encerrado' : ($i >= 12 ? 'pausado' : 'ativo');
            $semanasAtras = $situacaoFinal === 'ativo' ? 12 : 6; // pausados/encerrados têm menos histórico recente
            $inicio = new DateTimeImmutable((string) $a['data_inicio']);
            for ($w = $semanasAtras; $w >= 0; $w--) {
                $dia = $hoje->modify("-{$w} weeks");
                $ajuste = ((int) $dia->format('w') - (int) $a['dia_semana'] + 7) % 7;
                $dia = $dia->modify("-{$ajuste} days");
                if ($dia > $hoje || $dia < $inicio) {
                    continue;
                }
                $sorteio = mt_rand(1, 100);
                $presenca = match (true) {
                    $sorteio <= 80 => 'presente',
                    $sorteio <= 88 => 'falta_aluno_justificada',
                    $sorteio <= 93 => 'falta_aluno_sem_aviso',
                    $sorteio <= 97 => 'falta_professora',
                    default => 'feriado_evento',
                };
                $dados = ['aluno_id' => (int) $a['id'], 'data' => $dia->format('Y-m-d'), 'presenca' => $presenca];
                if ($presenca === 'presente') {
                    $dados += [
                        'tipo_aula_id' => $tipos[mt_rand(0, count($tipos) - 1)],
                        'conteudo' => self::TEMAS[mt_rand(0, count(self::TEMAS) - 1)],
                        'licao_casa' => 'Estudar ' . mt_rand(10, 30) . ' minutos por dia',
                        'hino' => mt_rand(1, 100) <= 30 ? 'Hino ' . mt_rand(1, 50) : null,
                    ];
                } elseif ($presenca === 'falta_aluno_justificada') {
                    $dados['motivo'] = ['Gripe', 'Viagem', 'Prova na escola', 'Compromisso familiar'][mt_rand(0, 3)];
                } elseif ($presenca === 'falta_professora') {
                    $dados['motivo'] = 'Aula cancelada pela professora';
                }
                $aula = $this->aulas->criar(array_filter($dados, static fn (mixed $v): bool => $v !== null));

                if ($presenca === 'presente' && mt_rand(1, 100) <= 35 && $situacaoFinal === 'ativo') {
                    $this->avaliarLicaoAtual((int) $a['id'], $dia->format('Y-m-d'), (int) $aula['id']);
                }
            }
        }
    }

    private function avaliarLicaoAtual(int $alunoId, string $data, int $aulaId): void
    {
        $ficha = $this->progresso->ficha($alunoId);
        $candidatos = array_values(array_filter(
            $ficha['metodos'],
            static fn (array $m): bool => $m['situacao'] === 'iniciado' && $m['licao_atual'] !== null && $m['tipo'] === 'metodo'
        ));
        if ($candidatos === []) {
            return;
        }
        $metodo = $candidatos[mt_rand(0, count($candidatos) - 1)];
        $licaoId = $metodo['licao_atual']['id'];
        $status = mt_rand(1, 100) <= 70 ? 'aprovado' : 'reprovado';
        $this->progresso->avaliar($alunoId, [['licao_id' => $licaoId, 'status' => $status]], $data, $aulaId);
        if ($status === 'reprovado' && mt_rand(1, 100) <= 60) {
            // nova tentativa uma semana depois (se já passou): a regra mantém a data inicial da sequência
            $retentativa = (new DateTimeImmutable($data))->modify('+7 days');
            if ($retentativa <= $this->relogio->hoje()) {
                $this->progresso->avaliar($alunoId, [['licao_id' => $licaoId, 'status' => 'reprovado']], $retentativa->format('Y-m-d'));
            }
        }
    }

    /** @param array<string,array<string,int>> $ids */
    private function registrarPagamentos(array $ids, DateTimeImmutable $hoje): void
    {
        $formas = array_values($ids['forma_pagamento']);
        $todas = $this->cobrancas->listar([])['dados'];
        foreach ($todas as $c) {
            $venc = new DateTimeImmutable((string) $c['vencimento']);
            $mesesAtras = (int) $hoje->format('Y') * 12 + (int) $hoje->format('n') - ((int) $venc->format('Y') * 12 + (int) $venc->format('n'));
            $sorteio = mt_rand(1, 100);
            $forma = $formas[mt_rand(0, count($formas) - 1)];
            $liquido = (float) $c['valor_liquido'];
            if ($mesesAtras >= 1) {
                $paga = $sorteio <= 82;
                $parcial = !$paga && $sorteio <= 90;
            } else {
                $paga = $venc <= $hoje ? $sorteio <= 50 : $sorteio <= 15; // mês corrente: parte já pagou
                $parcial = !$paga && ($venc <= $hoje ? $sorteio <= 62 : $sorteio <= 30); // parcial antes do vencimento = "Parcial"
            }
            if (!$paga && !$parcial) {
                continue;
            }
            $quando = $venc->modify(((int) mt_rand(-3, 6)) . ' days');
            if ($quando > $hoje) {
                $quando = $hoje;
            }
            $this->pagamentos->registrar((int) $c['id'], [
                'valor' => $paga ? $liquido : round($liquido / 2, 2),
                'data_pagamento' => $quando->format('Y-m-d'),
                'forma_pagamento_id' => $forma,
                'observacoes' => null,
            ]);
        }
    }

    /** Uma cobrança parcial (a vencer), uma com desconto e uma cancelada, para exercitar as telas. */
    private function ajustesFinanceiros(DateTimeImmutable $hoje): void
    {
        $lista = $this->cobrancas->listar([])['dados'];
        $pendentes = array_values(array_filter($lista, static fn (array $c): bool => $c['estado'] === 'pendente'));
        $atrasadas = array_values(array_filter($lista, static fn (array $c): bool => $c['estado'] === 'atrasado'));

        if (isset($pendentes[0])) {
            $this->pagamentos->registrar((int) $pendentes[0]['id'], [
                'valor' => round($pendentes[0]['valor_liquido'] / 2, 2),
                'data_pagamento' => $hoje->format('Y-m-d'),
                'observacoes' => 'Pagou metade; resto no vencimento',
            ]);
        }
        if (isset($atrasadas[0])) {
            $this->cobrancas->atualizar((int) $atrasadas[0]['id'], ['desconto' => 20, 'observacoes' => 'Desconto de irmãos']);
        }
        if (isset($atrasadas[1])) {
            $this->cobrancas->atualizar((int) $atrasadas[1]['id'], ['situacao' => 'cancelada', 'observacoes' => 'Aluno ausente no mês (acordo)']);
        }
    }

    /** @param list<array<string,mixed>> $alunos */
    private function progressoManual(array $alunos): void
    {
        $this->progresso->atualizarProgresso((int) $alunos[0]['id'], ['apto_jovens' => true, 'tecnica_m1' => 'concluido', 'tecnica_m2' => 'em_andamento']);
        $this->progresso->atualizarProgresso((int) $alunos[3]['id'], ['apto_jovens' => true, 'apto_adulto' => true, 'estagio_manual' => 'apto_culto_jovens']);
        $this->progresso->atualizarProgresso((int) $alunos[5]['id'], ['apto_adulto' => true, 'apto_oficializacao' => true, 'estagio_manual' => 'oficializado', 'tecnica_ped' => 'concluido']);
        $this->progresso->atualizarProgresso((int) $alunos[7]['id'], ['tecnica_m1' => 'em_andamento']);
    }

    /** @return array<string,int> */
    private function contagens(): array
    {
        $tabelas = ['usuarios', 'listas', 'alunos', 'aulas', 'cobrancas', 'pagamentos', 'metodos', 'licoes', 'aluno_metodo', 'avaliacoes_licao', 'progresso_aluno', 'sys_log'];
        $saida = [];
        foreach ($tabelas as $t) {
            $saida[$t] = (int) $this->db->escalar("SELECT COUNT(*) FROM `{$t}`" . ($t === 'sys_log' ? '' : ' WHERE sys_del IS NULL'));
        }
        return $saida;
    }
}

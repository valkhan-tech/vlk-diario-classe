<?php

declare(strict_types=1);

namespace App\Aplicacao\Progresso;

use App\Aplicacao\Aulas\AulaServico;
use App\Dominio\Progresso\HistoricoLicao;
use App\Dominio\Progresso\StatusLicao;
use App\Http\Erros\Conflito;
use App\Http\Erros\ErroValidacao;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Banco\Conexao;
use App\Infra\Relogio;
use App\Infra\Repositorios\AlunoMetodoRepositorio;
use App\Infra\Repositorios\AlunoRepositorio;
use App\Infra\Repositorios\AvaliacaoLicaoRepositorio;
use App\Infra\Repositorios\LicaoRepositorio;
use App\Infra\Repositorios\MetodoRepositorio;
use App\Infra\Repositorios\ProgressoAlunoRepositorio;
use DateTimeImmutable;

/** Progresso do aluno: métodos associados, avaliação de lições, estágio, aptidão e ficha de progresso. */
final class ProgressoServico
{
    public function __construct(
        private readonly AlunoRepositorio $alunos,
        private readonly MetodoRepositorio $metodos,
        private readonly LicaoRepositorio $licoes,
        private readonly AlunoMetodoRepositorio $vinculos,
        private readonly AvaliacaoLicaoRepositorio $avaliacoes,
        private readonly ProgressoAlunoRepositorio $progresso,
        private readonly ProgressoCalculadora $calculadora,
        private readonly AulaServico $aulas,
        private readonly Relogio $relogio,
        private readonly Conexao $db,
    ) {
    }

    /**
     * Ficha de progresso: estágio (calculado × manual), progresso geral, aptidão, métodos e frequência.
     *
     * @return array<string,mixed>
     */
    public function ficha(int $alunoId): array
    {
        $aluno = $this->alunos->buscarComInstrumento($alunoId) ?? throw new NaoEncontrado('Aluno não encontrado.');
        $calc = $this->calculadora->paraAlunos($alunoId)[$alunoId];

        $metodos = $calc['metodos'];
        foreach ($metodos as &$m) {
            $m['licao_atual'] = $this->licaoAtual($alunoId, (int) $m['metodo_id']);
        }
        unset($m);

        return [
            'aluno' => ['id' => (int) $aluno['id'], 'nome' => $aluno['nome'], 'instrumento' => $aluno['instrumento'], 'situacao' => $aluno['situacao']],
            'estagio' => $calc['estagio'] + ['calculado' => $calc['estagio_calculado'], 'manual' => $calc['manual']['estagio_manual']],
            'progresso_geral' => $calc['progresso_geral'],
            'aptidao' => [
                'jovens' => $calc['manual']['apto_jovens'],
                'adulto' => $calc['manual']['apto_adulto'],
                'oficializacao' => $calc['manual']['apto_oficializacao'],
            ],
            'tecnica' => [
                'm1' => $calc['manual']['tecnica_m1'],
                'm2' => $calc['manual']['tecnica_m2'],
                'ped' => $calc['manual']['tecnica_ped'],
            ],
            'metodos' => $metodos,
            'frequencia' => $this->aulas->resumoDoAluno($alunoId),
        ];
    }

    /**
     * Lições do método com o resumo de tentativas deste aluno (status vigente, data do status, tentativas, tempo).
     *
     * @return array{metodo:array<string,mixed>,vinculo:?array<string,mixed>,licoes:list<array<string,mixed>>}
     */
    public function licoesDoMetodo(int $alunoId, int $metodoId): array
    {
        $this->alunos->obter($alunoId);
        $metodo = $this->metodos->buscarDetalhado($metodoId) ?? throw new NaoEncontrado('Método não encontrado.');
        $vinculo = $this->vinculos->buscarVinculo($alunoId, $metodoId);

        $porLicao = $this->tentativasPorLicao($alunoId, $metodoId);
        $hoje = $this->relogio->hoje();
        $atualMarcada = false;
        $saida = [];
        foreach ($this->licoes->doMetodo($metodoId) as $l) {
            $r = HistoricoLicao::resumir($porLicao[(int) $l['id']] ?? [], $hoje);
            $ehAtual = !$atualMarcada && $r['status'] !== StatusLicao::Aprovado->value;
            $atualMarcada = $atualMarcada || $ehAtual;
            $saida[] = MetodoServico::apresentarLicao($l) + [
                'status' => $r['status'],
                'desde' => $r['desde'],
                'tentativas' => $r['tentativas'],
                'tempo_dias' => $r['tempoDias'],
                'atual' => $ehAtual,
            ];
        }
        return [
            'metodo' => MetodoServico::apresentar($metodo),
            'vinculo' => $vinculo === null ? null : [
                'situacao' => $vinculo['situacao'],
                'data_inicio' => $vinculo['data_inicio'],
                'data_situacao' => $vinculo['data_situacao'],
            ],
            'licoes' => $saida,
        ];
    }

    /** @return array<string,mixed> */
    public function associar(int $alunoId, int $metodoId, ?string $dataInicio = null): array
    {
        $this->alunos->obter($alunoId);
        $this->metodos->obter($metodoId);
        if ($this->vinculos->buscarVinculo($alunoId, $metodoId) !== null) {
            throw new Conflito('Este método já está associado ao aluno.');
        }
        $data = $dataInicio ?? $this->relogio->hoje()->format('Y-m-d');
        $this->vinculos->inserir([
            'aluno_id' => $alunoId,
            'metodo_id' => $metodoId,
            'data_inicio' => $data,
            'situacao' => 'iniciado',
            'data_situacao' => $data,
        ]);
        return $this->ficha($alunoId);
    }

    /**
     * Muda a situação (Iniciado / Pausado / Concluído); a data da situação é a informada ou hoje.
     *
     * @return array<string,mixed>
     */
    public function alterarSituacao(int $alunoId, int $metodoId, string $situacao, ?string $data = null): array
    {
        $vinculo = $this->vinculos->buscarVinculo($alunoId, $metodoId) ?? throw new NaoEncontrado('O método não está associado ao aluno.');
        $this->vinculos->atualizar((int) $vinculo['id'], [
            'situacao' => $situacao,
            'data_situacao' => $data ?? $this->relogio->hoje()->format('Y-m-d'),
        ]);
        return $this->ficha($alunoId);
    }

    /** @return array<string,mixed> */
    public function desassociar(int $alunoId, int $metodoId): array
    {
        $vinculo = $this->vinculos->buscarVinculo($alunoId, $metodoId) ?? throw new NaoEncontrado('O método não está associado ao aluno.');
        $this->vinculos->excluir((int) $vinculo['id']);
        return $this->ficha($alunoId);
    }

    /**
     * Registra avaliações de lição (cada uma é um novo registro com a data real da tentativa).
     * A sequência é só orientação: lição fora de ordem é aceita e sinalizada. Método não associado é associado (Iniciado).
     *
     * @param list<array{licao_id:int,status:string,data_tentativa?:?string,observacoes?:?string}> $itens
     * @return list<array<string,mixed>>
     */
    public function avaliar(int $alunoId, array $itens, ?string $dataPadrao = null, ?int $aulaId = null): array
    {
        $this->alunos->obter($alunoId);
        if ($itens === []) {
            throw new ErroValidacao(['avaliacoes' => 'Informe ao menos uma lição.']);
        }
        $padrao = $dataPadrao ?? $this->relogio->hoje()->format('Y-m-d');

        return $this->db->transacao(function () use ($alunoId, $itens, $padrao, $aulaId): array {
            $saida = [];
            foreach ($itens as $item) {
                $licao = $this->licoes->buscar((int) $item['licao_id']) ?? throw new ErroValidacao(['licao_id' => 'Lição não encontrada.']);
                $metodoId = (int) $licao['metodo_id'];
                $data = $item['data_tentativa'] ?? $padrao;

                if ($this->vinculos->buscarVinculo($alunoId, $metodoId) === null) {
                    $this->vinculos->inserir([
                        'aluno_id' => $alunoId, 'metodo_id' => $metodoId,
                        'data_inicio' => $data, 'situacao' => 'iniciado', 'data_situacao' => $data,
                    ]);
                }

                $atualAntes = $this->licaoAtual($alunoId, $metodoId);
                $foraDeOrdem = $atualAntes !== null && (int) $licao['ordem'] > $atualAntes['ordem'];

                $criada = $this->avaliacoes->inserir([
                    'aluno_id' => $alunoId,
                    'licao_id' => (int) $licao['id'],
                    'status' => $item['status'],
                    'data_tentativa' => $data,
                    'aula_id' => $aulaId,
                    'observacoes' => ($item['observacoes'] ?? '') !== '' ? $item['observacoes'] : null,
                ]);

                $depois = $this->licaoAtual($alunoId, $metodoId);
                $vinculo = $this->vinculos->buscarVinculo($alunoId, $metodoId);
                $saida[] = [
                    'avaliacao_id' => (int) $criada['id'],
                    'licao_id' => (int) $licao['id'],
                    'status' => $item['status'],
                    'data_tentativa' => $data,
                    'fora_de_ordem' => $foraDeOrdem,
                    'licao_atual' => $depois,
                    'concluir_sugerido' => $depois === null && ($vinculo['situacao'] ?? '') === 'iniciado',
                ];
            }
            return $saida;
        });
    }

    /** @return list<array<string,mixed>> tentativas de uma lição com a data real de cada uma */
    public function historicoDaLicao(int $alunoId, int $licaoId): array
    {
        return array_map(static fn (array $e): array => [
            'id' => (int) $e['id'],
            'status' => $e['status'],
            'data_tentativa' => $e['data_tentativa'],
            'aula_id' => $e['aula_id'] !== null ? (int) $e['aula_id'] : null,
            'observacoes' => $e['observacoes'],
        ], $this->avaliacoes->daLicao($alunoId, $licaoId));
    }

    /** Correção de lançamento errado: exclusão lógica (fica no log). */
    public function excluirAvaliacao(int $id): void
    {
        $this->avaliacoes->excluir($id);
    }

    /**
     * Atualiza o que a professora define: estágio manual (null = voltar ao calculado), aptidão e técnica.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed> ficha atualizada
     */
    public function atualizarProgresso(int $alunoId, array $dados): array
    {
        $this->alunos->obter($alunoId);
        foreach (['apto_jovens', 'apto_adulto', 'apto_oficializacao'] as $c) {
            if (array_key_exists($c, $dados)) {
                $dados[$c] = $dados[$c] ? 1 : 0;
            }
        }
        $existente = $this->progresso->doAluno($alunoId);
        if ($existente === null) {
            $this->progresso->inserir(['aluno_id' => $alunoId] + $dados);
        } elseif ($dados !== []) {
            $this->progresso->atualizar((int) $existente['id'], $dados);
        }
        return $this->ficha($alunoId);
    }

    /**
     * Estágio efetivo e aptidão de todos os alunos (para o painel inicial e filtros).
     *
     * @return array<int,array{estagio:array<string,mixed>,progresso_geral:?float,aptidao:bool}>
     */
    public function resumoDosAlunos(): array
    {
        $saida = [];
        foreach ($this->calculadora->paraAlunos() as $alunoId => $r) {
            $saida[$alunoId] = [
                'estagio' => $r['estagio'],
                'progresso_geral' => $r['progresso_geral'],
                'aptidao' => $r['manual']['apto_jovens'] || $r['manual']['apto_adulto'] || $r['manual']['apto_oficializacao'],
            ];
        }
        return $saida;
    }

    /* ---------- internos ---------- */

    /**
     * Primeira lição (por ordem) que ainda não está aprovada; null se todas estão (ou não há lições).
     *
     * @return array{id:int,ordem:int,titulo:string,status:string,desde:?string,tentativas:int}|null
     */
    private function licaoAtual(int $alunoId, int $metodoId): ?array
    {
        $porLicao = $this->tentativasPorLicao($alunoId, $metodoId);
        $hoje = $this->relogio->hoje();
        foreach ($this->licoes->doMetodo($metodoId) as $l) {
            $r = HistoricoLicao::resumir($porLicao[(int) $l['id']] ?? [], $hoje);
            if ($r['status'] !== StatusLicao::Aprovado->value) {
                return ['id' => (int) $l['id'], 'ordem' => (int) $l['ordem'], 'titulo' => (string) $l['titulo'], 'status' => $r['status'], 'desde' => $r['desde'], 'tentativas' => $r['tentativas']];
            }
        }
        return null;
    }

    /** @return array<int,list<array{status:string,data:string}>> */
    private function tentativasPorLicao(int $alunoId, int $metodoId): array
    {
        $saida = [];
        foreach ($this->avaliacoes->vigentes($alunoId) as $e) {
            if ((int) $e['metodo_id'] === $metodoId) {
                $saida[(int) $e['licao_id']][] = ['status' => (string) $e['status'], 'data' => (string) $e['data_tentativa']];
            }
        }
        return $saida;
    }

    public function hoje(): DateTimeImmutable
    {
        return $this->relogio->hoje();
    }
}

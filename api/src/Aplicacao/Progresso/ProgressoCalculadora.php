<?php

declare(strict_types=1);

namespace App\Aplicacao\Progresso;

use App\Dominio\Progresso\EstagioCalculado;
use App\Dominio\Progresso\HistoricoLicao;
use App\Dominio\Progresso\ProgressoGeral;
use App\Dominio\Progresso\StatusLicao;
use App\Infra\Relogio;
use App\Infra\Repositorios\AlunoMetodoRepositorio;
use App\Infra\Repositorios\AvaliacaoLicaoRepositorio;
use App\Infra\Repositorios\ProgressoAlunoRepositorio;

/**
 * Calcula, em lote, o progresso derivado dos alunos: média por método, progresso geral,
 * estágio calculado e estágio efetivo (manual > calculado), a partir de métodos, lições e avaliações.
 */
final class ProgressoCalculadora
{
    public function __construct(
        private readonly AlunoMetodoRepositorio $vinculos,
        private readonly AvaliacaoLicaoRepositorio $avaliacoes,
        private readonly ProgressoAlunoRepositorio $progresso,
        private readonly Relogio $relogio,
    ) {
    }

    /**
     * @return array<int,array{
     *   metodos:list<array<string,mixed>>,
     *   progresso_geral:?float,
     *   estagio_calculado:?string,
     *   estagio:array{valor:?string,origem:?string,rotulo:?string},
     *   manual:array<string,mixed>
     * }> indexado por aluno_id (para o aluno informado, ou todos os que têm métodos/progresso)
     */
    public function paraAlunos(?int $alunoId = null): array
    {
        $aprovadas = $this->aprovadasPorAlunoEMetodo($alunoId);

        $porAluno = [];
        $vinculos = $alunoId !== null ? $this->vinculos->doAluno($alunoId) : $this->vinculos->todos();
        foreach ($vinculos as $v) {
            $a = (int) $v['aluno_id'];
            $m = (int) $v['metodo_id'];
            $total = (int) $v['total_licoes'];
            $feitas = $aprovadas[$a][$m] ?? 0;
            $porAluno[$a]['metodos'][] = [
                'metodo_id' => $m,
                'metodo' => $v['metodo'],
                'tipo' => $v['tipo'],
                'nivel' => $v['nivel'],
                'situacao' => $v['situacao'],
                'data_inicio' => $v['data_inicio'],
                'data_situacao' => $v['data_situacao'],
                'total_licoes' => $total,
                'aprovadas' => $feitas,
                'media' => ProgressoGeral::mediaDoMetodo($total, $feitas, (string) $v['situacao']),
                'concluir_sugerido' => $v['situacao'] === 'iniciado' && $total > 0 && $feitas >= $total,
            ];
        }

        $manuais = [];
        foreach ($alunoId !== null ? array_filter([$this->progresso->doAluno($alunoId)]) : $this->progresso->todos() as $p) {
            $manuais[(int) $p['aluno_id']] = $p;
        }

        $resultado = [];
        foreach (array_unique([...array_keys($porAluno), ...array_keys($manuais), ...($alunoId !== null ? [$alunoId] : [])]) as $a) {
            $metodos = $porAluno[$a]['metodos'] ?? [];
            $calculado = EstagioCalculado::de(array_map(
                static fn (array $m): array => ['nivel' => $m['nivel'], 'situacao' => $m['situacao']],
                $metodos
            ));
            $manual = $manuais[$a] ?? null;
            $resultado[$a] = [
                'metodos' => $metodos,
                'progresso_geral' => ProgressoGeral::calcular(array_map(
                    static fn (array $m): array => ['totalLicoes' => $m['total_licoes'], 'aprovadas' => $m['aprovadas'], 'situacao' => $m['situacao']],
                    $metodos
                )),
                'estagio_calculado' => $calculado,
                'estagio' => EstagioCalculado::efetivo($manual['estagio_manual'] ?? null, $calculado),
                'manual' => self::apresentarManual($manual),
            ];
        }
        return $resultado;
    }

    /**
     * @param array<string,mixed>|null $p
     * @return array<string,mixed>
     */
    public static function apresentarManual(?array $p): array
    {
        return [
            'estagio_manual' => $p['estagio_manual'] ?? null,
            'apto_jovens' => (bool) ($p['apto_jovens'] ?? false),
            'apto_adulto' => (bool) ($p['apto_adulto'] ?? false),
            'apto_oficializacao' => (bool) ($p['apto_oficializacao'] ?? false),
            'tecnica_m1' => $p['tecnica_m1'] ?? 'nao_iniciado',
            'tecnica_m2' => $p['tecnica_m2'] ?? 'nao_iniciado',
            'tecnica_ped' => $p['tecnica_ped'] ?? 'nao_iniciado',
        ];
    }

    /**
     * Quantidade de lições com status vigente "aprovado", por aluno e método.
     *
     * @return array<int,array<int,int>>
     */
    private function aprovadasPorAlunoEMetodo(?int $alunoId): array
    {
        $porLicao = [];
        foreach ($this->avaliacoes->vigentes($alunoId) as $e) {
            $porLicao[(int) $e['aluno_id']][(int) $e['licao_id']]['metodo'] = (int) $e['metodo_id'];
            $porLicao[(int) $e['aluno_id']][(int) $e['licao_id']]['tentativas'][] = ['status' => (string) $e['status'], 'data' => (string) $e['data_tentativa']];
        }
        $hoje = $this->relogio->hoje();
        $contagem = [];
        foreach ($porLicao as $a => $licoes) {
            foreach ($licoes as $info) {
                if (HistoricoLicao::resumir($info['tentativas'], $hoje)['status'] === StatusLicao::Aprovado->value) {
                    $contagem[$a][$info['metodo']] = ($contagem[$a][$info['metodo']] ?? 0) + 1;
                }
            }
        }
        return $contagem;
    }
}

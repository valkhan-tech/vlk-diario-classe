<?php

declare(strict_types=1);

namespace App\Aplicacao\Financeiro;

use App\Dominio\Financeiro\Vencimento;
use App\Infra\Repositorios\AlunoRepositorio;
use App\Infra\Repositorios\CobrancaRepositorio;
use DateTimeImmutable;

/**
 * Gera a cobrança mensal: um registro por aluno ATIVO e competência, sem proporcional.
 * Idempotente: rodar de novo não duplica (cobranças existentes são contadas, não recriadas).
 */
final class GeracaoCobrancas
{
    public function __construct(
        private readonly AlunoRepositorio $alunos,
        private readonly CobrancaRepositorio $cobrancas,
    ) {
    }

    /** @return array{competencia:string,criadas:int,existentes:int,ignorados:int} */
    public function gerar(DateTimeImmutable $competencia): array
    {
        $primeiro = $competencia->modify('first day of this month')->setTime(0, 0);
        $ultimo = $primeiro->modify('last day of this month');
        $chave = $primeiro->format('Y-m-d');
        $criadas = $existentes = $ignorados = 0;

        foreach ($this->alunos->ativos() as $aluno) {
            $valor = (float) $aluno['valor_mensalidade'];
            $inicio = $aluno['data_inicio'] !== null ? new DateTimeImmutable((string) $aluno['data_inicio']) : null;

            // não cobra mensalidade zerada nem competência anterior ao início das aulas
            if ($valor <= 0 || ($inicio !== null && $inicio > $ultimo)) {
                $ignorados++;
                continue;
            }
            if ($this->cobrancas->existe((int) $aluno['id'], $chave)) {
                $existentes++;
                continue;
            }
            $vencimento = Vencimento::para((int) $primeiro->format('Y'), (int) $primeiro->format('n'), (int) $aluno['dia_vencimento']);
            $this->cobrancas->inserir([
                'aluno_id' => (int) $aluno['id'],
                'competencia' => $chave,
                'valor' => number_format($valor, 2, '.', ''),
                'desconto' => '0.00',
                'vencimento' => $vencimento->format('Y-m-d'),
                'situacao' => 'aberta',
            ]);
            $criadas++;
        }
        return ['competencia' => $primeiro->format('Y-m'), 'criadas' => $criadas, 'existentes' => $existentes, 'ignorados' => $ignorados];
    }
}

<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

use DateTimeImmutable;

/**
 * Resume as tentativas de uma lição para um aluno.
 *
 * - status vigente = o do último registro (sem registros → pendente);
 * - data do status = data do PRIMEIRO registro da sequência atual de mesmo status
 *   (reprovações seguidas mantêm a data inicial);
 * - tentativas = tamanho dessa sequência;
 * - tempo na lição (informativo) = dias entre a primeira tentativa e a aprovação (ou hoje, se ainda não aprovada).
 */
final class HistoricoLicao
{
    /**
     * @param list<array{status:string,data:string}> $avaliacoes em ordem cronológica (data, id)
     * @return array{status:string,desde:?string,tentativas:int,primeira:?string,tempoDias:?int}
     */
    public static function resumir(array $avaliacoes, ?DateTimeImmutable $hoje = null): array
    {
        if ($avaliacoes === []) {
            return ['status' => StatusLicao::Pendente->value, 'desde' => null, 'tentativas' => 0, 'primeira' => null, 'tempoDias' => null];
        }

        $ultimo = $avaliacoes[array_key_last($avaliacoes)];
        $inicioSequencia = count($avaliacoes) - 1;
        while ($inicioSequencia > 0 && $avaliacoes[$inicioSequencia - 1]['status'] === $ultimo['status']) {
            $inicioSequencia--;
        }

        $primeira = $avaliacoes[0]['data'];
        $fim = $ultimo['status'] === StatusLicao::Aprovado->value
            ? new DateTimeImmutable($avaliacoes[$inicioSequencia]['data'])
            : $hoje;

        return [
            'status' => $ultimo['status'],
            'desde' => $avaliacoes[$inicioSequencia]['data'],
            'tentativas' => count($avaliacoes) - $inicioSequencia,
            'primeira' => $primeira,
            'tempoDias' => $fim !== null ? (int) (new DateTimeImmutable($primeira))->diff($fim)->days : null,
        ];
    }
}

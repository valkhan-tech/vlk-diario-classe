<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

final class EstagioCalculado
{
    /**
     * Maior nível entre os métodos Iniciados ou Concluídos do aluno (métodos sem nível não entram).
     *
     * @param list<array{nivel:?string,situacao:string}> $metodos
     */
    public static function de(array $metodos): ?string
    {
        $maior = null;
        foreach ($metodos as $m) {
            if ($m['nivel'] === null || $m['situacao'] === SituacaoMetodo::Pausado->value) {
                continue;
            }
            $nivel = Nivel::from($m['nivel']);
            if ($maior === null || $nivel->ordem() > $maior->ordem()) {
                $maior = $nivel;
            }
        }
        return $maior?->value;
    }

    /**
     * Efetivo = manual se preenchido, senão o calculado.
     *
     * @return array{valor:?string,origem:?string,rotulo:?string}
     */
    public static function efetivo(?string $manual, ?string $calculado): array
    {
        $valor = $manual ?? $calculado;
        return [
            'valor' => $valor,
            'origem' => $manual !== null ? 'manual' : ($calculado !== null ? 'calculado' : null),
            'rotulo' => $valor !== null ? Estagio::from($valor)->rotulo() : null,
        ];
    }
}

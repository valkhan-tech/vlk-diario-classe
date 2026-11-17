<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Erros\ErroValidacao;
use Respect\Validation\Validatable;

/** Valida um array de entrada contra regras Respect\Validation e devolve só os campos validados. */
final class Validador
{
    /**
     * @param array<string,mixed> $dados
     * @param array<string,array{0:Validatable,1:string}> $regras campo => [regra, mensagem]
     * @param bool $parcial se true, campos ausentes são ignorados (atualização parcial)
     * @return array<string,mixed>
     */
    public static function validar(array $dados, array $regras, bool $parcial = false): array
    {
        $erros = [];
        $limpo = [];
        foreach ($regras as $campo => [$regra, $mensagem]) {
            // campo ausente: ignora em atualização parcial e quando a regra aceita ausência (opcional)
            if (!array_key_exists($campo, $dados) && ($parcial || $regra->validate(null))) {
                continue;
            }
            $valor = $dados[$campo] ?? null;
            if (!$regra->validate($valor)) {
                $erros[$campo] = $mensagem;
                continue;
            }
            $limpo[$campo] = is_string($valor) ? trim($valor) : $valor;
        }
        if ($erros !== []) {
            throw new ErroValidacao($erros);
        }
        return $limpo;
    }

    /**
     * @param mixed $corpo
     * @return array<string,mixed>
     */
    public static function corpo(mixed $corpo): array
    {
        return is_array($corpo) ? $corpo : [];
    }
}

<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

/**
 * Estágio efetivo do aluno. O calculado só produz os quatro primeiros (níveis dos métodos);
 * os de culto e a oficialização vêm da sobrescrita da professora.
 */
enum Estagio: string
{
    case Iniciante = 'iniciante';
    case Basico = 'basico';
    case Intermediario = 'intermediario';
    case Avancado = 'avancado';
    case AptoCultoJovens = 'apto_culto_jovens';
    case AptoCultoAdulto = 'apto_culto_adulto';
    case Oficializado = 'oficializado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $e): string => $e->value, self::cases());
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Iniciante => 'Iniciante',
            self::Basico => 'Básico',
            self::Intermediario => 'Intermediário',
            self::Avancado => 'Avançado',
            self::AptoCultoJovens => 'Apto — Culto Jovens',
            self::AptoCultoAdulto => 'Apto — Culto Adulto',
            self::Oficializado => 'Oficializado',
        };
    }
}

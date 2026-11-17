<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

/** Nível do método; dá o nome do estágio calculado do aluno. A ordem define o "maior" nível. */
enum Nivel: string
{
    case Iniciante = 'iniciante';
    case Basico = 'basico';
    case Intermediario = 'intermediario';
    case Avancado = 'avancado';

    public function ordem(): int
    {
        return match ($this) {
            self::Iniciante => 1,
            self::Basico => 2,
            self::Intermediario => 3,
            self::Avancado => 4,
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $n): string => $n->value, self::cases());
    }
}

<?php

declare(strict_types=1);

namespace App\Dominio\Aulas;

enum Presenca: string
{
    case Presente = 'presente';
    case FaltaAlunoJustificada = 'falta_aluno_justificada';
    case FaltaAlunoSemAviso = 'falta_aluno_sem_aviso';
    case FaltaProfessora = 'falta_professora';
    case FeriadoEvento = 'feriado_evento';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }

    public function ehFaltaDoAluno(): bool
    {
        return $this === self::FaltaAlunoJustificada || $this === self::FaltaAlunoSemAviso;
    }

    /** Entra no cálculo de frequência? (faltas da professora e feriados/eventos ficam de fora) */
    public function contaNaFrequencia(): bool
    {
        return $this === self::Presente || $this->ehFaltaDoAluno();
    }
}

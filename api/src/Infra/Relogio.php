<?php

declare(strict_types=1);

namespace App\Infra;

use DateTimeImmutable;
use DateTimeZone;

/** Fonte única de "agora" (hora de Brasília); substituível nos testes. */
class Relogio
{
    private DateTimeZone $fuso;

    public function __construct(string $fuso = 'America/Sao_Paulo', private readonly ?string $fixo = null)
    {
        $this->fuso = new DateTimeZone($fuso);
    }

    public function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->fixo ?? 'now', $this->fuso);
    }

    public function hoje(): DateTimeImmutable
    {
        return $this->agora()->setTime(0, 0, 0);
    }
}

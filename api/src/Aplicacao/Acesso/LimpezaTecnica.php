<?php

declare(strict_types=1);

namespace App\Aplicacao\Acesso;

use App\Infra\Repositorios\RedefinicaoSenhaRepositorio;
use App\Infra\Repositorios\SessaoRepositorio;
use App\Infra\Repositorios\TentativaLoginRepositorio;

/** Exclusão física dos registros técnicos vencidos (sessões, recuperações de senha, tentativas de login). */
final class LimpezaTecnica
{
    public const DIAS_TENTATIVAS = 30;

    public function __construct(
        private readonly SessaoRepositorio $sessoes,
        private readonly RedefinicaoSenhaRepositorio $redefinicoes,
        private readonly TentativaLoginRepositorio $tentativas,
    ) {
    }

    /** @return array{sessoes:int,redefinicoes:int,tentativas:int} */
    public function executar(): array
    {
        return [
            'sessoes' => $this->sessoes->limparExpiradas(),
            'redefinicoes' => $this->redefinicoes->limparVencidas(),
            'tentativas' => $this->tentativas->limparAntigas(self::DIAS_TENTATIVAS),
        ];
    }
}

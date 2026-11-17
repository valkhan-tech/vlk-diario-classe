<?php

declare(strict_types=1);

namespace Tests\Unit\Infra;

use App\Infra\Auditoria\Mascara;
use PHPUnit\Framework\TestCase;

final class MascaraTest extends TestCase
{
    public function test_mascara_segredos_e_remove_colunas_derivadas(): void
    {
        $registro = ['id' => 1, 'email' => 'a@b.c', 'senha_hash' => '$2y$10$abc', 'token_hash' => 'deadbeef', 'sys_ativo' => 1];

        $this->assertSame(
            ['id' => 1, 'email' => 'a@b.c', 'senha_hash' => '***', 'token_hash' => '***'],
            Mascara::aplicar($registro)
        );
    }

    public function test_nao_cria_colunas_ausentes(): void
    {
        $this->assertSame(['id' => 1], Mascara::aplicar(['id' => 1]));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Infra;

use App\Infra\Auditoria\Fingerprint;
use PHPUnit\Framework\TestCase;

final class FingerprintTest extends TestCase
{
    public function test_ordem_das_chaves_nao_altera_o_fingerprint(): void
    {
        $a = ['id' => 1, 'nome' => 'Ana', 'aninhado' => ['b' => 2, 'a' => 1]];
        $b = ['aninhado' => ['a' => 1, 'b' => 2], 'nome' => 'Ana', 'id' => 1];

        $this->assertSame(Fingerprint::calcular($a), Fingerprint::calcular($b));
    }

    public function test_mudar_um_valor_muda_o_fingerprint(): void
    {
        $this->assertNotSame(
            Fingerprint::calcular(['nome' => 'Ana']),
            Fingerprint::calcular(['nome' => 'Anna'])
        );
    }

    public function test_null_e_string_vazia_sao_diferentes(): void
    {
        $this->assertNotSame(
            Fingerprint::calcular(['obs' => null]),
            Fingerprint::calcular(['obs' => ''])
        );
    }

    public function test_inteiro_e_string_equivalente_geram_o_mesmo_fingerprint(): void
    {
        $this->assertSame(
            Fingerprint::calcular(['id' => 7, 'ativo' => true]),
            Fingerprint::calcular(['id' => '7', 'ativo' => '1'])
        );
    }

    public function test_e_sha256_em_hexadecimal(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', Fingerprint::calcular(['a' => 'ç']));
    }
}

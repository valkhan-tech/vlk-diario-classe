<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Financeiro\EstadoCobranca;
use App\Dominio\Financeiro\Vencimento;
use DateTimeImmutable as D;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FinanceiroTest extends TestCase
{
    /** @return array<string,array{string,int,int,int,string,string}> situacao, valor, desconto, pago, vencimento, esperado (hoje = 2026-11-17) */
    public static function estados(): array
    {
        return [
            'pendente antes do vencimento' => ['aberta', 15000, 0, 0, '2026-11-20', 'pendente'],
            'pendente no dia do vencimento' => ['aberta', 15000, 0, 0, '2026-11-17', 'pendente'],
            'atrasado: não confirmado após o vencimento' => ['aberta', 15000, 0, 0, '2026-11-16', 'atrasado'],
            'parcial antes do vencimento' => ['aberta', 15000, 0, 5000, '2026-11-20', 'parcial'],
            'parcial vencida é atrasado' => ['aberta', 15000, 0, 5000, '2026-11-10', 'atrasado'],
            'pago total' => ['aberta', 15000, 0, 15000, '2026-11-10', 'pago'],
            'pago com desconto' => ['aberta', 15000, 2000, 13000, '2026-11-10', 'pago'],
            'desconto total quita' => ['aberta', 15000, 15000, 0, '2026-11-10', 'pago'],
            'cancelada vence sobre tudo' => ['cancelada', 15000, 0, 0, '2026-01-01', 'cancelada'],
        ];
    }

    #[DataProvider('estados')]
    public function test_estado_da_cobranca(string $situacao, int $valor, int $desconto, int $pago, string $venc, string $esperado): void
    {
        $this->assertSame($esperado, EstadoCobranca::calcular($situacao, $valor, $desconto, $pago, new D($venc), new D('2026-11-17 15:30')));
    }

    /** @return array<string,array{int,int,int,string}> */
    public static function vencimentos(): array
    {
        return [
            'dia 10' => [2026, 11, 10, '2026-11-10'],
            '31 em fevereiro comum' => [2026, 2, 31, '2026-02-28'],
            '31 em fevereiro bissexto' => [2028, 2, 31, '2028-02-29'],
            '30 em abril' => [2026, 4, 30, '2026-04-30'],
            '31 em abril cai no dia 30' => [2026, 4, 31, '2026-04-30'],
            '29 em fevereiro comum' => [2026, 2, 29, '2026-02-28'],
            '31 em janeiro' => [2026, 1, 31, '2026-01-31'],
        ];
    }

    #[DataProvider('vencimentos')]
    public function test_vencimento_fixo_com_ajuste_em_mes_curto(int $ano, int $mes, int $dia, string $esperado): void
    {
        $this->assertSame($esperado, Vencimento::para($ano, $mes, $dia)->format('Y-m-d'));
    }
}

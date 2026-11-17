<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Alunos\Idade;
use App\Dominio\Financeiro\Dinheiro;
use DateTimeImmutable as D;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdadeTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function faixas(): array
    {
        return [
            '7 anos' => ['2019-06-01', 'Musicalização (0-7)'],
            '8 anos' => ['2018-06-01', 'Infantil (8-12)'],
            '12 anos' => ['2014-06-01', 'Infantil (8-12)'],
            '13 anos' => ['2013-06-01', 'Adolescente (13-17)'],
            '17 anos' => ['2009-06-01', 'Adolescente (13-17)'],
            '18 anos' => ['2008-06-01', 'Adulto (18+)'],
            'aniversário amanhã' => ['2008-06-02', 'Adolescente (13-17)'],
        ];
    }

    #[DataProvider('faixas')]
    public function test_faixa_etaria(string $nascimento, string $esperada): void
    {
        $this->assertSame($esperada, Idade::faixaEtaria(new D($nascimento), new D('2026-06-01')));
    }

    public function test_meses_ativos_conta_meses_completos_e_nao_fica_negativo(): void
    {
        $this->assertSame(0, Idade::mesesAtivos(new D('2026-05-15'), new D('2026-06-01')));
        $this->assertSame(1, Idade::mesesAtivos(new D('2026-05-01'), new D('2026-06-01')));
        $this->assertSame(14, Idade::mesesAtivos(new D('2025-04-10'), new D('2026-06-20')));
        $this->assertSame(0, Idade::mesesAtivos(new D('2027-01-01'), new D('2026-06-01')));
    }

    public function test_dinheiro_converte_sem_erro_de_ponto_flutuante(): void
    {
        $this->assertSame(15000, Dinheiro::centavos('150.00'));
        $this->assertSame(1999, Dinheiro::centavos(19.99));
        $this->assertSame(1, Dinheiro::centavos('0.01'));
        $this->assertSame('150.00', Dinheiro::decimal(15000));
        $this->assertSame('0.07', Dinheiro::decimal(7));
    }
}

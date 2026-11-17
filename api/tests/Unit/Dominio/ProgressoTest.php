<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Progresso\EstagioCalculado;
use App\Dominio\Progresso\HistoricoLicao;
use App\Dominio\Progresso\ProgressoGeral;
use DateTimeImmutable as D;
use PHPUnit\Framework\TestCase;

final class ProgressoTest extends TestCase
{
    public function test_exemplo_da_professora_progresso_geral_de_11_virgula_6(): void
    {
        $metodos = [
            ['totalLicoes' => 20, 'aprovadas' => 10, 'situacao' => 'iniciado'],   // 50%
            ['totalLicoes' => 480, 'aprovadas' => 48, 'situacao' => 'iniciado'],   // 10% (hinário)
        ];

        // (20×50% + 480×10%) ÷ 500 = 11,6%
        $this->assertSame(11.6, ProgressoGeral::calcular($metodos));
        $this->assertSame(50.0, ProgressoGeral::mediaDoMetodo(20, 10, 'iniciado'));
        $this->assertSame(10.0, ProgressoGeral::mediaDoMetodo(480, 48, 'iniciado'));
    }

    public function test_metodo_pausado_e_metodo_sem_licoes_ficam_de_fora(): void
    {
        $metodos = [
            ['totalLicoes' => 10, 'aprovadas' => 5, 'situacao' => 'iniciado'],
            ['totalLicoes' => 100, 'aprovadas' => 0, 'situacao' => 'pausado'],
            ['totalLicoes' => 0, 'aprovadas' => 0, 'situacao' => 'iniciado'],
        ];

        $this->assertSame(50.0, ProgressoGeral::calcular($metodos));
        $this->assertNull(ProgressoGeral::calcular([['totalLicoes' => 10, 'aprovadas' => 5, 'situacao' => 'pausado']]));
        $this->assertNull(ProgressoGeral::calcular([]));
        $this->assertNull(ProgressoGeral::mediaDoMetodo(0, 0, 'iniciado'));
    }

    public function test_metodo_concluido_vale_100_por_cento(): void
    {
        $this->assertSame(100.0, ProgressoGeral::mediaDoMetodo(20, 15, 'concluido'));
        $this->assertSame(100.0, ProgressoGeral::calcular([['totalLicoes' => 20, 'aprovadas' => 15, 'situacao' => 'concluido']]));
    }

    public function test_estagio_calculado_e_o_maior_nivel_entre_iniciados_e_concluidos(): void
    {
        $metodos = [
            ['nivel' => 'basico', 'situacao' => 'concluido'],
            ['nivel' => 'intermediario', 'situacao' => 'iniciado'],
            ['nivel' => 'avancado', 'situacao' => 'pausado'],   // pausado não conta
            ['nivel' => null, 'situacao' => 'iniciado'],         // sem nível não conta
        ];

        $this->assertSame('intermediario', EstagioCalculado::de($metodos));
        $this->assertNull(EstagioCalculado::de([]));
        $this->assertNull(EstagioCalculado::de([['nivel' => 'avancado', 'situacao' => 'pausado']]));
    }

    public function test_estagio_efetivo_prefere_a_sobrescrita_da_professora(): void
    {
        $this->assertSame(
            ['valor' => 'apto_culto_jovens', 'origem' => 'manual', 'rotulo' => 'Apto — Culto Jovens'],
            EstagioCalculado::efetivo('apto_culto_jovens', 'basico')
        );
        $this->assertSame(['valor' => 'basico', 'origem' => 'calculado', 'rotulo' => 'Básico'], EstagioCalculado::efetivo(null, 'basico'));
        $this->assertSame(['valor' => null, 'origem' => null, 'rotulo' => null], EstagioCalculado::efetivo(null, null));
    }

    public function test_reprovacoes_seguidas_mantem_a_data_inicial_e_aprovacao_mede_28_dias(): void
    {
        $reprovadas = [['status' => 'reprovado', 'data' => '2026-03-10'], ['status' => 'reprovado', 'data' => '2026-03-24']];

        $r = HistoricoLicao::resumir($reprovadas, new D('2026-04-01'));
        $this->assertSame('reprovado', $r['status']);
        $this->assertSame('2026-03-10', $r['desde'], 'a data do status continua a da primeira reprovação');
        $this->assertSame(2, $r['tentativas']);
        $this->assertSame(22, $r['tempoDias'], 'ainda não aprovada: conta até hoje (informativo)');

        $aprovada = HistoricoLicao::resumir([...$reprovadas, ['status' => 'aprovado', 'data' => '2026-04-07']]);
        $this->assertSame('aprovado', $aprovada['status']);
        $this->assertSame('2026-04-07', $aprovada['desde']);
        $this->assertSame(1, $aprovada['tentativas']);
        $this->assertSame(28, $aprovada['tempoDias'], '07/04 − 10/03 = 28 dias');
    }

    public function test_sem_registros_a_licao_esta_pendente(): void
    {
        $this->assertSame(
            ['status' => 'pendente', 'desde' => null, 'tentativas' => 0, 'primeira' => null, 'tempoDias' => null],
            HistoricoLicao::resumir([])
        );
    }

    public function test_sequencia_interrompida_recomeca_a_contagem(): void
    {
        $r = HistoricoLicao::resumir([
            ['status' => 'reprovado', 'data' => '2026-03-01'],
            ['status' => 'aprovado', 'data' => '2026-03-08'],
            ['status' => 'reprovado', 'data' => '2026-04-01'],
        ]);

        $this->assertSame('reprovado', $r['status']);
        $this->assertSame('2026-04-01', $r['desde']);
        $this->assertSame(1, $r['tentativas']);
    }
}

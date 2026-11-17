<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Aulas\Frequencia;
use App\Dominio\Aulas\Presenca;
use PHPUnit\Framework\TestCase;

final class FrequenciaTest extends TestCase
{
    public function test_faltas_da_professora_e_feriados_nao_entram_na_conta(): void
    {
        $presencas = ['presente', 'presente', 'falta_aluno_sem_aviso', 'falta_professora', 'feriado_evento', 'falta_aluno_justificada'];

        // 2 presentes de 4 aulas válidas (2 presenças + 2 faltas do aluno) = 50%
        $this->assertSame(50.0, Frequencia::percentual($presencas));
    }

    public function test_sem_base_de_calculo_retorna_nulo(): void
    {
        $this->assertNull(Frequencia::percentual([]));
        $this->assertNull(Frequencia::percentual(['falta_professora', 'feriado_evento']));
    }

    public function test_arredonda_uma_casa_e_aceita_o_enum(): void
    {
        $this->assertSame(66.7, Frequencia::percentual([Presenca::Presente, Presenca::Presente, Presenca::FaltaAlunoJustificada]));
        $this->assertSame(100.0, Frequencia::percentual([Presenca::Presente]));
    }
}

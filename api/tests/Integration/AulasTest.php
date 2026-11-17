<?php

declare(strict_types=1);

namespace Tests\Integration;

final class AulasTest extends TestCaseApi
{
    /** @return array<string,string> */
    private function cookies(): array
    {
        return $this->entrarComo($this->criarUsuario('professora'));
    }

    /**
     * @param array<string,string> $c
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function aluno(array $c, array $extra = []): array
    {
        $r = $this->requisicao('POST', '/api/v1/alunos', $extra + ['nome' => 'Ana', 'valor_mensalidade' => 100, 'dia_vencimento' => 5], $c);
        return $this->corpo($r)['dados'];
    }

    public function test_lancar_corrigir_e_excluir_aula_com_log(): void
    {
        $c = $this->cookies();
        $a = $this->aluno($c);
        $tipo = $this->corpo($this->requisicao('POST', '/api/v1/listas/tipo_aula', ['valor' => 'Prática'], $c))['dados']['id'];

        $r = $this->requisicao('POST', '/api/v1/aulas', [
            'aluno_id' => $a['id'], 'data' => '2026-11-10', 'presenca' => 'presente', 'tipo_aula_id' => $tipo,
            'conteudo' => 'Escala de Dó', 'licao_casa' => 'Estudar 15 min/dia',
        ], $c);
        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        $aula = $this->corpo($r)['dados'];
        $this->assertSame('Ana', $aula['aluno']);
        $this->assertSame('Prática', $aula['tipo_aula']);

        $put = $this->requisicao('PUT', "/api/v1/aulas/{$aula['id']}", ['presenca' => 'falta_aluno_justificada', 'motivo' => 'Gripe'], $c);
        $this->assertSame('falta_aluno_justificada', $this->corpo($put)['dados']['presenca']);

        $this->assertSame(204, $this->requisicao('DELETE', "/api/v1/aulas/{$aula['id']}", null, $c)->getStatusCode());
        $this->assertSame(404, $this->requisicao('GET', "/api/v1/aulas/{$aula['id']}", null, $c)->getStatusCode());
        $ops = array_column($this->db->consultar("SELECT operacao FROM sys_log WHERE tabela = 'aulas' ORDER BY id"), 'operacao');
        $this->assertSame(['INSERT', 'UPDATE', 'DELETE'], $ops);
    }

    public function test_validacoes_de_aula(): void
    {
        $c = $this->cookies();
        $r = $this->requisicao('POST', '/api/v1/aulas', ['aluno_id' => 999, 'data' => '10/11/2026', 'presenca' => 'talvez'], $c);
        $this->assertSame(422, $r->getStatusCode());
        $erros = $this->corpo($r)['erros'];
        $this->assertArrayHasKey('data', $erros);
        $this->assertArrayHasKey('presenca', $erros);

        $r2 = $this->requisicao('POST', '/api/v1/aulas', ['aluno_id' => 999, 'data' => '2026-11-10', 'presenca' => 'presente'], $c);
        $this->assertSame(422, $r2->getStatusCode(), 'aluno inexistente');
    }

    public function test_historico_resumo_de_frequencia_e_licao_de_casa_anterior(): void
    {
        $c = $this->cookies();
        $a = $this->aluno($c);
        $lancamentos = [
            ['2026-11-03', 'presente', 'Treinar escala'],
            ['2026-11-10', 'falta_aluno_sem_aviso', null],
            ['2026-11-17', 'falta_professora', null],
        ];
        foreach ($lancamentos as [$d, $p, $lc]) {
            $this->requisicao('POST', '/api/v1/aulas', ['aluno_id' => $a['id'], 'data' => $d, 'presenca' => $p, 'licao_casa' => $lc], $c);
        }

        $r = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$a['id']}/aulas", null, $c));
        $this->assertCount(3, $r['dados']);
        $this->assertSame('2026-11-17', $r['dados'][0]['data'], 'mais recentes primeiro');
        $this->assertSame(3, $r['resumo']['aulas']);
        $this->assertSame(50.0, $r['resumo']['frequencia']);

        $ant = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$a['id']}/aulas/anterior?antes=2026-11-10", null, $c))['dados'];
        $this->assertSame('Treinar escala', $ant['licao_casa']);
        $nenhuma = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$a['id']}/aulas/anterior?antes=2026-11-01", null, $c))['dados'];
        $this->assertNull($nenhuma);
    }

    public function test_calendario_agrega_o_mes_e_ignora_outros_meses(): void
    {
        $c = $this->cookies();
        $a = $this->aluno($c);
        $b = $this->aluno($c, ['nome' => 'Bia']);
        $lancamentos = [
            [$a, '2026-11-03', 'presente'],
            [$b, '2026-11-03', 'falta_aluno_justificada'],
            [$a, '2026-11-30', 'falta_professora'],
            [$a, '2026-12-01', 'presente'],
        ];
        foreach ($lancamentos as [$al, $d, $p]) {
            $this->requisicao('POST', '/api/v1/aulas', ['aluno_id' => $al['id'], 'data' => $d, 'presenca' => $p], $c);
        }

        $cal = $this->corpo($this->requisicao('GET', '/api/v1/calendario?mes=2026-11', null, $c))['dados'];

        $this->assertSame(3, $cal['resumo']['aulas']);
        $this->assertSame(1, $cal['resumo']['presentes']);
        $this->assertSame(1, $cal['resumo']['faltas_aluno']);
        $this->assertSame(1, $cal['resumo']['faltas_professora']);
        $this->assertCount(2, $cal['dias']['2026-11-03']);
        $this->assertArrayNotHasKey('2026-12-01', $cal['dias']);
        $this->assertSame(422, $this->requisicao('GET', '/api/v1/calendario?mes=2026-13', null, $c)->getStatusCode());
    }

    public function test_agenda_do_dia_lista_alunos_ativos_com_horario_e_marca_os_lancados(): void
    {
        $c = $this->cookies();
        // 2026-11-14 é sábado (dia_semana 6)
        $ana = $this->aluno($c, ['nome' => 'Ana', 'dia_semana' => 6, 'hora_aula' => '10:00']);
        $this->aluno($c, ['nome' => 'Bia', 'dia_semana' => 6, 'hora_aula' => '09:00']);
        $this->aluno($c, ['nome' => 'Caio', 'dia_semana' => 2, 'hora_aula' => '09:00']);
        $pausado = $this->aluno($c, ['nome' => 'Davi', 'dia_semana' => 6, 'hora_aula' => '08:00']);
        $this->requisicao('PUT', "/api/v1/alunos/{$pausado['id']}", ['situacao' => 'pausado'], $c);
        $this->requisicao('POST', '/api/v1/aulas', ['aluno_id' => $ana['id'], 'data' => '2026-11-14', 'presenca' => 'presente'], $c);

        $agenda = $this->corpo($this->requisicao('GET', '/api/v1/agenda/hoje?data=2026-11-14', null, $c))['dados'];

        $this->assertSame(['Bia', 'Ana'], array_column($agenda['itens'], 'aluno'), 'ordenado por hora, sem pausado nem outro dia');
        $this->assertNull($agenda['itens'][0]['aula_id']);
        $this->assertSame('presente', $agenda['itens'][1]['presenca']);
    }
}

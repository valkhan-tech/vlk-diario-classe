<?php

declare(strict_types=1);

namespace Tests\Integration;

final class ProgressoTest extends TestCaseApi
{
    /** @var array<string,string> */
    private array $c;

    /** @var array<string,mixed> */
    private array $aluno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = $this->entrarComo($this->criarUsuario('professora'));
        $this->aluno = $this->corpo($this->requisicao('POST', '/api/v1/alunos', ['nome' => 'Ana', 'valor_mensalidade' => 100, 'dia_vencimento' => 5], $this->c))['dados'];
    }

    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function metodo(string $nome, array $extra = []): array
    {
        $r = $this->requisicao('POST', '/api/v1/metodos', $extra + ['nome' => $nome], $this->c);
        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        return $this->corpo($r)['dados'];
    }

    /** @return list<array<string,mixed>> */
    private function licoes(int $metodoId, int $quantidade): array
    {
        $texto = implode("\n", array_map(static fn (int $i): string => "Lição {$i}", range(1, $quantidade)));
        $r = $this->requisicao('POST', "/api/v1/metodos/{$metodoId}/licoes/lote", ['texto' => $texto], $this->c);
        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        return $this->corpo($this->requisicao('GET', "/api/v1/metodos/{$metodoId}/licoes", null, $this->c))['dados'];
    }

    /** @param list<array<string,mixed>> $licoes @return array<string,mixed> */
    private function aprovar(array $licoes, int $quantidade, string $data = '2026-11-10'): array
    {
        $itens = array_map(
            static fn (array $l): array => ['licao_id' => $l['id'], 'status' => 'aprovado'],
            array_slice($licoes, 0, $quantidade)
        );
        $r = $this->requisicao('POST', "/api/v1/alunos/{$this->aluno['id']}/avaliacoes", ['avaliacoes' => $itens, 'data' => $data], $this->c);
        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        return $this->corpo($r);
    }

    /** @return array<string,mixed> */
    private function ficha(): array
    {
        return $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$this->aluno['id']}/ficha-progresso", null, $this->c))['dados'];
    }

    public function test_cadastrar_metodo_hinario_e_colar_lista_de_licoes_em_ordem(): void
    {
        $hinario = $this->metodo('Hinário de Jovens', ['tipo' => 'hinario']);
        $r = $this->requisicao('POST', "/api/v1/metodos/{$hinario['id']}/licoes/lote", ['texto' => "Hino 1\n\n  Hino 2  \nHino 3\n"], $this->c);

        $this->assertSame(['criadas' => 3, 'total' => 3], $this->corpo($r)['dados']);
        $licoes = $this->corpo($this->requisicao('GET', "/api/v1/metodos/{$hinario['id']}/licoes", null, $this->c))['dados'];
        $this->assertSame(['Hino 1', 'Hino 2', 'Hino 3'], array_column($licoes, 'titulo'));
        $this->assertSame([1, 2, 3], array_column($licoes, 'ordem'));

        $mais = $this->requisicao('POST', "/api/v1/metodos/{$hinario['id']}/licoes/lote", ['texto' => 'Hino 4'], $this->c);
        $this->assertSame(['criadas' => 1, 'total' => 4], $this->corpo($mais)['dados']);
        $this->assertSame(422, $this->requisicao('POST', "/api/v1/metodos/{$hinario['id']}/licoes/lote", ['texto' => "  \n "], $this->c)->getStatusCode());
        $this->assertSame(422, $this->requisicao('POST', '/api/v1/metodos', ['nome' => 'Hinário de Jovens'], $this->c)->getStatusCode(), 'nome único');
    }

    public function test_exemplo_da_professora_progresso_geral_11_virgula_6_via_api(): void
    {
        $a = $this->metodo('Faber Vol.1', ['nivel' => 'basico']);
        $h = $this->metodo('Hinário Oficial', ['tipo' => 'hinario']);
        $licoesA = $this->licoes((int) $a['id'], 20);
        $licoesH = $this->licoes((int) $h['id'], 480);

        $this->aprovar($licoesA, 10);
        $this->aprovar($licoesH, 48);
        $ficha = $this->ficha();

        $this->assertSame(11.6, $ficha['progresso_geral']);
        $medias = array_column($ficha['metodos'], 'media', 'metodo');
        $this->assertSame(50.0, $medias['Faber Vol.1']);
        $this->assertSame(10.0, $medias['Hinário Oficial']);
        $this->assertSame('basico', $ficha['estagio']['valor']);
        $this->assertSame('calculado', $ficha['estagio']['origem']);
    }

    public function test_reprovacoes_seguidas_mantem_a_data_inicial_e_o_tempo_na_licao(): void
    {
        $m = $this->metodo('Hanon', ['nivel' => 'intermediario']);
        $licoes = $this->licoes((int) $m['id'], 3);
        $id = (int) $licoes[0]['id'];
        $avaliar = function (string $status, string $data) use ($id): void {
            $r = $this->requisicao('POST', "/api/v1/alunos/{$this->aluno['id']}/avaliacoes", ['avaliacoes' => [['licao_id' => $id, 'status' => $status, 'data_tentativa' => $data]]], $this->c);
            $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        };

        $avaliar('reprovado', '2026-03-10');
        $avaliar('reprovado', '2026-03-24');
        $l = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$this->aluno['id']}/metodos/{$m['id']}/licoes", null, $this->c))['dados']['licoes'][0];
        $this->assertSame('reprovado', $l['status']);
        $this->assertSame('2026-03-10', $l['desde']);
        $this->assertSame(2, $l['tentativas']);
        $this->assertTrue($l['atual']);

        $avaliar('aprovado', '2026-04-07');
        $l = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$this->aluno['id']}/metodos/{$m['id']}/licoes", null, $this->c))['dados']['licoes'];
        $this->assertSame('aprovado', $l[0]['status']);
        $this->assertSame('2026-04-07', $l[0]['desde']);
        $this->assertSame(28, $l[0]['tempo_dias']);
        $this->assertTrue($l[1]['atual'], 'a próxima lição passa a ser a atual');

        $hist = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$this->aluno['id']}/licoes/{$id}/avaliacoes", null, $this->c))['dados'];
        $this->assertSame(['2026-03-10', '2026-03-24', '2026-04-07'], array_column($hist, 'data_tentativa'), 'cada tentativa guarda a data real');
    }

    public function test_sequencia_e_orientacao_fora_de_ordem_e_aceito_e_metodo_e_associado_sozinho(): void
    {
        $m = $this->metodo('Czerny');
        $licoes = $this->licoes((int) $m['id'], 5);

        $r = $this->requisicao('POST', "/api/v1/alunos/{$this->aluno['id']}/avaliacoes", ['avaliacoes' => [['licao_id' => $licoes[3]['id'], 'status' => 'aprovado']]], $this->c);

        $this->assertSame(201, $r->getStatusCode());
        $resultado = $this->corpo($r)['dados'][0];
        $this->assertTrue($resultado['fora_de_ordem'], 'pulou da 1ª para a 4ª');
        $this->assertSame(1, $resultado['licao_atual']['ordem']);
        $ficha = $this->ficha();
        $this->assertSame('iniciado', $ficha['metodos'][0]['situacao'], 'associado automaticamente');
        $this->assertSame(1, $ficha['metodos'][0]['aprovadas']);
    }

    public function test_metodo_pausado_sai_do_calculo_e_concluido_e_sugerido_ao_aprovar_tudo(): void
    {
        $a = $this->metodo('Método A', ['nivel' => 'basico']);
        $b = $this->metodo('Método B', ['nivel' => 'avancado']);
        $licoesA = $this->licoes((int) $a['id'], 2);
        $licoesB = $this->licoes((int) $b['id'], 10);
        $this->aprovar($licoesB, 5);
        $r = $this->aprovar($licoesA, 2);

        $this->assertTrue($r['dados'][1]['concluir_sugerido'], 'todas aprovadas: o sistema sugere Concluído');
        $this->assertSame('avancado', $this->ficha()['estagio']['valor'], 'maior nível entre os métodos em andamento');

        $this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/metodos/{$b['id']}", ['situacao' => 'pausado'], $this->c);
        $ficha = $this->ficha();
        $this->assertSame('basico', $ficha['estagio']['valor'], 'método pausado sai do cálculo');
        $this->assertSame(100.0, $ficha['progresso_geral']);

        $this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/metodos/{$a['id']}", ['situacao' => 'concluido'], $this->c);
        $this->assertSame(1, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela = 'aluno_metodo' AND operacao = 'UPDATE' AND registro_json LIKE '%concluido%'"));
    }

    public function test_sobrescrita_do_estagio_prevalece_e_voltar_ao_calculado(): void
    {
        $m = $this->metodo('Faber Vol.2', ['nivel' => 'intermediario']);
        $this->aprovar($this->licoes((int) $m['id'], 4), 1);

        $this->assertSame('intermediario', $this->ficha()['estagio']['valor']);

        $r = $this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/progresso", ['estagio_manual' => 'apto_culto_jovens'], $this->c);
        $estagio = $this->corpo($r)['dados']['estagio'];
        $this->assertSame('apto_culto_jovens', $estagio['valor']);
        $this->assertSame('manual', $estagio['origem']);
        $this->assertSame('intermediario', $estagio['calculado']);

        $volta = $this->corpo($this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/progresso", ['estagio_manual' => null], $this->c))['dados']['estagio'];
        $this->assertSame('intermediario', $volta['valor']);
        $this->assertSame('calculado', $volta['origem']);
        $this->assertSame(422, $this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/progresso", ['estagio_manual' => 'mestre'], $this->c)->getStatusCode());
    }

    public function test_aptidao_e_checkbox_da_professora_e_fica_no_log(): void
    {
        $r = $this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/progresso", ['apto_jovens' => true, 'tecnica_m1' => 'em_andamento'], $this->c);
        $ficha = $this->corpo($r)['dados'];

        $this->assertTrue($ficha['aptidao']['jovens']);
        $this->assertFalse($ficha['aptidao']['adulto']);
        $this->assertSame('em_andamento', $ficha['tecnica']['m1']);

        $this->requisicao('PUT', "/api/v1/alunos/{$this->aluno['id']}/progresso", ['apto_adulto' => true, 'apto_jovens' => false], $this->c);
        $ops = $this->db->consultar("SELECT operacao, sys_cri FROM sys_log WHERE tabela = 'progresso_aluno' ORDER BY id");
        $this->assertSame(['INSERT', 'UPDATE'], array_column($ops, 'operacao'), 'cada marcação é logada (data da marcação via log)');
        $this->assertSame(1, (int) $this->db->escalar('SELECT COUNT(*) FROM progresso_aluno'), 'um registro por aluno');
    }

    public function test_reordenar_licoes_e_regras_de_exclusao(): void
    {
        $m = $this->metodo('Ordenável');
        $licoes = $this->licoes((int) $m['id'], 3);
        $ids = array_column($licoes, 'id');

        $r = $this->requisicao('PUT', "/api/v1/metodos/{$m['id']}/licoes/ordem", ['ids' => [$ids[2], $ids[0], $ids[1]]], $this->c);
        $this->assertSame(['Lição 3', 'Lição 1', 'Lição 2'], array_column($this->corpo($r)['dados'], 'titulo'));
        $this->assertSame(422, $this->requisicao('PUT', "/api/v1/metodos/{$m['id']}/licoes/ordem", ['ids' => [$ids[0]]], $this->c)->getStatusCode());

        $this->aprovar($licoes, 1);
        $this->assertSame(409, $this->requisicao('DELETE', "/api/v1/licoes/{$ids[0]}", null, $this->c)->getStatusCode(), 'lição avaliada não é excluída');
        $this->assertSame(204, $this->requisicao('DELETE', "/api/v1/licoes/{$ids[2]}", null, $this->c)->getStatusCode());
        $this->assertSame(409, $this->requisicao('DELETE', "/api/v1/metodos/{$m['id']}", null, $this->c)->getStatusCode(), 'método com aluno associado');
    }

    public function test_excluir_avaliacao_errada_volta_o_status(): void
    {
        $m = $this->metodo('Corrigível');
        $licoes = $this->licoes((int) $m['id'], 2);
        $res = $this->aprovar($licoes, 1)['dados'][0];

        $this->assertSame(204, $this->requisicao('DELETE', '/api/v1/avaliacoes/' . $res['avaliacao_id'], null, $this->c)->getStatusCode());

        $this->assertSame('pendente', $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$this->aluno['id']}/metodos/{$m['id']}/licoes", null, $this->c))['dados']['licoes'][0]['status']);
        $this->assertSame(1, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela = 'avaliacoes_licao' AND operacao = 'DELETE'"));
    }

    public function test_associar_duas_vezes_e_validacoes(): void
    {
        $m = $this->metodo('Dupla');
        $url = "/api/v1/alunos/{$this->aluno['id']}/metodos";

        $this->assertSame(201, $this->requisicao('POST', $url, ['metodo_id' => $m['id']], $this->c)->getStatusCode());
        $this->assertSame(409, $this->requisicao('POST', $url, ['metodo_id' => $m['id']], $this->c)->getStatusCode());
        $this->assertSame(422, $this->requisicao('PUT', "{$url}/{$m['id']}", ['situacao' => 'abandonado'], $this->c)->getStatusCode());
        $this->assertSame(422, $this->requisicao('POST', "/api/v1/alunos/{$this->aluno['id']}/avaliacoes", ['avaliacoes' => [['licao_id' => 999, 'status' => 'aprovado']]], $this->c)->getStatusCode());
        $this->assertSame(422, $this->requisicao('POST', "/api/v1/alunos/{$this->aluno['id']}/avaliacoes", ['avaliacoes' => [['licao_id' => 1, 'status' => 'talvez']]], $this->c)->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

final class InicioAuditoriaTest extends TestCaseApi
{
    /** @var array<string,string> */
    private array $prof;

    /** @var array<string,string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prof = $this->entrarComo($this->criarUsuario('professora'));
        $this->admin = $this->entrarComo($this->criarUsuario('admin'));
    }

    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function aluno(array $extra = []): array
    {
        $r = $this->requisicao('POST', '/api/v1/alunos', $extra + ['nome' => 'Ana', 'valor_mensalidade' => 100, 'dia_vencimento' => 5], $this->prof);
        return $this->corpo($r)['dados'];
    }

    public function test_painel_inicial_com_kpis_reais(): void
    {
        // hoje (relógio fixo) = 2026-11-17, terça (dia_semana 2)
        $ana = $this->aluno(['nome' => 'Ana', 'dia_semana' => 2, 'hora_aula' => '09:00']);
        $bia = $this->aluno(['nome' => 'Bia', 'dia_semana' => 2, 'hora_aula' => '10:00']);
        $this->aluno(['nome' => 'Caio']);
        $pausado = $this->aluno(['nome' => 'Davi']);
        $this->requisicao('PUT', "/api/v1/alunos/{$pausado['id']}", ['situacao' => 'pausado'], $this->prof);
        $this->requisicao('POST', '/api/v1/aulas', ['aluno_id' => $ana['id'], 'data' => '2026-11-17', 'presenca' => 'presente'], $this->prof);
        $this->requisicao('POST', '/api/v1/cobrancas/gerar', ['competencia' => '2026-11'], $this->prof); // vencidas dia 5: 3 atrasadas (R$ 100 cada)
        $m = $this->corpo($this->requisicao('POST', '/api/v1/metodos', ['nome' => 'Faber', 'nivel' => 'basico'], $this->prof))['dados'];
        $this->requisicao('POST', "/api/v1/alunos/{$bia['id']}/metodos", ['metodo_id' => $m['id']], $this->prof);
        $this->requisicao('PUT', "/api/v1/alunos/{$bia['id']}/progresso", ['apto_jovens' => true], $this->prof);

        $p = $this->corpo($this->requisicao('GET', '/api/v1/inicio', null, $this->prof))['dados'];

        $this->assertSame(3, $p['kpis']['alunos_ativos'], 'pausado não conta');
        $this->assertSame(2, $p['kpis']['aulas_hoje']);
        $this->assertSame(1, $p['kpis']['aulas_hoje_lancadas']);
        $this->assertSame(3, $p['kpis']['cobrancas_atrasadas']);
        $this->assertSame(300.0, $p['kpis']['valor_atrasado']);
        $this->assertSame(1, $p['kpis']['aptos_culto']);
        $estagios = array_column($p['por_estagio'], 'quantidade', 'estagio');
        $this->assertSame(1, $estagios['basico']);
        $this->assertSame(2, $estagios['sem_estagio']);
    }

    public function test_auditoria_e_somente_do_administrador_e_filtra(): void
    {
        $this->aluno(['nome' => 'Ana']);
        $this->assertSame(403, $this->requisicao('GET', '/api/v1/auditoria', null, $this->prof)->getStatusCode());

        $r = $this->corpo($this->requisicao('GET', '/api/v1/auditoria?tabela=alunos&operacao=INSERT', null, $this->admin));

        $this->assertSame(1, $r['total']);
        $this->assertSame('alunos', $r['dados'][0]['tabela']);
        $this->assertSame('POST /api/v1/alunos', $r['dados'][0]['origem']);
        $this->assertStringContainsString('AlunosController:criar', (string) $r['dados'][0]['controller']);
        $this->assertContains('alunos', $r['tabelas']);
        $detalhe = $this->corpo($this->requisicao('GET', '/api/v1/auditoria/' . $r['dados'][0]['id'], null, $this->admin))['dados'];
        $this->assertSame('Ana', $detalhe['registro']['nome']);
    }

    public function test_verificar_fingerprint_confere_e_acusa_alteracao_fora_do_sistema(): void
    {
        $a = $this->aluno(['nome' => 'Ana']);
        $logId = (int) $this->db->escalar("SELECT MAX(id) FROM sys_log WHERE tabela = 'alunos' AND registro_id = ?", [$a['id']]);

        $ok = $this->corpo($this->requisicao('GET', "/api/v1/auditoria/{$logId}/verificar", null, $this->admin))['dados'];
        $this->assertTrue($ok['integro']);
        $this->assertTrue($ok['ultima_do_registro']);
        $this->assertTrue($ok['confere_com_banco']);

        // alteração direta no banco (fora do sistema)
        $this->db->executar("UPDATE alunos SET nome = 'Ana Alterada' WHERE id = ?", [$a['id']]);
        $alterado = $this->corpo($this->requisicao('GET', "/api/v1/auditoria/{$logId}/verificar", null, $this->admin))['dados'];
        $this->assertTrue($alterado['integro'], 'o log em si continua íntegro');
        $this->assertFalse($alterado['confere_com_banco'], 'mas a linha atual já não bate com o último fingerprint');

        // adulteração do próprio log
        $this->db->executar("UPDATE sys_log SET registro_json = JSON_SET(registro_json, '$.nome', 'Outro') WHERE id = ?", [$logId]);
        $adulterado = $this->corpo($this->requisicao('GET', "/api/v1/auditoria/{$logId}/verificar", null, $this->admin))['dados'];
        $this->assertFalse($adulterado['integro']);
    }

    public function test_verificar_entrada_que_nao_e_a_ultima_nao_compara_com_o_banco(): void
    {
        $a = $this->aluno(['nome' => 'Ana']);
        $primeiro = (int) $this->db->escalar("SELECT MAX(id) FROM sys_log WHERE tabela = 'alunos' AND registro_id = ?", [$a['id']]);
        $this->requisicao('PUT', "/api/v1/alunos/{$a['id']}", ['nome' => 'Ana Maria'], $this->prof);

        $r = $this->corpo($this->requisicao('GET', "/api/v1/auditoria/{$primeiro}/verificar", null, $this->admin))['dados'];

        $this->assertTrue($r['integro']);
        $this->assertFalse($r['ultima_do_registro']);
        $this->assertNull($r['confere_com_banco']);
        $this->assertSame(404, $this->requisicao('GET', '/api/v1/auditoria/999999/verificar', null, $this->admin)->getStatusCode());
    }
}

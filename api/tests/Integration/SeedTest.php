<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infra\Seed\Semeador;

final class SeedTest extends TestCaseApi
{
    /** @return array<string,int> */
    private function semear(): array
    {
        return $this->container->get(Semeador::class)->executar(true);
    }

    public function test_semeador_cobre_o_sistema_inteiro_e_gera_log(): void
    {
        $c = $this->semear();

        foreach (['usuarios', 'listas', 'alunos', 'aulas', 'cobrancas', 'pagamentos', 'metodos', 'licoes', 'aluno_metodo', 'avaliacoes_licao', 'progresso_aluno', 'sys_log'] as $t) {
            $this->assertGreaterThan(0, $c[$t], "tabela {$t} sem dados");
        }
        $this->assertSame(16, $c['alunos']);
        $this->assertSame(480 + 20 + 25 + 30 + 30 + 15 + 15, $c['licoes'], 'inclui hinário de jovens (50) e oficial (430)');
        $this->assertSame(4, $c['usuarios'] - 0, 'admin, professora, responsável e inativo');

        $situacoes = array_column($this->db->consultar('SELECT situacao, COUNT(*) AS n FROM alunos GROUP BY situacao'), 'n', 'situacao');
        $this->assertSame(['ativo' => 12, 'pausado' => 2, 'encerrado' => 2], array_map('intval', $situacoes));

        $presencas = array_column($this->db->consultar('SELECT presenca FROM aulas GROUP BY presenca'), 'presenca');
        $this->assertCount(5, $presencas, 'todas as presenças aparecem');

        $faixas = $this->corpoDe('/api/v1/alunos?incluir_encerrados=1');
        $this->assertCount(4, array_unique(array_filter(array_column($faixas, 'faixa_etaria'))), 'todas as faixas etárias');

        // toda tabela de negócio tem log
        foreach (['alunos', 'aulas', 'cobrancas', 'pagamentos', 'metodos', 'licoes', 'aluno_metodo', 'avaliacoes_licao', 'progresso_aluno', 'listas', 'usuarios'] as $t) {
            $this->assertGreaterThan(0, (int) $this->db->escalar('SELECT COUNT(*) FROM sys_log WHERE tabela = ?', [$t]), "sem log de {$t}");
        }
    }

    public function test_cobrancas_cobrem_todos_os_estados_e_so_ativos_a_partir_da_pausa(): void
    {
        $this->semear();
        $resumo = $this->corpoDe('/api/v1/cobrancas', 'resumo');

        foreach (['pendente', 'parcial', 'atrasado', 'pago', 'cancelada'] as $estado) {
            $this->assertGreaterThan(0, $resumo['por_estado'][$estado], "nenhuma cobrança {$estado}");
        }
        // pausados/encerrados só têm cobranças dos meses em que estavam ativos (3 meses mais antigos)
        $recentes = (int) $this->db->escalar(
            "SELECT COUNT(*) FROM cobrancas c JOIN alunos a ON a.id = c.aluno_id
              WHERE a.situacao <> 'ativo' AND c.competencia >= '2026-09-01' AND c.sys_del IS NULL"
        );
        $this->assertSame(0, $recentes);
    }

    public function test_dados_de_progresso_e_logins_de_teste_funcionam(): void
    {
        $this->semear();

        $painel = $this->corpoDe('/api/v1/inicio');
        $this->assertSame(12, $painel['kpis']['alunos_ativos']);
        $this->assertGreaterThan(0, $painel['kpis']['aptos_culto']);
        $estagios = array_column($painel['por_estagio'], 'quantidade', 'estagio');
        $this->assertGreaterThan(0, $estagios['oficializado']);

        // há método concluído, pausado e iniciado
        $sit = array_column($this->db->consultar('SELECT DISTINCT situacao FROM aluno_metodo'), 'situacao');
        sort($sit);
        $this->assertSame(['concluido', 'iniciado', 'pausado'], $sit);
        // há lição reprovada mais de uma vez (data inicial mantida)
        $repetidas = (int) $this->db->escalar("SELECT COUNT(*) FROM (SELECT aluno_id, licao_id FROM avaliacoes_licao WHERE status = 'reprovado' AND sys_del IS NULL GROUP BY aluno_id, licao_id HAVING COUNT(*) > 1) x");
        $this->assertGreaterThan(0, $repetidas);

        foreach ([['admin@ddc.local', 'Admin@123'], ['professora@ddc.local', 'Professora@123']] as [$email, $senha]) {
            $r = $this->requisicao('POST', '/api/v1/sessao', ['email' => $email, 'senha' => $senha]);
            $this->assertSame(200, $r->getStatusCode(), "login {$email}");
        }
        $this->assertSame(401, $this->requisicao('POST', '/api/v1/sessao', ['email' => 'inativo@ddc.local', 'senha' => 'Inativo@123'])->getStatusCode(), 'usuário inativo não entra');
    }

    public function test_semear_duas_vezes_gera_os_mesmos_dados(): void
    {
        $a = $this->semear();
        $b = $this->semear();

        $this->assertSame($a, $b);
    }

    public function test_recusa_semear_sobre_dados_existentes_sem_limpar(): void
    {
        $this->semear();

        $this->expectException(\RuntimeException::class);
        $this->container->get(Semeador::class)->executar(false);
    }

    /** @return array<mixed> */
    private function corpoDe(string $uri, string $chave = 'dados'): array
    {
        $r = $this->requisicao('POST', '/api/v1/sessao', ['email' => 'professora@ddc.local', 'senha' => 'Professora@123']);
        preg_match('/ddc_sessao=([0-9a-f]+)/', $r->getHeaderLine('Set-Cookie'), $m);
        return $this->corpo($this->requisicao('GET', $uri, null, ['ddc_sessao' => $m[1]]))[$chave];
    }
}

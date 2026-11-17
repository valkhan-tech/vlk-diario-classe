<?php

declare(strict_types=1);

namespace Tests\Integration;

final class FinanceiroTest extends TestCaseApi
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
        $r = $this->requisicao('POST', '/api/v1/alunos', $extra + ['nome' => 'Ana', 'valor_mensalidade' => 150, 'dia_vencimento' => 10], $c);
        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        return $this->corpo($r)['dados'];
    }

    /** @param array<string,string> $c @return array<string,mixed> */
    private function gerar(array $c, string $competencia): array
    {
        $r = $this->requisicao('POST', '/api/v1/cobrancas/gerar', ['competencia' => $competencia], $c);
        $this->assertSame(200, $r->getStatusCode(), (string) $r->getBody());
        return $this->corpo($r)['dados'];
    }

    public function test_gerar_duas_vezes_nao_duplica_e_so_gera_para_ativos(): void
    {
        $c = $this->cookies();
        $this->aluno($c, ['nome' => 'Ativa']);
        $pausado = $this->aluno($c, ['nome' => 'Pausado']);
        $encerrado = $this->aluno($c, ['nome' => 'Encerrado']);
        $this->requisicao('PUT', "/api/v1/alunos/{$pausado['id']}", ['situacao' => 'pausado'], $c);
        $this->requisicao('PUT', "/api/v1/alunos/{$encerrado['id']}", ['situacao' => 'encerrado'], $c);

        $primeira = $this->gerar($c, '2026-11');
        $segunda = $this->gerar($c, '2026-11');

        $this->assertSame(1, $primeira['criadas']);
        $this->assertSame(0, $segunda['criadas']);
        $this->assertSame(1, $segunda['existentes']);
        $this->assertSame(1, (int) $this->db->escalar('SELECT COUNT(*) FROM cobrancas'));
    }

    public function test_vencimento_no_dia_fixo_do_aluno_e_ajuste_em_mes_curto(): void
    {
        $c = $this->cookies();
        $this->aluno($c, ['dia_vencimento' => 31]);

        $this->gerar($c, '2026-02');
        $this->gerar($c, '2026-04');

        $venc = array_column($this->db->consultar('SELECT competencia, vencimento FROM cobrancas ORDER BY competencia'), 'vencimento', 'competencia');
        $this->assertSame('2026-02-28', $venc['2026-02-01']);
        $this->assertSame('2026-04-30', $venc['2026-04-01']);
    }

    public function test_mudar_a_mensalidade_nao_altera_cobrancas_ja_criadas(): void
    {
        $c = $this->cookies();
        $a = $this->aluno($c, ['valor_mensalidade' => 150]);
        $this->gerar($c, '2026-10');

        $this->requisicao('PUT', "/api/v1/alunos/{$a['id']}", ['valor_mensalidade' => 200, 'dia_vencimento' => 20], $c);
        $this->gerar($c, '2026-11');

        $linhas = $this->db->consultar('SELECT competencia, valor, vencimento FROM cobrancas ORDER BY competencia');
        $this->assertSame('150.00', $linhas[0]['valor']);
        $this->assertSame('2026-10-10', $linhas[0]['vencimento']);
        $this->assertSame('200.00', $linhas[1]['valor']);
        $this->assertSame('2026-11-20', $linhas[1]['vencimento']);
    }

    public function test_competencia_anterior_ao_inicio_das_aulas_e_mensalidade_zero_sao_ignoradas(): void
    {
        $c = $this->cookies();
        $this->aluno($c, ['nome' => 'Novo', 'data_inicio' => '2026-12-05']);
        $this->aluno($c, ['nome' => 'Bolsista', 'valor_mensalidade' => 0]);

        $r = $this->gerar($c, '2026-11');

        $this->assertSame(0, $r['criadas']);
        $this->assertSame(2, $r['ignorados']);
    }

    public function test_recriar_cobranca_apos_exclusao_logica_e_possivel(): void
    {
        $c = $this->cookies();
        $this->aluno($c);
        $this->gerar($c, '2026-11');
        $id = (int) $this->db->escalar('SELECT id FROM cobrancas');
        $this->db->executar('UPDATE cobrancas SET sys_del = NOW() WHERE id = ?', [$id]);

        $this->assertSame(1, $this->gerar($c, '2026-11')['criadas']);
    }

    public function test_baixa_parcial_total_estorno_e_estados(): void
    {
        $c = $this->cookies();
        $forma = $this->corpo($this->requisicao('POST', '/api/v1/listas/forma_pagamento', ['valor' => 'Pix'], $c))['dados']['id'];
        $this->aluno($c, ['dia_vencimento' => 25]);
        $this->gerar($c, '2026-11'); // hoje (fixo) = 17/11 → vence 25/11
        $id = (int) $this->db->escalar('SELECT id FROM cobrancas');

        $inicial = $this->corpo($this->requisicao('GET', "/api/v1/cobrancas/{$id}", null, $c))['dados'];
        $this->assertSame('pendente', $inicial['estado']);

        $p1 = $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 50, 'data_pagamento' => '2026-11-17', 'forma_pagamento_id' => $forma], $c);
        $this->assertSame(201, $p1->getStatusCode(), (string) $p1->getBody());
        $parcial = $this->corpo($p1)['dados'];
        $this->assertSame('parcial', $parcial['estado']);
        $this->assertSame(100.0, $parcial['saldo']);

        $p2 = $this->corpo($this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 100, 'data_pagamento' => '2026-11-18'], $c))['dados'];
        $this->assertSame('pago', $p2['estado']);
        $this->assertCount(2, $p2['pagamentos']);

        $estorno = $this->corpo($this->requisicao('DELETE', '/api/v1/pagamentos/' . $p2['pagamentos'][1]['id'], null, $c))['dados'];
        $this->assertSame('parcial', $estorno['estado'], 'estorno reabre a cobrança');
        $this->assertSame(2, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela = 'pagamentos' AND operacao = 'INSERT'"));
        $this->assertSame(1, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela = 'pagamentos' AND operacao = 'DELETE'"));
    }

    public function test_pagamento_acima_do_saldo_e_aceito_e_o_excedente_fica_registrado(): void
    {
        $c = $this->cookies();
        $this->aluno($c, ['dia_vencimento' => 25]);
        $this->gerar($c, '2026-11');
        $id = (int) $this->db->escalar('SELECT id FROM cobrancas');
        $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 100, 'data_pagamento' => '2026-11-17'], $c);

        $r = $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 80, 'data_pagamento' => '2026-11-18'], $c);

        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        $cobranca = $this->corpo($r)['dados'];
        $this->assertSame('pago', $cobranca['estado']);
        $this->assertSame(180.0, $cobranca['pago']);
        $this->assertSame(0.0, $cobranca['saldo']);
        $this->assertSame(30.0, $cobranca['excedente'], '150 cobrados, 180 pagos');
        $lista = $this->corpo($this->requisicao('GET', '/api/v1/cobrancas?competencia=2026-11', null, $c));
        $this->assertSame(30.0, $lista['resumo']['excedente']);
        $this->assertSame(150.0, $lista['resumo']['previsto'], 'o excedente não aumenta o previsto');

        $estorno = $this->corpo($this->requisicao('DELETE', '/api/v1/pagamentos/' . $cobranca['pagamentos'][1]['id'], null, $c))['dados'];
        $this->assertSame(0.0, $estorno['excedente']);
        $this->assertSame(50.0, $estorno['saldo']);
        $this->assertSame(422, $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 0, 'data_pagamento' => '2026-11-18'], $c)->getStatusCode(), 'valor zero continua inválido');
    }

    public function test_cobranca_vencida_sem_quitacao_fica_atrasada_e_deixa_de_estar_ao_pagar(): void
    {
        $c = $this->cookies();
        $this->aluno($c, ['dia_vencimento' => 5]);
        $this->gerar($c, '2026-11'); // venceu 05/11, hoje 17/11
        $id = (int) $this->db->escalar('SELECT id FROM cobrancas');

        $atrasada = $this->corpo($this->requisicao('GET', "/api/v1/cobrancas/{$id}", null, $c))['dados'];
        $this->assertSame('atrasado', $atrasada['estado']);
        $this->assertSame(12, $atrasada['dias_atraso']);

        $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 50, 'data_pagamento' => '2026-11-17'], $c);
        $ainda = $this->corpo($this->requisicao('GET', "/api/v1/cobrancas/{$id}", null, $c))['dados'];
        $this->assertSame('atrasado', $ainda['estado'], 'parcial vencida continua atrasada');

        $pago = $this->corpo($this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 100, 'data_pagamento' => '2026-11-17'], $c))['dados'];
        $this->assertSame('pago', $pago['estado']);
    }

    public function test_cancelar_cobranca_bloqueia_pagamentos_e_sai_do_resumo(): void
    {
        $c = $this->cookies();
        $this->aluno($c);
        $this->gerar($c, '2026-11');
        $id = (int) $this->db->escalar('SELECT id FROM cobrancas');

        $this->requisicao('PUT', "/api/v1/cobrancas/{$id}", ['situacao' => 'cancelada'], $c);

        $lista = $this->corpo($this->requisicao('GET', '/api/v1/cobrancas?competencia=2026-11', null, $c));
        $this->assertSame('cancelada', $lista['dados'][0]['estado']);
        $this->assertSame(0.0, $lista['resumo']['previsto']);
        $this->assertSame(409, $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 10, 'data_pagamento' => '2026-11-17'], $c)->getStatusCode());
    }

    public function test_ficha_financeira_do_aluno_traz_totais(): void
    {
        $c = $this->cookies();
        $a = $this->aluno($c, ['dia_vencimento' => 5]);
        $this->gerar($c, '2026-10');
        $this->gerar($c, '2026-11');
        $id = (int) $this->db->escalar("SELECT id FROM cobrancas WHERE competencia = '2026-10-01'");
        $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => 150, 'data_pagamento' => '2026-10-08'], $c);

        $ficha = $this->corpo($this->requisicao('GET', "/api/v1/alunos/{$a['id']}/ficha-financeira", null, $c))['dados'];

        $this->assertCount(2, $ficha['cobrancas']);
        $this->assertSame(300.0, $ficha['resumo']['previsto']);
        $this->assertSame(150.0, $ficha['resumo']['recebido']);
        $this->assertSame(150.0, $ficha['resumo']['atrasado'], 'novembro venceu dia 5 e não foi paga');
        $this->assertSame(1, $ficha['resumo']['por_estado']['pago']);
        $this->assertSame(1, $ficha['resumo']['por_estado']['atrasado']);
    }

    public function test_filtro_por_estado_e_log_da_geracao(): void
    {
        $c = $this->cookies();
        $this->aluno($c, ['nome' => 'A', 'dia_vencimento' => 5]);
        $this->aluno($c, ['nome' => 'B', 'dia_vencimento' => 28]);
        $this->gerar($c, '2026-11');

        $atrasadas = $this->corpo($this->requisicao('GET', '/api/v1/cobrancas?competencia=2026-11&estado=atrasado', null, $c))['dados'];
        $this->assertSame(['A'], array_column($atrasadas, 'aluno'));
        $this->assertSame(2, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela = 'cobrancas' AND operacao = 'INSERT'"));
    }
}

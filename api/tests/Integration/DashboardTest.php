<?php

declare(strict_types=1);

namespace Tests\Integration;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Http\Message\ResponseInterface;

final class DashboardTest extends TestCaseApi
{
    /** @var array<string,string> */
    private array $c;

    /** @var array<string,mixed> */
    private array $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = $this->entrarComo($this->criarUsuario('professora'));
        $pix = $this->corpo($this->requisicao('POST', '/api/v1/listas/forma_pagamento', ['valor' => 'Pix'], $this->c))['dados']['id'];
        $din = $this->corpo($this->requisicao('POST', '/api/v1/listas/forma_pagamento', ['valor' => 'Dinheiro'], $this->c))['dados']['id'];
        $this->ana = $this->corpo($this->requisicao('POST', '/api/v1/alunos', ['nome' => 'Ana', 'valor_mensalidade' => 100, 'dia_vencimento' => 5], $this->c))['dados'];
        $bia = $this->corpo($this->requisicao('POST', '/api/v1/alunos', ['nome' => 'Bia', 'valor_mensalidade' => 200, 'dia_vencimento' => 28], $this->c))['dados'];
        foreach (['2026-09', '2026-10', '2026-11'] as $mes) {
            $this->requisicao('POST', '/api/v1/cobrancas/gerar', ['competencia' => $mes], $this->c);
        }
        // Ana: setembro pago (Pix), outubro parcial 40 (Dinheiro), novembro sem pagamento (atrasada desde 05/11)
        // Bia: setembro pago (Pix), outubro e novembro sem pagamento (outubro atrasada; novembro vence 28/11)
        $pagar = function (string $mes, int $alunoId, float $valor, int $forma): void {
            $id = (int) $this->db->escalar('SELECT id FROM cobrancas WHERE aluno_id = ? AND competencia = ?', [$alunoId, "{$mes}-01"]);
            $r = $this->requisicao('POST', "/api/v1/cobrancas/{$id}/pagamentos", ['valor' => $valor, 'data_pagamento' => "{$mes}-10", 'forma_pagamento_id' => $forma], $this->c);
            $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        };
        $pagar('2026-09', (int) $this->ana['id'], 100, $pix);
        $pagar('2026-10', (int) $this->ana['id'], 40, $din);
        $pagar('2026-09', (int) $bia['id'], 200, $pix);
    }

    public function test_indicadores_batem_com_as_cobrancas_e_pagamentos(): void
    {
        $d = $this->corpo($this->requisicao('GET', '/api/v1/dashboards/financeiro?de=2026-09&ate=2026-11', null, $this->c))['dados'];

        // previsto: (100+200) x 3 meses = 900; recebido: 100+40+200 = 340
        $this->assertSame(900.0, $d['kpis']['previsto']);
        $this->assertSame(340.0, $d['kpis']['recebido']);
        $this->assertSame(560.0, $d['kpis']['aberto']);
        // atrasado: Ana out (60) + Ana nov (100) + Bia out (200) = 360; Bia nov vence 28/11 (não atrasada)
        $this->assertSame(360.0, $d['kpis']['atrasado']);
        $this->assertSame(40.0, $d['kpis']['inadimplencia']);
        $this->assertSame(['2026-09', '2026-10', '2026-11'], array_column($d['por_mes'], 'competencia'));
        $this->assertSame(300.0, $d['por_mes'][0]['recebido']);
        $this->assertSame(['Pix', 'Dinheiro'], array_column($d['por_forma'], 'forma'));
        $this->assertSame(300.0, $d['por_forma'][0]['valor']);
    }

    public function test_envelhecimento_e_ranking_de_atrasos(): void
    {
        $d = $this->corpo($this->requisicao('GET', '/api/v1/dashboards/financeiro?de=2026-09&ate=2026-11', null, $this->c))['dados'];

        // hoje = 17/11: Ana nov 12d (100) | Ana out venceu 05/10 = 43d (60) | Bia out venceu 28/10 = 20d (200)
        $faixas = array_column($d['envelhecimento'], null, 'faixa');
        $this->assertSame(300.0, $faixas['1 a 30 dias']['valor']);
        $this->assertSame(2, $faixas['1 a 30 dias']['quantidade']);
        $this->assertSame(60.0, $faixas['31 a 60 dias']['valor']);
        $this->assertSame(0.0, $faixas['61 dias ou mais']['valor']);
        $this->assertSame(['Bia', 'Ana'], array_column($d['ranking'], 'aluno'));
        $this->assertSame(200.0, $d['ranking'][0]['saldo']);
        $this->assertSame(160.0, $d['ranking'][1]['saldo']);
    }

    public function test_periodo_invalido_e_recusado(): void
    {
        $this->assertSame(422, $this->requisicao('GET', '/api/v1/dashboards/financeiro?de=2026-11&ate=2026-09', null, $this->c)->getStatusCode());
        $this->assertSame(422, $this->requisicao('GET', '/api/v1/dashboards/financeiro?de=2020-01&ate=2026-11', null, $this->c)->getStatusCode(), 'máximo de 24 meses');
        $this->assertSame(401, $this->requisicao('GET', '/api/v1/dashboards/financeiro')->getStatusCode());
    }

    /** @return array{0:\PhpOffice\PhpSpreadsheet\Spreadsheet,1:list<string>} planilha lida e lista de arquivos internos do .xlsx */
    private function abrir(ResponseInterface $r): array
    {
        $this->assertSame(200, $r->getStatusCode(), (string) $r->getBody());
        $this->assertStringContainsString('spreadsheetml.sheet', $r->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment', $r->getHeaderLine('Content-Disposition'));
        $arquivo = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        file_put_contents($arquivo, (string) $r->getBody());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($arquivo));
        $internos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $internos[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        $planilha = IOFactory::load($arquivo);
        @unlink($arquivo);
        return [$planilha, $internos];
    }

    public function test_exportacao_de_cobrancas_tem_formulas_de_total_e_filtro(): void
    {
        [$planilha] = $this->abrir($this->requisicao('GET', '/api/v1/exportacoes/cobrancas.xlsx?de=2026-09&ate=2026-11', null, $this->c));
        $aba = $planilha->getSheetByName('Cobranças');

        $this->assertNotNull($aba);
        $this->assertSame('Competência', $aba->getCell('A3')->getValue());
        $this->assertSame('=D4-E4', $aba->getCell('F4')->getValue());
        $this->assertSame('TOTAL', $aba->getCell('A10')->getValue(), '6 cobranças + cabeçalho');
        $this->assertSame('=SUM(D4:D9)', $aba->getCell('D10')->getValue());
        $this->assertSame(900.0, (float) $aba->getCell('D10')->getCalculatedValue());
        $this->assertSame(340.0, (float) $aba->getCell('G10')->getCalculatedValue());
        $this->assertSame('A3:I9', $aba->getAutoFilter()->getRange());
    }

    public function test_exportacao_do_resumo_mensal_traz_graficos(): void
    {
        [$planilha, $internos] = $this->abrir($this->requisicao('GET', '/api/v1/exportacoes/resumo-mensal.xlsx?de=2026-09&ate=2026-11', null, $this->c));
        $aba = $planilha->getSheetByName('Resumo');

        $this->assertSame('=SUM(B4:B6)', $aba->getCell('B7')->getValue());
        $this->assertSame(900.0, (float) $aba->getCell('B7')->getCalculatedValue());
        $this->assertNotEmpty(array_filter($internos, static fn (string $n): bool => str_starts_with($n, 'xl/charts/chart')), 'o arquivo deve conter gráficos');
        $this->assertGreaterThanOrEqual(2, count(array_filter($internos, static fn (string $n): bool => (bool) preg_match('#^xl/charts/chart\d+\.xml$#', $n))));
    }

    public function test_exportacoes_de_inadimplencia_formas_e_ficha(): void
    {
        [$inad, $internosInad] = $this->abrir($this->requisicao('GET', '/api/v1/exportacoes/inadimplencia.xlsx', null, $this->c));
        $this->assertSame(360.0, (float) $inad->getSheetByName('Atrasadas')->getCell('E7')->getCalculatedValue());
        $this->assertNotNull($inad->getSheetByName('Envelhecimento'));
        $this->assertNotEmpty(array_filter($internosInad, static fn (string $n): bool => str_contains($n, 'charts/chart')));

        [$formas, $internosFormas] = $this->abrir($this->requisicao('GET', '/api/v1/exportacoes/recebimentos-forma.xlsx?de=2026-09&ate=2026-11', null, $this->c));
        $this->assertSame(340.0, (float) $formas->getSheetByName('Formas de pagamento')->getCell('C6')->getCalculatedValue());
        $this->assertNotEmpty(array_filter($internosFormas, static fn (string $n): bool => str_contains($n, 'charts/chart')));

        [$ficha] = $this->abrir($this->requisicao('GET', "/api/v1/exportacoes/ficha-financeira.xlsx?aluno_id={$this->ana['id']}", null, $this->c));
        $this->assertSame(300.0, (float) $ficha->getSheetByName('Ficha financeira')->getCell('C8')->getCalculatedValue());
        $this->assertSame(140.0, (float) $ficha->getSheetByName('Pagamentos')->getCell('D6')->getCalculatedValue());
    }

    public function test_exportacao_desconhecida_ou_sem_login_e_recusada(): void
    {
        $this->assertSame(404, $this->requisicao('GET', '/api/v1/exportacoes/foo.xlsx', null, $this->c)->getStatusCode());
        $this->assertSame(401, $this->requisicao('GET', '/api/v1/exportacoes/cobrancas.xlsx')->getStatusCode());
        $this->assertSame(422, $this->requisicao('GET', '/api/v1/exportacoes/ficha-financeira.xlsx', null, $this->c)->getStatusCode());
    }
}

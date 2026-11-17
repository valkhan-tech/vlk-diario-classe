<?php

declare(strict_types=1);

namespace App\Infra\Excel;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gera as planilhas financeiras (.xlsx) formatadas, com fórmulas de total e gráficos.
 * Recebe dados já calculados pelos serviços; não consulta o banco.
 */
final class ExportadorFinanceiro
{
    private const MOEDA = '"R$" #,##0.00';
    private const COR_CABECALHO = '4338CA';

    /**
     * @param list<array<string,mixed>> $cobrancas cobranças apresentadas (estado, saldo...)
     */
    public function cobrancas(array $cobrancas, string $titulo): string
    {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet()->setTitle('Cobranças');
        $this->titulo($aba, $titulo, 'I');
        $this->cabecalho($aba, 3, ['Competência', 'Aluno', 'Vencimento', 'Valor', 'Desconto', 'Líquido', 'Pago', 'Saldo', 'Estado']);

        $linha = 4;
        foreach ($cobrancas as $c) {
            $aba->fromArray([
                $c['competencia'], $c['aluno'], $this->dataBr((string) $c['vencimento']), $c['valor'], $c['desconto'],
                "=D{$linha}-E{$linha}", $c['pago'], "=MAX(0,F{$linha}-G{$linha})", ucfirst((string) $c['estado']),
            ], null, "A{$linha}");
            $linha++;
        }
        $ultima = max(4, $linha - 1);
        $this->moeda($aba, "D4:H{$linha}");
        if ($cobrancas !== []) {
            $aba->setCellValue("A{$linha}", 'TOTAL');
            foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
                $aba->setCellValue("{$col}{$linha}", "=SUM({$col}4:{$col}{$ultima})");
            }
            $aba->getStyle("A{$linha}:I{$linha}")->getFont()->setBold(true);
            $aba->getStyle("A{$linha}:I{$linha}")->getBorders()->getTop()->setBorderStyle('thin');
            $aba->setAutoFilter("A3:I{$ultima}");
        }
        $aba->freezePane('A4');
        $this->larguras($aba, ['A' => 14, 'B' => 30, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 14, 'H' => 14, 'I' => 14]);
        return $this->gerar($planilha);
    }

    /**
     * @param list<array{competencia:string,previsto:float,recebido:float,aberto:float,atrasado:float}> $meses
     */
    public function resumoMensal(array $meses, string $titulo): string
    {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet()->setTitle('Resumo');
        $this->titulo($aba, $titulo, 'F');
        $this->cabecalho($aba, 3, ['Competência', 'Previsto', 'Recebido', 'Em aberto', 'Atrasado', '% recebido']);

        $linha = 4;
        foreach ($meses as $m) {
            $aba->fromArray([$m['competencia'], $m['previsto'], $m['recebido'], $m['aberto'], $m['atrasado'], "=IF(B{$linha}=0,0,C{$linha}/B{$linha})"], null, "A{$linha}");
            $linha++;
        }
        $ultima = max(4, $linha - 1);
        $aba->setCellValue("A{$linha}", 'TOTAL');
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $aba->setCellValue("{$col}{$linha}", "=SUM({$col}4:{$col}{$ultima})");
        }
        $aba->setCellValue("F{$linha}", "=IF(B{$linha}=0,0,C{$linha}/B{$linha})");
        $aba->getStyle("A{$linha}:F{$linha}")->getFont()->setBold(true);
        $this->moeda($aba, "B4:E{$linha}");
        $aba->getStyle("F4:F{$linha}")->getNumberFormat()->setFormatCode('0.0%');
        $this->larguras($aba, ['A' => 14, 'B' => 16, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 12]);

        if ($meses !== []) {
            $aba->addChart($this->grafico('barras', 'Previsto × Recebido por competência', 'Resumo', 3, $ultima, 'A', ['B', 'C'], 'H3', 'P20'));
            $aba->addChart($this->grafico('linha', 'Atrasado por competência', 'Resumo', 3, $ultima, 'A', ['E'], 'H22', 'P38'));
        }
        return $this->gerar($planilha);
    }

    /**
     * @param list<array<string,mixed>> $atrasadas cobranças atrasadas
     * @param list<array{faixa:string,quantidade:int,valor:float}> $faixas
     */
    public function inadimplencia(array $atrasadas, array $faixas, string $titulo): string
    {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet()->setTitle('Atrasadas');
        $this->titulo($aba, $titulo, 'F');
        $this->cabecalho($aba, 3, ['Aluno', 'Competência', 'Vencimento', 'Dias de atraso', 'Saldo', 'Líquido']);
        $linha = 4;
        foreach ($atrasadas as $c) {
            $aba->fromArray([$c['aluno'], $c['competencia'], $this->dataBr((string) $c['vencimento']), $c['dias_atraso'], $c['saldo'], $c['valor_liquido']], null, "A{$linha}");
            $linha++;
        }
        $ultima = max(4, $linha - 1);
        $aba->setCellValue("A{$linha}", 'TOTAL');
        $aba->setCellValue("E{$linha}", "=SUM(E4:E{$ultima})");
        $aba->setCellValue("F{$linha}", "=SUM(F4:F{$ultima})");
        $aba->getStyle("A{$linha}:F{$linha}")->getFont()->setBold(true);
        $this->moeda($aba, "E4:F{$linha}");
        $aba->freezePane('A4');
        $this->larguras($aba, ['A' => 30, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 16, 'F' => 16]);

        $env = $planilha->createSheet()->setTitle('Envelhecimento');
        $this->titulo($env, 'Saldo atrasado por faixa de atraso', 'C');
        $this->cabecalho($env, 3, ['Faixa', 'Cobranças', 'Saldo']);
        $l = 4;
        foreach ($faixas as $f) {
            $env->fromArray([$f['faixa'], $f['quantidade'], $f['valor']], null, "A{$l}");
            $l++;
        }
        $env->setCellValue("A{$l}", 'TOTAL');
        $env->setCellValue("B{$l}", "=SUM(B4:B" . ($l - 1) . ')');
        $env->setCellValue("C{$l}", "=SUM(C4:C" . ($l - 1) . ')');
        $env->getStyle("A{$l}:C{$l}")->getFont()->setBold(true);
        $this->moeda($env, "C4:C{$l}");
        $this->larguras($env, ['A' => 22, 'B' => 14, 'C' => 16]);
        $env->addChart($this->grafico('barras', 'Saldo atrasado por faixa', 'Envelhecimento', 3, $l - 1, 'A', ['C'], 'E3', 'M18'));
        return $this->gerar($planilha);
    }

    /**
     * @param list<array{forma:string,quantidade:int,valor:float}> $formas
     */
    public function recebimentosPorForma(array $formas, string $titulo): string
    {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet()->setTitle('Formas de pagamento');
        $this->titulo($aba, $titulo, 'C');
        $this->cabecalho($aba, 3, ['Forma', 'Pagamentos', 'Valor']);
        $linha = 4;
        foreach ($formas as $f) {
            $aba->fromArray([$f['forma'], $f['quantidade'], $f['valor']], null, "A{$linha}");
            $linha++;
        }
        $ultima = max(4, $linha - 1);
        $aba->setCellValue("A{$linha}", 'TOTAL');
        $aba->setCellValue("B{$linha}", "=SUM(B4:B{$ultima})");
        $aba->setCellValue("C{$linha}", "=SUM(C4:C{$ultima})");
        $aba->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true);
        $this->moeda($aba, "C4:C{$linha}");
        $this->larguras($aba, ['A' => 26, 'B' => 14, 'C' => 16]);
        if ($formas !== []) {
            $aba->addChart($this->grafico('pizza', 'Recebimentos por forma de pagamento', 'Formas de pagamento', 3, $ultima, 'A', ['C'], 'E3', 'L20'));
        }
        return $this->gerar($planilha);
    }

    /**
     * @param array<string,mixed> $aluno
     * @param list<array<string,mixed>> $cobrancas cobranças apresentadas, cada uma com 'pagamentos'
     */
    public function fichaFinanceira(array $aluno, array $cobrancas): string
    {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet()->setTitle('Ficha financeira');
        $this->titulo($aba, 'Ficha financeira — ' . $aluno['nome'], 'H');
        $aba->setCellValue('A2', sprintf('Mensalidade R$ %s · vence todo dia %d', number_format((float) $aluno['valor_mensalidade'], 2, ',', '.'), $aluno['dia_vencimento']));
        $this->cabecalho($aba, 4, ['Competência', 'Vencimento', 'Valor', 'Desconto', 'Líquido', 'Pago', 'Saldo', 'Estado']);
        $linha = 5;
        foreach ($cobrancas as $c) {
            $aba->fromArray([
                $c['competencia'], $this->dataBr((string) $c['vencimento']), $c['valor'], $c['desconto'],
                "=C{$linha}-D{$linha}", $c['pago'], "=MAX(0,E{$linha}-F{$linha})", ucfirst((string) $c['estado']),
            ], null, "A{$linha}");
            $linha++;
        }
        $ultima = max(5, $linha - 1);
        $aba->setCellValue("A{$linha}", 'TOTAL');
        foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
            $aba->setCellValue("{$col}{$linha}", "=SUM({$col}5:{$col}{$ultima})");
        }
        $aba->getStyle("A{$linha}:H{$linha}")->getFont()->setBold(true);
        $this->moeda($aba, "C5:G{$linha}");
        $aba->freezePane('A5');
        $this->larguras($aba, ['A' => 14, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 14, 'H' => 14]);

        $pag = $planilha->createSheet()->setTitle('Pagamentos');
        $this->titulo($pag, 'Pagamentos — ' . $aluno['nome'], 'D');
        $this->cabecalho($pag, 3, ['Competência', 'Data', 'Forma', 'Valor']);
        $l = 4;
        foreach ($cobrancas as $c) {
            foreach ($c['pagamentos'] ?? [] as $p) {
                $pag->fromArray([$c['competencia'], $this->dataBr((string) $p['data_pagamento']), $p['forma'] ?? '', $p['valor']], null, "A{$l}");
                $l++;
            }
        }
        $pag->setCellValue("A{$l}", 'TOTAL');
        $pag->setCellValue("D{$l}", "=SUM(D4:D" . max(4, $l - 1) . ')');
        $pag->getStyle("A{$l}:D{$l}")->getFont()->setBold(true);
        $this->moeda($pag, "D4:D{$l}");
        $this->larguras($pag, ['A' => 14, 'B' => 14, 'C' => 20, 'D' => 14]);
        return $this->gerar($planilha);
    }

    /* ---------- helpers ---------- */

    private function titulo(Worksheet $aba, string $texto, string $ultimaColuna): void
    {
        $aba->setCellValue('A1', $texto);
        $aba->mergeCells("A1:{$ultimaColuna}1");
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    }

    /** @param list<string> $titulos */
    private function cabecalho(Worksheet $aba, int $linha, array $titulos): void
    {
        $aba->fromArray($titulos, null, "A{$linha}");
        $ultima = chr(ord('A') + count($titulos) - 1);
        $estilo = $aba->getStyle("A{$linha}:{$ultima}{$linha}");
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_CABECALHO);
        $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function moeda(Worksheet $aba, string $faixa): void
    {
        $aba->getStyle($faixa)->getNumberFormat()->setFormatCode(self::MOEDA);
    }

    /** @param array<string,int> $larguras */
    private function larguras(Worksheet $aba, array $larguras): void
    {
        foreach ($larguras as $coluna => $largura) {
            $aba->getColumnDimension($coluna)->setWidth($largura);
        }
    }

    private function dataBr(string $iso): string
    {
        return $iso === '' ? '' : date('d/m/Y', (int) strtotime($iso));
    }

    /**
     * @param list<string> $colunasValores letras das colunas das séries (cabeçalho na $linhaCabecalho)
     */
    private function grafico(string $tipo, string $titulo, string $nomeAba, int $linhaCabecalho, int $ultimaLinha, string $colunaCategorias, array $colunasValores, string $de, string $ate): Chart
    {
        $aba = "'{$nomeAba}'";
        $n = max(1, $ultimaLinha - $linhaCabecalho);
        $primeira = $linhaCabecalho + 1;
        $rotulos = $valores = [];
        foreach ($colunasValores as $col) {
            $rotulos[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$aba}!\${$col}\${$linhaCabecalho}", null, 1);
            $valores[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$aba}!\${$col}\${$primeira}:\${$col}\${$ultimaLinha}", null, $n);
        }
        $categorias = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$aba}!\${$colunaCategorias}\${$primeira}:\${$colunaCategorias}\${$ultimaLinha}", null, $n)];

        $serie = match ($tipo) {
            'pizza' => new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STANDARD, range(0, count($valores) - 1), $rotulos, $categorias, $valores),
            'linha' => new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, count($valores) - 1), $rotulos, $categorias, $valores),
            default => new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($valores) - 1), $rotulos, $categorias, $valores),
        };
        if ($tipo === 'barras') {
            $serie->setPlotDirection(DataSeries::DIRECTION_COL);
        }
        $grafico = new Chart('g' . md5($titulo), new Title($titulo), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$serie]));
        $grafico->setTopLeftPosition($de);
        $grafico->setBottomRightPosition($ate);
        return $grafico;
    }

    private function gerar(Spreadsheet $planilha): string
    {
        $arquivo = (string) tempnam(sys_get_temp_dir(), 'ddc');
        try {
            $escritor = IOFactory::createWriter($planilha, 'Xlsx');
            $escritor->setIncludeCharts(true);
            $escritor->save($arquivo);
            return (string) file_get_contents($arquivo);
        } finally {
            @unlink($arquivo);
            $planilha->disconnectWorksheets();
        }
    }
}

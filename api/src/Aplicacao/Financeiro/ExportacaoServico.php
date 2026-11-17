<?php

declare(strict_types=1);

namespace App\Aplicacao\Financeiro;

use App\Dominio\Financeiro\EstadoCobranca;
use App\Http\Erros\ErroValidacao;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Excel\ExportadorFinanceiro;

/** Monta os dados e gera os arquivos .xlsx financeiros. Retorna [nome do arquivo, conteúdo]. */
final class ExportacaoServico
{
    public const TIPOS = ['cobrancas', 'resumo-mensal', 'inadimplencia', 'recebimentos-forma', 'ficha-financeira'];

    public function __construct(
        private readonly CobrancaServico $cobrancas,
        private readonly DashboardServico $dashboard,
        private readonly ExportadorFinanceiro $exportador,
    ) {
    }

    /**
     * @param array{de?:?string,ate?:?string,aluno_id?:?int} $filtros
     * @return array{0:string,1:string}
     */
    public function gerar(string $tipo, array $filtros): array
    {
        return match ($tipo) {
            'cobrancas' => $this->cobrancasDoPeriodo($filtros),
            'resumo-mensal' => $this->resumoMensal($filtros),
            'inadimplencia' => $this->inadimplencia(),
            'recebimentos-forma' => $this->recebimentosPorForma($filtros),
            'ficha-financeira' => $this->fichaFinanceira($filtros),
            default => throw new NaoEncontrado('Tipo de exportação desconhecido.'),
        };
    }

    /** @param array{de?:?string,ate?:?string} $f @return array{0:string,1:string} */
    private function cobrancasDoPeriodo(array $f): array
    {
        [$de, $ate] = $this->periodo($f);
        $lista = $this->cobrancas->listarPeriodo($de, $ate);
        return ["cobrancas-{$de}-a-{$ate}.xlsx", $this->exportador->cobrancas($lista, "Cobranças de {$de} a {$ate}")];
    }

    /** @param array{de?:?string,ate?:?string} $f @return array{0:string,1:string} */
    private function resumoMensal(array $f): array
    {
        [$de, $ate] = $this->periodo($f);
        $r = $this->dashboard->resumo($de, $ate);
        return ["resumo-mensal-{$de}-a-{$ate}.xlsx", $this->exportador->resumoMensal($r['por_mes'], "Resumo mensal — {$de} a {$ate}")];
    }

    /** @return array{0:string,1:string} */
    private function inadimplencia(): array
    {
        $todas = $this->cobrancas->listar(['estado' => EstadoCobranca::ATRASADO])['dados'];
        $faixas = $this->dashboard->envelhecimento($todas);
        return ['inadimplencia-' . date('Y-m-d') . '.xlsx', $this->exportador->inadimplencia($todas, $faixas, 'Cobranças atrasadas em ' . date('d/m/Y'))];
    }

    /** @param array{de?:?string,ate?:?string} $f @return array{0:string,1:string} */
    private function recebimentosPorForma(array $f): array
    {
        [$de, $ate] = $this->periodo($f);
        [$inicio, $fim] = $this->dashboard->periodo($de, $ate);
        $formas = $this->dashboard->formasNoPeriodo($inicio, $fim);
        return ["recebimentos-forma-{$de}-a-{$ate}.xlsx", $this->exportador->recebimentosPorForma($formas, "Recebimentos por forma de pagamento — {$de} a {$ate}")];
    }

    /** @param array{aluno_id?:?int} $f @return array{0:string,1:string} */
    private function fichaFinanceira(array $f): array
    {
        if (empty($f['aluno_id'])) {
            throw new ErroValidacao(['aluno_id' => 'Informe o aluno.']);
        }
        $ficha = $this->cobrancas->fichaFinanceira((int) $f['aluno_id']);
        return ["ficha-financeira-{$f['aluno_id']}.xlsx", $this->exportador->fichaFinanceira($ficha['aluno'], $ficha['cobrancas'])];
    }

    /** @param array{de?:?string,ate?:?string} $f @return array{0:string,1:string} */
    private function periodo(array $f): array
    {
        $de = $f['de'] ?? date('Y-m', strtotime('-5 months'));
        $ate = $f['ate'] ?? date('Y-m');
        $this->dashboard->periodo((string) $de, (string) $ate);
        return [(string) $de, (string) $ate];
    }
}

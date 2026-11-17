<?php

declare(strict_types=1);

namespace App\Aplicacao\Financeiro;

use App\Dominio\Financeiro\EstadoCobranca;
use App\Http\Erros\ErroValidacao;
use App\Infra\Repositorios\CobrancaRepositorio;
use App\Infra\Repositorios\PagamentoRepositorio;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/** Agregações do dashboard financeiro (previsto × recebido por competência, inadimplência, formas, envelhecimento). */
final class DashboardServico
{
    public const MAX_MESES = 24;

    public function __construct(
        private readonly CobrancaRepositorio $cobrancas,
        private readonly PagamentoRepositorio $pagamentos,
        private readonly CobrancaServico $servico,
    ) {
    }

    /**
     * @param string $de  AAAA-MM
     * @param string $ate AAAA-MM
     * @return array<string,mixed>
     */
    public function resumo(string $de, string $ate): array
    {
        [$inicio, $fim] = $this->periodo($de, $ate);
        $itens = array_map($this->servico->apresentar(...), $this->cobrancas->listar([
            'de' => $inicio->format('Y-m-d'),
            'ate' => $fim->format('Y-m-d'),
        ]));
        $geral = CobrancaServico::resumir($itens);

        return [
            'periodo' => ['de' => $de, 'ate' => $ate],
            'kpis' => [
                'previsto' => $geral['previsto'],
                'recebido' => $geral['recebido'],
                'aberto' => $geral['aberto'],
                'atrasado' => $geral['atrasado'],
                'inadimplencia' => $geral['previsto'] > 0 ? round($geral['atrasado'] / $geral['previsto'] * 100, 1) : 0.0,
                'cobrancas' => $geral['quantidade'],
                'por_estado' => $geral['por_estado'],
            ],
            'por_mes' => $this->porMes($itens, $inicio, $fim),
            'por_forma' => $this->formasNoPeriodo($inicio, $fim),
            'envelhecimento' => $this->envelhecimento($itens),
            'ranking' => $this->ranking($itens),
        ];
    }

    /** @return array{0:DateTimeImmutable,1:DateTimeImmutable} */
    public function periodo(string $de, string $ate): array
    {
        foreach ([$de, $ate] as $c) {
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $c) !== 1) {
                throw new ErroValidacao(['periodo' => 'Informe o período como AAAA-MM.']);
            }
        }
        $inicio = new DateTimeImmutable($de . '-01');
        $fim = new DateTimeImmutable($ate . '-01');
        if ($fim < $inicio) {
            throw new ErroValidacao(['periodo' => 'O mês final deve ser igual ou posterior ao inicial.']);
        }
        if ((int) $inicio->diff($fim)->format('%y') * 12 + (int) $inicio->diff($fim)->format('%m') >= self::MAX_MESES) {
            throw new ErroValidacao(['periodo' => 'O período máximo é de ' . self::MAX_MESES . ' meses.']);
        }
        return [$inicio, $fim];
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @return list<array<string,mixed>>
     */
    public function porMes(array $itens, DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $grupos = [];
        foreach ($itens as $c) {
            $grupos[$c['competencia']][] = $c;
        }
        $saida = [];
        foreach (new DatePeriod($inicio, new DateInterval('P1M'), $fim->modify('+1 day')) as $mes) {
            $chave = $mes->format('Y-m');
            $r = CobrancaServico::resumir($grupos[$chave] ?? []);
            $saida[] = [
                'competencia' => $chave,
                'previsto' => $r['previsto'],
                'recebido' => $r['recebido'],
                'aberto' => $r['aberto'],
                'atrasado' => $r['atrasado'],
            ];
        }
        return $saida;
    }

    /** @return list<array{forma:string,quantidade:int,valor:float}> */
    public function formasNoPeriodo(DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        return array_map(static fn (array $l): array => [
            'forma' => (string) $l['forma'],
            'quantidade' => (int) $l['quantidade'],
            'valor' => round((float) $l['valor'], 2),
        ], $this->pagamentos->porForma($inicio->format('Y-m-d'), $fim->format('Y-m-d')));
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @return list<array{faixa:string,quantidade:int,valor:float}>
     */
    public function envelhecimento(array $itens): array
    {
        $faixas = ['1 a 30 dias' => [0, 0.0], '31 a 60 dias' => [0, 0.0], '61 dias ou mais' => [0, 0.0]];
        foreach ($itens as $c) {
            if ($c['estado'] !== EstadoCobranca::ATRASADO) {
                continue;
            }
            $faixa = $c['dias_atraso'] <= 30 ? '1 a 30 dias' : ($c['dias_atraso'] <= 60 ? '31 a 60 dias' : '61 dias ou mais');
            $faixas[$faixa][0]++;
            $faixas[$faixa][1] += $c['saldo'];
        }
        $saida = [];
        foreach ($faixas as $nome => [$qtd, $valor]) {
            $saida[] = ['faixa' => $nome, 'quantidade' => $qtd, 'valor' => round($valor, 2)];
        }
        return $saida;
    }

    /**
     * Alunos com mais saldo atrasado (até 10).
     *
     * @param list<array<string,mixed>> $itens
     * @return list<array{aluno_id:int,aluno:string,cobrancas:int,saldo:float,maior_atraso:int}>
     */
    public function ranking(array $itens): array
    {
        $por = [];
        foreach ($itens as $c) {
            if ($c['estado'] !== EstadoCobranca::ATRASADO) {
                continue;
            }
            $id = $c['aluno_id'];
            $por[$id] ??= ['aluno_id' => $id, 'aluno' => (string) $c['aluno'], 'cobrancas' => 0, 'saldo' => 0.0, 'maior_atraso' => 0];
            $por[$id]['cobrancas']++;
            $por[$id]['saldo'] = round($por[$id]['saldo'] + $c['saldo'], 2);
            $por[$id]['maior_atraso'] = max($por[$id]['maior_atraso'], $c['dias_atraso']);
        }
        usort($por, static fn (array $a, array $b): int => $b['saldo'] <=> $a['saldo']);
        return array_slice(array_values($por), 0, 10);
    }
}

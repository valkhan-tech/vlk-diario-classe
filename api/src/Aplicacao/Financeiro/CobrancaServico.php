<?php

declare(strict_types=1);

namespace App\Aplicacao\Financeiro;

use App\Dominio\Financeiro\Dinheiro;
use App\Dominio\Financeiro\EstadoCobranca;
use App\Http\Erros\Conflito;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Relogio;
use App\Infra\Repositorios\AlunoRepositorio;
use App\Infra\Repositorios\CobrancaRepositorio;
use App\Infra\Repositorios\PagamentoRepositorio;
use DateTimeImmutable;

final class CobrancaServico
{
    public function __construct(
        private readonly CobrancaRepositorio $cobrancas,
        private readonly PagamentoRepositorio $pagamentos,
        private readonly AlunoRepositorio $alunos,
        private readonly Relogio $relogio,
    ) {
    }

    /**
     * @param array{competencia?:?string,aluno_id?:?int,estado?:?string,de?:?string,ate?:?string} $filtros competencia = AAAA-MM
     * @return array{dados:list<array<string,mixed>>,resumo:array<string,mixed>}
     */
    public function listar(array $filtros = []): array
    {
        $consulta = $filtros;
        if (!empty($consulta['competencia'])) {
            $consulta['competencia'] .= '-01';
        }
        unset($consulta['estado']);
        $itens = array_map($this->apresentar(...), $this->cobrancas->listar($consulta));
        if (!empty($filtros['estado'])) {
            $itens = array_values(array_filter($itens, static fn (array $c): bool => $c['estado'] === $filtros['estado']));
        }
        return ['dados' => $itens, 'resumo' => self::resumir($itens)];
    }

    /** @return array<string,mixed> */
    public function obter(int $id): array
    {
        $c = $this->cobrancas->buscarComPago($id) ?? throw new NaoEncontrado('Cobrança não encontrada.');
        return $this->apresentar($c) + ['pagamentos' => array_map(self::apresentarPagamento(...), $this->pagamentos->daCobranca($id))];
    }

    /**
     * @param array<string,mixed> $dados valor, desconto, vencimento, situacao, observacoes
     * @return array<string,mixed>
     */
    public function atualizar(int $id, array $dados): array
    {
        $atual = $this->cobrancas->obter($id);
        $valor = Dinheiro::centavos($dados['valor'] ?? $atual['valor']);
        $desconto = Dinheiro::centavos($dados['desconto'] ?? $atual['desconto']);
        if ($desconto > $valor) {
            throw new \App\Http\Erros\ErroValidacao(['desconto' => 'O desconto não pode ser maior que o valor.']);
        }
        foreach (['valor', 'desconto'] as $c) {
            if (isset($dados[$c])) {
                $dados[$c] = number_format((float) $dados[$c], 2, '.', '');
            }
        }
        $this->cobrancas->atualizar($id, $dados);
        return $this->obter($id);
    }

    /**
     * Ficha financeira do aluno: cobranças com estado, pagamentos e totais.
     *
     * @return array<string,mixed>
     */
    public function fichaFinanceira(int $alunoId): array
    {
        $aluno = $this->alunos->buscarComInstrumento($alunoId) ?? throw new NaoEncontrado('Aluno não encontrado.');
        $cobrancas = array_map($this->apresentar(...), $this->cobrancas->listar(['aluno_id' => $alunoId]));
        foreach ($cobrancas as &$c) {
            $c['pagamentos'] = array_map(self::apresentarPagamento(...), $this->pagamentos->daCobranca($c['id']));
        }
        unset($c);
        return [
            'aluno' => [
                'id' => (int) $aluno['id'],
                'nome' => $aluno['nome'],
                'valor_mensalidade' => (float) $aluno['valor_mensalidade'],
                'dia_vencimento' => (int) $aluno['dia_vencimento'],
                'situacao' => $aluno['situacao'],
            ],
            'cobrancas' => $cobrancas,
            'resumo' => self::resumir($cobrancas),
        ];
    }

    /**
     * @param array<string,mixed> $c linha com 'pago' (DECIMAL somado) e 'aluno'
     * @return array<string,mixed>
     */
    public function apresentar(array $c): array
    {
        $valor = Dinheiro::centavos((string) $c['valor']);
        $desconto = Dinheiro::centavos((string) $c['desconto']);
        $pago = Dinheiro::centavos((string) $c['pago']);
        $liquido = $valor - $desconto;
        $vencimento = new DateTimeImmutable((string) $c['vencimento']);
        $hoje = $this->relogio->hoje();
        $estado = EstadoCobranca::calcular((string) $c['situacao'], $valor, $desconto, $pago, $vencimento, $hoje);

        return [
            'id' => (int) $c['id'],
            'aluno_id' => (int) $c['aluno_id'],
            'aluno' => $c['aluno'] ?? null,
            'competencia' => substr((string) $c['competencia'], 0, 7),
            'valor' => Dinheiro::numero($valor),
            'desconto' => Dinheiro::numero($desconto),
            'valor_liquido' => Dinheiro::numero($liquido),
            'pago' => Dinheiro::numero($pago),
            'saldo' => Dinheiro::numero(max(0, $liquido - $pago)),
            'vencimento' => $c['vencimento'],
            'dias_atraso' => $estado === EstadoCobranca::ATRASADO ? (int) $vencimento->diff($hoje)->days : 0,
            'situacao' => $c['situacao'],
            'estado' => $estado,
            'observacoes' => $c['observacoes'],
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    public static function apresentarPagamento(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'cobranca_id' => (int) $p['cobranca_id'],
            'valor' => (float) $p['valor'],
            'data_pagamento' => $p['data_pagamento'],
            'forma_pagamento_id' => $p['forma_pagamento_id'] !== null ? (int) $p['forma_pagamento_id'] : null,
            'forma' => $p['forma'] ?? null,
            'observacoes' => $p['observacoes'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $itens cobranças apresentadas
     * @return array<string,mixed>
     */
    public static function resumir(array $itens): array
    {
        $r = ['quantidade' => count($itens), 'previsto' => 0.0, 'recebido' => 0.0, 'aberto' => 0.0, 'atrasado' => 0.0, 'por_estado' => array_fill_keys(EstadoCobranca::todos(), 0)];
        foreach ($itens as $c) {
            $r['por_estado'][$c['estado']]++;
            if ($c['estado'] === EstadoCobranca::CANCELADA) {
                continue;
            }
            $r['previsto'] += $c['valor_liquido'];
            $r['recebido'] += $c['pago'];
            $r['aberto'] += $c['saldo'];
            if ($c['estado'] === EstadoCobranca::ATRASADO) {
                $r['atrasado'] += $c['saldo'];
            }
        }
        foreach (['previsto', 'recebido', 'aberto', 'atrasado'] as $k) {
            $r[$k] = round($r[$k], 2);
        }
        return $r;
    }

    public function exigirAberta(int $id): void
    {
        $c = $this->cobrancas->obter($id);
        if ($c['situacao'] === 'cancelada') {
            throw new Conflito('Cobrança cancelada não recebe pagamentos.');
        }
    }
}

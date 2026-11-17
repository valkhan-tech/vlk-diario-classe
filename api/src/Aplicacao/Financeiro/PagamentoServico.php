<?php

declare(strict_types=1);

namespace App\Aplicacao\Financeiro;

use App\Dominio\Financeiro\Dinheiro;
use App\Http\Erros\ErroValidacao;
use App\Infra\Repositorios\CobrancaRepositorio;
use App\Infra\Repositorios\ListaRepositorio;
use App\Infra\Repositorios\PagamentoRepositorio;

final class PagamentoServico
{
    public function __construct(
        private readonly PagamentoRepositorio $pagamentos,
        private readonly CobrancaRepositorio $cobrancas,
        private readonly ListaRepositorio $listas,
        private readonly CobrancaServico $servico,
    ) {
    }

    /**
     * Baixa total ou parcial. Não aceita valor acima do saldo.
     *
     * @param array{valor:float|int|string,data_pagamento:string,forma_pagamento_id?:?int,observacoes?:?string} $dados
     * @return array<string,mixed> cobrança atualizada
     */
    public function registrar(int $cobrancaId, array $dados): array
    {
        $this->servico->exigirAberta($cobrancaId);
        $cobranca = $this->cobrancas->buscarComPago($cobrancaId);
        $atual = $this->servico->apresentar($cobranca ?? []);
        $valor = Dinheiro::centavos($dados['valor']);

        if ($valor <= 0) {
            throw new ErroValidacao(['valor' => 'Informe um valor maior que zero.']);
        }
        if ($valor > Dinheiro::centavos($atual['saldo'])) {
            throw new ErroValidacao(['valor' => 'O valor é maior que o saldo da cobrança.']);
        }
        if (!empty($dados['forma_pagamento_id']) && $this->listas->buscarDoTipo((int) $dados['forma_pagamento_id'], 'forma_pagamento') === null) {
            throw new ErroValidacao(['forma_pagamento_id' => 'Forma de pagamento inválida.']);
        }

        $this->pagamentos->inserir([
            'cobranca_id' => $cobrancaId,
            'valor' => Dinheiro::decimal($valor),
            'data_pagamento' => $dados['data_pagamento'],
            'forma_pagamento_id' => !empty($dados['forma_pagamento_id']) ? (int) $dados['forma_pagamento_id'] : null,
            'observacoes' => ($dados['observacoes'] ?? '') !== '' ? $dados['observacoes'] : null,
        ]);
        return $this->servico->obter($cobrancaId);
    }

    /**
     * Estorno: exclusão lógica do pagamento (a cobrança volta a refletir o saldo).
     *
     * @return array<string,mixed> cobrança atualizada
     */
    public function estornar(int $pagamentoId): array
    {
        $pagamento = $this->pagamentos->obter($pagamentoId);
        $this->pagamentos->excluir($pagamentoId);
        return $this->servico->obter((int) $pagamento['cobranca_id']);
    }
}

<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class PagamentoRepositorio extends RepositorioBase
{
    public function tabela(): string
    {
        return 'pagamentos';
    }

    public function colunas(): array
    {
        return ['cobranca_id', 'valor', 'data_pagamento', 'forma_pagamento_id', 'observacoes'];
    }

    /** @return list<array<string,mixed>> */
    public function daCobranca(int $cobrancaId): array
    {
        return $this->db->consultar(
            'SELECT p.*, f.valor AS forma FROM pagamentos p
               LEFT JOIN listas f ON f.id = p.forma_pagamento_id
              WHERE p.cobranca_id = ? AND p.sys_del IS NULL ORDER BY p.data_pagamento, p.id',
            [$cobrancaId]
        );
    }

    public function totalPago(int $cobrancaId): int
    {
        return (int) round(((float) $this->db->escalar(
            'SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE cobranca_id = ? AND sys_del IS NULL',
            [$cobrancaId]
        )) * 100);
    }
}

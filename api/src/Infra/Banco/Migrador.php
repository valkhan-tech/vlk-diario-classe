<?php

declare(strict_types=1);

namespace App\Infra\Banco;

/** Aplica as migrações SQL (uso em desenvolvimento e testes; em produção a execução é manual). */
final class Migrador
{
    public const PADRAO_NOME = '/^\d{14}_[a-z0-9_]+\.sql$/';

    public function __construct(private readonly Conexao $db, private readonly string $diretorio)
    {
    }

    /** @return list<string> nomes dos arquivos, em ordem */
    public function arquivos(): array
    {
        $arquivos = array_map('basename', glob(rtrim($this->diretorio, '/\\') . '/*.sql') ?: []);
        sort($arquivos);
        return $arquivos;
    }

    /** @return list<string> arquivos com nome fora do padrão */
    public function nomesInvalidos(): array
    {
        return array_values(array_filter(
            $this->arquivos(),
            static fn (string $a): bool => preg_match(self::PADRAO_NOME, $a) !== 1
        ));
    }

    /** @return list<string> */
    public function aplicadas(): array
    {
        $existe = $this->db->escalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sys_migracao'"
        );
        if ((int) $existe === 0) {
            return [];
        }
        return array_column($this->db->consultar('SELECT arquivo FROM sys_migracao ORDER BY arquivo'), 'arquivo');
    }

    /** @return list<string> */
    public function pendentes(): array
    {
        return array_values(array_diff($this->arquivos(), $this->aplicadas()));
    }

    /** @return list<string> arquivos aplicados nesta chamada */
    public function aplicar(): array
    {
        $feitas = [];
        foreach ($this->pendentes() as $arquivo) {
            if (preg_match(self::PADRAO_NOME, $arquivo) !== 1) {
                throw new \RuntimeException("Nome de migração fora do padrão: {$arquivo}");
            }
            $sql = (string) file_get_contents(rtrim($this->diretorio, '/\\') . '/' . $arquivo);
            $pdo = $this->db->pdo();
            // múltiplas instruções exigem prepares emulados
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
            try {
                $stmt = $pdo->query($sql);
                // percorre todos os resultados para que erros de instruções posteriores sejam lançados
                while ($stmt->nextRowset()) {
                }
            } finally {
                $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            }
            if (!in_array($arquivo, $this->aplicadas(), true)) {
                throw new \RuntimeException("A migração {$arquivo} não se registrou em sys_migracao.");
            }
            $feitas[] = $arquivo;
        }
        return $feitas;
    }

    /** Apaga TODAS as tabelas do banco atual (somente para bancos de teste/desenvolvimento). */
    public function apagarTudo(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tabelas = $this->db->consultar(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        foreach ($tabelas as $linha) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $linha['t'] . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

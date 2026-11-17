<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use PDO;
use PDOStatement;

/** Conexão PDO preguiçosa com transações aninháveis (savepoints). */
final class Conexao
{
    private ?PDO $pdo = null;
    private int $nivel = 0;

    /** @param array{host:string,porta:int,nome:string,usuario:string,senha:string} $cfg */
    public function __construct(private readonly array $cfg)
    {
    }

    public function nomeBanco(): string
    {
        return $this->cfg['nome'];
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->cfg['host'],
                $this->cfg['porta'],
                $this->cfg['nome']
            );
            $this->pdo = new PDO($dsn, $this->cfg['usuario'], $this->cfg['senha'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-03:00'",
            ]);
        }
        return $this->pdo;
    }

    /**
     * Executa $fn dentro de uma transação (ou savepoint, se já houver uma).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transacao(callable $fn): mixed
    {
        $pdo = $this->pdo();
        $ponto = 'sp' . $this->nivel;
        if ($this->nivel === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT {$ponto}");
        }
        $this->nivel++;
        try {
            $resultado = $fn();
        } catch (\Throwable $e) {
            $this->nivel--;
            if ($this->nivel === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp{$this->nivel}");
            }
            throw $e;
        }
        $this->nivel--;
        if ($this->nivel === 0) {
            $pdo->commit();
        } else {
            $pdo->exec("RELEASE SAVEPOINT sp{$this->nivel}");
        }
        return $resultado;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function consultar(string $sql, array $params = []): array
    {
        return $this->preparar($sql, $params)->fetchAll();
    }

    /** @param array<int|string,mixed> $params @return array<string,mixed>|null */
    public function um(string $sql, array $params = []): ?array
    {
        $linha = $this->preparar($sql, $params)->fetch();
        return $linha === false ? null : $linha;
    }

    /** @param array<int|string,mixed> $params */
    public function escalar(string $sql, array $params = []): mixed
    {
        $linha = $this->preparar($sql, $params)->fetch(PDO::FETCH_NUM);
        return $linha === false ? null : $linha[0];
    }

    /** @param array<int|string,mixed> $params */
    public function executar(string $sql, array $params = []): int
    {
        return $this->preparar($sql, $params)->rowCount();
    }

    public function ultimoId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<int|string,mixed> $params */
    private function preparar(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $chave => $valor) {
            $posicao = is_int($chave) ? $chave + 1 : $chave;
            $tipo = match (true) {
                $valor === null => PDO::PARAM_NULL,
                is_bool($valor) => PDO::PARAM_BOOL,
                is_int($valor) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($posicao, $valor, $tipo);
        }
        $stmt->execute();
        return $stmt;
    }
}

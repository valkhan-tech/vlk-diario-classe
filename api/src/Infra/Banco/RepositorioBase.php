<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Http\Erros\NaoEncontrado;
use App\Infra\Auditoria\RegistradorLog;

/**
 * Base dos repositórios de tabelas de negócio. Toda gravação acontece em transação:
 * executa, RELÊ o registro e grava sys_log (com fingerprint). Se o log falhar, a operação é desfeita.
 */
abstract class RepositorioBase
{
    public function __construct(protected readonly Conexao $db, protected readonly RegistradorLog $log)
    {
    }

    abstract public function tabela(): string;

    /** @return list<string> colunas graváveis (sem id nem sys_*) */
    abstract public function colunas(): array;

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        return $this->db->um(
            sprintf('SELECT * FROM `%s` WHERE id = ? AND sys_del IS NULL', $this->tabela()),
            [$id]
        );
    }

    /** @return array<string,mixed> */
    public function obter(int $id): array
    {
        return $this->buscar($id) ?? throw new NaoEncontrado('Registro não encontrado.');
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed> registro criado (relido)
     */
    public function inserir(array $dados): array
    {
        $dados = $this->filtrar($dados);
        return $this->db->transacao(function () use ($dados): array {
            if ($dados === []) {
                $this->db->executar(sprintf('INSERT INTO `%s` () VALUES ()', $this->tabela()));
            } else {
                $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", array_keys($dados)));
                $marcas = implode(', ', array_fill(0, count($dados), '?'));
                $this->db->executar(
                    sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $this->tabela(), $cols, $marcas),
                    array_values($dados)
                );
            }
            $id = $this->db->ultimoId();
            $registro = $this->lerPorId($id);
            $this->log->registrar('INSERT', $this->tabela(), $id, $registro);
            return $registro;
        });
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed> registro atualizado (relido)
     */
    public function atualizar(int $id, array $dados): array
    {
        $dados = $this->filtrar($dados);
        return $this->db->transacao(function () use ($id, $dados): array {
            $this->obter($id);
            if ($dados !== []) {
                $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($dados)));
                $this->db->executar(
                    sprintf('UPDATE `%s` SET %s WHERE id = ? AND sys_del IS NULL', $this->tabela(), $sets),
                    [...array_values($dados), $id]
                );
            }
            $registro = $this->lerPorId($id);
            $this->log->registrar('UPDATE', $this->tabela(), $id, $registro);
            return $registro;
        });
    }

    /** Exclusão lógica (preenche sys_del). */
    public function excluir(int $id): void
    {
        $this->db->transacao(function () use ($id): void {
            $this->obter($id);
            $this->db->executar(
                sprintf('UPDATE `%s` SET sys_del = NOW() WHERE id = ? AND sys_del IS NULL', $this->tabela()),
                [$id]
            );
            $this->log->registrar('DELETE', $this->tabela(), $id, $this->lerPorId($id));
        });
    }

    /** @return array<string,mixed> inclui registros excluídos */
    protected function lerPorId(int $id): array
    {
        return $this->db->um(sprintf('SELECT * FROM `%s` WHERE id = ?', $this->tabela()), [$id])
            ?? throw new \LogicException('Registro não encontrado após gravar.');
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function filtrar(array $dados): array
    {
        $desconhecidas = array_diff(array_keys($dados), $this->colunas());
        if ($desconhecidas !== []) {
            throw new \LogicException(
                'Colunas não graváveis em ' . $this->tabela() . ': ' . implode(', ', $desconhecidas)
            );
        }
        return $dados;
    }
}

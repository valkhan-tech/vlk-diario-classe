<?php

declare(strict_types=1);

namespace App\Aplicacao\Cadastros;

use App\Http\Erros\Conflito;
use App\Http\Erros\ErroValidacao;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Banco\Conexao;
use App\Infra\Repositorios\ListaRepositorio;

final class ListaServico
{
    /** tipo => [tabela, coluna] que referenciam o item da lista */
    private const USOS = [
        'instrumento' => ['alunos', 'instrumento_id'],
        'tipo_aula' => ['aulas', 'tipo_aula_id'],
        'forma_pagamento' => ['pagamentos', 'forma_pagamento_id'],
    ];

    public function __construct(private readonly ListaRepositorio $listas, private readonly Conexao $db)
    {
    }

    public function exigirTipo(string $tipo): void
    {
        if (!in_array($tipo, ListaRepositorio::TIPOS, true)) {
            throw new NaoEncontrado('Tipo de lista desconhecido.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function listar(string $tipo, bool $apenasAtivos = false): array
    {
        $this->exigirTipo($tipo);
        return array_map(self::apresentar(...), $this->listas->porTipo($tipo, $apenasAtivos));
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function criar(string $tipo, array $dados): array
    {
        $this->exigirTipo($tipo);
        $this->exigirUnico($tipo, (string) $dados['valor'], null);
        return self::apresentar($this->listas->inserir([
            'tipo' => $tipo,
            'valor' => $dados['valor'],
            'ordem' => (int) ($dados['ordem'] ?? 0),
            'ativo' => ($dados['ativo'] ?? true) ? 1 : 0,
        ]));
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function atualizar(string $tipo, int $id, array $dados): array
    {
        $this->exigirTipo($tipo);
        $this->obterDoTipo($id, $tipo);
        if (isset($dados['valor'])) {
            $this->exigirUnico($tipo, (string) $dados['valor'], $id);
        }
        if (isset($dados['ativo'])) {
            $dados['ativo'] = $dados['ativo'] ? 1 : 0;
        }
        return self::apresentar($this->listas->atualizar($id, $dados));
    }

    public function excluir(string $tipo, int $id): void
    {
        $this->exigirTipo($tipo);
        $this->obterDoTipo($id, $tipo);
        [$tabela, $coluna] = self::USOS[$tipo];
        $existe = (int) $this->db->escalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tabela]
        ) > 0;
        if ($existe) {
            $uso = (int) $this->db->escalar("SELECT COUNT(*) FROM `{$tabela}` WHERE `{$coluna}` = ? AND sys_del IS NULL", [$id]);
            if ($uso > 0) {
                throw new Conflito("Item em uso por {$uso} registro(s). Desative-o em vez de excluir.");
            }
        }
        $this->listas->excluir($id);
    }

    /** @return array<string,mixed> */
    private function obterDoTipo(int $id, string $tipo): array
    {
        return $this->listas->buscarDoTipo($id, $tipo) ?? throw new NaoEncontrado('Item não encontrado.');
    }

    private function exigirUnico(string $tipo, string $valor, ?int $exceto): void
    {
        if ($this->listas->existeValor($tipo, $valor, $exceto)) {
            throw new ErroValidacao(['valor' => 'Já existe um item com este nome.']);
        }
    }

    /**
     * @param array<string,mixed> $linha
     * @return array<string,mixed>
     */
    private static function apresentar(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'tipo' => $linha['tipo'],
            'valor' => $linha['valor'],
            'ordem' => (int) $linha['ordem'],
            'ativo' => (bool) $linha['ativo'],
        ];
    }
}

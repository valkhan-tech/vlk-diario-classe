<?php

declare(strict_types=1);

namespace App\Aplicacao\Cadastros;

use App\Dominio\Alunos\Idade;
use App\Http\Erros\ErroValidacao;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Relogio;
use App\Infra\Repositorios\AlunoRepositorio;
use App\Infra\Repositorios\ListaRepositorio;
use DateTimeImmutable;

final class AlunoServico
{
    public function __construct(
        private readonly AlunoRepositorio $alunos,
        private readonly ListaRepositorio $listas,
        private readonly Relogio $relogio,
    ) {
    }

    /**
     * @param array{q?:?string,instrumento_id?:?int,situacao?:?string,incluir_encerrados?:bool} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = []): array
    {
        return array_map($this->apresentar(...), $this->alunos->listar($filtros));
    }

    /** @return array<string,mixed> */
    public function obter(int $id): array
    {
        $linha = $this->alunos->buscarComInstrumento($id) ?? throw new NaoEncontrado('Aluno não encontrado.');
        return $this->apresentar($linha);
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function criar(array $dados): array
    {
        $this->validarInstrumento($dados);
        $criado = $this->alunos->inserir($this->normalizar($dados) + ['situacao' => 'ativo']);
        return $this->obter((int) $criado['id']);
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function atualizar(int $id, array $dados): array
    {
        $this->alunos->obter($id);
        $this->validarInstrumento($dados);
        $this->alunos->atualizar($id, $this->normalizar($dados));
        return $this->obter($id);
    }

    public function excluir(int $id): void
    {
        $this->alunos->excluir($id);
    }

    /** @param array<string,mixed> $dados */
    private function validarInstrumento(array $dados): void
    {
        if (!empty($dados['instrumento_id']) && $this->listas->buscarDoTipo((int) $dados['instrumento_id'], 'instrumento') === null) {
            throw new ErroValidacao(['instrumento_id' => 'Instrumento inválido.']);
        }
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function normalizar(array $dados): array
    {
        foreach (['data_nascimento', 'data_inicio', 'contato', 'responsavel_nome', 'observacoes', 'hora_aula', 'instrumento_id', 'dia_semana'] as $campo) {
            if (array_key_exists($campo, $dados) && $dados[$campo] === '') {
                $dados[$campo] = null;
            }
        }
        if (isset($dados['hora_aula']) && strlen((string) $dados['hora_aula']) === 5) {
            $dados['hora_aula'] .= ':00';
        }
        return $dados;
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function apresentar(array $a): array
    {
        $hoje = $this->relogio->hoje();
        return [
            'id' => (int) $a['id'],
            'nome' => $a['nome'],
            'data_nascimento' => $a['data_nascimento'],
            'faixa_etaria' => $a['data_nascimento'] ? Idade::faixaEtaria(new DateTimeImmutable((string) $a['data_nascimento']), $hoje) : null,
            'instrumento_id' => $a['instrumento_id'] !== null ? (int) $a['instrumento_id'] : null,
            'instrumento' => $a['instrumento'] ?? null,
            'contato' => $a['contato'],
            'responsavel_nome' => $a['responsavel_nome'],
            'dia_semana' => $a['dia_semana'] !== null ? (int) $a['dia_semana'] : null,
            'hora_aula' => $a['hora_aula'] !== null ? substr((string) $a['hora_aula'], 0, 5) : null,
            'valor_mensalidade' => (float) $a['valor_mensalidade'],
            'dia_vencimento' => (int) $a['dia_vencimento'],
            'data_inicio' => $a['data_inicio'],
            'meses_ativos' => $a['data_inicio'] ? Idade::mesesAtivos(new DateTimeImmutable((string) $a['data_inicio']), $hoje) : null,
            'situacao' => $a['situacao'],
            'observacoes' => $a['observacoes'],
        ];
    }
}

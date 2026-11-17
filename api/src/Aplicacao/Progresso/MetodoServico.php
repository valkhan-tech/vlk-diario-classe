<?php

declare(strict_types=1);

namespace App\Aplicacao\Progresso;

use App\Http\Erros\Conflito;
use App\Http\Erros\ErroValidacao;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Banco\Conexao;
use App\Infra\Repositorios\ListaRepositorio;
use App\Infra\Repositorios\LicaoRepositorio;
use App\Infra\Repositorios\MetodoRepositorio;

/** Cadastro de métodos (incluindo hinários) e das lições de cada método. */
final class MetodoServico
{
    public function __construct(
        private readonly MetodoRepositorio $metodos,
        private readonly LicaoRepositorio $licoes,
        private readonly ListaRepositorio $listas,
        private readonly Conexao $db,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listar(bool $apenasAtivos = false, ?string $tipo = null): array
    {
        return array_map(self::apresentar(...), $this->metodos->listar($apenasAtivos, $tipo));
    }

    /** @return array<string,mixed> */
    public function obter(int $id): array
    {
        return self::apresentar($this->metodos->buscarDetalhado($id) ?? throw new NaoEncontrado('Método não encontrado.'));
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function criar(array $dados): array
    {
        $this->validar($dados, null);
        $criado = $this->metodos->inserir($this->normalizar($dados) + ['tipo' => 'metodo', 'ativo' => 1]);
        return $this->obter((int) $criado['id']);
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function atualizar(int $id, array $dados): array
    {
        $this->metodos->obter($id);
        $this->validar($dados, $id);
        $this->metodos->atualizar($id, $this->normalizar($dados));
        return $this->obter($id);
    }

    public function excluir(int $id): void
    {
        $this->metodos->obter($id);
        $alunos = $this->metodos->totalAlunos($id);
        if ($alunos > 0) {
            throw new Conflito("O método está associado a {$alunos} aluno(s). Desative-o em vez de excluir.");
        }
        $this->db->transacao(function () use ($id): void {
            foreach ($this->licoes->doMetodo($id) as $licao) {
                $this->licoes->excluir((int) $licao['id']);
            }
            $this->metodos->excluir($id);
        });
    }

    /* ---------- lições ---------- */

    /** @return list<array<string,mixed>> */
    public function licoes(int $metodoId): array
    {
        $this->metodos->obter($metodoId);
        return array_map(self::apresentarLicao(...), $this->licoes->doMetodo($metodoId));
    }

    /**
     * @param array{titulo:string,ordem?:?int,observacoes?:?string} $dados
     * @return array<string,mixed>
     */
    public function criarLicao(int $metodoId, array $dados): array
    {
        $this->metodos->obter($metodoId);
        $ordem = isset($dados['ordem']) ? (int) $dados['ordem'] : $this->licoes->proximaOrdem($metodoId);
        $this->exigirOrdemLivre($metodoId, $ordem, null);
        return self::apresentarLicao($this->licoes->inserir([
            'metodo_id' => $metodoId,
            'ordem' => $ordem,
            'titulo' => $dados['titulo'],
            'observacoes' => ($dados['observacoes'] ?? '') !== '' ? $dados['observacoes'] : null,
        ]));
    }

    /**
     * Cadastro em lote: cada linha não vazia vira uma lição, em sequência (indispensável para os 430 hinos).
     *
     * @return array{criadas:int,total:int}
     */
    public function colarLista(int $metodoId, string $texto): array
    {
        $this->metodos->obter($metodoId);
        $titulos = array_values(array_filter(
            array_map(static fn (string $l): string => trim($l), preg_split('/\R/u', $texto) ?: []),
            static fn (string $l): bool => $l !== ''
        ));
        if ($titulos === []) {
            throw new ErroValidacao(['texto' => 'Cole ao menos uma linha.']);
        }
        foreach ($titulos as $t) {
            if (mb_strlen($t) > 255) {
                throw new ErroValidacao(['texto' => 'Cada linha deve ter até 255 caracteres.']);
            }
        }
        $this->db->transacao(function () use ($metodoId, $titulos): void {
            $ordem = $this->licoes->proximaOrdem($metodoId);
            foreach ($titulos as $titulo) {
                $this->licoes->inserir(['metodo_id' => $metodoId, 'ordem' => $ordem++, 'titulo' => $titulo]);
            }
        });
        return ['criadas' => count($titulos), 'total' => count($this->licoes->doMetodo($metodoId))];
    }

    /**
     * @param array<string,mixed> $dados titulo, observacoes
     * @return array<string,mixed>
     */
    public function atualizarLicao(int $licaoId, array $dados): array
    {
        $this->licoes->obter($licaoId);
        return self::apresentarLicao($this->licoes->atualizar($licaoId, $dados));
    }

    public function excluirLicao(int $licaoId): void
    {
        $this->licoes->obter($licaoId);
        $avaliacoes = $this->licoes->totalAvaliacoes($licaoId);
        if ($avaliacoes > 0) {
            throw new Conflito("A lição possui {$avaliacoes} avaliação(ões) registrada(s) e não pode ser excluída.");
        }
        $this->licoes->excluir($licaoId);
    }

    /**
     * Reordena as lições do método conforme a lista de ids (deve conter todas, sem repetição).
     *
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    public function reordenar(int $metodoId, array $ids): array
    {
        $atuais = array_map(static fn (array $l): int => (int) $l['id'], $this->licoes->doMetodo($metodoId));
        $esperado = $atuais;
        $recebido = array_map('intval', $ids);
        sort($esperado);
        sort($recebido);
        if ($esperado !== $recebido) {
            throw new ErroValidacao(['ids' => 'Informe todas as lições do método, sem repetir.']);
        }
        $this->db->transacao(function () use ($ids): void {
            // duas passagens para não violar a unicidade (método, ordem) no meio do caminho
            foreach ($ids as $i => $id) {
                $this->licoes->atualizar((int) $id, ['ordem' => 100000 + $i + 1]);
            }
            foreach ($ids as $i => $id) {
                $this->licoes->atualizar((int) $id, ['ordem' => $i + 1]);
            }
        });
        return $this->licoes($metodoId);
    }

    /* ---------- internos ---------- */

    /** @param array<string,mixed> $dados */
    private function validar(array $dados, ?int $exceto): void
    {
        if (isset($dados['nome']) && $this->metodos->existeNome((string) $dados['nome'], $exceto)) {
            throw new ErroValidacao(['nome' => 'Já existe um método com este nome.']);
        }
        if (!empty($dados['instrumento_id']) && $this->listas->buscarDoTipo((int) $dados['instrumento_id'], 'instrumento') === null) {
            throw new ErroValidacao(['instrumento_id' => 'Instrumento inválido.']);
        }
    }

    private function exigirOrdemLivre(int $metodoId, int $ordem, ?int $exceto): void
    {
        foreach ($this->licoes->doMetodo($metodoId) as $l) {
            if ((int) $l['ordem'] === $ordem && (int) $l['id'] !== $exceto) {
                throw new ErroValidacao(['ordem' => 'Já existe uma lição nesta posição.']);
            }
        }
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function normalizar(array $dados): array
    {
        foreach (['nivel', 'instrumento_id', 'observacoes'] as $c) {
            if (array_key_exists($c, $dados) && $dados[$c] === '') {
                $dados[$c] = null;
            }
        }
        if (isset($dados['ativo'])) {
            $dados['ativo'] = $dados['ativo'] ? 1 : 0;
        }
        return $dados;
    }

    /**
     * @param array<string,mixed> $m
     * @return array<string,mixed>
     */
    public static function apresentar(array $m): array
    {
        return [
            'id' => (int) $m['id'],
            'nome' => $m['nome'],
            'tipo' => $m['tipo'],
            'nivel' => $m['nivel'],
            'instrumento_id' => $m['instrumento_id'] !== null ? (int) $m['instrumento_id'] : null,
            'instrumento' => $m['instrumento'] ?? null,
            'ativo' => (bool) $m['ativo'],
            'observacoes' => $m['observacoes'],
            'total_licoes' => (int) ($m['total_licoes'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $l
     * @return array<string,mixed>
     */
    public static function apresentarLicao(array $l): array
    {
        return [
            'id' => (int) $l['id'],
            'metodo_id' => (int) $l['metodo_id'],
            'ordem' => (int) $l['ordem'],
            'titulo' => $l['titulo'],
            'observacoes' => $l['observacoes'],
        ];
    }
}

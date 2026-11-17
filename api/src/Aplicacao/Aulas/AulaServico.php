<?php

declare(strict_types=1);

namespace App\Aplicacao\Aulas;

use App\Dominio\Aulas\Frequencia;
use App\Dominio\Aulas\Presenca;
use App\Http\Erros\ErroValidacao;
use App\Http\Erros\NaoEncontrado;
use App\Infra\Repositorios\AlunoRepositorio;
use App\Infra\Repositorios\AulaRepositorio;
use App\Infra\Repositorios\ListaRepositorio;

final class AulaServico
{
    public function __construct(
        private readonly AulaRepositorio $aulas,
        private readonly AlunoRepositorio $alunos,
        private readonly ListaRepositorio $listas,
    ) {
    }

    /**
     * @param array{aluno_id?:?int,de?:?string,ate?:?string,limite?:?int} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = []): array
    {
        return array_map(self::apresentar(...), $this->aulas->listar($filtros));
    }

    /** @return array<string,mixed> */
    public function obter(int $id): array
    {
        return self::apresentar($this->aulas->buscarDetalhada($id) ?? throw new NaoEncontrado('Aula não encontrada.'));
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function criar(array $dados): array
    {
        $this->validarVinculos($dados);
        $criada = $this->aulas->inserir($this->normalizar($dados));
        return $this->obter((int) $criada['id']);
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function atualizar(int $id, array $dados): array
    {
        $this->aulas->obter($id);
        $this->validarVinculos($dados);
        $this->aulas->atualizar($id, $this->normalizar($dados));
        return $this->obter($id);
    }

    public function excluir(int $id): void
    {
        $this->aulas->excluir($id);
    }

    /** @return array<string,mixed>|null última aula antes da data (exibe a lição de casa anterior) */
    public function anterior(int $alunoId, string $antesDe): ?array
    {
        $linha = $this->aulas->anteriorDoAluno($alunoId, $antesDe);
        return $linha === null ? null : self::apresentar($linha);
    }

    /** @return array{aulas:int,presentes:int,faltas_aluno:int,faltas_professora:int,feriados:int,frequencia:?float} */
    public function resumoDoAluno(int $alunoId, ?string $de = null, ?string $ate = null): array
    {
        $presencas = $this->aulas->presencasDoAluno($alunoId, $de, $ate);
        $conta = array_count_values($presencas);
        return [
            'aulas' => count($presencas),
            'presentes' => $conta[Presenca::Presente->value] ?? 0,
            'faltas_aluno' => ($conta[Presenca::FaltaAlunoJustificada->value] ?? 0) + ($conta[Presenca::FaltaAlunoSemAviso->value] ?? 0),
            'faltas_professora' => $conta[Presenca::FaltaProfessora->value] ?? 0,
            'feriados' => $conta[Presenca::FeriadoEvento->value] ?? 0,
            'frequencia' => Frequencia::percentual($presencas),
        ];
    }

    /** @param array<string,mixed> $dados */
    private function validarVinculos(array $dados): void
    {
        if (isset($dados['aluno_id']) && $this->alunos->buscar((int) $dados['aluno_id']) === null) {
            throw new ErroValidacao(['aluno_id' => 'Aluno não encontrado.']);
        }
        if (!empty($dados['tipo_aula_id']) && $this->listas->buscarDoTipo((int) $dados['tipo_aula_id'], 'tipo_aula') === null) {
            throw new ErroValidacao(['tipo_aula_id' => 'Tipo de aula inválido.']);
        }
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function normalizar(array $dados): array
    {
        foreach (['motivo', 'tipo_aula_id', 'metodo_id', 'conteudo', 'hino', 'licao_casa', 'video_url', 'observacoes'] as $c) {
            if (array_key_exists($c, $dados) && $dados[$c] === '') {
                $dados[$c] = null;
            }
        }
        return $dados;
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    public static function apresentar(array $a): array
    {
        return [
            'id' => (int) $a['id'],
            'aluno_id' => (int) $a['aluno_id'],
            'aluno' => $a['aluno'] ?? null,
            'data' => $a['data'],
            'presenca' => $a['presenca'],
            'motivo' => $a['motivo'],
            'tipo_aula_id' => $a['tipo_aula_id'] !== null ? (int) $a['tipo_aula_id'] : null,
            'tipo_aula' => $a['tipo_aula'] ?? null,
            'metodo_id' => $a['metodo_id'] !== null ? (int) $a['metodo_id'] : null,
            'conteudo' => $a['conteudo'],
            'hino' => $a['hino'],
            'licao_casa' => $a['licao_casa'],
            'video_url' => $a['video_url'],
            'observacoes' => $a['observacoes'],
        ];
    }
}

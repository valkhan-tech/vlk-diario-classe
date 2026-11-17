<?php

declare(strict_types=1);

namespace App\Aplicacao\Aulas;

use App\Dominio\Aulas\Presenca;
use App\Infra\Relogio;
use App\Infra\Repositorios\AlunoRepositorio;
use App\Infra\Repositorios\AulaRepositorio;
use DateTimeImmutable;

final class AgendaServico
{
    public function __construct(
        private readonly AlunoRepositorio $alunos,
        private readonly AulaRepositorio $aulas,
        private readonly Relogio $relogio,
    ) {
    }

    /**
     * Alunos ativos com horário no dia da semana de $data, marcando quem já teve aula lançada.
     *
     * @return array{data:string,itens:list<array<string,mixed>>}
     */
    public function doDia(?string $data = null): array
    {
        $dia = $data !== null ? new DateTimeImmutable($data) : $this->relogio->hoje();
        $diaSemana = (int) $dia->format('w');
        $iso = $dia->format('Y-m-d');

        $lancadas = [];
        foreach ($this->aulas->doDia($iso) as $aula) {
            $lancadas[(int) $aula['aluno_id']] = $aula;
        }

        $agenda = [];
        foreach ($this->alunos->ativos() as $aluno) {
            if ($aluno['dia_semana'] === null || (int) $aluno['dia_semana'] !== $diaSemana) {
                continue;
            }
            $aula = $lancadas[(int) $aluno['id']] ?? null;
            $agenda[] = [
                'aluno_id' => (int) $aluno['id'],
                'aluno' => $aluno['nome'],
                'hora' => $aluno['hora_aula'] !== null ? substr((string) $aluno['hora_aula'], 0, 5) : null,
                'aula_id' => $aula !== null ? (int) $aula['id'] : null,
                'presenca' => $aula['presenca'] ?? null,
            ];
        }
        usort($agenda, static fn (array $a, array $b): int => [$a['hora'] ?? '99:99', $a['aluno']] <=> [$b['hora'] ?? '99:99', $b['aluno']]);
        return ['data' => $iso, 'itens' => $agenda];
    }

    /**
     * Calendário do mês: aulas por dia e resumo.
     *
     * @return array{mes:string,resumo:array<string,int>,dias:array<string,list<array<string,mixed>>>}
     */
    public function calendario(string $mes): array
    {
        $inicio = new DateTimeImmutable($mes . '-01');
        $fim = $inicio->modify('last day of this month');
        $dias = [];
        $resumo = ['aulas' => 0, 'presentes' => 0, 'faltas_aluno' => 0, 'faltas_professora' => 0, 'feriados' => 0];

        foreach ($this->aulas->doPeriodo($inicio->format('Y-m-d'), $fim->format('Y-m-d')) as $aula) {
            $presenca = Presenca::from((string) $aula['presenca']);
            $resumo['aulas']++;
            match (true) {
                $presenca === Presenca::Presente => $resumo['presentes']++,
                $presenca->ehFaltaDoAluno() => $resumo['faltas_aluno']++,
                $presenca === Presenca::FaltaProfessora => $resumo['faltas_professora']++,
                default => $resumo['feriados']++,
            };
            $dias[(string) $aula['data']][] = [
                'id' => (int) $aula['id'],
                'aluno_id' => (int) $aula['aluno_id'],
                'aluno' => $aula['aluno'],
                'presenca' => $aula['presenca'],
            ];
        }
        return ['mes' => $mes, 'resumo' => $resumo, 'dias' => $dias];
    }
}

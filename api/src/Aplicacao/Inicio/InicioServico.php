<?php

declare(strict_types=1);

namespace App\Aplicacao\Inicio;

use App\Aplicacao\Aulas\AgendaServico;
use App\Aplicacao\Financeiro\CobrancaServico;
use App\Aplicacao\Progresso\ProgressoCalculadora;
use App\Aplicacao\Progresso\ProgressoServico;
use App\Dominio\Financeiro\EstadoCobranca;
use App\Dominio\Progresso\Estagio;
use App\Infra\Relogio;
use App\Infra\Repositorios\AlunoRepositorio;
use DateTimeImmutable;

/** Painel inicial: KPIs reais, agenda do dia, estágios, números do mês, aniversariantes e notificações. */
final class InicioServico
{
    public function __construct(
        private readonly AlunoRepositorio $alunos,
        private readonly AgendaServico $agenda,
        private readonly CobrancaServico $cobrancas,
        private readonly ProgressoServico $progresso,
        private readonly ProgressoCalculadora $calculadora,
        private readonly Relogio $relogio,
    ) {
    }

    /** @return array<string,mixed> */
    public function painel(): array
    {
        $hoje = $this->relogio->hoje();
        $ativos = $this->alunos->ativos();
        $nomes = [];
        foreach ($ativos as $a) {
            $nomes[(int) $a['id']] = (string) $a['nome'];
        }
        $resumos = $this->progresso->resumoDosAlunos();

        $porEstagio = [];
        foreach (Estagio::cases() as $e) {
            $porEstagio[$e->value] = ['estagio' => $e->value, 'rotulo' => $e->rotulo(), 'quantidade' => 0];
        }
        $porEstagio['sem_estagio'] = ['estagio' => 'sem_estagio', 'rotulo' => 'Sem estágio', 'quantidade' => 0];
        $aptos = 0;
        foreach ($ativos as $a) {
            $r = $resumos[(int) $a['id']] ?? null;
            $porEstagio[$r['estagio']['valor'] ?? 'sem_estagio']['quantidade']++;
            if ($r !== null && $r['aptidao']) {
                $aptos++;
            }
        }

        $atrasadas = $this->cobrancas->listar(['estado' => EstadoCobranca::ATRASADO]);
        $agenda = $this->agenda->doDia();
        $semLancamento = array_values(array_filter($agenda['itens'], static fn (array $i): bool => $i['aula_id'] === null));
        $conclusoes = $this->conclusoesPendentes($nomes);
        $mes = $this->numerosDoMes($hoje);
        $aniversariantes = $this->aniversariantes($ativos, $hoje);

        return [
            'kpis' => [
                'alunos_ativos' => count($ativos),
                'aulas_hoje' => count($agenda['itens']),
                'aulas_hoje_lancadas' => count($agenda['itens']) - count($semLancamento),
                'cobrancas_atrasadas' => $atrasadas['resumo']['quantidade'],
                'valor_atrasado' => $atrasadas['resumo']['atrasado'],
                'aptos_culto' => $aptos,
                'conclusoes_pendentes' => count($conclusoes),
            ],
            'agenda' => $agenda,
            'por_estagio' => array_values($porEstagio),
            'mes' => $mes,
            'aniversariantes' => $aniversariantes,
            'notificacoes' => $this->notificacoes($atrasadas['resumo'], $semLancamento, $conclusoes, $aniversariantes, $hoje),
        ];
    }

    /**
     * Métodos Iniciados com todas as lições aprovadas (o sistema sugere "Concluído").
     *
     * @param array<int,string> $nomes
     * @return list<array{aluno_id:int,aluno:string,metodo:string}>
     */
    private function conclusoesPendentes(array $nomes): array
    {
        $lista = [];
        foreach ($this->calculadora->paraAlunos() as $alunoId => $r) {
            if (!isset($nomes[$alunoId])) {
                continue; // só alunos ativos
            }
            foreach ($r['metodos'] as $m) {
                if ($m['concluir_sugerido']) {
                    $lista[] = ['aluno_id' => $alunoId, 'aluno' => $nomes[$alunoId], 'metodo' => (string) $m['metodo']];
                }
            }
        }
        return $lista;
    }

    /** @return array{competencia:string,aulas:int,frequencia:?float,previsto:float,recebido:float,vencido:float} */
    private function numerosDoMes(DateTimeImmutable $hoje): array
    {
        $competencia = $hoje->format('Y-m');
        $cal = $this->agenda->calendario($competencia)['resumo'];
        $base = $cal['presentes'] + $cal['faltas_aluno'];
        $fin = $this->cobrancas->listar(['competencia' => $competencia])['resumo'];
        return [
            'competencia' => $competencia,
            'aulas' => $cal['aulas'],
            'frequencia' => $base > 0 ? round($cal['presentes'] / $base * 100, 1) : null,
            'previsto' => $fin['previsto'],
            'recebido' => $fin['recebido'],
            'vencido' => $fin['atrasado'],
        ];
    }

    /**
     * Aniversários dos alunos ativos nos próximos 7 dias (inclui hoje).
     *
     * @param list<array<string,mixed>> $ativos
     * @return list<array{aluno_id:int,aluno:string,data:string,idade:int,hoje:bool}>
     */
    private function aniversariantes(array $ativos, DateTimeImmutable $hoje): array
    {
        $saida = [];
        foreach ($ativos as $a) {
            if ($a['data_nascimento'] === null) {
                continue;
            }
            $nasc = new DateTimeImmutable((string) $a['data_nascimento']);
            foreach ([(int) $hoje->format('Y'), (int) $hoje->format('Y') + 1] as $ano) {
                $dia = (int) $nasc->format('j');
                $mes = (int) $nasc->format('n');
                if ($mes === 2 && $dia === 29 && !checkdate(2, 29, $ano)) {
                    $dia = 28;
                }
                $aniversario = $hoje->setDate($ano, $mes, $dia);
                $faltam = (int) $hoje->diff($aniversario)->format('%r%a');
                if ($faltam >= 0 && $faltam <= 7) {
                    $saida[] = [
                        'aluno_id' => (int) $a['id'],
                        'aluno' => (string) $a['nome'],
                        'data' => $aniversario->format('Y-m-d'),
                        'idade' => $ano - (int) $nasc->format('Y'),
                        'hoje' => $faltam === 0,
                    ];
                    break;
                }
            }
        }
        usort($saida, static fn (array $x, array $y): int => $x['data'] <=> $y['data']);
        return $saida;
    }

    /**
     * @param array<string,mixed> $resumoAtrasadas
     * @param list<array<string,mixed>> $semLancamento
     * @param list<array{aluno_id:int,aluno:string,metodo:string}> $conclusoes
     * @param list<array<string,mixed>> $aniversariantes
     * @return list<array{tipo:string,titulo:string,detalhe:string,link:string}>
     */
    private function notificacoes(array $resumoAtrasadas, array $semLancamento, array $conclusoes, array $aniversariantes, DateTimeImmutable $hoje): array
    {
        $n = [];
        if ($resumoAtrasadas['quantidade'] > 0) {
            $q = $resumoAtrasadas['quantidade'];
            $n[] = [
                'tipo' => 'erro',
                'titulo' => $q === 1 ? '1 cobrança atrasada' : "{$q} cobranças atrasadas",
                'detalhe' => 'R$ ' . number_format((float) $resumoAtrasadas['atrasado'], 2, ',', '.') . ' em aberto',
                'link' => '/financeiro',
            ];
        }
        foreach ($semLancamento as $i) {
            $n[] = [
                'tipo' => 'info',
                'titulo' => 'Aula de hoje sem lançamento',
                'detalhe' => $i['aluno'] . ($i['hora'] ? ' · ' . $i['hora'] : ''),
                'link' => '/aulas/nova?aluno=' . $i['aluno_id'] . '&data=' . $hoje->format('Y-m-d'),
            ];
        }
        foreach ($conclusoes as $c) {
            $n[] = [
                'tipo' => 'ok',
                'titulo' => 'Todas as lições aprovadas',
                'detalhe' => $c['aluno'] . ' concluiu “' . $c['metodo'] . '” — confirme a conclusão',
                'link' => '/alunos/' . $c['aluno_id'],
            ];
        }
        foreach ($aniversariantes as $a) {
            if ($a['hoje']) {
                $n[] = ['tipo' => 'info', 'titulo' => 'Aniversário hoje', 'detalhe' => $a['aluno'] . ' completa ' . $a['idade'] . ' anos', 'link' => '/alunos/' . $a['aluno_id']];
            }
        }
        return $n;
    }
}

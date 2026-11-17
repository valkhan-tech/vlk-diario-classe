<?php

declare(strict_types=1);

namespace App\Aplicacao\Inicio;

use App\Aplicacao\Aulas\AgendaServico;
use App\Aplicacao\Financeiro\CobrancaServico;
use App\Aplicacao\Progresso\ProgressoServico;
use App\Dominio\Financeiro\EstadoCobranca;
use App\Dominio\Progresso\Estagio;
use App\Infra\Repositorios\AlunoRepositorio;

/** Painel inicial: KPIs reais, agenda do dia e distribuição por estágio efetivo. */
final class InicioServico
{
    public function __construct(
        private readonly AlunoRepositorio $alunos,
        private readonly AgendaServico $agenda,
        private readonly CobrancaServico $cobrancas,
        private readonly ProgressoServico $progresso,
    ) {
    }

    /** @return array<string,mixed> */
    public function painel(): array
    {
        $ativos = $this->alunos->ativos();
        $resumos = $this->progresso->resumoDosAlunos();

        $porEstagio = [];
        foreach (Estagio::cases() as $e) {
            $porEstagio[$e->value] = ['estagio' => $e->value, 'rotulo' => $e->rotulo(), 'quantidade' => 0];
        }
        $porEstagio['sem_estagio'] = ['estagio' => 'sem_estagio', 'rotulo' => 'Sem estágio', 'quantidade' => 0];
        $aptos = 0;
        foreach ($ativos as $a) {
            $r = $resumos[(int) $a['id']] ?? null;
            $chave = $r['estagio']['valor'] ?? 'sem_estagio';
            $porEstagio[$chave]['quantidade']++;
            if ($r !== null && $r['aptidao']) {
                $aptos++;
            }
        }

        $atrasadas = $this->cobrancas->listar(['estado' => EstadoCobranca::ATRASADO]);
        $agenda = $this->agenda->doDia();

        return [
            'kpis' => [
                'alunos_ativos' => count($ativos),
                'aulas_hoje' => count($agenda['itens']),
                'aulas_hoje_lancadas' => count(array_filter($agenda['itens'], static fn (array $i): bool => $i['aula_id'] !== null)),
                'cobrancas_atrasadas' => $atrasadas['resumo']['quantidade'],
                'valor_atrasado' => $atrasadas['resumo']['atrasado'],
                'aptos_culto' => $aptos,
            ],
            'agenda' => $agenda,
            'por_estagio' => array_values($porEstagio),
        ];
    }
}

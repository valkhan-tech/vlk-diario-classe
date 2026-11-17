<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infra\Auditoria\ContextoOperacao;
use App\Infra\Auditoria\Fingerprint;
use App\Infra\Auditoria\RegistradorLog;
use App\Infra\Banco\RepositorioBase;

final class RepositorioBaseTest extends TestCaseBanco
{
    private function repo(?RegistradorLog $log = null): RepositorioBase
    {
        $log ??= $this->container->get(RegistradorLog::class);
        return new class ($this->db, $log) extends RepositorioBase {
            public function tabela(): string
            {
                return 'listas';
            }

            public function colunas(): array
            {
                return ['tipo', 'valor', 'ordem', 'ativo'];
            }
        };
    }

    public function test_inserir_grava_o_registro_e_o_log_com_fingerprint_recalculavel(): void
    {
        $contexto = $this->container->get(ContextoOperacao::class);
        $contexto->usuarioId = null;
        $contexto->origem = 'POST /api/v1/listas/instrumento';
        $contexto->controller = 'ListasController:criar';

        $criado = $this->repo()->inserir(['tipo' => 'instrumento', 'valor' => 'Piano', 'ordem' => 1]);

        $this->assertSame('Piano', $criado['valor']);
        $log = $this->db->um('SELECT * FROM sys_log WHERE tabela = ? AND registro_id = ?', ['listas', $criado['id']]);
        $this->assertNotNull($log);
        $this->assertSame('INSERT', $log['operacao']);
        $this->assertSame('POST /api/v1/listas/instrumento', $log['origem']);
        $this->assertSame('ListasController:criar', $log['controller']);
        $this->assertSame(Fingerprint::calcular(json_decode($log['registro_json'], true)), $log['fingerprint']);
        $this->assertSame('Piano', json_decode($log['registro_json'], true)['valor']);
    }

    public function test_atualizar_e_excluir_geram_logs_e_a_exclusao_e_logica(): void
    {
        $repo = $this->repo();
        $criado = $repo->inserir(['tipo' => 'instrumento', 'valor' => 'Flauta']);
        $repo->atualizar((int) $criado['id'], ['valor' => 'Flauta doce']);
        $repo->excluir((int) $criado['id']);

        $this->assertNull($repo->buscar((int) $criado['id']));
        $linha = $this->db->um('SELECT * FROM listas WHERE id = ?', [$criado['id']]);
        $this->assertNotNull($linha['sys_del'], 'sys_del deve ser preenchido');
        $operacoes = array_column($this->db->consultar('SELECT operacao FROM sys_log ORDER BY id'), 'operacao');
        $this->assertSame(['INSERT', 'UPDATE', 'DELETE'], $operacoes);
        $ultimo = $this->db->um('SELECT registro_json FROM sys_log ORDER BY id DESC LIMIT 1');
        $this->assertNotNull(json_decode($ultimo['registro_json'], true)['sys_del'], 'o log do DELETE traz o registro após a ação');
    }

    public function test_falha_ao_gravar_o_log_desfaz_a_operacao(): void
    {
        $logQueFalha = new class ($this->db, $this->container->get(ContextoOperacao::class)) extends RegistradorLog {
            public function registrar(string $operacao, string $tabela, int $id, array $registro): void
            {
                throw new \RuntimeException('falha ao logar');
            }
        };

        try {
            $this->repo($logQueFalha)->inserir(['tipo' => 'instrumento', 'valor' => 'Órgão']);
            $this->fail('Deveria lançar exceção.');
        } catch (\RuntimeException $e) {
            $this->assertSame('falha ao logar', $e->getMessage());
        }

        $this->assertSame(0, (int) $this->db->escalar('SELECT COUNT(*) FROM listas'));
    }

    public function test_unicidade_considera_apenas_registros_nao_excluidos(): void
    {
        $repo = $this->repo();
        $primeiro = $repo->inserir(['tipo' => 'instrumento', 'valor' => 'Violão']);

        try {
            $repo->inserir(['tipo' => 'instrumento', 'valor' => 'Violão']);
            $this->fail('Duplicidade deveria falhar.');
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
        }

        $repo->excluir((int) $primeiro['id']);
        $segundo = $repo->inserir(['tipo' => 'instrumento', 'valor' => 'Violão']);
        $this->assertNotSame($primeiro['id'], $segundo['id']);
    }

    public function test_coluna_nao_gravavel_e_recusada(): void
    {
        $this->expectException(\LogicException::class);
        $this->repo()->inserir(['tipo' => 'instrumento', 'valor' => 'X', 'sys_del' => '2026-01-01']);
    }
}

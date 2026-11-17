<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infra\Banco\Migrador;
use App\Kernel;

final class MigracoesTest extends TestCaseBanco
{
    private function migrador(): Migrador
    {
        return new Migrador($this->db, Kernel::raiz() . '/migrations');
    }

    public function test_todos_os_arquivos_seguem_o_padrao_de_nome(): void
    {
        $this->assertSame([], $this->migrador()->nomesInvalidos());
    }

    public function test_todas_as_migracoes_estao_aplicadas_e_registradas(): void
    {
        $this->assertSame([], $this->migrador()->pendentes());
        $this->assertSame($this->migrador()->arquivos(), $this->migrador()->aplicadas());
    }

    public function test_toda_tabela_tem_as_colunas_sys(): void
    {
        $tabelas = array_column($this->db->consultar(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'
        ), 't');

        $this->assertNotEmpty($tabelas);
        foreach ($tabelas as $tabela) {
            if ($tabela === 'sys_migracao') {
                continue;
            }
            $colunas = array_column($this->db->consultar(
                'SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?',
                [$tabela]
            ), 'c');
            foreach (['id', 'sys_cri', 'sys_alt', 'sys_del'] as $obrigatoria) {
                $this->assertContains($obrigatoria, $colunas, "A tabela {$tabela} não tem a coluna {$obrigatoria}.");
            }
        }
    }

    public function test_sys_alt_e_sys_del_aceitam_nulo_e_sys_cri_tem_default(): void
    {
        $linhas = $this->db->consultar(
            "SELECT table_name AS t, column_name AS c, is_nullable AS n, column_default AS d
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND column_name IN ('sys_cri','sys_alt','sys_del')
                AND table_name <> 'sys_migracao'"
        );
        foreach ($linhas as $l) {
            if ($l['c'] === 'sys_cri') {
                $this->assertSame('NO', $l['n'], "{$l['t']}.sys_cri deve ser NOT NULL");
                $this->assertNotNull($l['d'], "{$l['t']}.sys_cri deve ter DEFAULT");
            } else {
                $this->assertSame('YES', $l['n'], "{$l['t']}.{$l['c']} deve aceitar NULL");
            }
        }
    }
}

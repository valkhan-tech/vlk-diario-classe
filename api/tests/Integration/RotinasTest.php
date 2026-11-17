<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Aplicacao\Acesso\LimpezaTecnica;

final class RotinasTest extends TestCaseApi
{
    public function test_limpeza_remove_so_o_que_venceu(): void
    {
        $u = $this->criarUsuario();
        $this->entrarComo($u); // sessão válida
        $this->db->executar("INSERT INTO sessoes (usuario_id, token_hash, expira_em) VALUES (?, 'a', DATE_SUB(NOW(), INTERVAL 1 DAY))", [$u['id']]);
        $this->db->executar("INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em) VALUES (?, 'b', DATE_SUB(NOW(), INTERVAL 1 DAY))", [$u['id']]);
        $this->db->executar("INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em) VALUES (?, 'c', DATE_ADD(NOW(), INTERVAL 1 DAY))", [$u['id']]);
        $this->db->executar("INSERT INTO tentativas_login (email, ip, sucesso, sys_cri) VALUES ('x@y.z', '1.1.1.1', 0, DATE_SUB(NOW(), INTERVAL 31 DAY))");
        $this->db->executar("INSERT INTO tentativas_login (email, ip, sucesso) VALUES ('x@y.z', '1.1.1.1', 0)");

        $r = $this->container->get(LimpezaTecnica::class)->executar();

        $this->assertSame(['sessoes' => 1, 'redefinicoes' => 1, 'tentativas' => 1], array_intersect_key($r, ['sessoes' => 1, 'redefinicoes' => 1, 'tentativas' => 1]));
        $this->assertSame(1, (int) $this->db->escalar('SELECT COUNT(*) FROM sessoes'), 'sessão válida permanece');
        $this->assertSame(1, (int) $this->db->escalar('SELECT COUNT(*) FROM redefinicoes_senha'));
        $this->assertSame(2, (int) $this->db->escalar('SELECT COUNT(*) FROM tentativas_login'), 'a recente e a do login de teste permanecem');
    }
}

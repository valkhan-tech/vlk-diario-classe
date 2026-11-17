<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infra\Email\EmailEmMemoria;
use App\Infra\Email\EmailEnviador;

final class AcessoTest extends TestCaseApi
{
    public function test_login_ok_define_cookie_httponly_e_nao_expoe_o_hash(): void
    {
        $u = $this->criarUsuario('professora');

        $r = $this->requisicao('POST', '/api/v1/sessao', ['email' => $u['email'], 'senha' => $u['senha_pura']]);

        $this->assertSame(200, $r->getStatusCode());
        $cookie = $r->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('ddc_sessao=', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
        $corpo = $this->corpo($r);
        $this->assertSame('professora', $corpo['usuario']['perfil']);
        $this->assertArrayNotHasKey('senha_hash', $corpo['usuario']);
    }

    public function test_no_banco_so_existe_o_hash_do_token(): void
    {
        $u = $this->criarUsuario();
        $cookies = $this->entrarComo($u);

        $linha = $this->db->um('SELECT token_hash FROM sessoes');
        $this->assertNotSame($cookies['ddc_sessao'], $linha['token_hash']);
        $this->assertSame(hash('sha256', $cookies['ddc_sessao']), $linha['token_hash']);
    }

    public function test_credenciais_invalidas_respondem_401_generico(): void
    {
        $u = $this->criarUsuario();

        $erradaSenha = $this->requisicao('POST', '/api/v1/sessao', ['email' => $u['email'], 'senha' => 'errada123']);
        $erradoEmail = $this->requisicao('POST', '/api/v1/sessao', ['email' => 'ninguem@ddc.test', 'senha' => 'errada123']);

        $this->assertSame(401, $erradaSenha->getStatusCode());
        $this->assertSame(401, $erradoEmail->getStatusCode());
        $this->assertSame($this->corpo($erradaSenha)['title'], $this->corpo($erradoEmail)['title']);
    }

    public function test_a_quinta_falha_bloqueia_ate_com_a_senha_correta(): void
    {
        $u = $this->criarUsuario();
        for ($i = 0; $i < 5; $i++) {
            $this->requisicao('POST', '/api/v1/sessao', ['email' => $u['email'], 'senha' => 'errada123']);
        }

        $r = $this->requisicao('POST', '/api/v1/sessao', ['email' => $u['email'], 'senha' => $u['senha_pura']]);

        $this->assertSame(429, $r->getStatusCode());
    }

    public function test_sem_sessao_responde_401_e_com_sessao_responde_o_usuario(): void
    {
        $u = $this->criarUsuario();

        $this->assertSame(401, $this->requisicao('GET', '/api/v1/eu')->getStatusCode());

        $r = $this->requisicao('GET', '/api/v1/eu', null, $this->entrarComo($u));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame($u['email'], $this->corpo($r)['usuario']['email']);
    }

    public function test_sessao_expirada_e_logout_invalidam_o_acesso(): void
    {
        $u = $this->criarUsuario();
        $cookies = $this->entrarComo($u);
        $this->db->executar('UPDATE sessoes SET expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
        $this->assertSame(401, $this->requisicao('GET', '/api/v1/eu', null, $cookies)->getStatusCode());

        $cookies = $this->entrarComo($u);
        $this->assertSame(204, $this->requisicao('DELETE', '/api/v1/sessao', null, $cookies)->getStatusCode());
        $this->assertSame(401, $this->requisicao('GET', '/api/v1/eu', null, $cookies)->getStatusCode());
    }

    public function test_requisicao_que_grava_sem_cabecalho_csrf_e_recusada(): void
    {
        $u = $this->criarUsuario();

        $r = $this->requisicao('POST', '/api/v1/sessao', ['email' => $u['email'], 'senha' => $u['senha_pura']], [], [], false);

        $this->assertSame(403, $r->getStatusCode());
    }

    public function test_professora_nao_acessa_usuarios_mas_admin_acessa(): void
    {
        $prof = $this->criarUsuario('professora');
        $admin = $this->criarUsuario('admin');

        $this->assertSame(401, $this->requisicao('GET', '/api/v1/usuarios')->getStatusCode());
        $this->assertSame(403, $this->requisicao('GET', '/api/v1/usuarios', null, $this->entrarComo($prof))->getStatusCode());
        $this->assertSame(200, $this->requisicao('GET', '/api/v1/usuarios', null, $this->entrarComo($admin))->getStatusCode());
    }

    public function test_admin_cria_usuario_com_log_do_autor_e_senha_mascarada(): void
    {
        $admin = $this->criarUsuario('admin');
        $cookies = $this->entrarComo($admin);

        $r = $this->requisicao('POST', '/api/v1/usuarios', [
            'nome' => 'Nova Professora', 'email' => 'Nova@DDC.test', 'perfil' => 'professora', 'senha' => 'Provisoria1',
        ], $cookies);

        $this->assertSame(201, $r->getStatusCode());
        $criado = $this->corpo($r)['dados'];
        $this->assertSame('nova@ddc.test', $criado['email']);
        $this->assertTrue($criado['troca_senha_obrigatoria']);
        $log = $this->db->um("SELECT * FROM sys_log WHERE tabela = 'usuarios' AND operacao = 'INSERT' AND registro_id = ?", [$criado['id']]);
        $this->assertSame((int) $admin['id'], (int) $log['usuario_id']);
        $this->assertSame('POST /api/v1/usuarios', $log['origem']);
        $this->assertStringContainsString('UsuariosController:criar', (string) $log['controller']);
        $this->assertSame('***', json_decode($log['registro_json'], true)['senha_hash']);
    }

    public function test_senha_definida_pelo_admin_exige_troca_antes_de_usar_o_sistema(): void
    {
        $admin = $this->criarUsuario('admin');
        $adminCookies = $this->entrarComo($admin);
        $prof = $this->criarUsuario('professora');
        $this->requisicao('POST', "/api/v1/usuarios/{$prof['id']}/senha", ['senha' => 'Temporaria9'], $adminCookies);
        $prof['senha_pura'] = 'Temporaria9';

        $cookies = $this->entrarComo($prof);
        $this->assertSame(200, $this->requisicao('GET', '/api/v1/eu', null, $cookies)->getStatusCode());
        $bloqueada = $this->requisicao('GET', '/api/v1/usuarios', null, $cookies);
        $this->assertSame(403, $bloqueada->getStatusCode());
        $this->assertSame('troca_senha_obrigatoria', $this->corpo($bloqueada)['codigo']);

        $troca = $this->requisicao('POST', '/api/v1/senha/trocar', ['atual' => 'Temporaria9', 'nova' => 'Definitiva7'], $cookies);
        $this->assertSame(204, $troca->getStatusCode());
        $this->assertSame(0, (int) $this->db->escalar('SELECT troca_senha_obrigatoria FROM usuarios WHERE id = ?', [$prof['id']]));
    }

    public function test_senha_fraca_e_recusada_com_422(): void
    {
        $admin = $this->criarUsuario('admin');

        $r = $this->requisicao('POST', '/api/v1/usuarios', [
            'nome' => 'X', 'email' => 'x@ddc.test', 'perfil' => 'professora', 'senha' => 'curta',
        ], $this->entrarComo($admin));

        $this->assertSame(422, $r->getStatusCode());
        $this->assertArrayHasKey('senha', $this->corpo($r)['erros']);
    }

    public function test_nao_remove_o_ultimo_administrador(): void
    {
        $admin = $this->criarUsuario('admin');
        $outro = $this->criarUsuario('admin');
        $cookies = $this->entrarComo($admin);

        $this->assertSame(409, $this->requisicao('DELETE', "/api/v1/usuarios/{$admin['id']}", null, $cookies)->getStatusCode(), 'não pode excluir a si mesmo');
        $this->assertSame(204, $this->requisicao('DELETE', "/api/v1/usuarios/{$outro['id']}", null, $cookies)->getStatusCode());
        $r = $this->requisicao('PUT', "/api/v1/usuarios/{$admin['id']}", ['ativo' => false], $cookies);
        $this->assertSame(409, $r->getStatusCode());
    }

    public function test_recuperacao_de_senha_por_email_com_token_de_uso_unico(): void
    {
        $u = $this->criarUsuario();
        /** @var EmailEmMemoria $email */
        $email = $this->container->get(EmailEnviador::class);

        $this->assertSame(204, $this->requisicao('POST', '/api/v1/senha/recuperar', ['email' => 'ninguem@ddc.test'])->getStatusCode());
        $this->assertCount(0, $email->enviados);

        $this->assertSame(204, $this->requisicao('POST', '/api/v1/senha/recuperar', ['email' => $u['email']])->getStatusCode());
        $this->assertCount(1, $email->enviados);
        preg_match('/token=([0-9a-f]{64})/', $email->enviados[0]['corpo'], $m);
        $this->assertNotEmpty($m[1] ?? null);

        $ok = $this->requisicao('POST', '/api/v1/senha/redefinir', ['token' => $m[1], 'senha' => 'NovaSenha55']);
        $this->assertSame(204, $ok->getStatusCode());
        $u['senha_pura'] = 'NovaSenha55';
        $this->entrarComo($u);

        $reuso = $this->requisicao('POST', '/api/v1/senha/redefinir', ['token' => $m[1], 'senha' => 'OutraSenha66']);
        $this->assertSame(422, $reuso->getStatusCode());
    }

    public function test_token_de_recuperacao_expirado_e_recusado(): void
    {
        $u = $this->criarUsuario();
        /** @var EmailEmMemoria $email */
        $email = $this->container->get(EmailEnviador::class);
        $this->requisicao('POST', '/api/v1/senha/recuperar', ['email' => $u['email']]);
        preg_match('/token=([0-9a-f]{64})/', $email->enviados[0]['corpo'], $m);
        $this->db->executar('UPDATE redefinicoes_senha SET expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE)');

        $r = $this->requisicao('POST', '/api/v1/senha/redefinir', ['token' => $m[1], 'senha' => 'NovaSenha55']);

        $this->assertSame(422, $r->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

final class CadastrosTest extends TestCaseApi
{
    /** @return array<string,string> */
    private function professora(): array
    {
        return $this->entrarComo($this->criarUsuario('professora'));
    }

    /** @return array<string,mixed> */
    private function aluno(array $cookies, array $extra = []): array
    {
        $r = $this->requisicao('POST', '/api/v1/alunos', $extra + [
            'nome' => 'Ana Souza', 'valor_mensalidade' => 150, 'dia_vencimento' => 10,
        ], $cookies);
        $this->assertSame(201, $r->getStatusCode(), (string) $r->getBody());
        return $this->corpo($r)['dados'];
    }

    public function test_rotas_exigem_login_e_perfil_de_operacao(): void
    {
        $this->assertSame(401, $this->requisicao('GET', '/api/v1/alunos')->getStatusCode());
        $resp = $this->entrarComo($this->criarUsuario('responsavel'));
        $this->assertSame(403, $this->requisicao('GET', '/api/v1/alunos', null, $resp)->getStatusCode());
    }

    public function test_listas_criar_listar_atualizar_e_unicidade(): void
    {
        $c = $this->professora();
        $criado = $this->requisicao('POST', '/api/v1/listas/instrumento', ['valor' => 'Piano', 'ordem' => 1], $c);
        $this->assertSame(201, $criado->getStatusCode());
        $id = $this->corpo($criado)['dados']['id'];

        $dup = $this->requisicao('POST', '/api/v1/listas/instrumento', ['valor' => 'Piano'], $c);
        $this->assertSame(422, $dup->getStatusCode());

        $this->assertSame(200, $this->requisicao('PUT', "/api/v1/listas/instrumento/{$id}", ['valor' => 'Piano acústico', 'ativo' => false], $c)->getStatusCode());
        $lista = $this->corpo($this->requisicao('GET', '/api/v1/listas/instrumento?ativos=1', null, $c))['dados'];
        $this->assertCount(0, $lista, 'item inativo não aparece com ativos=1');
        $this->assertCount(1, $this->corpo($this->requisicao('GET', '/api/v1/listas/instrumento', null, $c))['dados']);
        $this->assertSame(404, $this->requisicao('GET', '/api/v1/listas/inexistente', null, $c)->getStatusCode());
    }

    public function test_cadastrar_aluno_com_derivados_e_log(): void
    {
        $c = $this->professora();
        $instr = $this->corpo($this->requisicao('POST', '/api/v1/listas/instrumento', ['valor' => 'Órgão'], $c))['dados']['id'];

        $aluno = $this->aluno($c, [
            'nome' => 'Bruno', 'instrumento_id' => $instr, 'data_nascimento' => '2010-03-01', 'data_inicio' => '2026-01-10',
            'dia_semana' => 6, 'hora_aula' => '09:00', 'dia_vencimento' => 31, 'valor_mensalidade' => '180.50',
        ]);

        $this->assertSame('Órgão', $aluno['instrumento']);
        $this->assertSame('09:00', $aluno['hora_aula']);
        $this->assertSame(180.5, $aluno['valor_mensalidade']);
        $this->assertSame('ativo', $aluno['situacao']);
        $this->assertNotNull($aluno['faixa_etaria']);
        $this->assertGreaterThanOrEqual(0, $aluno['meses_ativos']);
        $this->assertSame(1, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela='alunos' AND operacao='INSERT' AND registro_id=?", [$aluno['id']]));
    }

    public function test_dia_de_vencimento_fora_de_1_a_31_e_recusado(): void
    {
        $c = $this->professora();
        foreach ([0, 32] as $dia) {
            $r = $this->requisicao('POST', '/api/v1/alunos', ['nome' => 'X', 'valor_mensalidade' => 1, 'dia_vencimento' => $dia], $c);
            $this->assertSame(422, $r->getStatusCode(), "dia {$dia}");
            $this->assertArrayHasKey('dia_vencimento', $this->corpo($r)['erros']);
        }
    }

    public function test_encerrados_ficam_ocultos_por_padrao_e_filtros_funcionam(): void
    {
        $c = $this->professora();
        $a = $this->aluno($c, ['nome' => 'Carla']);
        $b = $this->aluno($c, ['nome' => 'Davi']);
        $this->requisicao('PUT', "/api/v1/alunos/{$b['id']}", ['situacao' => 'encerrado'], $c);

        $padrao = $this->corpo($this->requisicao('GET', '/api/v1/alunos', null, $c))['dados'];
        $this->assertSame(['Carla'], array_column($padrao, 'nome'));
        $todos = $this->corpo($this->requisicao('GET', '/api/v1/alunos?incluir_encerrados=1', null, $c))['dados'];
        $this->assertCount(2, $todos);
        $enc = $this->corpo($this->requisicao('GET', '/api/v1/alunos?situacao=encerrado', null, $c))['dados'];
        $this->assertSame(['Davi'], array_column($enc, 'nome'));
        $busca = $this->corpo($this->requisicao('GET', '/api/v1/alunos?q=car', null, $c))['dados'];
        $this->assertSame([$a['id']], array_column($busca, 'id'));
    }

    public function test_excluir_aluno_e_logico_e_aparece_no_log(): void
    {
        $c = $this->professora();
        $a = $this->aluno($c);

        $this->assertSame(204, $this->requisicao('DELETE', "/api/v1/alunos/{$a['id']}", null, $c)->getStatusCode());

        $this->assertSame(404, $this->requisicao('GET', "/api/v1/alunos/{$a['id']}", null, $c)->getStatusCode());
        $this->assertNotNull($this->db->um('SELECT sys_del FROM alunos WHERE id = ?', [$a['id']])['sys_del']);
        $this->assertSame(1, (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log WHERE tabela='alunos' AND operacao='DELETE'"));
    }

    public function test_item_de_lista_em_uso_nao_pode_ser_excluido(): void
    {
        $c = $this->professora();
        $instr = $this->corpo($this->requisicao('POST', '/api/v1/listas/instrumento', ['valor' => 'Flauta'], $c))['dados']['id'];
        $this->aluno($c, ['instrumento_id' => $instr]);

        $this->assertSame(409, $this->requisicao('DELETE', "/api/v1/listas/instrumento/{$instr}", null, $c)->getStatusCode());
    }

    public function test_instrumento_inexistente_e_recusado(): void
    {
        $c = $this->professora();
        $r = $this->requisicao('POST', '/api/v1/alunos', ['nome' => 'Eva', 'valor_mensalidade' => 1, 'dia_vencimento' => 5, 'instrumento_id' => 999], $c);
        $this->assertSame(422, $r->getStatusCode());
    }
}

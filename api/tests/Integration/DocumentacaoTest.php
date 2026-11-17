<?php

declare(strict_types=1);

namespace Tests\Integration;

final class DocumentacaoTest extends TestCaseApi
{
    public function test_somente_administrador_acessa_a_documentacao(): void
    {
        $prof = $this->criarUsuario('professora');
        $admin = $this->criarUsuario('admin');

        foreach (['/api/docs', '/api/openapi.json', '/api/docs/scalar.js'] as $uri) {
            $this->assertSame(401, $this->requisicao('GET', $uri)->getStatusCode(), "{$uri} sem sessão");
            $this->assertSame(403, $this->requisicao('GET', $uri, null, $this->entrarComo($prof))->getStatusCode(), "{$uri} professora");
            $this->assertSame(200, $this->requisicao('GET', $uri, null, $this->entrarComo($admin))->getStatusCode(), "{$uri} admin");
        }
    }

    public function test_openapi_e_valido_e_descreve_as_rotas_de_acesso(): void
    {
        $admin = $this->criarUsuario('admin');

        $r = $this->requisicao('GET', '/api/openapi.json', null, $this->entrarComo($admin));
        $doc = $this->corpo($r);

        $this->assertStringStartsWith('3.', $doc['openapi']);
        $this->assertArrayHasKey('/api/v1/sessao', $doc['paths']);
        $this->assertArrayHasKey('/api/v1/usuarios', $doc['paths']);
        $this->assertArrayHasKey('sessao', $doc['components']['securitySchemes']);
    }

    public function test_pagina_de_docs_aponta_para_o_script_hospedado_localmente(): void
    {
        $admin = $this->criarUsuario('admin');

        $r = $this->requisicao('GET', '/api/docs', null, $this->entrarComo($admin));

        $html = (string) $r->getBody();
        $this->assertStringContainsString('/api/docs/scalar.js', $html);
        $this->assertStringNotContainsString('cdn.', $html);
    }
}

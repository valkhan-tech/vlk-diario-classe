<?php

declare(strict_types=1);

namespace Tests\Integration;

final class HealthTest extends TestCaseApi
{
    public function test_health_responde_ok_com_banco(): void
    {
        $resposta = $this->requisicao('GET', '/api/v1/health');

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertStringContainsString('application/json', $resposta->getHeaderLine('Content-Type'));
        $corpo = $this->corpo($resposta);
        $this->assertSame('ok', $corpo['status']);
        $this->assertSame('ok', $corpo['banco']);
    }

    public function test_rota_inexistente_responde_problem_json_404(): void
    {
        $resposta = $this->requisicao('GET', '/api/v1/nao-existe');

        $this->assertSame(404, $resposta->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $resposta->getHeaderLine('Content-Type'));
        $this->assertSame(404, $this->corpo($resposta)['status']);
    }
}

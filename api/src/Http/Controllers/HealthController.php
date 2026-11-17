<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resposta;
use App\Infra\Banco\Conexao;
use App\Infra\Config;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HealthController
{
    public function __construct(private readonly Conexao $db, private readonly Config $config)
    {
    }

    public function verificar(Request $request, Response $response): Response
    {
        try {
            $this->db->escalar('SELECT 1');
            $banco = 'ok';
        } catch (\Throwable) {
            $banco = 'erro';
        }
        $arquivoVersao = $this->config->raizApi() . '/VERSION';
        $versao = is_file($arquivoVersao) ? trim((string) file_get_contents($arquivoVersao)) : 'dev';

        return Resposta::json($response, [
            'status' => $banco === 'ok' ? 'ok' : 'degradado',
            'versao' => $versao,
            'banco' => $banco,
        ], $banco === 'ok' ? 200 : 503);
    }
}

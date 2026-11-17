<?php

declare(strict_types=1);

namespace App\Infra\Docs;

use App\Infra\Config;
use OpenApi\Generator;

/** Gera o openapi.json a partir dos atributos em src/Http. */
final class OpenApiGerador
{
    public function __construct(private readonly Config $config)
    {
    }

    public function caminhoArquivo(): string
    {
        return $this->config->raizApi() . '/storage/openapi.json';
    }

    public function gerar(): string
    {
        $openapi = (new Generator())->generate([$this->config->raizApi() . '/src/Http']);
        $json = $openapi?->toJson();
        if ($json === null) {
            throw new \RuntimeException('Não foi possível gerar o OpenAPI.');
        }
        return $json;
    }

    public function gravar(): string
    {
        $json = $this->gerar();
        file_put_contents($this->caminhoArquivo(), $json);
        return $this->caminhoArquivo();
    }

    /** Em produção usa o arquivo gerado no pacote; fora dela, gera na hora se faltar. */
    public function obter(): string
    {
        $arquivo = $this->caminhoArquivo();
        if (is_file($arquivo)) {
            return (string) file_get_contents($arquivo);
        }
        if ($this->config->ehProducao()) {
            throw new \RuntimeException('openapi.json ausente no pacote publicado.');
        }
        return $this->gerar();
    }
}

<?php

declare(strict_types=1);

namespace App\Aplicacao\Auditoria;

use App\Http\Erros\NaoEncontrado;
use App\Infra\Auditoria\Fingerprint;
use App\Infra\Auditoria\Mascara;
use App\Infra\Banco\Conexao;

/** Consulta e verificação do log de auditoria (sys_log). Somente leitura. */
final class AuditoriaServico
{
    public function __construct(private readonly Conexao $db)
    {
    }

    /**
     * @param array{tabela?:?string,registro_id?:?int,usuario_id?:?int,operacao?:?string,de?:?string,ate?:?string,limite?:?int,pagina?:?int} $f
     * @return array{dados:list<array<string,mixed>>,total:int,pagina:int,limite:int,tabelas:list<string>}
     */
    public function listar(array $f = []): array
    {
        $onde = ['1 = 1'];
        $params = [];
        if (!empty($f['tabela'])) {
            $onde[] = 'l.tabela = ?';
            $params[] = $f['tabela'];
        }
        if (!empty($f['registro_id'])) {
            $onde[] = 'l.registro_id = ?';
            $params[] = (int) $f['registro_id'];
        }
        if (!empty($f['usuario_id'])) {
            $onde[] = 'l.usuario_id = ?';
            $params[] = (int) $f['usuario_id'];
        }
        if (!empty($f['operacao']) && in_array($f['operacao'], ['INSERT', 'UPDATE', 'DELETE'], true)) {
            $onde[] = 'l.operacao = ?';
            $params[] = $f['operacao'];
        }
        if (!empty($f['de'])) {
            $onde[] = 'l.sys_cri >= ?';
            $params[] = $f['de'] . ' 00:00:00';
        }
        if (!empty($f['ate'])) {
            $onde[] = 'l.sys_cri <= ?';
            $params[] = $f['ate'] . ' 23:59:59';
        }
        $limite = max(1, min(200, (int) ($f['limite'] ?? 50)));
        $pagina = max(1, (int) ($f['pagina'] ?? 1));
        $filtro = implode(' AND ', $onde);

        $total = (int) $this->db->escalar("SELECT COUNT(*) FROM sys_log l WHERE {$filtro}", $params);
        $linhas = $this->db->consultar(
            "SELECT l.id, l.sys_cri AS quando, l.usuario_id, u.nome AS usuario, l.usuario_perfil, l.operacao, l.tabela, l.registro_id,
                    l.origem, l.controller, l.fingerprint, l.ip
               FROM sys_log l LEFT JOIN usuarios u ON u.id = l.usuario_id
              WHERE {$filtro} ORDER BY l.id DESC LIMIT {$limite} OFFSET " . (($pagina - 1) * $limite),
            $params
        );

        return [
            'dados' => array_map(static fn (array $l): array => [
                'id' => (int) $l['id'],
                'quando' => $l['quando'],
                'usuario_id' => $l['usuario_id'] !== null ? (int) $l['usuario_id'] : null,
                'usuario' => $l['usuario'] ?? ($l['usuario_id'] === null ? 'Sistema' : null),
                'perfil' => $l['usuario_perfil'],
                'operacao' => $l['operacao'],
                'tabela' => $l['tabela'],
                'registro_id' => (int) $l['registro_id'],
                'origem' => $l['origem'],
                'controller' => $l['controller'],
                'fingerprint' => $l['fingerprint'],
                'ip' => $l['ip'],
            ], $linhas),
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'tabelas' => array_column($this->db->consultar('SELECT DISTINCT tabela FROM sys_log ORDER BY tabela'), 'tabela'),
        ];
    }

    /** @return array<string,mixed> entrada completa, com o JSON do registro */
    public function obter(int $id): array
    {
        $l = $this->db->um(
            'SELECT l.*, u.nome AS usuario FROM sys_log l LEFT JOIN usuarios u ON u.id = l.usuario_id WHERE l.id = ?',
            [$id]
        ) ?? throw new NaoEncontrado('Entrada de log não encontrada.');

        return [
            'id' => (int) $l['id'],
            'quando' => $l['sys_cri'],
            'usuario_id' => $l['usuario_id'] !== null ? (int) $l['usuario_id'] : null,
            'usuario' => $l['usuario'] ?? ($l['usuario_id'] === null ? 'Sistema' : null),
            'perfil' => $l['usuario_perfil'],
            'operacao' => $l['operacao'],
            'tabela' => $l['tabela'],
            'registro_id' => (int) $l['registro_id'],
            'origem' => $l['origem'],
            'controller' => $l['controller'],
            'ip' => $l['ip'],
            'user_agent' => $l['user_agent'],
            'fingerprint' => $l['fingerprint'],
            'registro' => json_decode((string) $l['registro_json'], true),
        ];
    }

    /**
     * Verifica a integridade:
     *  1. o fingerprint registrado confere com o hash do JSON gravado no log;
     *  2. se esta é a última entrada do registro, compara com a linha atual da tabela
     *     (acusa alteração feita fora do sistema).
     *
     * @return array{integro:bool,fingerprint_registrado:string,fingerprint_calculado:string,ultima_do_registro:bool,confere_com_banco:?bool,fingerprint_atual:?string,registro_existe:?bool}
     */
    public function verificar(int $id): array
    {
        $l = $this->db->um('SELECT * FROM sys_log WHERE id = ?', [$id]) ?? throw new NaoEncontrado('Entrada de log não encontrada.');

        $registro = json_decode((string) $l['registro_json'], true);
        $calculado = Fingerprint::calcular(is_array($registro) ? $registro : []);
        $integro = hash_equals((string) $l['fingerprint'], $calculado);

        $ultima = (int) $this->db->escalar(
            'SELECT MAX(id) FROM sys_log WHERE tabela = ? AND registro_id = ?',
            [$l['tabela'], $l['registro_id']]
        ) === (int) $l['id'];

        $confere = null;
        $atual = null;
        $existe = null;
        if ($ultima && $this->tabelaExiste((string) $l['tabela'])) {
            $linha = $this->db->um(sprintf('SELECT * FROM `%s` WHERE id = ?', $l['tabela']), [$l['registro_id']]);
            $existe = $linha !== null;
            if ($linha !== null) {
                $atual = Fingerprint::calcular(Mascara::aplicar($linha));
                $confere = hash_equals((string) $l['fingerprint'], $atual);
            } else {
                $confere = false;
            }
        }

        return [
            'integro' => $integro,
            'fingerprint_registrado' => (string) $l['fingerprint'],
            'fingerprint_calculado' => $calculado,
            'ultima_do_registro' => $ultima,
            'confere_com_banco' => $confere,
            'fingerprint_atual' => $atual,
            'registro_existe' => $existe,
        ];
    }

    private function tabelaExiste(string $tabela): bool
    {
        return (int) $this->db->escalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tabela]
        ) > 0;
    }
}

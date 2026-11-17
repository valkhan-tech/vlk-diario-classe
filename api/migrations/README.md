# Migrações (SQL manual)

Executadas **manualmente** (phpMyAdmin → Importar, ou `mysql < arquivo.sql`), **em ordem de nome**.

## Nome do arquivo

`AAAAMMDDHHMMSS_descricao_em_minusculas.sql` — timestamp (hora local) + descrição curta com `_`.

Exemplos:

- `20261117143000_criar_tabela_sys_migracao.sql`
- `20261117143500_criar_tabela_alunos.sql`

## Regras

- Somente **para frente**: correção = nova migração. Nunca editar uma migração já aplicada em produção.
- **Um conceito por arquivo** (DDL do MySQL não é transacional).
- **Toda tabela** tem `id`, `sys_cri`, `sys_alt`, `sys_del` (exceção: `sys_migracao`). Os testes de integração conferem.
- Cada arquivo **se registra** no final, em `sys_migracao`.
- Cabeçalho obrigatório: descrição, autor e reversão manual (como desfazer, se possível).

## Modelo

```sql
-- Descrição: criar a tabela de alunos
-- Autor: <nome>
-- Reversão manual: DROP TABLE alunos; DELETE FROM sys_migracao WHERE arquivo = '20261117143500_criar_tabela_alunos.sql';

CREATE TABLE alunos (
    id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome    VARCHAR(150)    NOT NULL,
    -- ... colunas da tabela ...
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117143500_criar_tabela_alunos.sql');
```

Unicidade com exclusão lógica: coluna gerada `sys_ativo` (`IF(sys_del IS NULL, 1, NULL)`) dentro do índice único.

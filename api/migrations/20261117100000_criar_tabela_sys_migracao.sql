-- Descrição: tabela de controle das migrações executadas
-- Autor: equipe
-- Reversão manual: DROP TABLE sys_migracao;

CREATE TABLE sys_migracao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arquivo VARCHAR(190) NOT NULL,
    executada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_sys_migracao_arquivo (arquivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117100000_criar_tabela_sys_migracao.sql');

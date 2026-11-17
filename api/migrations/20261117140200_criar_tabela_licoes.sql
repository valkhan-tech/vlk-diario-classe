-- Descrição: lições de cada método, em ordem (via de regra sequenciais)
-- Autor: equipe
-- Reversão manual: DROP TABLE licoes; DELETE FROM sys_migracao WHERE arquivo = '20261117140200_criar_tabela_licoes.sql';

CREATE TABLE licoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metodo_id BIGINT UNSIGNED NOT NULL,
    ordem INT UNSIGNED NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_licoes_metodo_ordem (metodo_id, ordem, sys_ativo),
    CONSTRAINT fk_licoes_metodo FOREIGN KEY (metodo_id) REFERENCES metodos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117140200_criar_tabela_licoes.sql');

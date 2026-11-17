-- Descrição: listas editáveis (instrumentos, tipos de aula, formas de pagamento)
-- Autor: equipe
-- Reversão manual: DROP TABLE listas; DELETE FROM sys_migracao WHERE arquivo = '20261117100400_criar_tabela_listas.sql';

CREATE TABLE listas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('instrumento','tipo_aula','forma_pagamento') NOT NULL,
    valor VARCHAR(100) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_listas_tipo_valor (tipo, valor, sys_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117100400_criar_tabela_listas.sql');

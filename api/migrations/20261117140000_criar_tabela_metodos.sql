-- Descrição: métodos de estudo (inclui hinários, que são métodos do tipo 'hinario'); o nível dá o nome do estágio
-- Autor: equipe
-- Reversão manual: DROP TABLE metodos; DELETE FROM sys_migracao WHERE arquivo = '20261117140000_criar_tabela_metodos.sql';

CREATE TABLE metodos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    tipo ENUM('metodo','hinario') NOT NULL DEFAULT 'metodo',
    nivel ENUM('iniciante','basico','intermediario','avancado') NULL DEFAULT NULL,
    instrumento_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'nulo = vale para qualquer instrumento',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_metodos_nome (nome, sys_ativo),
    CONSTRAINT fk_metodos_instrumento FOREIGN KEY (instrumento_id) REFERENCES listas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117140000_criar_tabela_metodos.sql');

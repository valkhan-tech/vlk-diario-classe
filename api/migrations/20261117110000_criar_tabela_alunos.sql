-- Descrição: cadastro de alunos (horário semanal, mensalidade e dia de vencimento fixo)
-- Autor: equipe
-- Reversão manual: DROP TABLE alunos; DELETE FROM sys_migracao WHERE arquivo = '20261117110000_criar_tabela_alunos.sql';

CREATE TABLE alunos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    data_nascimento DATE NULL DEFAULT NULL,
    instrumento_id BIGINT UNSIGNED NULL DEFAULT NULL,
    contato VARCHAR(150) NULL DEFAULT NULL,
    responsavel_nome VARCHAR(150) NULL DEFAULT NULL,
    dia_semana TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '0=domingo ... 6=sabado',
    hora_aula TIME NULL DEFAULT NULL,
    valor_mensalidade DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT '1 a 31; 29-31 em mes curto vence no ultimo dia',
    data_inicio DATE NULL DEFAULT NULL,
    situacao ENUM('ativo','pausado','encerrado') NOT NULL DEFAULT 'ativo',
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_alunos_nome (nome),
    KEY idx_alunos_situacao (situacao),
    CONSTRAINT fk_alunos_instrumento FOREIGN KEY (instrumento_id) REFERENCES listas (id),
    CONSTRAINT ck_alunos_dia_vencimento CHECK (dia_vencimento BETWEEN 1 AND 31),
    CONSTRAINT ck_alunos_dia_semana CHECK (dia_semana IS NULL OR dia_semana BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117110000_criar_tabela_alunos.sql');

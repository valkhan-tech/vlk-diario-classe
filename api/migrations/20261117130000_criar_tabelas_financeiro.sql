-- Descrição: cobranças mensais (uma por aluno e competência) e pagamentos (baixas, inclusive parciais)
-- Autor: equipe
-- Reversão manual: DROP TABLE pagamentos, cobrancas; DELETE FROM sys_migracao WHERE arquivo = '20261117130000_criar_tabelas_financeiro.sql';

CREATE TABLE cobrancas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aluno_id BIGINT UNSIGNED NOT NULL,
    competencia DATE NOT NULL COMMENT 'primeiro dia do mes',
    valor DECIMAL(12,2) NOT NULL,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vencimento DATE NOT NULL,
    situacao ENUM('aberta','cancelada') NOT NULL DEFAULT 'aberta',
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cobrancas_aluno_competencia (aluno_id, competencia, sys_ativo),
    KEY idx_cobrancas_competencia (competencia),
    KEY idx_cobrancas_vencimento (vencimento),
    CONSTRAINT fk_cobrancas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id),
    CONSTRAINT ck_cobrancas_valores CHECK (valor >= 0 AND desconto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pagamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cobranca_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    forma_pagamento_id BIGINT UNSIGNED NULL DEFAULT NULL,
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_pagamentos_cobranca (cobranca_id),
    KEY idx_pagamentos_data (data_pagamento),
    CONSTRAINT fk_pagamentos_cobranca FOREIGN KEY (cobranca_id) REFERENCES cobrancas (id),
    CONSTRAINT fk_pagamentos_forma FOREIGN KEY (forma_pagamento_id) REFERENCES listas (id),
    CONSTRAINT ck_pagamentos_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117130000_criar_tabelas_financeiro.sql');

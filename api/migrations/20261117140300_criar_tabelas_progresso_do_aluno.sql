-- Descrição: métodos do aluno (situação), avaliações de lição (só de acréscimo) e progresso manual (estágio, aptidão, técnica)
-- Autor: equipe
-- Reversão manual: DROP TABLE progresso_aluno, avaliacoes_licao, aluno_metodo; DELETE FROM sys_migracao WHERE arquivo = '20261117140300_criar_tabelas_progresso_do_aluno.sql';

CREATE TABLE aluno_metodo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aluno_id BIGINT UNSIGNED NOT NULL,
    metodo_id BIGINT UNSIGNED NOT NULL,
    data_inicio DATE NOT NULL,
    situacao ENUM('iniciado','pausado','concluido') NOT NULL DEFAULT 'iniciado',
    data_situacao DATE NOT NULL,
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_aluno_metodo (aluno_id, metodo_id, sys_ativo),
    CONSTRAINT fk_aluno_metodo_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id),
    CONSTRAINT fk_aluno_metodo_metodo FOREIGN KEY (metodo_id) REFERENCES metodos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE avaliacoes_licao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aluno_id BIGINT UNSIGNED NOT NULL,
    licao_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pendente','aprovado','reprovado') NOT NULL,
    data_tentativa DATE NOT NULL,
    aula_id BIGINT UNSIGNED NULL DEFAULT NULL,
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_avaliacoes_aluno_licao (aluno_id, licao_id, data_tentativa, id),
    CONSTRAINT fk_avaliacoes_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id),
    CONSTRAINT fk_avaliacoes_licao FOREIGN KEY (licao_id) REFERENCES licoes (id),
    CONSTRAINT fk_avaliacoes_aula FOREIGN KEY (aula_id) REFERENCES aulas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE progresso_aluno (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aluno_id BIGINT UNSIGNED NOT NULL,
    estagio_manual ENUM('iniciante','basico','intermediario','avancado','apto_culto_jovens','apto_culto_adulto','oficializado') NULL DEFAULT NULL COMMENT 'nulo = usar o calculado',
    apto_jovens TINYINT(1) NOT NULL DEFAULT 0,
    apto_adulto TINYINT(1) NOT NULL DEFAULT 0,
    apto_oficializacao TINYINT(1) NOT NULL DEFAULT 0,
    tecnica_m1 ENUM('nao_iniciado','em_andamento','concluido') NOT NULL DEFAULT 'nao_iniciado',
    tecnica_m2 ENUM('nao_iniciado','em_andamento','concluido') NOT NULL DEFAULT 'nao_iniciado',
    tecnica_ped ENUM('nao_iniciado','em_andamento','concluido') NOT NULL DEFAULT 'nao_iniciado',
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_progresso_aluno (aluno_id, sys_ativo),
    CONSTRAINT fk_progresso_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117140300_criar_tabelas_progresso_do_aluno.sql');

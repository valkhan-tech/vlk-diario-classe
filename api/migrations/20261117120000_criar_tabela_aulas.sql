-- Descrição: aulas lançadas (um registro por aluno e aula); presença não gera progresso
-- Autor: equipe
-- Reversão manual: DROP TABLE aulas; DELETE FROM sys_migracao WHERE arquivo = '20261117120000_criar_tabela_aulas.sql';

CREATE TABLE aulas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aluno_id BIGINT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    presenca ENUM('presente','falta_aluno_justificada','falta_aluno_sem_aviso','falta_professora','feriado_evento') NOT NULL,
    motivo VARCHAR(255) NULL DEFAULT NULL,
    tipo_aula_id BIGINT UNSIGNED NULL DEFAULT NULL,
    metodo_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK adicionada na migracao de metodos',
    conteudo TEXT NULL,
    hino VARCHAR(150) NULL DEFAULT NULL,
    licao_casa TEXT NULL,
    video_url VARCHAR(255) NULL DEFAULT NULL,
    observacoes TEXT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_aulas_aluno_data (aluno_id, data),
    KEY idx_aulas_data (data),
    CONSTRAINT fk_aulas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id),
    CONSTRAINT fk_aulas_tipo FOREIGN KEY (tipo_aula_id) REFERENCES listas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117120000_criar_tabela_aulas.sql');

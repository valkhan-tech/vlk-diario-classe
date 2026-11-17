-- Descrição: log de auditoria das gravações (só acréscimo; não gera log para si mesmo)
-- Autor: equipe
-- Reversão manual: DROP TABLE sys_log; DELETE FROM sys_migracao WHERE arquivo = '20261117100100_criar_tabela_sys_log.sql';

CREATE TABLE sys_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    usuario_perfil VARCHAR(20) NULL,
    operacao ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    tabela VARCHAR(64) NOT NULL,
    registro_id BIGINT UNSIGNED NOT NULL,
    origem VARCHAR(255) NOT NULL,
    controller VARCHAR(190) NULL,
    registro_json JSON NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_sys_log_registro (tabela, registro_id, id),
    KEY idx_sys_log_usuario (usuario_id),
    KEY idx_sys_log_cri (sys_cri)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117100100_criar_tabela_sys_log.sql');

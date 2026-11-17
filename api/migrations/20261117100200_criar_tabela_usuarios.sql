-- Descrição: usuários do sistema (administrador, professora e, no futuro, responsáveis)
-- Autor: equipe
-- Reversão manual: DROP TABLE usuarios; DELETE FROM sys_migracao WHERE arquivo = '20261117100200_criar_tabela_usuarios.sql';

CREATE TABLE usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin','professora','responsavel') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    troca_senha_obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
    ultimo_login_em DATETIME NULL DEFAULT NULL,
    sys_cri DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sys_alt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    sys_del DATETIME NULL DEFAULT NULL,
    sys_ativo TINYINT GENERATED ALWAYS AS (IF(sys_del IS NULL, 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email, sys_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_migracao (arquivo) VALUES ('20261117100200_criar_tabela_usuarios.sql');

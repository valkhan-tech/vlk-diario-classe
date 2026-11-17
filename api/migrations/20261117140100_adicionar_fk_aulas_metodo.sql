-- Descrição: liga aulas.metodo_id à tabela de métodos
-- Autor: equipe
-- Reversão manual: ALTER TABLE aulas DROP FOREIGN KEY fk_aulas_metodo; DELETE FROM sys_migracao WHERE arquivo = '20261117140100_adicionar_fk_aulas_metodo.sql';

ALTER TABLE aulas ADD CONSTRAINT fk_aulas_metodo FOREIGN KEY (metodo_id) REFERENCES metodos (id);

INSERT INTO sys_migracao (arquivo) VALUES ('20261117140100_adicionar_fk_aulas_metodo.sql');

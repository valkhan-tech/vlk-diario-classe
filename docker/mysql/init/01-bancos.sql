-- Executado apenas na primeira inicializacao do volume de dados.
CREATE DATABASE IF NOT EXISTS `ddc_dev`  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS `ddc_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE USER IF NOT EXISTS 'ddc'@'%' IDENTIFIED BY 'ddc';
GRANT ALL PRIVILEGES ON `ddc_dev`.*  TO 'ddc'@'%';
GRANT ALL PRIVILEGES ON `ddc_test`.* TO 'ddc'@'%';
FLUSH PRIVILEGES;

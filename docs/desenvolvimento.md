# Ambiente de desenvolvimento (Docker)

Docker serve **apenas para desenvolvimento e testes**. A producao (HostGator compartilhada, PHP 8.4 + MySQL/MariaDB + Apache) **nao usa Docker**.

## Pre-requisitos

- Docker Desktop (Compose v2) com o daemon ativo.
- Portas livres no host: 8080, 8081, 8025, 3307 (e 5173 se usar o front).
- Dica Windows: clone o repositorio com `core.autocrlf=input` (ou LF) para evitar CRLF em scripts.

## Subir

```bash
docker compose up -d --build     # app, db, mailpit, phpmyadmin
docker compose ps                # todos "running"; db "healthy"
docker compose --profile web up -d   # opcional: tambem o Vite (front React)
docker compose down              # desliga (mantem os dados do banco)
```

## URLs e portas

| Servico     | URL / porta host          | Observacao                                   |
|-------------|---------------------------|----------------------------------------------|
| App (Apache)| http://localhost:8080     | docroot `public/`; API em `/api/v1/...`      |
| phpMyAdmin  | http://localhost:8081     | login `ddc`/`ddc` ou `root`/`root`           |
| Mailpit UI  | http://localhost:8025     | SMTP interno `mailpit:1025` (sem auth)       |
| MySQL       | `127.0.0.1:3307`          | para clientes no host (DBeaver, mysql, ...)  |
| Vite        | http://localhost:5173     | so com `--profile web`; proxy `/api` -> `http://app` |

Se alguma porta do host estiver ocupada, sobrescreva via variavel de ambiente ou arquivo `.env` na raiz:
`APP_PORT`, `PMA_PORT`, `MAILPIT_PORT`, `DB_PORT_HOST`, `VITE_PORT` (ex.: `PMA_PORT=8082 docker compose up -d`).
No Windows, um `netsh interface portproxy` antigo pode "roubar" uma porta (ex.: 8081); veja com `netsh interface portproxy show all`.

## Banco de dados

| Item           | Valor                                   |
|----------------|-----------------------------------------|
| Host (container) | `db` : 3306                           |
| Host (maquina)   | `127.0.0.1` : 3307                    |
| Usuario / senha  | `ddc` / `ddc` (root: `root` / `root`) |
| Bancos           | `ddc_dev` (desenvolvimento), `ddc_test` (testes) |
| Charset / collation | `utf8mb4` / `utf8mb4_0900_ai_ci`, fuso `-03:00` |

Os bancos sao criados por `docker/mysql/init/01-bancos.sql` apenas na **primeira** subida do volume.

Cliente MySQL:

```bash
docker compose exec db mysql -uddc -pddc ddc_dev      # dentro do container db
mysql -h 127.0.0.1 -P 3307 -uddc -pddc ddc_dev        # pelo cliente do host
docker compose exec app mysql -h db -uddc -pddc       # a partir do container app
```

Resetar o banco (APAGA todos os dados e recria `ddc_dev`/`ddc_test`):

```bash
docker compose down -v && docker compose up -d
```

## Comandos dentro do container `app`

O repositorio inteiro e montado em `/var/www/html` (bind mount); editar no host reflete na hora.

```bash
docker compose exec app composer install        # instala api/vendor (rodar a partir da pasta api/ se o composer.json estiver la)
docker compose exec app composer test           # suite de testes
docker compose exec app php api/bin/migrar.php  # migracoes (serao criados)
docker compose exec app php api/bin/seed.php    # dados iniciais (serao criados)
docker compose exec app bash                    # shell
```

> `api/bin/migrar.php`, `api/bin/seed.php` e os scripts do Composer serao criados no desenvolvimento da API; ate la os comandos acima nao existem. Se o `composer.json` ficar em `api/`, use `docker compose exec -w /var/www/html/api app composer install`.

## Variaveis de ambiente do container

Definidas em `docker-compose.yml` (o PHP le com `getenv`/`$_ENV`): `APP_ENV`, `APP_URL`, `APP_TIMEZONE`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_NAME_TEST`, `SESSION_COOKIE`, `SESSION_TTL_MINUTES`, `SMTP_*`. Em producao a configuracao vem de `api/.env`.

## Testes de integracao

A suite de integracao **so roda em banco cujo nome termina em `_test`** (`DB_NAME_TEST=ddc_test`). Ela deve recusar-se a executar em `ddc_dev` ou em qualquer outro banco, pois recria/limpa tabelas.

## E-mail

Todo e-mail enviado pelo app (SMTP `mailpit:1025`) fica retido no Mailpit: http://localhost:8025 (API: `/api/v1/messages`).

## Observacoes

- **Desempenho no Windows:** o bind mount do repositorio (incluindo `api/vendor`) e mais lento que o disco nativo. Se o `composer install` ou as requisicoes ficarem lentos, monte um volume nomeado so para o vendor, adicionando em `app.volumes` do compose: `- vendor_api:/var/www/html/api/vendor` (e declarando `vendor_api:` em `volumes:`). Nesse caso o `vendor/` nao aparece no host (a IDE nao enxerga as libs).
- **Permissoes:** o Apache roda como `www-data`; no bind mount do Docker Desktop os arquivos aparecem como `root` com permissao ampla, entao a escrita em `api/storage` funciona sem ajustes.
- **HTTPS:** o `.htaccess` so redireciona para HTTPS fora de `localhost`/`127.0.0.1`; em dev acesse por `http://localhost:8080`.
- `mysql`/`mysqldump` do container `app` sao do MariaDB client, configurados sem TLS (`docker/php/client.cnf`) por causa do certificado autoassinado do MySQL 8.

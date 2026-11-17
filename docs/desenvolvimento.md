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

## Primeiros passos (do zero até o sistema rodando com dados)

```bash
docker compose up -d --build                                              # sobe app, db, mailpit, phpmyadmin
docker compose exec app sh -c "cd api && composer install"                # dependências PHP
docker compose exec app php api/bin/migrar.php                            # cria as tabelas em ddc_dev
docker compose exec app php api/bin/seed.php --limpar                     # dados de demonstração
cd web && npm install && npm run build && cd ..                           # compila o front para public/
```

Abra http://localhost:8080 e entre com um dos usuários de teste:

| Perfil      | E-mail                  | Senha            |
|-------------|-------------------------|------------------|
| Administrador | `admin@ddc.local`     | `Admin@123`      |
| Professora  | `professora@ddc.local`  | `Professora@123` |
| Responsável (futuro) | `responsavel@ddc.local` | `Responsavel@123` |
| Inativo (não entra)  | `inativo@ddc.local`     | `Inativo@123`    |

- **Documentação da API** (só administrador): http://localhost:8080/api/docs
- **E-mails** (recuperação de senha): http://localhost:8025
- Para desenvolver o front com recarga automática: `cd web && npm run dev` (http://localhost:5173, com proxy de `/api` para o Docker).

## Comandos do dia a dia

```bash
docker compose exec app sh -c "cd api && composer test"          # unitários + integração (banco ddc_test)
docker compose exec app sh -c "cd api && composer test:unit"     # só unitários (sem banco)
docker compose exec app sh -c "cd api && composer analyse"       # PHPStan
docker compose exec app php api/bin/migrar.php                   # aplica migrações pendentes no ddc_dev
docker compose exec app php api/bin/migrar.php --teste --zerar   # recria o ddc_test do zero
docker compose exec app php api/bin/seed.php --limpar            # apaga os dados do ddc_dev e semeia de novo
docker compose exec app php api/bin/cobrancas.php [AAAA-MM]      # gera as cobranças (o cron faz isso todo dia em produção)
docker compose exec app php api/bin/limpeza.php                  # remove sessões/tokens/tentativas vencidos
docker compose exec app php api/bin/gerar-openapi.php            # regenera api/storage/openapi.json
cd web && npm test                                               # testes do front (Vitest)
node bin/release.mjs                                             # testes + análise + build + pacote release/ddc-*.zip
```

### O que o seeder cria (`api/bin/seed.php`)

Dados determinísticos (mesma semente → mesmos dados; as datas são relativas a "hoje"), gravados **pelos serviços da aplicação**, então as regras valem e o `sys_log` é gerado:

- 4 usuários (admin, professora, responsável, inativo) e as listas (instrumentos, tipos de aula, formas de pagamento);
- 16 alunos: 12 ativos, 2 pausados e 2 encerrados, todas as faixas etárias, horários semanais e dias de vencimento 5/10/15/28/30/31;
- 8 métodos, incluindo **Hinário de Jovens (50)** e **Hinário Oficial (430)**, com 615 lições no total;
- ~180 aulas das últimas 13 semanas, com as 5 presenças possíveis, lição de casa e conteúdo;
- ~190 avaliações de lição (aprovações, reprovações repetidas com data inicial mantida, métodos Concluído e Pausado);
- ~80 cobranças dos últimos 6 meses em **todos os estados** (pendente, parcial, atrasado, pago, cancelada), com desconto, pagamentos parciais e várias formas de pagamento — pausados e encerrados só têm cobranças dos meses em que estavam ativos;
- aptidão para culto, estágios sobrescritos e técnica.

O seeder se recusa a rodar com `APP_ENV=production` e **não vai no pacote de entrega**.

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

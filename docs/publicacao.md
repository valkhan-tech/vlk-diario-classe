# Publicação na HostGator — https://dc.valkhan.com.br

Passo a passo para a **primeira instalação** (banco vazio + só o usuário administrador) e para as **atualizações** seguintes.
Premissas: hospedagem compartilhada com cPanel, **PHP 8.4**, MySQL/MariaDB, sem SSH obrigatório. Tudo é feito pelo navegador (cPanel) e por 2 arquivos gerados no seu computador.

> **Pasta do projeto:** o guia usa `RAIZ` para a pasta do domínio no servidor (ex.: `/home/SEU_USUARIO/dc.valkhan.com.br`). Dentro dela ficam **`public/`** (o que o Apache serve) e **`api/`** (código PHP, fora do acesso público).

---

## 1. No seu computador: gerar os 2 arquivos

Com o Docker de desenvolvimento ligado (`docker compose up -d`), na raiz do repositório:

```bash
# 1) Pacote do sistema (roda testes + análise + build) → release/ddc-AAAAMMDD-HHMM.zip
node bin/release.mjs

# 2) SQL de instalação (todas as tabelas + administrador) → release/instalacao.sql
docker compose exec app php api/bin/gerar-instalacao.php "Paulo Lima" paulo.lima@valkhan.com.br "Teste@20"
```

- O `instalacao.sql` já traz o administrador **paulo.lima@valkhan.com.br** com a senha **Teste@20** guardada como *hash* (a senha em texto não fica no arquivo). O sistema **obriga a trocar a senha no primeiro acesso**.
- A pasta `release/` não vai para o Git. **Não envie `instalacao.sql` ao servidor**; ele só é importado pelo phpMyAdmin.
- O banco criado fica **vazio**, só com o administrador (sem alunos, listas ou métodos; elas são cadastradas dentro do sistema).

## 2. No cPanel: domínio, PHP e HTTPS

1. **Domínios → Criar subdomínio** (ou "Domínios" → "Criar um novo domínio"): `dc.valkhan.com.br`.
   Em **Raiz do documento** informe `dc.valkhan.com.br/public` (isto cria `RAIZ/public`). A pasta `RAIZ/api` ficará **ao lado** de `public`, fora do alcance do navegador.
2. **MultiPHP Manager**: selecione `dc.valkhan.com.br` e escolha **PHP 8.4**.
3. **MultiPHP INI Editor** (ou "Selecionar versão do PHP → Extensões"): confira que estão ativas **`pdo_mysql`, `mbstring`, `zip`, `gd`, `xml`, `fileinfo`, `openssl`, `sodium`** (a `gd` é necessária para os gráficos do Excel).
4. **SSL/TLS Status → Run AutoSSL** para `dc.valkhan.com.br` e confirme o cadeado em https. O sistema já redireciona http → https.

## 3. No cPanel: banco de dados

1. **Bancos de dados MySQL → Criar novo banco**: por exemplo `usuario_dc` (o cPanel acrescenta o prefixo do usuário).
2. **Adicionar novo usuário**: por exemplo `usuario_dc` com **senha forte** (anote).
3. **Adicionar usuário ao banco**: marque **TODOS OS PRIVILÉGIOS**.
4. **phpMyAdmin** → clique no banco criado → aba **Importar** → escolha `release/instalacao.sql` → **Importar**.
   Resultado esperado: **16 tabelas** e **1 linha** em `usuarios`. (Foi validado em MySQL 8.0 e MariaDB 10.11.)

## 4. Enviar o sistema

1. **Gerenciador de Arquivos** → abra `RAIZ` (a pasta que contém `public`).
2. **Carregar** o arquivo `release/ddc-AAAAMMDD-HHMM.zip`.
3. Clique no zip → **Extrair** em `RAIZ`. Devem existir `RAIZ/public/` (com `index.html`, `assets/`, `api.php`, `.htaccess`) e `RAIZ/api/` (com `bootstrap.php`, `vendor/`, `src/`…).
4. Apague o `.zip` do servidor.

## 5. Configurar `api/.env` (uma vez)

No Gerenciador de Arquivos, em `RAIZ/api/`, copie `.env.example` para **`.env`** e edite:

```ini
APP_ENV=production
APP_URL=https://dc.valkhan.com.br
APP_TIMEZONE=America/Sao_Paulo

DB_HOST=localhost
DB_PORT=3306
DB_NAME=usuario_dc          # o nome COM prefixo que o cPanel criou
DB_USER=usuario_dc
DB_PASS=a-senha-do-banco

SESSION_COOKIE=dc_sessao
SESSION_TTL_MINUTES=720

# E-mail (recuperação de senha). Crie antes a conta nao-responder@valkhan.com.br em "Contas de e-mail".
SMTP_HOST=mail.valkhan.com.br
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USER=nao-responder@valkhan.com.br
SMTP_PASS=senha-da-conta-de-email
SMTP_FROM=nao-responder@valkhan.com.br
SMTP_FROM_NAME="Diário de Classe"
```

- Apague as linhas `DB_NAME_TEST` e comentários que sobrarem (não são usados em produção).
- `APP_ENV=production` é obrigatório: ele desliga os detalhes de erro e libera o cookie `Secure`.
- Permissões: `RAIZ/api/storage/logs/` precisa ser gravável pelo seu usuário (o padrão do cPanel, 755, já serve).

## 6. Agendar as rotinas (cron)

**cPanel → Tarefas Cron** → intervalo **uma vez ao dia** (ex.: 02:00). Use o PHP 8.4 do cPanel (confira o caminho em *MultiPHP Manager*; costuma ser `/opt/cpanel/ea-php84/root/usr/bin/php`):

```
/opt/cpanel/ea-php84/root/usr/bin/php /home/SEU_USUARIO/dc.valkhan.com.br/api/bin/cobrancas.php
/opt/cpanel/ea-php84/root/usr/bin/php /home/SEU_USUARIO/dc.valkhan.com.br/api/bin/limpeza.php
```

- `cobrancas.php` gera as cobranças do mês (só alunos ativos; rodar de novo não duplica). Há também o botão **"Gerar cobranças da competência"** no Financeiro como contingência.
- `limpeza.php` remove sessões, tokens de recuperação e tentativas de login vencidos.

## 7. Conferir

1. `https://dc.valkhan.com.br/api/v1/health` → `{"status":"ok","versao":"AAAAMMDD-HHMM","banco":"ok"}`.
2. `https://dc.valkhan.com.br` → tela de login. Entre com **paulo.lima@valkhan.com.br / Teste@20** → o sistema pede a **nova senha** (mín. 8 caracteres, com letras e números). Troque na hora.
3. Em **Usuários**, crie a professora e defina a senha provisória dela (ela também troca no primeiro acesso).
4. **Documentação da API** (só administrador): `https://dc.valkhan.com.br/api/docs`.
5. **Esqueci minha senha** → o e-mail deve chegar (se não chegar, revise `SMTP_*` e o SPF/DKIM do domínio no cPanel → *Entrega de e-mail*).

## 8. Atualizações futuras

1. Gere um novo pacote: `node bin/release.mjs`.
2. Se houver **migrações novas** em `api/migrations/` (arquivos `AAAAMMDDHHMMSS_*.sql` ainda não aplicados), importe-as no phpMyAdmin **na ordem do nome**. Cada uma se registra em `sys_migracao`; para ver o que já foi aplicado: `SELECT arquivo FROM sys_migracao ORDER BY arquivo;`.
3. Envie o novo `.zip` e **extraia por cima** em `RAIZ` (o pacote **não contém** `.env`, então a sua configuração é preservada).
4. Confira `/api/v1/health` (a `versao` muda).

## 9. Problemas comuns

| Sintoma | Causa provável |
|---|---|
| Página em branco / 500 na raiz | PHP diferente de 8.4 no domínio, ou `.htaccess` não extraído (arquivos ocultos: ative "Mostrar arquivos ocultos" no Gerenciador) |
| `/api/v1/health` com `"banco":"erro"` | `DB_NAME`/`DB_USER`/`DB_PASS` errados (lembre do prefixo do cPanel) ou usuário sem privilégios no banco |
| Login não "pega" (volta ao login) | `APP_URL` sem `https://` ou domínio sem SSL ativo (o cookie é `Secure`) |
| `/api/docs` dá 403 | Normal para quem não é administrador |
| Gráficos do Excel ausentes / erro ao exportar | Extensão `gd` desativada no PHP do domínio |
| E-mail de recuperação não chega | `SMTP_*` incorretos ou conta de e-mail inexistente |

## 10. Segurança (resumo)

- **Troque a senha inicial** no primeiro acesso (obrigatório no sistema) e use uma senha única e longa.
- `RAIZ/api/` fica fora do `public/`: o código, o `.env` e os logs **não** são acessíveis pelo navegador.
- Faça **backup do banco** (cPanel → Backup ou phpMyAdmin → Exportar); o backup automático do banco está fora do escopo do sistema.

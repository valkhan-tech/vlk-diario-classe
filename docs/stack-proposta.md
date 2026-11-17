# Definição de stack — Diário Pedagógico Musical (v2)

**Status:** **aprovada em 17/11/2026** (com os ajustes registrados abaixo) · **Próximo passo:** escopo do projeto
**Substitui** a proposta anterior (offline/PWA/UUID/fonte de verdade). As regras de **negócio** da professora continuam valendo (ver `docs/wiki/regras.html`); as de **tecnologia** passam a ser as deste documento, definidas por você e por mim.

> Este documento define **como** construir, não **o que** entra em cada entrega.
> Versões exatas são travadas em `composer.lock` / `package-lock.json` no início do projeto; aqui valem as **versões principais**.

---

## 1. O que mudou em relação à primeira proposta

| Antes | Agora |
|---|---|
| Planilha/Excel como banco; offline; 3 vias de escrita; fila e sincronização | **MySQL centralizado.** Sem offline, sem fila, sem sincronização. Excel só como **exportação**. |
| Login Microsoft (Graph) | **Login próprio**: administrador, professora e, no futuro, **pais/responsáveis** (consulta de progresso e financeiro). |
| UUID gerado no cliente (por causa do offline) | **BIGINT auto-incremento** (confirmado). |
| Front e API com deploy automático | **Upload manual** (confirmado), com script que monta o pacote. |
| Dexie, Workbox, outbox, `sync_ops` | **Removidos.** |
| App atual (HTML/JS + planilhas) | Movido para **`/legacy`** (confirmado), só como referência. **Começar do zero**: sem migração de dados da planilha. |

Limites de conexões/CPU para o tamanho do público deixam de ser critério de decisão.

---

## 2. Decisões confirmadas por você

| Tema | Decisão |
|---|---|
| Hospedagem | HostGator compartilhada, **PHP 8.4**, **sem Docker** |
| Banco | **MySQL** |
| Domínio | `https://ddc.cpod.com.br/` (front) e `https://ddc.cpod.com.br/api/*` (API). Um só subdomínio. |
| API | **Slim**, com **documentação navegável auto-gerada**, de melhor usabilidade que o Swagger padrão. Acesso **só do administrador logado**. |
| Front | **React compilado** (SPA) |
| Usuários | Administrador e professora (uma só); futuramente pais/responsáveis |
| E-mail | **SMTP** (recuperar senha, convite); o **administrador também define senhas** na área administrativa |
| Chave primária | **BIGINT auto-incremento** |
| Migrações | **SQL, execução manual**, nome `timestamp-descritivo` |
| Convenção de tabelas | Toda tabela com `sys_cri`, `sys_alt`, `sys_del` (datetime) |
| Log | Toda operação de gravação no banco gera log com usuário, JSON do registro, origem (URL/controller), tipo da operação (tabela e id) e **fingerprint** do registro completo após a ação |
| Financeiro | Dashboards financeiros; **exportação Excel formatada, com totais e gráficos** |
| Fichas | **Ficha financeira** e **ficha de progresso** do aluno(a), separadas |
| Testes | **Unitários + integração com MySQL de teste** na API |
| Deploy | **Upload manual** (FTP/cPanel) |
| Dados atuais | **Começar do zero** |
| Caminho no servidor | `/home/domains/ddc.cpod.com.br/` com `public/` (docroot configurado no cPanel, **confirmado**) e `api/` |
| Backup do banco | **Fora do escopo de desenvolvimento** |
| Escolhas da seção 12 | **Aprovadas** (exceto o backup, retirado) |

---

## 3. Arquitetura e estrutura de pastas

```
https://ddc.cpod.com.br/        ── Apache (cPanel) ──►  docroot = /home/domains/ddc.cpod.com.br/public
        │
        ├─ /            → public/index.html + assets/   (React compilado, SPA)
        └─ /api/*       → public/api.php  ──require──►  ../api/  (PHP, fora do docroot)
                                                              │ PDO
                                                         MySQL
```

**Repositório (e espelho no servidor)**
```
/public              ← docroot do subdomínio (o ÚNICO que o Apache serve)
│  ├─ index.html, assets/        (saída do build do React; não versionada)
│  ├─ api.php                    (autoroute: entrada única da API)
│  └─ .htaccess                  (HTTPS, /api/* → api.php, fallback da SPA)
/api                 ← PHP, FORA do docroot
│  ├─ .env                       (só no servidor; .env.example no repositório)
│  ├─ composer.json, vendor/
│  ├─ src/                       (Aplicação, Domínio, Infra)
│  ├─ config/, bin/ (cron e utilitários), storage/ (logs, openapi.json)
│  ├─ migrations/                (SQL manuais)
│  └─ tests/
/web                 ← código-fonte do React (Vite); build vai para /public
/docs                ← Wiki, esta definição, escopo
/legacy              ← app antigo (HTML/JS) e planilhas, só referência; fora do pacote de entrega
/bin                 ← scripts de montagem do pacote de entrega
```

**No servidor (HostGator)**
```
/home/domains/ddc.cpod.com.br/
├─ public/    ← docroot do subdomínio (configurado no cPanel)
└─ api/       ← PHP, .env e demais arquivos (fora do docroot)
```

**Pontos de projeto**
- `public/api.php` é mínimo: define o *base path* `/api`, carrega `../api/bootstrap.php` e entrega a requisição ao Slim. Nenhuma lógica nele.
- `public/.htaccess`: redireciona HTTP→HTTPS; `^api(/.*)?$` → `api.php`; qualquer outra rota que não seja arquivo existente → `index.html` (rotas do React); bloqueia arquivos ocultos.
- Como `/api` (código) está **fora** do docroot e `/api/*` (URL) é só reescrita, não há conflito de nomes.
- Mesma origem: **sem CORS** em produção; cookie de sessão simples.
- **Confirmado:** o cPanel aponta o docroot do subdomínio para `/home/domains/ddc.cpod.com.br/public/`; a contingência de colocar `api/` dentro do docroot deixa de ser necessária.

---

## 4. Back-end (API PHP 8.4 + Slim)

| Camada | Escolha | Observação |
|---|---|---|
| Linguagem | **PHP 8.4**, `declare(strict_types=1)` | Tipagem estrita, `readonly`, enums, atributos. |
| HTTP | **Slim 4** + `slim/psr7` | Roteamento e middlewares PSR-15. Sem ORM. |
| Injeção de dependências | **PHP-DI** | Leve; facilita trocar repositórios por *fakes* nos testes unitários. |
| Banco | **PDO** (MySQL, `utf8mb4`, InnoDB), consultas preparadas, **repositórios** em SQL explícito | Sem ORM: as consultas de dashboard financeiro (agregações) ficam legíveis e controláveis. |
| Validação | **Respect\Validation** (ou validadores próprios) | Regras de entrada e regras de negócio em classes separadas. |
| Autenticação | Sessão opaca em cookie (seção 6) | Perfis e permissões por middleware. |
| E-mail | **PHPMailer** (SMTP) | Recuperação de senha e convite. |
| Planilhas | **PhpSpreadsheet** | Exportação formatada com fórmulas e gráficos (confirmado). |
| Logs de aplicação | **Monolog** em `api/storage/logs/` | Separado do log de auditoria do banco (seção 5). |
| Config | **vlucas/phpdotenv** | `.env` fora do docroot. |
| Documentação da API | **swagger-php** (atributos) → OpenAPI 3.1 + **Scalar** (seção 9) | |
| Qualidade | **PHPUnit**, **PHPStan** (nível alto), **PHP-CS-Fixer** | |

**Camadas** (dependência só de fora para dentro):
`Http (controllers, middlewares, DTOs)` → `Aplicação (casos de uso/serviços)` → `Domínio (entidades, regras: estágio, progresso %, atrasado, cobrança)` ← `Infra (repositórios PDO, SMTP, planilhas)`.

**Padrões da API**
- REST/JSON em `/api/v1`; erros em `application/problem+json`; datas ISO 8601; **dinheiro em centavos inteiros no domínio** e `DECIMAL(12,2)` no banco.
- Toda rota declara o **perfil** que pode usá-la; rotas de pais (futuro) validam o **vínculo responsável–aluno**.
- Exclusão **lógica** (`sys_del`) em todas as tabelas.
- `GET /api/v1/health` (versão publicada e conexão com o banco) para conferir o deploy.

---

## 5. Banco de dados

### 5.1 Convenções de toda tabela
| Coluna | Regra |
|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` (chave primária) |
| `sys_cri` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `sys_alt` | `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` (o banco mantém, inclusive em edição manual) |
| `sys_del` | `DATETIME NULL` — exclusão lógica; preenchido pelo app; consultas padrão filtram `sys_del IS NULL` |

- **Fuso:** a conexão fixa `America/Sao_Paulo` (`SET time_zone = '-03:00'`) e os `DATETIME` guardam hora local de Brasília.
- **Unicidade com exclusão lógica:** o MySQL não tem índice único parcial; o padrão é uma coluna gerada `sys_ativo` (`IF(sys_del IS NULL, 1, NULL)`) incluída no índice único. Ex.: uma cobrança por aluno e competência *entre as não excluídas*.
- Dinheiro `DECIMAL(12,2)`; `utf8mb4_0900_ai_ci` (ou o *collation* equivalente do MySQL/MariaDB do plano).
- Um **teste de integração** confere no `information_schema` que **toda** tabela (exceto `sys_migracao`, se você preferir isentá-la) tem as três colunas.

### 5.2 Migrações (SQL manual)
- Pasta `api/migrations/`; arquivo `AAAAMMDDHHMMSS_descricao_em_minusculas.sql` (ex.: `20261117143000_criar_tabela_alunos.sql`).
- **Somente para frente**: correção = nova migração. Cada arquivo traz no cabeçalho `-- Descrição`, `-- Autor` e `-- Reversão manual` (como desfazer, se for possível).
- Um conceito por arquivo (DDL do MySQL não é transacional).
- **Registro de execução:** a primeira migração cria `sys_migracao`; **cada arquivo termina com** `INSERT INTO sys_migracao (arquivo) VALUES ('<nome do arquivo>')`. Assim, ao importar o `.sql` no phpMyAdmin (ou via `mysql <`), ele mesmo se registra e dá para ver o que já foi aplicado. *(Escolha minha; ver seção 12.)*
- Os testes de integração aplicam **todas** as migrações, em ordem, num banco de teste vazio e **validam o padrão do nome**.

### 5.3 Log de operações (auditoria)
Tabela `sys_log`, **só de acréscimo**, escrita **na mesma transação** da operação (se o log falhar, a operação não é confirmada).

| Campo | Conteúdo |
|---|---|
| `usuario_id`, `usuario_perfil` | Quem fez (nulo quando for tarefa do sistema, ex.: cron) |
| `operacao` | `INSERT` / `UPDATE` / `DELETE` (exclusão lógica) |
| `tabela`, `registro_id` | O que foi alterado |
| `origem` | URL + método (`PUT /api/v1/alunos/12`) e classe/método do *controller* (ou `cron:gerar-cobrancas`) |
| `registro_json` | **Registro completo após a ação**, relido do banco (inclui `sys_*`), sem campos secretos (hash de senha, tokens) |
| `fingerprint` | **SHA-256** do `registro_json` canônico (chaves ordenadas, valores normalizados) |
| `ip`, `user_agent` | Contexto da requisição *(proposta minha)* |

Como funciona: os repositórios herdam de uma base que, dentro de uma transação, executa o comando, **relê a linha**, calcula o fingerprint e grava o log. Não há como gravar sem logar usando o repositório. Só **gravações** são logadas (não leituras).
**Fora do log** *(aprovado)*: `sys_log` (não gera log para si mesmo) e tabelas puramente técnicas (`sessoes`, `tentativas_login`, `redefinicoes_senha`), para não gerar ruído nem registrar segredos.
**Limite assumido:** o usuário do banco na hospedagem compartilhada é um só; a imutabilidade do log é garantida pelo código, não por permissão no MySQL. Um *hash encadeado* entre entradas pode ser adicionado depois, se houver necessidade de detectar adulteração.

### 5.4 Esboço das tabelas (detalhamento no escopo)
| Tabela | Observações |
|---|---|
| `usuarios`, `sessoes`, `redefinicoes_senha`, `tentativas_login` | Perfis `admin`, `professora`, `responsavel`; senha com `password_hash`; troca obrigatória quando o administrador define a senha |
| `responsaveis`, `responsavel_aluno` | Preparar o acesso dos pais (futuro): vínculo N–N |
| `alunos` | Situação Ativo/Pausado/Encerrado; dia de vencimento fixo (1–31) |
| `aulas` | Presença e conteúdo |
| `cobrancas` | Única por aluno e competência (entre as não excluídas); vencimento gravado na criação; **Atrasado é derivado** (Pendente + vencimento passado) |
| `pagamentos` | Baixas (data, valor, forma). *Separar cobrança de pagamento permite baixa parcial e melhores relatórios; a confirmar no escopo.* |
| `metodos`, `licoes`, `aluno_metodo`, `avaliacoes_licao` | Conforme `docs/wiki/metodos.html`; método com **nível** (Iniciante/Básico/Intermediário/Avançado); avaliações só de acréscimo |
| `progresso_aluno` | Sobrescrita de estágio e checkboxes de aptidão (jovens, adulto, oficialização) |
| `listas` | Opções dos selects |
| `sys_log`, `sys_migracao` | Auditoria e controle de migrações |

---

## 6. Autenticação e perfis

- **Sessão opaca**: token aleatório no cookie `HttpOnly; Secure; SameSite=Lax`; no banco guarda-se só o **hash** do token, com validade deslizante e revogação. Sem JWT.
- **CSRF**: mesma origem + `SameSite` + cabeçalho obrigatório nas rotas que gravam.
- **Senha**: `password_hash` (algoritmo padrão do PHP 8.4, com `PASSWORD_ARGON2ID` se a hospedagem tiver o suporte), **limite de tentativas** de login, mensagens de erro genéricas.
- **Recuperação de senha por e-mail** (token de uso único com validade, guardado em hash) **e** **definição de senha pelo administrador** na área administrativa (troca obrigatória no primeiro acesso).
- **Perfis**: `admin` (usuários, configurações, documentação da API, log), `professora` (operação completa), `responsavel` (somente leitura das fichas dos seus alunos, no futuro).
- **E-mail**: SMTP de uma conta criada no cPanel do próprio domínio (ex.: `nao-responder@cpod.com.br`), com SPF/DKIM do domínio para não cair em spam.

---

## 7. Front-end (React compilado)

| Camada | Escolha |
|---|---|
| Base | **Vite** + **React 19** + **TypeScript** |
| Rotas | **React Router** (SPA, rotas por perfil: `/admin`, `/`, futuramente `/responsavel`) |
| Dados de servidor | **TanStack Query** (cache, recarregar, estados de carregando/erro) |
| Cliente da API | **openapi-typescript** + **openapi-fetch**: tipos gerados do OpenAPI da própria API, evitando divergência entre front e back |
| Formulários | **React Hook Form** + **Zod** |
| UI | **Tailwind CSS** + componentes sem estilo (**Radix UI**); acessibilidade por padrão |
| Tabelas | **TanStack Table** (ordenação e filtros nas listas e no financeiro) |
| Gráficos | **Recharts** (barras, linhas, rosca). Alternativa: ECharts, se os dashboards crescerem muito |
| Datas/valores | **date-fns** (pt-BR) e `Intl` para moeda |
| Qualidade | **ESLint + Prettier**, **Vitest** + Testing Library |

**Telas-chave decididas por você:** dashboards financeiros; **ficha financeira do aluno**; **ficha de progresso do aluno(a)**; área administrativa (usuários e senhas, log, documentação da API).
**Impressão:** as fichas terão CSS de impressão; PDF gerado no servidor (Dompdf) só se o escopo pedir.
**Build:** `vite build` com saída em `public/` (sem apagar `api.php` e `.htaccess`, que são versionados); arquivos com hash no nome; `index.html` sem cache.

---

## 8. Financeiro: dashboards e exportações

- **Cobrança mensal criada pelo sistema:** comando `api/bin/gerar-cobrancas.php`, idempotente (chave única aluno + competência), só para alunos **Ativos**, sem proporcional, vencimento no dia fixo do aluno (dia 29–31 em mês curto = último dia). Agendado no **cron do cPanel** (diário); o administrador também tem um botão “gerar cobranças do mês” como contingência.
- **Dashboards (agregados no servidor, em SQL):** previsto × recebido por mês; inadimplência/Atrasado e envelhecimento; receita por forma de pagamento; ranking de atrasos; evolução mensal.
- **Exportação Excel (PhpSpreadsheet):** cabeçalhos formatados, filtros, **fórmulas de total e subtotal**, congelamento de painel e **gráficos** (barras/linha/pizza) nas abas de resumo. Tipos previstos: lista de cobranças do período, resumo mensal, inadimplência, recebimentos por forma de pagamento e **ficha financeira do aluno**.
- Exportar **não** altera dados; a geração é uma rota autenticada, e o download passa pela API (sem arquivos públicos soltos).

---

## 9. Documentação navegável da API

- **Fonte:** atributos `swagger-php` nos controllers e DTOs → `openapi.json` (OpenAPI 3.1).
- **Interface:** **Scalar**, com busca, navegação por grupos, exemplos de código e “testar requisição”; mais agradável que o Swagger UI padrão. *Alternativa:* Redoc (só leitura, ótima navegação) ou Stoplight Elements.
- **Acesso:** `https://ddc.cpod.com.br/api/docs`, **somente administrador logado**; o `openapi.json` também é protegido.
- **Hospedagem dos arquivos:** o Scalar fica **dentro da própria API** (sem CDN), entregue pela rota protegida.
- **Geração:** feita **ao montar o pacote** (`api/storage/openapi.json`), não em produção; em desenvolvimento, sob demanda.
- **Teste automatizado:** o `openapi.json` é validado, e o front **gera os tipos** a partir dele.

---

## 10. Testes

| Tipo | Ferramenta | Alcance |
|---|---|---|
| **Unitários** (sem banco) | PHPUnit, repositórios *fake* via PHP-DI | Regras de domínio (estágio, progresso %, Atrasado, cobrança mensal, data do status/tempo na lição), serviços, fingerprint e montagem do log, validadores, autorização por perfil |
| **Integração** (MySQL de teste) | PHPUnit | Aplica **todas as migrações** em um banco vazio; valida o padrão do nome e as colunas `sys_*` em toda tabela; repositórios e consultas de dashboard; transação operação + log; unicidade com exclusão lógica; login/sessão |
| Front | Vitest + Testing Library | Telas críticas e formulários |

- Banco de teste **separado** (`..._test`); a suíte **se recusa a rodar** se o nome do banco não terminar em `_test`.
- Comandos: `composer test`, `composer test:unit`, `composer test:integration`, `composer analyse` (PHPStan).
- Como o deploy é manual, os testes rodam **localmente antes de montar o pacote**; o script de entrega **não gera o pacote se os testes falharem**. *(Se quiser, o GitHub Actions pode rodar os testes com um MySQL de serviço, sem fazer deploy.)*

---

## 11. Ambiente local e entrega (manual)

**Desenvolvimento local, sem Docker** *(premissa minha: PHP 8.4 CLI, Composer, Node LTS e um MySQL local, por exemplo Laragon ou XAMPP)*
- API: `php -S 127.0.0.1:8080 -t public public/dev-router.php` (o *router* de desenvolvimento reproduz o `.htaccess`).
- Front: servidor do Vite com **proxy** de `/api` para a porta 8080.
- Dois bancos locais: `ddc_dev` e `ddc_test`.

**Entrega manual**
1. `bin/release` (script): roda testes e análise estática → `vite build` → `composer install --no-dev -o` → gera `openapi.json` → monta `release/ddc-AAAAMMDD-HHMM.zip` com `public/` e `api/` **sem** `tests/`, **sem** `.env`, **sem** logs.
2. Aplicar no phpMyAdmin as **migrações pendentes** (os `.sql` novos, em ordem).
3. Enviar o `.zip` pelo **Gerenciador de Arquivos do cPanel** e **extrair** em `/home/domains/ddc.cpod.com.br/` (muito mais rápido e seguro que enviar milhares de arquivos de `vendor/` por FTP).
4. Conferir `GET /api/v1/health` (mostra a versão publicada).
- `.env` de produção é criado **uma vez** no servidor, em `api/.env`, e nunca vai no pacote.

**Rotina (cron do cPanel):** gerar cobranças do mês (diário). **Backup do banco está fora do escopo de desenvolvimento.**
**Segurança mínima:** HTTPS com redirecionamento e HSTS; cabeçalhos de segurança (CSP, `X-Content-Type-Options`, `Referrer-Policy`); `api/` fora do docroot; saída sempre escapada; consultas preparadas; log de auditoria de gravações; dados de alunos (inclusive menores): LGPD, só o necessário.

---

## 12. Escolhas minhas (aprovadas em 17/11/2026, exceto o backup)

| # | Escolha | Alternativa |
|---|---|---|
| 1 | Cada migração **se registra** em `sys_migracao` ao final do próprio `.sql` | Registro manual ou sem registro |
| 2 | `sys_alt` atualizado pelo banco (`ON UPDATE`), `sys_cri` por `DEFAULT`; `sys_del` pelo app | Tudo pelo app |
| 3 | Hora local de Brasília nos `DATETIME` (conexão com `-03:00`) | Gravar em UTC |
| 4 | `sys_log` **não gera log para si mesmo**; as tabelas técnicas (`sessoes`, `tentativas_login`, `redefinicoes_senha`) também ficam fora; apenas gravações são logadas; segredos mascarados | Logar tudo, inclusive leituras |
| 5 | Log com `ip` e `user_agent` além do que você pediu | Só o que você pediu |
| 6 | PHP-DI nos testes unitários; Respect\Validation | Montagem manual; validação própria |
| 7 | Scalar **embutido** (sem CDN), documentação gerada ao montar o pacote | CDN; Redoc |
| 8 | Recharts para os dashboards | ECharts |
| 9 | `cobrancas` separada de `pagamentos` (baixa parcial) | Pagamento na própria cobrança |
| 10 | Script `bin/release` gera um `.zip` para extrair no cPanel | Enviar arquivos por FTP |
| 11 | ~~Backup diário do banco por cron~~ — **retirado: fora do escopo de desenvolvimento** | — |
| 12 | Hash do log **sem** encadeamento (pode ser adicionado) | Encadear desde já |

---

## 13. Itens a confirmar na HostGator/cPanel (rápidos, antes de codar)

| Item | Por que importa | Se faltar |
|---|---|---|
| **PHP 8.4 ativo** para o subdomínio (MultiPHP) e extensões: `pdo_mysql`, `mbstring`, `zip`, `gd`, `xml`/`simplexml`/`xmlwriter`, `fileinfo`, `openssl`, `sodium` | PhpSpreadsheet (gráficos exigem `gd`), e-mail, senha | Gráficos no Excel dependem de `gd` |
| **Cron** habilitado (e qual intervalo mínimo) | Cobrança mensal | Botão manual do administrador |
| Versão do **MySQL/MariaDB** | Sintaxe de coluna gerada, `JSON`, *collation* | Ajustar as migrações |
| **SMTP**: criar conta de e-mail e SPF/DKIM do domínio | Recuperação de senha e convites | Só definição de senha pelo administrador |
| Limites `memory_limit` e `max_execution_time` | Geração de Excel com gráficos | Exportar por período menor |

---

## 14. Fora desta definição (vem no escopo)

Funcionalidades por entrega, telas e fluxos, matriz de permissões por perfil, modelo de dados detalhado, critérios de aceite, ordem e esforço, plano de testes por funcionalidade e política de retenção dos logs.

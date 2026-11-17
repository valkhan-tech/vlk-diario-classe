# Diário de Classe v2 — Plano de implementação (E0–E6 + Docker + Seeder)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (execução nativa, linear, nesta sessão). Steps usam checkbox (`- [ ]`).

**Goal:** Implementar as entregas E0–E6 do escopo aprovado, mais ambiente Docker de desenvolvimento e um seeder abrangente.

**Architecture:** SPA React (Vite) servida de `public/`, API PHP 8.4 (Slim 4, PDO sem ORM, PHP-DI) atrás de `public/api.php`, código em `api/` fora do docroot. MySQL é a fonte de verdade; toda gravação passa por `RepositorioBase`, que relê o registro e grava `sys_log` (com fingerprint SHA-256) na mesma transação. Regras derivadas ficam em `App\Dominio` (puras, testadas sem banco).

**Tech Stack:** PHP 8.4, Slim 4 + slim/psr7, PHP-DI, vlucas/phpdotenv, Monolog, PHPMailer, PhpSpreadsheet, swagger-php + Scalar, Respect\Validation, PHPUnit, PHPStan · React 19, TypeScript, Vite, React Router, TanStack Query, React Hook Form + Zod, Tailwind, Recharts, date-fns, openapi-typescript/openapi-fetch, Vitest · MySQL 8.0, Mailpit (dev).

**Spec:** `docs/escopo.md` (escopo) e `docs/stack-proposta.md` (stack). Regras de negócio: `docs/wiki/regras.html`.

## Global Constraints
- Domínio único: `https://ddc.cpod.com.br/` (front) e `/api/*` (API). Docroot = `public/`; `api/` fora do docroot.
- **Sem offline.** Chave primária `BIGINT UNSIGNED AUTO_INCREMENT`. **Toda** tabela tem `sys_cri` (`DEFAULT CURRENT_TIMESTAMP`), `sys_alt` (`ON UPDATE CURRENT_TIMESTAMP`), `sys_del` (`NULL`), exclusão lógica. Exceção única: `sys_migracao`.
- Hora local de Brasília nos `DATETIME` (conexão `SET time_zone = '-03:00'`). Dinheiro em centavos inteiros no domínio, `DECIMAL(12,2)` no banco.
- Migrações: `api/migrations/AAAAMMDDHHMMSS_descricao.sql`, só para frente, cada arquivo termina com `INSERT INTO sys_migracao (arquivo) VALUES ('<nome>')`, execução manual (em dev: `api/bin/migrar.php`).
- `sys_log`: usuário, origem (URL+controller), tabela/id, operação (`INSERT`/`UPDATE`/`DELETE`), `registro_json` (registro **completo relido após a ação**, segredos mascarados), `fingerprint` SHA-256, ip, user_agent. **Não** loga: `sys_log`, `sessoes`, `tentativas_login`, `redefinicoes_senha`, `sys_migracao`. Só gravações.
- Sessão opaca em cookie `HttpOnly; Secure; SameSite=Lax` (Secure desligado quando `APP_ENV=local`); no banco só o hash do token; CSRF por cabeçalho `X-Requested-With: XMLHttpRequest` nas rotas que gravam.
- Erros em `application/problem+json`. API em `/api/v1`. Documentação `/api/docs` e `/api/openapi.json` **só administrador**.
- Testes: PHPUnit unitários (sem banco) + integração (MySQL; **recusa rodar** se `DB_NAME_TEST` não terminar em `_test`).
- Exportação Excel formatada com totais (fórmulas) e gráficos. Banco de teste/dev em Docker; produção sem Docker.
- Regras: ver `docs/escopo.md` seção 5 (estado da cobrança, progresso geral, estágio, data do status) — valores numéricos de aceite: progresso **11,6%**; Reprovado 10/03 e 24/03 + Aprovado 07/04 → desde **10/03**, 2 tentativas, **28 dias**.

## Review Focus
1. Cobrança parcial vencida deve ser **Atrasado**; dia 29–31 em mês curto vence no último dia; gerar duas vezes não duplica (teste de unicidade com `sys_ativo`).
2. Exclusão lógica + unicidade: recriar cobrança/e-mail/ordem de lição após excluir o anterior deve funcionar.
3. Falha ao gravar `sys_log` **desfaz** a operação (mesma transação).
4. Perfil `professora` nunca acessa `/usuarios`, `/auditoria`, `/docs`; rotas sem sessão → 401; escrita sem cabeçalho CSRF → 403.
5. Fingerprint é determinístico e independe da ordem das chaves; verificação acusa registro alterado fora do sistema.

---

## Estrutura de arquivos (decisões)

```
docker-compose.yml, docker/          (agente Docker)
api/composer.json, phpunit.xml, phpstan.neon
api/bootstrap.php                    carrega .env, container, Slim, rotas
api/config/{app.php,container.php,routes.php}
api/src/Http/{Controllers,Middleware,Resposta.php,Erros}
api/src/Aplicacao/<Modulo>/...       serviços (casos de uso)
api/src/Dominio/<Modulo>/...         regras puras + enums
api/src/Infra/{Banco,Auditoria,Email,Excel,Docs,Sessao}/...
api/migrations/*.sql   api/bin/{migrar,seed,gerar-openapi,cobrancas,limpeza,gerar-semente-admin}.php
api/tests/{Unit,Integration}/...     api/storage/{logs,openapi.json}
web/                                 Vite + React + TS (src/{api,auth,components,pages,lib})
bin/release.php                      monta release/ddc-AAAAMMDD-HHMM.zip
```

Convenção de namespace: `App\Http`, `App\Aplicacao`, `App\Dominio`, `App\Infra` (PSR-4 → `api/src/`).

---

## Tarefas

### T0 — Docker (agente em paralelo)
Criado por subagente: `docker-compose.yml`, `docker/`, `docs/desenvolvimento.md`. Serviços `app` (php:8.4-apache, docroot `public/`, :8080), `db` (mysql:8.0, :3307, bancos `ddc_dev` e `ddc_test`, usuário `ddc`/`ddc`), `mailpit` (:8025), `phpmyadmin` (:8081), `web` (perfil opcional, :5173).
- [ ] Verificar: `docker compose ps` saudável; `curl -i localhost:8080/api/v1/health` responde (501 do placeholder até T1).

### T1 — E0a: núcleo da API, migrações e auditoria
**Files:** `api/composer.json`, `api/bootstrap.php`, `api/config/*`, `api/src/Infra/Banco/{Conexao,RepositorioBase,Migrador}.php`, `api/src/Infra/Auditoria/{Fingerprint,Mascara,ContextoOperacao,RegistradorLog}.php`, `api/src/Http/{Resposta,Erros/*}.php`, migrações `sys_migracao`, `sys_log`, `usuarios`, `sessoes`, `redefinicoes_senha`, `tentativas_login`, `listas`, `api/bin/migrar.php`, `phpunit.xml`.
**Interfaces (produz):**
- `Conexao::transacao(callable $fn): mixed` (aninhável), `consultar(string,array): array`, `um(string,array): ?array`, `executar(string,array): int`, `ultimoId(): int`.
- `Fingerprint::calcular(array $registro): string` (SHA-256 do JSON canônico: chaves ordenadas recursivamente, escalares como string, `null` preservado).
- `Mascara::aplicar(array $registro): array` (troca `senha_hash`, `token_hash` por `***`).
- `ContextoOperacao` (mutável, compartilhado): `usuarioId`, `perfil`, `origem`, `controller`, `ip`, `userAgent`.
- `RepositorioBase` (abstrato, `tabela(): string`, `colunas(): array`): `buscar(int): ?array` (só `sys_del IS NULL`), `inserir(array): array`, `atualizar(int, array): array`, `excluir(int): void`; cada um em transação: executa → relê → `RegistradorLog::registrar(string $operacao, string $tabela, int $id, array $registro)`.
- `Resposta::json(ResponseInterface, mixed, int=200)`; exceções `ErroValidacao(array $erros)` 422, `NaoAutenticado` 401, `Proibido` 403, `NaoEncontrado` 404, `Conflito` 409 → handler `problem+json`.
- [ ] Testes unitários: `FingerprintTest` (ordem de chaves não altera; muda ao mudar valor; `null` ≠ `""`), `MascaraTest`, `ContextoOperacaoTest`.
- [ ] Testes de integração: `MigracoesTest` (aplica todas num banco vazio; **toda** tabela exceto `sys_migracao` tem `sys_cri/sys_alt/sys_del`; padrão do nome), `RepositorioBaseTest` (insert/update/excluir geram `sys_log` com fingerprint = recalculado; falha no log desfaz a operação; unicidade com `sys_ativo` permite recriar após excluir).
- [ ] Implementar; `GET /api/v1/health` retorna `{status, versao, banco}`. Rodar `docker compose exec app composer test`. Commit.

### T2 — E0b: acesso, usuários e autorização
**Files:** `api/src/Aplicacao/Acesso/{AutenticacaoServico,SenhaServico,UsuarioServico}.php`, `api/src/Infra/{Sessao,Email}/*`, `api/src/Http/Middleware/{Autenticacao,Perfis,Csrf,Contexto}.php`, controllers `Sessao`, `Senha`, `Usuarios`, migração/script de semente do 1º admin (`bin/gerar-semente-admin.php`).
**Interfaces:** `AutenticacaoServico::entrar(string $email, string $senha, string $ip, string $ua): array{token:string, usuario:array}` (5 falhas em 15 min por e-mail **ou** ip → `Proibido`/429), `::sair(string $token)`, `::usuarioDoToken(string $token): ?array`; `SenhaServico::solicitarRecuperacao(string $email)`, `::redefinir(string $token, string $nova)`, `::trocar(int $usuarioId, string $atual, string $nova)`; `UsuarioServico::criar/atualizar/definirSenha(int,string)` (marca `troca_senha_obrigatoria`); `EmailEnviador::enviar(string $para, string $assunto, string $corpo): void` (PHPMailer SMTP; `EmailEmMemoria` nos testes).
- [ ] Testes: login ok/erro genérico, bloqueio na 5ª falha, sessão expirada, hash do token no banco, recuperação (token uso único e validade), administrador define senha → troca obrigatória, matriz de perfis por rota (professora → 403 em `/usuarios`), POST sem cabeçalho CSRF → 403.
- [ ] Implementar e commitar.

### T3 — E0c: documentação da API, entrega e front base
**Files:** `api/src/Infra/Docs/*`, `api/resources/docs/scalar.js` (auto-hospedado), `api/bin/gerar-openapi.php`, `bin/release.php`, `web/**` (Vite, Tailwind, router, cliente tipado, auth, layout tablet-first, telas Login/Recuperar/Trocar senha/Usuários), `phpstan.neon`.
**Interfaces:** `GET /api/docs` (HTML Scalar) e `GET /api/openapi.json` (admin); front `useAuth(): {usuario, entrar(), sair()}`; `api` = `createClient<paths>()` com `X-Requested-With`.
- [ ] Testes: `OpenApiTest` (JSON válido, contém `/sessao`), Vitest `login.test.tsx`; `composer analyse` sem erros.
- [ ] `bin/release.php` gera `.zip` com `public/` (build) + `api/` (vendor sem dev, sem tests/.env). Commit.

### T4 — E1: listas e alunos
**Files:** `Dominio/Alunos/{Idade,SituacaoAluno}.php`, `Infra/Repositorios/{Listas,Alunos}.php`, `Aplicacao/Alunos/AlunoServico.php`, controllers, migrações `alunos`, front `pages/alunos/*`, `pages/listas`.
**Interfaces:** `Idade::faixaEtaria(DateTimeImmutable $nasc, DateTimeImmutable $hoje): string` (Criança <12 · Adolescente 12–17 · Adulto ≥18), `Idade::mesesAtivos(inicio, hoje): int`. Rotas: `/listas/{tipo}`, `/alunos` (busca `q`, filtros `instrumento_id`, `situacao`; encerrados ocultos por padrão), `/alunos/{id}`.
- [ ] Testes: `IdadeTest`; integração: CRUD com log, `dia_vencimento` 1–31 (422 fora), encerrados ocultos, permissões. Commit.

### T5 — E2: aulas, calendário e agenda
**Files:** `Dominio/Aulas/{Presenca,Frequencia}.php`, `Infra/Repositorios/Aulas.php`, `Aplicacao/Aulas/{AulaServico,AgendaServico}.php`, migração `aulas`, front `pages/aulas/*` (lançar, histórico, calendário, hoje).
**Interfaces:** `Presenca` enum (`presente`, `falta_aluno_justificada`, `falta_aluno_sem_aviso`, `falta_professora`); `Frequencia::percentual(array $presencas): ?float` (faltas da professora fora; sem base → `null`). Rotas: `/aulas`, `/alunos/{id}/aulas`, `/calendario?mes=AAAA-MM`, `/agenda/hoje`.
- [ ] Testes: `FrequenciaTest`; integração: lançar/editar/excluir, calendário agrega por dia, agenda de hoje marca lançadas. Commit.

### T6 — E3: financeiro operacional
**Files:** `Dominio/Financeiro/{EstadoCobranca,Vencimento}.php`, `Aplicacao/Financeiro/{GeracaoCobrancas,CobrancaServico,PagamentoServico}.php`, repositórios, migrações `cobrancas` (coluna gerada `sys_ativo`, único `aluno_id+competencia+sys_ativo`) e `pagamentos`, `api/bin/{cobrancas,limpeza}.php`, front `pages/financeiro/*` + aba Financeira da ficha.
**Interfaces:** `EstadoCobranca::calcular(string $situacao, int $valorCent, int $descontoCent, int $pagoCent, DateTimeImmutable $venc, DateTimeImmutable $hoje): string` ∈ `cancelada|pago|atrasado|parcial|pendente`; `Vencimento::para(int $ano, int $mes, int $dia): DateTimeImmutable`; `GeracaoCobrancas::gerar(DateTimeImmutable $competencia): array{criadas:int, existentes:int}` (só alunos `ativo`, valor da mensalidade no momento). Rotas: `/cobrancas`, `/cobrancas/gerar`, `/cobrancas/{id}/pagamentos`, `/alunos/{id}/ficha-financeira`.
- [ ] Testes: `EstadoCobrancaTest` (parcial vencida = atrasado), `VencimentoTest` (31/fev → 28/29), integração: gerar 2× não duplica, Pausado/Encerrado ignorados, mudar mensalidade não altera existentes, baixa parcial/total/estorno reabre. Commit.

### T7 — E4: dashboards e Excel
**Files:** `Aplicacao/Financeiro/DashboardServico.php`, `Infra/Excel/{ExportadorCobrancas,ExportadorResumo,ExportadorInadimplencia,ExportadorFormas,ExportadorFicha}.php`, front `pages/financeiro/dashboard`.
**Interfaces:** `DashboardServico::resumo(DateTimeImmutable $de, DateTimeImmutable $ate): array{previsto,recebido,aberto,atrasado,inadimplencia,porMes[],porForma[],envelhecimento[],ranking[]}`; rotas `/dashboards/financeiro`, `/exportacoes/{tipo}.xlsx`.
- [ ] Testes: totais do dashboard = soma de cobranças/pagamentos; xlsx abre via PhpSpreadsheet, tem fórmula `SUM`, tem gráfico. Commit.

### T8 — E5: progresso
**Files:** `Dominio/Progresso/{Nivel,Estagio,StatusLicao,SituacaoMetodo,MediaMetodo,ProgressoGeral,EstagioCalculado,HistoricoLicao}.php`, `Aplicacao/Progresso/*`, migrações `metodos`, `licoes`, `aluno_metodo`, `avaliacoes_licao`, `progresso_aluno`, front `pages/progresso/*` (métodos, lições, colar lista, ficha de progresso, avaliar na aula).
**Interfaces:** `ProgressoGeral::calcular(array $metodos): ?float` (cada item `{totalLicoes:int, aprovadas:int, situacao:string}`; Pausado e sem lições fora; Concluído = 100%); `EstagioCalculado::de(array $metodos): ?string` (maior nível entre Iniciado/Concluído); `EstagioEfetivo::de(?string $manual, ?string $calculado): array{valor,origem}`; `HistoricoLicao::resumir(array $avaliacoes): array{status,desde,tentativas,tempoDias}`.
- [ ] Testes (valores do escopo): 20 lições/10 aprov + 480/48 → **11,6**; histórico 10/03, 24/03 reprovado + 07/04 aprovado → `desde 10/03`, 2 tentativas, **28** dias; Pausado fora; sobrescrita prevalece. Integração: colar lista cria lições em ordem; sugestão de Concluído; aptidão logada. Commit.

### T9 — E6: Início, impressão e auditoria
**Files:** `Aplicacao/Inicio/InicioServico.php`, `Aplicacao/Auditoria/AuditoriaServico.php`, front `pages/inicio`, `pages/auditoria`, CSS de impressão das fichas.
**Interfaces:** `AuditoriaServico::verificar(int $logId): array{integro:bool, atual:?bool}` (hash do `registro_json` = `fingerprint`; se for o último log do registro, compara com a linha atual).
- [ ] Testes: `verificar` acusa linha alterada por SQL direto; KPIs batem com origem. Revisão de acessibilidade (zoom, foco, botões). Commit.

### T10 — Seeder abrangente e fechamento
**Files:** `api/bin/seed.php` (+ `api/src/Infra/Seed/*`), `docs/desenvolvimento.md` (acréscimos).
Gera, determinístico (`mt_srand(2026)`), **pelos serviços/repositórios** (para gerar `sys_log`): usuários (`admin@ddc.local` / `Admin@123`, `professora@ddc.local` / `Professora@123`), listas, ~16 alunos (ativos, pausados, encerrados, vários instrumentos e dias de vencimento 5/10/15/28/30/31), 3 meses de aulas (todas as presenças), cobranças dos últimos 4 meses com pagos, parciais, atrasados e cancelada, métodos (3 + hinário de jovens 50 + hinário oficial 430), associações em várias situações, avaliações com repetições de reprovação, aptidões e estágios manuais. Recusa rodar com `APP_ENV=production`.
- [ ] Teste: `SeedTest` roda o seeder num banco de teste e confere contagens e que `sys_log` tem registros. Verificação final: suites completas, build do front, `bin/release.php`. Commit.

---

## Auto-revisão
- Cobertura do escopo: E0 (T1–T3), E1 (T4), E2 (T5), E3 (T6), E4 (T7), E5 (T8), E6 (T9), seeder (T10), Docker (T0). Opcionais aprovados por sugestão (lição de casa anterior, atalho WhatsApp, data da aptidão via log) entram em T5, T4, T8.
- Nomes e assinaturas usados nas tarefas seguintes conferem com os definidos em T1 (`Conexao`, `RepositorioBase`, `RegistradorLog`, `Fingerprint`).
- Execução: o dono pediu para seguir linearmente até concluir todas as fases; este plano não aguarda revisão adicional.

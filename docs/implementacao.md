# Implementação v2 — estado, verificação e pontos de atenção

**Data:** 17/11/2026 · **Base:** `docs/escopo.md` (E0–E6) e `docs/stack-proposta.md` · **Plano:** `docs/superpowers/plans/2026-11-17-implementacao-v2.md`

## 1. O que foi entregue

| Entrega | Conteúdo | Estado |
|---|---|---|
| **E0** Fundação e acesso | Slim + PHP-DI, `problem+json`, migrações, `RepositorioBase` (grava + relê + `sys_log` com fingerprint na mesma transação), login por sessão opaca em cookie, limite de tentativas, recuperação de senha por e-mail (SMTP), troca e definição de senha pelo administrador, CSRF por cabeçalho, perfis por rota, usuários, `/api/docs` (Scalar auto-hospedado, só admin), `bin/release` | ✔ |
| **E1** Cadastros | Listas (instrumentos, tipos de aula, formas de pagamento), alunos (busca, filtros, horário semanal, dia de vencimento, situação, WhatsApp/ligar), ficha | ✔ |
| **E2** Aulas | Lançar/corrigir/excluir aula, histórico, frequência, calendário mensal, agenda do dia, lição de casa anterior | ✔ |
| **E3** Financeiro | Cobrança mensal idempotente (só ativos, sem proporcional, vencimento fixo com ajuste em mês curto), baixa total/parcial, estorno, ajuste/cancelamento, ficha financeira, cron `bin/cobrancas.php` e `bin/limpeza.php` | ✔ |
| **E4** Dashboards e Excel | KPIs, previsto × recebido, inadimplência, formas de pagamento, envelhecimento, ranking; 5 exportações `.xlsx` formatadas, com fórmulas de total e gráficos | ✔ |
| **E5** Progresso | Métodos e hinários (nível dá o estágio), lições (uma a uma, colar lista, reordenar), métodos do aluno (Iniciado/Pausado/Concluído), avaliação de lição com data (histórico só de acréscimo, data inicial mantida), estágio calculado × manual, progresso geral %, aptidão (checkbox), técnica, ficha de progresso, avaliação dentro da tela de aula | ✔ |
| **E6** Início e auditoria | Painel (KPIs reais, agenda, estágios), impressão A4 das fichas, consulta ao `sys_log` com **verificação de fingerprint** (acusa alteração fora do sistema) | ✔ |
| Docker de desenvolvimento | `app` (PHP 8.4 + Apache), MySQL 8, Mailpit, phpMyAdmin | ✔ |
| Seeder abrangente | `api/bin/seed.php` (ver `docs/desenvolvimento.md`) | ✔ |

Opcionais incluídos (os marcados "incluir" no escopo): lição de casa anterior ao lançar a aula, atalho de WhatsApp/ligar, data da aptidão via log.

## 2. Como foi verificado (evidência)

| Verificação | Resultado |
|---|---|
| API: 121 testes (unitários + integração em MySQL `ddc_test`), 720 asserções | passam |
| PHPStan nível 6 | sem erros |
| Front: 11 testes (Vitest) e `tsc --noEmit` | passam |
| Casos numéricos do escopo | **progresso geral 11,6%**; reprovado 10/03 e 24/03 + aprovado 07/04 → desde 10/03, 2 tentativas, **28 dias** (testes unitário e de API) |
| Atrasado = não confirmado no vencimento, inclusive pagamento parcial vencido | teste unitário (9 cenários) e de API |
| Vencimento dia 31 em fevereiro/abril; gerar 2× não duplica; Pausado/Encerrado fora | testes |
| Toda tabela (exceto `sys_migracao`) tem `sys_cri/sys_alt/sys_del`; padrão do nome das migrações | teste de integração |
| Falha ao gravar o log desfaz a operação; recriar registro após exclusão lógica | testes |
| Excel: fórmulas `SUM`, autofiltro e gráficos presentes no `.xlsx` (lido de volta com PhpSpreadsheet) | testes |
| Pacote `bin/release.mjs`: testes → PHPStan → testes do front → build → `.zip` (6,3 MB), sem `.env`, testes, seeder nem router de dev | executado |
| **Navegador real (Chrome headless) contra o Docker**, 2 roteiros com seeder: login/perfis, todas as telas, abas da ficha, calendário, dashboard com gráficos, download do Excel, `/api/docs`, cadastro de aluno pelo formulário, validação do vencimento, aula com lição aprovada, geração de cobrança, baixa de pagamento, método + lista colada, auditoria | sem erros HTTP/JS (além do 401 esperado de `/eu` antes do login) |
| E-mail de recuperação de senha pelo SMTP real (PHPMailer → Mailpit) | recebido |

Os roteiros de navegador **não estão no repositório** (rodaram do diretório de trabalho temporário). Se quiser, viram uma suíte Playwright em `web/e2e`.

## 2.1 Identidade visual (17/11/2026)

Aplicada a paleta **Madeira & Âmbar** do mockup (`docs/mockup/`), sem roxo, com as 7 cores das notas (dó…si) em gráficos, avatares e selos.

- **Tokens:** `web/src/index.css` remapeia as escalas do Tailwind (slate, emerald, red, amber, sky) para a paleta; fontes Fraunces (títulos) e Inter (texto), **hospedadas no próprio pacote** (sem Google Fonts).
- **Navegação:** menu lateral colorido no computador (≥1024 px), só ícones no tablet (≥768 px) e barra inferior + "Mais" no celular. Busca de alunos, notificações (cobranças atrasadas, aula sem lançamento, conclusões a confirmar, aniversários) e menu do usuário na barra superior.
- **Tablet e celular:** alvos de toque ≥44 px, campos a 16 px (sem zoom no iOS), tabelas viram **cartões** no celular, modais viram *bottom sheets*, barra de ações fixa nos formulários, respeito à área segura (`safe-area`), sem rolagem horizontal (verificado em 390, 820 e 1280 px).
- **Novos componentes:** `Avatar`, `Anel`, `Barra`, `Interruptor`, `Segmentos`, `ToastProvider` (avisos rápidos), ícones SVG próprios.
- **API:** `/inicio` ganhou `notificacoes`, `aniversariantes` e `mes` (frequência e recebido); `/alunos` devolve `estagio` e `progresso_geral`.
- **Testes:** `npm test` agora usa `scripts/testar.mjs` (no Windows, `e:\` e `E:\` duplicavam módulos e o Vitest falhava).

## 3. Diferenças em relação ao `stack-proposta.md`

| Previsto | Feito | Motivo / impacto |
|---|---|---|
| TanStack Table | tabelas simples (`<table>`) | volume pequeno; menos código |
| `openapi-typescript` + `openapi-fetch` | tipos do front escritos à mão (`web/src/api/tipos.ts`) | a documentação descreve rotas e parâmetros, mas não todos os schemas de resposta |
| ESLint + Prettier, PHP-CS-Fixer | **não configurados** | ficaram fora; `tsc` estrito e PHPStan cobrem o essencial |
| PDF no servidor (Dompdf) | só impressão A4 (CSS) | era "só se o escopo pedir" |
| 4 presenças (regras) | **5** (`feriado_evento` existia na planilha original) | fora do cálculo de frequência, como a falta da professora |
| — | coluna gerada `sys_ativo` (unicidade com exclusão lógica) é omitida do JSON do log | é derivada de `sys_del` |
| Radix UI | usado em diálogos e abas (`dialog`, `tabs`) | o resto é HTML nativo + Tailwind |

Versões instaladas (as mais recentes disponíveis hoje): React 19, React Router 7, TypeScript 7, Vite 8, Vitest 5, Tailwind 4, Zod 4; PHP 8.4, Slim 4.15, PHPUnit 13, PHPStan 2, PhpSpreadsheet 5, PHPMailer 7, swagger-php 7, Respect\Validation 2.

## 4. O que NÃO foi possível verificar aqui (depende da HostGator)

- Execução real no plano de hospedagem: extensões PHP (`gd`, `zip`), **cron**, **SMTP com SPF/DKIM**, docroot em `/home/domains/ddc.cpod.com.br/public/`, comportamento do `.htaccess` sob o Apache do cPanel (testado no Apache do Docker).
- Migrações no phpMyAdmin do plano (a sintaxe foi validada no MySQL 8.0; se o plano usar **MariaDB**, vale rodar o conjunto uma vez e conferir colunas geradas e `CHECK`).

## 5. Publicação (resumo)

1. `node bin/release.mjs` (com o Docker no ar) → `release/ddc-AAAAMMDD-HHMM.zip`.
2. No phpMyAdmin, aplicar em ordem as migrações novas de `api/migrations/`.
3. **Primeiro administrador:** `docker compose exec app php api/bin/gerar-semente-admin.php "Nome" email@dominio.com` imprime o `INSERT` (colar no phpMyAdmin) e a senha provisória; a troca é obrigatória no primeiro acesso.
4. Enviar o `.zip` ao Gerenciador de Arquivos do cPanel e extrair em `/home/domains/ddc.cpod.com.br/`.
5. Criar `api/.env` **uma vez** no servidor (modelo: `api/.env.example`: `APP_ENV=production`, `APP_URL=https://ddc.cpod.com.br`, banco, SMTP).
6. Cron do cPanel (diário): `php /home/domains/ddc.cpod.com.br/api/bin/cobrancas.php` e `php /home/domains/ddc.cpod.com.br/api/bin/limpeza.php`.
7. Conferir `https://ddc.cpod.com.br/api/v1/health` (mostra a versão publicada).

## 6. Pontos para você decidir / conferir

1. **Cálculo do estágio:** implementado como combinado — nível do método (maior entre Iniciados/Concluídos), progresso geral por média ponderada. Se preferir o nível do método *atual*, muda uma função (`EstagioCalculado::de`).
2. **Pagamento acima do saldo:** aceito (decisão de 17/11/2026). A tela alerta antes de confirmar e a cobrança mostra "+R$ x a mais"; a API devolve o campo `excedente` (cobrança e resumo). O excedente não aumenta o previsto, não gera saldo negativo e some ao estornar o pagamento.
3. **Seeder fora do pacote:** `bin/seed.php` e `src/Infra/Seed` não vão para produção.
4. **Responsável (futuro):** o perfil existe e todas as rotas atuais o recusam; as telas dos pais ficam para a próxima fase.
5. Os `.xlsx` do app antigo continuam em `legacy/`; o `README.md` da raiz descreve a nova estrutura.

# Escopo do projeto — Diário de Classe (v2)

**Status:** proposta para aprovação · **Data:** 17/11/2026 · **Depende de:** [stack aprovada](stack-proposta.md)
**Fontes:** regras de negócio da professora (`docs/wiki/regras.html`), pendências e roadmap da avaliação (`docs/wiki/`), decisões de stack de 17/11/2026.
**Nada de desenvolvimento começa antes da sua aprovação deste documento.**

Esforço é **relativo** (P pequeno · M médio · G grande), não prazo.

---

## 1. O que você precisa aprovar

| # | Decisão | Onde |
|---|---|---|
| A | Objetivo, limites e o que **não** entra (seções 2 e 3) | 2, 3 |
| B | Conteúdo e **ordem das 7 entregas** (E0–E6) | 6, 7 |
| C | Quais **opcionais** entram agora ou ficam para depois | 8 |
| D | As **suposições** que tomei (podem estar erradas) | 12 |
| E | O momento de **entrar em uso real** (sugiro após a E3) | 7 |

---

## 2. Objetivo

Sistema web, em `https://ddc.cpod.com.br`, para **uma professora de música** (Piano, Órgão, Flauta) registrar aulas, acompanhar o **progresso por métodos e lições** e controlar o **financeiro** dos alunos, com **login** e **banco MySQL centralizado**. Substitui o app antigo (HTML/JS sobre planilha Excel no OneDrive), mantido em `legacy/` só como referência.

**Perfis:** administrador e professora agora; **pais/responsáveis** consultam progresso e financeiro **no futuro** (não nesta fase, mas nada do desenho deve impedir).

### Regras de negócio que valem (resumo)
- **Presença não gera progresso.** Presença é só frequência (4 situações: Presente; Falta — aluno justificada; Falta — aluno sem aviso; Falta — professora).
- **Progresso = métodos e lições.** Lição: Pendente / Aprovado / Reprovado, com data. Lições são, via de regra, sequenciais (o sistema orienta, não impede). Lição reprovada pode ser repetida e **mantém a data inicial** da sequência de mesmo status. O hinário (50 de jovens + 430 do culto oficial) é um **método** como os outros. Situação do método para o aluno: Iniciado, Pausado, Concluído.
- **Progresso geral (%)** = média ponderada simples das médias de cada método associado (média do método = aprovadas ÷ total; peso = nº de lições; Pausado fica fora).
- **Estágio** (Iniciante, Básico, Intermediário, Avançado) está associado ao **nível do método**. Estágio calculado = maior nível entre os métodos Iniciados ou Concluídos; a professora pode **sobrescrever** a qualquer momento; **efetivo = manual se preenchido, senão calculado**.
- **Aptidão para culto** é checkbox da professora (jovens, adulto, oficialização), sem regra rígida.
- **Cobrança mensal criada pelo sistema**, só para alunos **Ativos**, **sem proporcional**, vencimento em **dia fixo por aluno**. **Atrasado** = não confirmado na data de vencimento.
- **Tempo na lição** é só informativo.

---

## 3. Fora do escopo desta fase

| Item | Observação |
|---|---|
| Modo offline, PWA, sincronização | Removidos pela decisão de stack |
| Portal dos pais/responsáveis | Perfil existe no modelo; telas e convites ficam para depois |
| Pagamento online (Pix, boleto, cartão), conciliação bancária | Baixa é manual pela professora |
| Multa e juros por atraso | Não definidos pelo negócio |
| Mais de uma professora, multi-escola | Fora da projeção |
| Backup do banco | Fora do escopo de desenvolvimento |
| Migração de dados da planilha | Começar do zero |
| Importação de planilha Excel | Excel é só **exportação** |
| Notificações automáticas (e-mail/WhatsApp) aos alunos | Futuro |
| App nativo | Web responsivo (tablet-first) |

---

## 4. Perfis e permissões

| Funcionalidade | Administrador | Professora | Responsável *(futuro)* |
|---|:-:|:-:|:-:|
| Login, trocar a própria senha, recuperar senha | ✔ | ✔ | ✔ |
| Gerir usuários, definir senhas | ✔ | — | — |
| Documentação da API (`/api/docs`) | ✔ | — | — |
| Consultar auditoria (`sys_log`) | ✔ | — | — |
| Listas (instrumentos, tipos de aula, formas de pagamento) | ✔ | ✔ | — |
| Alunos, aulas, calendário | ✔ | ✔ | só os seus alunos (leitura) |
| Financeiro, dashboards, exportações Excel | ✔ | ✔ | ficha financeira dos seus alunos (leitura) |
| Métodos, lições, progresso, estágio, aptidão | ✔ | ✔ | ficha de progresso dos seus alunos (leitura) |

*Suposição 1:* o administrador é um **superconjunto** da professora (pode operar tudo, além da administração).

---

## 5. Modelo de dados (resumo)

Toda tabela: `id BIGINT AUTO_INCREMENT`, `sys_cri`, `sys_alt`, `sys_del`. Exclusão é **lógica**. Detalhes das convenções em `stack-proposta.md`, seção 5.

| Tabela | Colunas principais | Regras |
|---|---|---|
| `usuarios` | nome, email, senha_hash, perfil (`admin`/`professora`/`responsavel`), ativo, troca_senha_obrigatoria, ultimo_login_em | e-mail único entre os não excluídos |
| `sessoes`, `redefinicoes_senha`, `tentativas_login` | token em hash, validade, ip, user_agent, usada_em | técnicas; fora do log |
| `listas` | tipo (`instrumento`/`tipo_aula`/`forma_pagamento`), valor, ordem, ativo | únicas por (tipo, valor) |
| `alunos` | nome, data_nascimento, instrumento_id, contato, responsavel_nome, dia_semana, hora_aula, valor_mensalidade, dia_vencimento (1–31), data_inicio, situacao (`ativo`/`pausado`/`encerrado`), observacoes | faixa etária e meses ativos **derivados** |
| `aulas` | aluno_id, data, presenca (4 valores), motivo, tipo_aula_id, metodo_id (opcional), conteudo, hino, licao_casa, video_url, observacoes | uma linha por aluno e aula |
| `cobrancas` | aluno_id, competencia (1º dia do mês), valor, desconto, vencimento, situacao (`aberta`/`cancelada`), observacoes | **única** por aluno + competência (entre as não excluídas) |
| `pagamentos` | cobranca_id, valor, data_pagamento, forma_pagamento_id, observacoes | baixa parcial permitida; estorno = exclusão lógica |
| `metodos` | nome, tipo (`metodo`/`hinario`), nivel (Iniciante/Básico/Intermediário/Avançado), instrumento_id (opcional), ativo, observacoes | |
| `licoes` | metodo_id, ordem, titulo, observacoes | ordem única por método |
| `aluno_metodo` | aluno_id, metodo_id, data_inicio, situacao (`iniciado`/`pausado`/`concluido`), data_situacao | único por aluno + método |
| `avaliacoes_licao` | aluno_id, licao_id, status (`pendente`/`aprovado`/`reprovado`), data_tentativa, aula_id (opcional), observacoes | **só de acréscimo**; correção = exclusão lógica + novo registro |
| `progresso_aluno` | aluno_id (1:1), estagio_manual (nulo = usar o calculado), apto_jovens, apto_adulto, apto_oficializacao, tecnica_m1, tecnica_m2, tecnica_ped | |
| `sys_log`, `sys_migracao` | conforme `stack-proposta.md` | `sys_log` não gera log para si |

**Valores fixos no código (não editáveis em tela):** presença, situação do aluno, situação do método, status da lição, estado da cobrança, níveis/estágios.
**Estágios possíveis (efetivo):** Iniciante, Básico, Intermediário, Avançado, Apto — Culto Jovens, Apto — Culto Adulto, Oficializado. O **calculado** só produz os quatro primeiros; os demais vêm da sobrescrita.

### Regras derivadas (calculadas no domínio da API, com testes)
| Regra | Definição |
|---|---|
| Estado da cobrança | `cancelada` → Cancelada · pago ≥ valor−desconto → **Pago** · vencimento < hoje (não quitada, mesmo com pagamento parcial) → **Atrasado** · pago > 0 → **Parcial** · senão **Pendente** |
| Geração mensal | uma cobrança por aluno **Ativo** e competência; valor = mensalidade do aluno no momento da criação; vencimento = dia do aluno (29–31 em mês curto → último dia do mês); alterar o aluno **não** muda cobranças já criadas |
| Média do método | aprovadas ÷ total de lições (Pendente e Reprovado = não realizadas; Concluído = 100%); método sem lições fica fora |
| Progresso geral | Σ(lições × média do método) ÷ Σ lições, só métodos Iniciados/Concluídos |
| Estágio calculado | maior nível entre métodos Iniciados/Concluídos; sem métodos → sem calculado |
| Estágio efetivo | `estagio_manual` se preenchido; senão o calculado |
| Data do status da lição | data do **primeiro** registro da sequência atual de mesmo status; tentativas = tamanho da sequência; tempo na lição = data da aprovação − data inicial |
| Frequência | presenças ÷ aulas com aluno presente ou ausente (faltas da professora não contam) no período |
| Faixa etária, meses ativos | derivados de nascimento e início |

---

## 6. Entregas

Cada entrega termina em **código testado + pacote publicável + roteiro de aceite** e só é considerada concluída com a sua aprovação. Telas **tablet-first** e usáveis no computador.

### E0 — Fundação e acesso · G
- **API:** Slim + DI, `.env`, erros `problem+json`, logs, fuso, `GET /api/v1/health` (versão e banco).
- **Banco:** migrações iniciais (`sys_migracao`, `sys_log`, `usuarios`, `sessoes`, `redefinicoes_senha`, `tentativas_login`, `listas`); **repositório base** que grava + relê + gera o log com fingerprint **na mesma transação**.
- **Acesso:** login, logout, sessão em cookie, limite de tentativas, recuperação de senha por e-mail (SMTP), troca de senha, **administrador define senha** (troca obrigatória no 1º acesso), primeiro administrador criado por **migração de semente** (senha temporária; hash gerado localmente por script).
- **Autorização** por perfil em todas as rotas.
- **Documentação da API:** OpenAPI gerado dos atributos + **Scalar** em `/api/docs` (só administrador); front com tipos gerados.
- **Front:** React/TypeScript/Vite, layout tablet-first, rotas protegidas, login/recuperar/trocar senha, tratamento padrão de erros e avisos, tela de **usuários** (administrador).
- **Entrega:** `bin/release` (testes → build → `.zip`), roteiro de publicação manual.

**Aceite:** professora entra e sai; 5 senhas erradas bloqueiam temporariamente; e-mail de recuperação chega e a senha é trocada; administrador cria usuário e define senha; professora **não** acessa `/api/docs` nem usuários; **toda** gravação gera linha em `sys_log` com usuário, origem, JSON e fingerprint **recalculável**; teste de integração garante `sys_cri/alt/del` em **todas** as tabelas; `https://ddc.cpod.com.br` e `/api/v1/health` respondem em produção.

### E1 — Cadastros: alunos e listas · M
- **Listas** editáveis: instrumentos, tipos de aula, formas de pagamento.
- **Alunos:** listar (busca por nome; filtros por instrumento e situação; encerrados ocultos por padrão), criar, editar, mudar situação, excluir (lógico), **horário semanal** (dia + hora), **dia de vencimento**, valor da mensalidade, contato, responsável, início, observações.
- **Ficha do aluno** (estrutura com abas **Cadastro · Financeira · Progresso**; as duas últimas são preenchidas nas entregas E3 e E5).

**Aceite:** cadastrar, editar e inativar aluno sem tocar em planilha; faixa etária e meses ativos corretos; dia de vencimento só aceita 1–31; excluir some da lista e fica no log; permissões respeitadas.

### E2 — Aulas e calendário · M
- **Lançar aula:** aluno, data, presença (4 situações), motivo (para faltas), tipo de aula, método/conteúdo, hino, lição de casa, vídeo, observações.
- **Editar e excluir** aula lançada por engano (exclusão lógica, no log).
- **Histórico** de aulas por aluno e **frequência (%)**.
- **Calendário** mensal (pontos coloridos por situação, resumo do mês, detalhe do dia).
- **Aulas de hoje** pela agenda semanal dos alunos Ativos, com atalho “lançar”.

**Aceite:** lançar, corrigir e excluir aula; falta da professora não entra na frequência; “aulas de hoje” lista quem tem horário no dia e marca quem já foi lançado; calendário bate com as aulas lançadas.

### E3 — Financeiro operacional · G
- **Geração mensal** idempotente: comando agendado no **cron** (diário) **e** botão do administrador/professora “gerar cobranças da competência” (inclusive competências passadas).
- **Cobranças:** listar e filtrar (competência, aluno, estado), ajustar valor/desconto/vencimento de uma cobrança, cancelar.
- **Pagamentos:** baixa total ou parcial (data, valor, forma), **estorno**.
- **Ficha financeira do aluno** (tela): resumo, cobranças com estado, pagamentos, saldo, ações.
- **Visão do mês:** pendentes, atrasados e pagos; KPI de pendências.
- O cron diário também **limpa** sessões vencidas, tokens de recuperação vencidos e tentativas de login com mais de 30 dias.

**Aceite:** rodar a geração duas vezes não duplica; Pausado/Encerrado não geram cobrança; vencimento dia 31 em fevereiro cai no último dia; mudar a mensalidade não altera cobranças existentes; baixa parcial antes do vencimento → “Parcial”; vencimento passado sem quitação (inclusive parcial) → “Atrasado”, que deixa de valer ao quitar; estorno reabre a cobrança; tudo auditado.

### E4 — Dashboards financeiros e Excel · M
- **Dashboard** (agregado no servidor): previsto × recebido × em aberto × atrasado; % de inadimplência; evolução dos últimos 12 meses; recebimento por forma de pagamento; envelhecimento do atraso (1–30, 31–60, 61+ dias); ranking de atrasos. Filtros de período.
- **Exportações Excel** (formatadas, **com totais e gráficos**): (1) cobranças do período, (2) resumo mensal com gráficos, (3) inadimplência, (4) recebimentos por forma de pagamento, (5) ficha financeira do aluno.

**Aceite:** totais do dashboard = totais do Excel = soma das cobranças/pagamentos; as planilhas abrem no Excel sem pedir reparo; fórmulas de total/subtotal funcionam; gráficos presentes; acesso só autenticado.

### E5 — Progresso: métodos, lições, estágio e aptidão · G
- **Métodos e hinário:** cadastro (tipo, **nível**, instrumento opcional, ativo); **lições** uma a uma, **colando uma lista**, reordenar e ativar/inativar (o hinário entra com 50 + 430 lições).
- **Aluno × método:** associar, situação (Iniciado/Pausado/Concluído) e data; o sistema **sugere** “Concluído” quando todas as lições estão aprovadas e a professora confirma.
- **Avaliação de lições:** Pendente/Aprovado/Reprovado com data (padrão hoje, editável), a partir da ficha e **da tela de lançar aula**; sequência orientativa (sugere a próxima, avisa se fora de ordem); tentativas, data do status e tempo na lição.
- **Estágio:** calculado, sobrescrita e efetivo (com origem visível e “voltar ao calculado”); **progresso geral (%)**.
- **Aptidão** (jovens, adulto, oficialização) e **técnica** (3 campos do legado).
- **Ficha de progresso do aluno(a)** (tela): estágio (calculado × manual), progresso geral, aptidão, métodos associados (situação, %, lição atual, tentativas, tempo), lições com status e data, frequência.

**Aceite (casos numéricos):** método de 20 lições com 10 aprovadas (50%) + hinário de 480 com 48 aprovadas (10%) → progresso geral **11,6%**; Reprovado em 10/03 e 24/03, Aprovado em 07/04 → data do status da reprovação **10/03**, 2 tentativas, tempo na lição **28 dias**; método Pausado não entra no cálculo; sobrescrita prevalece e “voltar ao calculado” restaura; marcar/desmarcar aptidão fica no log.
**Dependência de conteúdo:** os **títulos dos 480 hinos** (e das lições dos métodos) precisam ser fornecidos; o sistema entrega a ferramenta de colar listas, não o conteúdo.

### E6 — Início, fichas para impressão e auditoria · M
- **Início:** KPIs reais (alunos Ativos, aulas de hoje, cobranças atrasadas, aptos para culto), agenda do dia, distribuição por estágio efetivo.
- **Impressão** das fichas financeira e de progresso (CSS de impressão).
- **Auditoria (administrador):** consulta ao `sys_log` por tabela, registro, usuário, operação e período; ver o JSON; **verificar fingerprint** (recalcula e compara).
- **Acabamento:** estados vazios, textos, acessibilidade (seção 9), revisão de permissões.

**Aceite:** KPIs conferem com as telas de origem; a impressão cabe em A4; a verificação de fingerprint acusa registro alterado fora do sistema; checklist de acessibilidade cumprido.

---

## 7. Ordem, dependências e entrada em uso

```
E0 ─► E1 ─┬─► E2 ─────────────┐
          ├─► E3 ─► E4         ├─► E6
          └────────► E5 (usa E2 para avaliar na aula)
```
- **Ordem sugerida:** E0 → E1 → E2 → E3 → E4 → E5 → E6. **Esforço relativo:** E0 G · E1 M · E2 M · E3 G · E4 M · E5 G · E6 M.
- **Entrada em uso real sugerida: após a E3** (cadastro + aulas + cobrança), porque já substitui o diário e o controle financeiro; E4 e E5 chegam depois, sem quebrar o que já está em uso.
- Não há ambiente de homologação (um só subdomínio). Cada entrega é **validada localmente com roteiro de aceite** e publicada manualmente.
- Com dados reais já em uso, **migrações passam a ser sempre para frente e revisadas** antes de executar no phpMyAdmin.

---

## 8. Opcionais (você decide se entram agora)

| Opcional | Esforço | Entrega natural | Sugestão |
|---|:-:|---|---|
| Alertas: faltas consecutivas, aluno sem aula há N dias, cobrança atrasada, aniversariantes | M | E6 | Adiar |
| Mostrar a lição de casa da aula anterior ao lançar a próxima | P | E2 | **Incluir** |
| Reposição estruturada (falta justificada → remarcação) | M | E2 | Adiar |
| Atalho WhatsApp/ligar a partir do contato do aluno | P | E1 | **Incluir** |
| Recibo de pagamento (impressão) | P | E3 | Adiar |
| Linha do tempo da evolução (lições aprovadas ao longo do tempo) | M | E5 | Adiar |
| Data em que a aptidão foi marcada (histórico simples; já sai do log) | P | E5 | **Incluir** (via log) |
| PDF gerado no servidor (Dompdf) em vez de só impressão | M | E6 | Adiar |

---

## 9. Requisitos não funcionais

| Tema | Requisito |
|---|---|
| Dispositivos | Tablet-first (iPad/Android) e computador; navegadores atuais (Chrome, Edge, Safari, Firefox — duas últimas versões) |
| Idioma/formatos | pt-BR; datas `dd/mm/aaaa`; moeda BRL |
| Acessibilidade | Zoom permitido; navegação por teclado; foco visível; rótulos em campos; contraste adequado; botões reais (não `div` clicável); mensagens de erro que não somem sozinhas |
| Segurança | HTTPS e HSTS; sessão `HttpOnly`/`Secure`/`SameSite`; CSRF; limite de tentativas; hash de senha; consultas preparadas; saída escapada; `api/` fora do docroot; segredos só no `.env` do servidor |
| Privacidade (LGPD) | Dados mínimos de alunos (inclusive menores); acesso só por perfil; auditoria de gravações |
| Auditoria | Toda gravação em `sys_log` (usuário, origem, tabela/id, operação, JSON, fingerprint SHA-256), na mesma transação |
| **Retenção** | `sys_log`: **sem expurgo** nesta fase. Tabelas técnicas (sessões, recuperações vencidas, tentativas > 30 dias): **exclusão física** pelo cron diário. Cobrança, pagamento e demais dados: exclusão lógica, mantidos |
| Observabilidade | Log de aplicação em arquivo (`api/storage/logs`), separado do `sys_log`; `GET /api/v1/health` |
| Suporte a PHP | PHP 8.4, `declare(strict_types=1)`, PHPStan em nível alto |

---

## 10. Plano de testes

| Camada | O que cobre | Quando roda |
|---|---|---|
| **Unitários** (PHPUnit, sem banco) | Todas as regras derivadas da seção 5; geração mensal; fingerprint e montagem do log; autorização por perfil; validadores | A cada mudança e **antes** de montar o pacote |
| **Integração** (MySQL `_test`) | Aplica **todas** as migrações num banco vazio; valida o padrão do nome e as colunas `sys_*` em toda tabela; repositórios; transação operação + log; unicidade com exclusão lógica; dashboards; login/sessão | Idem; a suíte **se recusa** a rodar fora de banco `_test` |
| **Front** (Vitest + Testing Library) | Formulários e telas críticas (lançar aula, baixa de pagamento, avaliação de lição) | Idem |
| **Aceite manual** | Roteiro por entrega (os critérios da seção 6) | Ao fim de cada entrega |

Metas propostas: **≥ 90%** de cobertura nas classes de domínio (regras); todo bug corrigido ganha um teste que o reproduz. O script de entrega **não gera o pacote** se testes ou análise estática falharem.

---

## 11. API (visão geral)

Base `/api/v1`, JSON, erros `problem+json`, toda rota autenticada (exceto login/recuperação/health) e autorizada por perfil. Documentada em `/api/docs`.

| Grupo | Exemplos |
|---|---|
| Acesso | `POST /sessao`, `DELETE /sessao`, `GET /eu`, `POST /senha/recuperar`, `POST /senha/redefinir`, `POST /senha/trocar` |
| Administração | `/usuarios` (CRUD, definir senha), `/auditoria` (consulta, verificar fingerprint) |
| Cadastros | `/listas/{tipo}`, `/alunos`, `/alunos/{id}` |
| Aulas | `/aulas`, `/alunos/{id}/aulas`, `/calendario?mes=`, `/agenda/hoje` |
| Financeiro | `/cobrancas`, `/cobrancas/gerar`, `/cobrancas/{id}/pagamentos`, `/alunos/{id}/ficha-financeira`, `/dashboards/financeiro`, `/exportacoes/*.xlsx` |
| Progresso | `/metodos`, `/metodos/{id}/licoes`, `/alunos/{id}/metodos`, `/alunos/{id}/avaliacoes`, `/alunos/{id}/ficha-progresso`, `/alunos/{id}/aptidao` |
| Sistema | `GET /health`, `GET /inicio` (KPIs) |

---

## 12. Suposições minhas (corrija o que estiver errado)

| # | Suposição |
|---|---|
| 1 | Administrador = superconjunto da professora |
| 2 | **Um** horário semanal por aluno (dia + hora), como no app antigo |
| 3 | Aula = **um aluno por registro** (sem aula coletiva), como no app antigo |
| 4 | Mantidos os **3 campos de técnica** do legado (m1, m2, ped) em `progresso_aluno`; descartados: “% de teoria”, “nº de hinos”, “lista de hinos” (substituídos por métodos/lições) e “Atualizar progresso?” (a avaliação passa a ser feita na própria tela de aula) |
| 5 | **Desconto** é um valor em R$ na cobrança; **sem** multa/juros |
| 6 | Baixa **parcial** é permitida; estorno = exclusão lógica do pagamento |
| 7 | O 1º administrador entra por **migração de semente** com senha temporária e troca obrigatória (hospedagem compartilhada pode não ter SSH) |
| 8 | Tabelas técnicas têm **exclusão física** pelo cron; `sys_log` nunca é expurgado nesta fase |
| 9 | Entrada em uso real **após a E3** |
| 10 | O conteúdo dos 480 hinos e das lições dos métodos é **fornecido por você/professora**; o sistema só oferece a carga em lote |
| 11 | E-mail de saída com remetente do próprio domínio (ex.: `nao-responder@cpod.com.br`) |
| 12 | Sem ambiente de homologação; validação local + roteiro de aceite |

---

## 13. O que acontece com as pendências da avaliação do app antigo

| Pendências | Destino |
|---|---|
| P01–P05, P08 (init, layout A–T, datas, `res.ok`, token) | **Obsoletas**: reescrita sem planilha e sem Graph |
| P06, P07, P19 (status do pagamento, duplicidade, “Atrasado”) | **E3** |
| P09, P22 (offline, três vias de escrita) | **Superadas** pela decisão de stack (banco centralizado, IDs e transações) |
| P10 (edição de aluno, vínculos por nome) | **E1** (chaves por ID) |
| P11, P12, P20, P21 (progresso, estágio, hinário, métodos e lições) | **E5** |
| P13 (controles inertes; horário não gravado) | **Obsoleta**; horário estruturado em **E1** |
| P14 (funcionalidades ausentes) | **E1–E6** e **opcionais** (seção 8) |
| P15 (HTML sem escape) | React escapa por padrão; revisão em **E6** |
| P16, P17 (legado, higiene do repositório) | **Resolvidas** (`legacy/`, `.gitignore`) |
| P18 (acessibilidade) | Requisito da seção 9; checklist em **E6** |

---

## 14. Riscos

| Risco | Mitigação |
|---|---|
| Plano de hospedagem sem alguma extensão/capacidade (`gd`, `zip`, cron, SMTP) | Confirmar **antes da E0** (lista na seção 13 de `stack-proposta.md`); alternativas já previstas |
| Conteúdo dos métodos/hinário atrasar | E5 entrega o cadastro e a carga em lote; o conteúdo é operacional |
| Regras de progresso e estágio mal interpretadas | Casos numéricos de aceite na E5 e testes unitários |
| Migração manual com dados reais em uso (após a E3) | Migrações só para frente, revisadas, com reversão manual documentada |
| Deploy manual esquecer algo | `bin/release` monta o pacote; `/health` mostra a versão publicada |
| Gráficos do Excel dependem da extensão `gd` | Verificar antes da E4; plano B: Excel sem gráficos |

---

## 15. Próximo passo

Aprovando este escopo (ou ajustando as seções 1, 8 e 12), o passo seguinte é o **plano de implementação da E0**, que detalha as tarefas, os arquivos e a ordem de construção. Nenhum código de produto é escrito antes disso.

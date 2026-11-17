# melhorias técnicas

- o projeto está crescendo, modularize por páginas e componentes, para facilitar a manutenção e organização do código.
- vi na estrutura que temos listas.metodos e tem uma lista fixa, estas listas estão em planilha de listas, e todas as listas dentro da planilha Listas da coluna header da linha 4 devem ser convertidas para serem dinâmicas, ou seja, puxar do backend, para facilitar a manutenção e adição de novas listas no futuro, a lista pode ser armazenada em localstorage para melhor performance e atualizada ao clicar em "Atualizar tudo" os demais dados de alunos e dinâmicos não devem ser armazenados em localstorage, para evitar problemas de dados desatualizados. 

# Adicionar suporte para edição completa do aluno

- ao entrar no aluno, permitir num botão novo chamado "Editar" onde leva para uma tela específica de edição do aluno, onde tem os campos para alteração.

# Faltou a parte financeira, dentro do aluno

- adicionar um campo para verificar os dados financeiros do aluno, permitindo fazer o lançamento de mensalidades, pagamentos, etc.

# melhorias de UI e UX

- adicionar loader nas ações que dependem de sincronização com o backend, para melhorar a experiência do usuário.
- Adicionar mensagens de sucesso e erro para as ações realizadas, para dar feedback ao usuário sobre o que aconteceu.

---

## Plano de Ação

> Decisões coletadas: JS puro multi-arquivo via `<script>`, listas dinâmicas do Excel, edição salva imediatamente via Graph API, financeiro baseado na aba "Financeiro" já existente no modelo, loader skeleton nas listas.

---

### Feature 1 — Modularização ✅

**Abordagem:** JS puro separado em arquivos incluídos via `<script>` no `index.html`, sem bundler.

**Estrutura de arquivos proposta:**
```
js/
  config.js       ← constantes (SCOPES, GRAPH_BASE, CLIENT_ID), funções MSAL/Graph (loadMsal, iniciarLogin, obterToken, desconectar)
  db.js           ← objeto DB, carregarDadosPlanilha, atualizarTudo, salvarAula
  listas.js       ← carregarListas, getLista, populateSelect (dinâmico)
  ui.js           ← showToast, addLog, setStatus, atualizarPassos, atualizarSyncIndicator, avatarH, badgeE, pTag, skeletonList
  home.js         ← renderHome
  alunos.js       ← renderListaAlunos, filtrarAlunos, abrirFicha, voltarLista, irLancamento
  aluno-editar.js ← abrirEdicao, salvarEdicaoAluno
  lancamento.js   ← initLancamento, setPresenca, salvarAula
  financeiro.js   ← carregarFinanceiro, renderFinanceiro, salvarPagamento
  calendario.js   ← renderCalendario, navMes, abrirDia, fecharModal
  relatorio.js    ← gerarRelatorio
  app.js          ← appConectado, navTo, restaurarEstado, atualizarNavBloqueio, INIT
```

**Ordem dos `<script>` no `index.html`** (dependências primeiro):
```html
<script src="js/config.js"></script>
<script src="js/ui.js"></script>
<script src="js/listas.js"></script>
<script src="js/db.js"></script>
<script src="js/home.js"></script>
<script src="js/alunos.js"></script>
<script src="js/aluno-editar.js"></script>
<script src="js/lancamento.js"></script>
<script src="js/financeiro.js"></script>
<script src="js/calendario.js"></script>
<script src="js/relatorio.js"></script>
<script src="js/app.js"></script>
```

**Passos:**
1. Criar cada arquivo JS copiando as funções correspondentes do `diario.js` atual.
2. Remover `diario.js` e substituir pelos `<script>` acima no `index.html`.
3. Validar no browser que nenhuma referência está quebrada.

---

### Feature 2 — Listas Dinâmicas ✅

**Estrutura da aba "Listas" no Excel:**
- Linha 1: título
- Linha 2: instrução
- Linha 3 (índice 2): **cabeçalhos** — `INSTRUMENTOS | ESTÁGIOS | TIPO DE AULA | PRESENÇA | TÉCNICA | PRONTIDÃO CULTO | MÉTODOS / APOSTILAS | HINOS — BÁSICO | HINOS — INTERM. | HINOS — CULTO | PAGAMENTO | STATUS ALUNO | FAIXA ETÁRIA`
- Linha 4+ (índice 3+): valores de cada coluna

**Parsing (em `listas.js`):**
```javascript
// values[2] = array de headers
// values.slice(3) = linhas de dados; cada coluna i corresponde ao header[i]
// Resultado: { INSTRUMENTOS: [...], ESTÁGIOS: [...], ... }
```

**Armazenamento:** `localStorage.setItem("mpm_listas", JSON.stringify(parsed))`
- Carregado no init a partir do localStorage (sem chamada de rede)
- Atualizado apenas em `carregarDadosPlanilha()` (botão "Atualizar tudo")
- `DB.alunos` e `DB.aulas` **não** são salvos no localStorage

**Campos que passam a usar listas dinâmicas:**
| Campo no formulário | Chave na lista |
|---|---|
| Instrumento (edição aluno) | `INSTRUMENTOS` |
| Estágio (edição aluno) | `ESTÁGIOS` |
| Tipo de aula (lançamento) | `TIPO DE AULA` |
| Presença (lançamento) | `PRESENÇA` |
| Método (lançamento + aluno) | `MÉTODOS / APOSTILAS` |
| Técnica m1/m2/ped (edição) | `TÉCNICA` |
| Prontidão culto (edição) | `PRONTIDÃO CULTO` |
| Forma de pagamento (financeiro) | `PAGAMENTO` |
| Status do aluno (edição) | `STATUS ALUNO` |

**Passos:**
1. Criar `listas.js` com `carregarListasPlanilha()`, `getLista(nome)`, `populateSelect(selectId, nomeLista)`.
2. Chamar `carregarListasPlanilha()` dentro de `carregarDadosPlanilha()`.
3. No init, restaurar listas do localStorage via `restaurarListas()`.
4. Substituir todos os `<option>` hardcoded por chamadas a `populateSelect()`.
5. Remover `DB.listas.metodos` (hardcoded).

---

### Feature 3 — Edição Completa do Aluno ✅

**HTML — nova tela `#editar-aluno`** (dentro de `#page-alunos`, mesmo padrão do `#ficha-aluno`):
```
← Voltar | Editar Aluno
  — Dados gerais: nome, nasc, instrumento (select dinâmico), horário, mensalidade, início, status aluno
  — Progresso: estágio (select), teoria (0-100), método (select), página, hinos, listahinos
  — Técnica: m1 / m2 / ped (selects com TÉCNICA)
  — Prontidão para culto: jovens / adulto / ofic (selects com PRONTIDÃO CULTO)
  — Observações: textarea
  [Salvar alterações] ← btn-success
```

**Gravação via Graph API:**
- Endpoint: `PATCH /me/drive/items/{fileId}/workbook/worksheets('Alunos')/range(address='A{row}:T{row}')`
- Onde `row` = índice da linha do aluno na planilha (ID salvo no objeto aluno ou buscado por nome)
- Botão fica disabled + spinner durante o PATCH
- Toast de sucesso ou erro ao concluir

**Passos:**
1. Adicionar HTML da tela de edição em `index.html`.
2. Criar `js/aluno-editar.js` com `abrirEdicao(id)`, `salvarEdicaoAluno()`.
3. Adicionar botão "Editar" na `#ficha-aluno` (ao lado de "Lançar nova aula").
4. Implementar lógica de PATCH com mapeamento de campos para colunas A–T.

---

### Feature 4 — Módulo Financeiro ✅

**Estrutura real da aba "Financeiro" (modelo já existente):**
- Linha 1: título
- Linha 2 (cabeçalhos): `Mês/Ano Ref. | Aluno | Valor Cobrado (R$) | Valor Pago (R$) | Status | Data Pagamento | Forma Pagamento | Desconto/Obs | Saldo (R$)`
- Linha 3+ dados — datas como serial number Excel (converter: `new Date((serial - 25569) * 86400000)`)
- Status possíveis: `Pago | Pendente | Atrasado`

**O que implementar:**

**a) Seção na ficha do aluno — "Financeiro":**
- Card com resumo: total pago no ano, pendências em aberto, saldo atual
- Lista das últimas 6 mensalidades do aluno (badge de status colorido)
- Botão "Lançar pagamento" → abre formulário inline/modal

**b) Formulário de lançamento:**
```
Mês/Ano Ref.:  [MM/YYYY]
Valor Cobrado: [number]   Valor Pago: [number]
Status:        [select — PAGAMENTO → Pago/Pendente/Atrasado]
Data Pagamento:[date]
Forma:         [select dinâmico — lista PAGAMENTO]
Desconto/Obs:  [text]
[Salvar]
```
- Ao salvar: POST append de linha na aba Financeiro via Graph API
- Endpoint: `POST /workbook/worksheets('Financeiro')/tables('...') /rows/add` ou range append

**c) KPI na home — "Pendências":**
- Já existe o card `kpi-pend` com valor fixo "R$ 570"
- Calcular dinamicamente: soma dos `Saldo` negativos de todos os alunos

**Passos:**
1. Em `carregarDadosPlanilha()`, adicionar leitura da aba Financeiro → `DB.financeiro = [...]`.
2. Criar `js/financeiro.js` com `renderFinanceiro(alunoNome)`, `salvarPagamento()`, `calcularPendencias()`.
3. Adicionar HTML da seção financeira na ficha do aluno.
4. Atualizar `renderHome()` para calcular `kpi-pend` dinamicamente.

---

### Feature 5 — Loaders / Skeleton ✅

**Componente skeleton genérico (em `ui.js`):**
```javascript
function skeletonList(n = 3) {
  return Array(n).fill(
    `<div class="skeleton-item"><div class="skel skel-avatar"></div>
     <div class="skel-lines"><div class="skel skel-line"></div>
     <div class="skel skel-line short"></div></div></div>`
  ).join('');
}
```

**CSS (em `diario.css`):**
```css
.skel { background: linear-gradient(90deg, #E4E2F0 25%, #EEEDFE 50%, #E4E2F0 75%);
        background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 6px }
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
```

**Onde aplicar:**
- `renderListaAlunos()`: antes da chamada async, mostrar `skeletonList(5)` no `#lista-content`
- `renderFinanceiro()`: skeleton enquanto carrega
- Botões de ação (salvar aula, salvar edição, lançar pagamento): pattern já existente no btn-atualizar — estender para todos

**Passos:**
1. Adicionar CSS de skeleton em `diario.css`.
2. Criar `skeletonList()` em `ui.js`.
3. Aplicar nos pontos listados.

---

### Prioridade e Sequência de Implementação

| # | Feature | Depende de | Status |
|---|---|---|---|
| 1 | Modularização | — | ✅ Concluído |
| 2 | Listas Dinâmicas | Modularização (listas.js) | ✅ Concluído |
| 3 | Edição do Aluno | Modularização + Listas | ✅ Concluído |
| 4 | Módulo Financeiro | Modularização + Listas | ✅ Concluído |
| 5 | Loaders/Skeleton | Modularização (ui.js) | ✅ Concluído |

**Ordem recomendada:** 1 → 5 → 2 → 3 → 4



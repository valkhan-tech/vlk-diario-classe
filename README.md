# 🎹 Diário Pedagógico Musical

Sistema de gestão de aulas e alunos para professores de música.  
Desenvolvido para Piano, Órgão e Flauta — com integração ao OneDrive.

---

## O que é este sistema

Um aplicativo web que funciona no navegador do tablet (Samsung ou qualquer outro), com dados armazenados em um arquivo Excel no OneDrive. Não precisa instalar nada.

**Componentes:**
- `index.html` — o app web (este arquivo)
- `Diario_Pedagogico_Musical.xlsx` — a planilha no OneDrive (fonte de dados)

---

## Guia de Configuração — Passo a Passo

### ETAPA 1 · Subir o app no GitHub Pages

> **GitHub Pages** hospeda o app gratuitamente com um link fixo.

**1.1 — Criar conta no GitHub**

1. Acesse **github.com** no navegador
2. Clique em **Sign up** (Criar conta)
3. Preencha: e-mail, senha, nome de usuário
4. Confirme o e-mail que chegará na caixa de entrada

**1.2 — Criar um repositório**

1. Após o login, clique no botão verde **New** (ou **+** → New repository)
2. Preencha:
   - **Repository name:** `diario-musical` (ou qualquer nome sem espaços)
   - **Visibility:** Public ✓ *(necessário para GitHub Pages gratuito)*
3. Clique em **Create repository**

**1.3 — Fazer upload do arquivo**

1. Na página do repositório, clique em **uploading an existing file**
2. Arraste o arquivo `index.html` para a área indicada
3. Clique em **Commit changes**

**1.4 — Ativar GitHub Pages**

1. Clique na aba **Settings** (engrenagem) do repositório
2. No menu lateral, clique em **Pages**
3. Em **Source**, selecione: `Deploy from a branch`
4. Em **Branch**, selecione: `main` → pasta `/ (root)`
5. Clique em **Save**
6. Aguarde 1–2 minutos e atualize a página
7. Aparecerá o link: `https://SEU-USUARIO.github.io/diario-musical/`

**1.5 — Salvar como atalho no tablet**

1. Abra o link no **Chrome** do tablet Samsung
2. Toque no menu ⋮ (três pontos)
3. Toque em **Adicionar à tela inicial**
4. Confirme — o ícone aparecerá como um app na tela inicial

---

### ETAPA 2 · Subir o Excel no OneDrive

**2.1 — No tablet Samsung**

1. Abra o app **OneDrive** (instale pela Galaxy Store ou Google Play se necessário)
2. Entre com a conta Microsoft (@outlook.com ou @hotmail.com)
3. Toque em **+** (botão flutuante)
4. Toque em **Carregar** → **Fotos e arquivos**
5. Navegue até onde está o arquivo `Diario_Pedagogico_Musical.xlsx`
6. Selecione e aguarde o upload

**2.2 — Anotar o caminho do arquivo**

Após o upload, o caminho padrão será:
```
/Diario_Pedagogico_Musical.xlsx
```

Se você criou uma pasta antes de subir, será algo como:
```
/Documentos/Diario_Pedagogico_Musical.xlsx
```

Anote esse caminho — você precisará dele na configuração do app.

---

### ETAPA 3 · Registrar o app no Azure (necessário para integração)

> Este passo é técnico mas é feito uma única vez. Peça ajuda ao Paulo ou siga o passo a passo abaixo.

**Por que é necessário?**  
Para o app poder acessar o OneDrive com segurança, a Microsoft exige que ele esteja registrado no Azure Active Directory. É gratuito.

**3.1 — Acessar o portal Azure**

1. Acesse **portal.azure.com** no computador (mais fácil do que no tablet)
2. Entre com a **mesma conta Microsoft** do OneDrive
3. Na barra de pesquisa, digite: **App registrations**
4. Clique em **App registrations** nos resultados

**3.2 — Criar o registro**

1. Clique em **+ New registration** (Novo registro)
2. Preencha:

| Campo | Valor |
|-------|-------|
| **Name** | `Diário Pedagógico Musical` |
| **Supported account types** | `Personal Microsoft accounts only` |
| **Redirect URI** | `https://SEU-USUARIO.github.io/diario-musical/` |

> ⚠️ **Atenção ao Redirect URI:** substitua `SEU-USUARIO` pelo seu usuário do GitHub e `diario-musical` pelo nome do repositório que criou.

3. Clique em **Register**

**3.3 — Copiar o Application ID**

1. Após criar, você verá a página de visão geral do app
2. Copie o valor de **Application (client) ID**
   - Parece com: `a1b2c3d4-e5f6-7890-abcd-ef1234567890`
3. Guarde esse valor — você vai colar no app

**3.4 — Configurar permissões**

1. No menu lateral, clique em **API permissions**
2. Clique em **+ Add a permission**
3. Selecione **Microsoft Graph**
4. Selecione **Delegated permissions**
5. Busque e adicione:
   - `Files.ReadWrite`
   - `User.Read`
6. Clique em **Add permissions**
7. Clique em **Grant admin consent** (se disponível) e confirme

---

### ETAPA 4 · Conectar o app ao OneDrive

**4.1 — Abrir as Configurações no app**

1. Abra o app no tablet (pelo atalho na tela inicial)
2. Toque na aba **Config** (ícone de engrenagem) na barra inferior

**4.2 — Inserir o Client ID**

1. Role a tela até a seção **ID do aplicativo (Azure)**
2. No campo **Client ID**, cole o valor copiado no passo 3.3
3. O valor é salvo automaticamente

**4.3 — Fazer login**

1. Toque no botão **Entrar com Microsoft**
2. Uma janela da Microsoft abrirá
3. Entre com a conta pessoal (@outlook.com ou @hotmail.com)
4. Autorize as permissões solicitadas

> ⚠️ Se aparecer aviso "Este app não é verificado pela Microsoft":  
> Clique em **Informações avançadas** → **Continuar para diário-pedagógico-musical (não seguro)**  
> Isso ocorre porque o registro é pessoal e não passou pela verificação formal (processo pago e necessário apenas para apps comerciais).

**4.4 — Selecionar o arquivo Excel**

1. Após o login, a seção **Arquivo Excel** ficará disponível
2. No campo **Caminho no OneDrive**, confirme o caminho do arquivo:
   ```
   /Diario_Pedagogico_Musical.xlsx
   ```
   ou ajuste conforme onde você salvou
3. Toque em **Buscar**
4. Se o arquivo for encontrado, aparecerá uma confirmação em verde

**4.5 — Primeira sincronização**

1. Toque em **Sincronizar agora**
2. Acompanhe o log de sincronização que aparecerá
3. Após a conclusão, o app estará conectado e funcionando

---

## Como usar no dia a dia

### Lançar uma aula

1. Toque em **Lançar** na barra inferior
2. Selecione o aluno no dropdown
3. Confirme a data (preenchida automaticamente com hoje)
4. Toque em um dos botões de presença:
   - ✓ **Presente** — aula realizada
   - **Justificada** — aluno avisou a falta
   - **Sem aviso** — aluno faltou sem comunicar
   - **Professora** — aula cancelada pela professora
5. Se presente: preencha tipo de aula, método, conteúdo, hino, lição de casa
6. Toque em **Salvar registro**

> Se o OneDrive estiver conectado e a sincronização automática estiver ativa, o dado é enviado ao Excel imediatamente.

### Consultar ficha de um aluno

1. Toque em **Alunos** na barra inferior
2. Toque no nome do aluno
3. Veja: estágio atual, progresso em barras, técnica, prontidão para culto e histórico de aulas
4. Toque em **Gerar relatório PDF** para exportar

### Ver o calendário de frequência

1. Toque em **Agenda** na barra inferior
2. Use as setas para navegar entre meses
3. Cada ponto colorido representa uma aula:
   - 🟢 Verde — presente
   - 🔴 Vermelho — falta sem aviso
   - 🟡 Amarelo — falta justificada / remarcada
   - ⚫ Cinza — cancelada pela professora
4. Toque em qualquer dia para ver os detalhes

### Sincronizar manualmente

1. Toque em **Config** → **Sincronizar agora**
2. Útil quando a sincronização automática estiver desativada ou após editar o Excel diretamente

---

## Estrutura do arquivo Excel

O arquivo `Diario_Pedagogico_Musical.xlsx` tem 6 abas:

| Aba | Descrição |
|-----|-----------|
| **Alunos** | Cadastro completo: nome, instrumento, horário, mensalidade |
| **Diário de Aulas** | Registro de cada aula com presença, conteúdo, hinos, lição |
| **Progresso** | Estágio, teoria, técnica (1 mão/2 mãos/pedaleira), culto |
| **Financeiro** | Mensalidades, status de pagamento, histórico |
| **Dashboard** | Visão geral automática com fórmulas (atualiza sozinho) |
| **Listas** | Configure aqui os métodos, hinos e opções dos dropdowns |

### Personalizando as listas (aba Listas)

Para adicionar novos métodos, hinos ou opções:
1. Abra o arquivo Excel no OneDrive (pelo app Microsoft 365 do tablet)
2. Vá para a aba **Listas**
3. Adicione os itens nas colunas correspondentes
4. Salve — as alterações ficam disponíveis imediatamente no Excel
5. Toque em **Sincronizar agora** no app para carregar as novas opções

---

## Estrutura dos arquivos

```
diario-musical/
├── index.html          ← App web (único arquivo necessário)
├── README.md           ← Esta documentação
└── Diario_Pedagogico_Musical.xlsx  ← Sobe no OneDrive (não no GitHub)
```

> O arquivo Excel **não** vai para o GitHub — fica apenas no OneDrive.

---

## Solução de problemas

### O login com a Microsoft não funciona

- Verifique se o **Client ID** foi colado corretamente (sem espaços extras)
- Confirme que o **Redirect URI** no Azure bate exatamente com o endereço do app no GitHub Pages
- Tente em modo anônimo/privado do navegador para descartar cache

### "Arquivo não encontrado" ao buscar o Excel

- Verifique o caminho exato no OneDrive — maiúsculas e minúsculas importam
- Exemplos válidos: `/Diario_Pedagogico_Musical.xlsx` ou `/Documentos/Diario_Pedagogico_Musical.xlsx`
- Abra o OneDrive no tablet e navegue até o arquivo para confirmar o caminho

### A sincronização falha com erro 401

- O token de acesso expirou — toque em **Entrar com Microsoft** novamente
- O login silencioso tentará renovar automaticamente; se não conseguir, pedirá login manual

### O app não abre no tablet

- Confirme que o GitHub Pages está ativo (Settings → Pages → deve mostrar o link em verde)
- Tente acessar pelo Chrome (não pelo Samsung Internet, que pode ter restrições)
- Limpe o cache do navegador se estiver carregando uma versão antiga

### Perdi o Client ID do Azure

1. Acesse **portal.azure.com**
2. Vá em **App registrations** → **All applications**
3. Clique no app **Diário Pedagógico Musical**
4. O **Application (client) ID** estará na página inicial do app

---

## Próximas funcionalidades (roadmap)

- [ ] Importação automática do Excel para o app (leitura de todos os alunos e histórico)
- [ ] Notificações de alunos com muitas faltas
- [ ] Envio automático de relatório por WhatsApp
- [ ] Modo offline com sincronização posterior
- [ ] Cadastro de novos alunos direto no app

---

## Informações técnicas

| Item | Detalhe |
|------|---------|
| Hospedagem | GitHub Pages (gratuito) |
| Autenticação | Microsoft MSAL.js v3 (OAuth 2.0) |
| Dados | Microsoft Graph API v1.0 — Excel Online |
| Armazenamento | OneDrive pessoal |
| Compatibilidade | Chrome, Edge, Samsung Internet (Android 8+) |
| Frameworks | Nenhum — HTML/CSS/JS puro |

---

*Desenvolvido para gestão de aulas de Piano, Órgão e Flauta — MVP v1.0*

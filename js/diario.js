// ── MSAL / Microsoft Graph ────────────────────────────────────────────────
// CDN carregado dinamicamente para não bloquear o app offline
let msalInstance = null;
let msalLoaded = false;
let graphToken = null;
let userInfo = null;
let excelFileId = null;

const SCOPES = ["Files.ReadWrite", "User.Read"];
const GRAPH_BASE = "https://graph.microsoft.com/v1.0";
const DEFAULT_CLIENT_ID = "1e727060-da1f-4cc2-bb6c-9a77aa3132cc";

let appConectado = false;

function getClientId() {
  return localStorage.getItem("mpm_client_id") || DEFAULT_CLIENT_ID;
}
function salvarClientId(v) {
  localStorage.setItem("mpm_client_id", v.trim());
}
function copiarClientId() {
  const v = document.getElementById("input-clientid").value;
  if (v) navigator.clipboard.writeText(v).then(() => showToast("Copiado!"));
}

// Carrega MSAL sob demanda
async function loadMsal() {
  if (msalLoaded) return true;
  return new Promise((res, rej) => {
    const s = document.createElement("script");
    s.src =
      "https://cdn.jsdelivr.net/npm/@azure/msal-browser@3.27.0/lib/msal-browser.min.js";
    s.onload = () => {
      msalLoaded = true;
      res(true);
    };
    s.onerror = () => rej(new Error("Falha ao carregar MSAL"));
    document.head.appendChild(s);
  });
}

async function iniciarLogin() {
  const clientId =
    document.getElementById("input-clientid").value.trim() || getClientId();
  if (!clientId) {
    showToast("Cole o Client ID do Azure primeiro", "warn");
    document.getElementById("input-clientid").focus();
    return;
  }
  salvarClientId(clientId);
  setStatus("connecting");
  try {
    await loadMsal();
    const config = {
      auth: {
        clientId,
        authority: "https://login.microsoftonline.com/consumers",
        redirectUri: location.href.split("?")[0].replace(/\/?$/, "/"),
      },
      cache: { cacheLocation: "localStorage", storeAuthStateInCookie: true },
    };
    msalInstance = new msal.PublicClientApplication(config);
    await msalInstance.initialize();
    const result = await msalInstance.loginPopup({ scopes: SCOPES });
    graphToken = result.accessToken;
    userInfo = result.account;
    localStorage.setItem(
      "mpm_account",
      JSON.stringify({
        username: result.account.username,
        name: result.account.name,
      }),
    );
    appConectado = true;
    atualizarNavBloqueio();
    setStatus("connected");
    addLog("ok", `Login: ${result.account.username}`);
    showToast("Conectado com sucesso! ✓", "success");
    atualizarPassos(2);
  } catch (e) {
    const raw = e.message || "Erro desconhecido";
    const isSpaError =
      raw.includes("AADSTS70002") || raw.includes("client_secret");
    const userMsg = isSpaError
      ? "Configuração Azure incorreta: veja o log para instruções de correção."
      : "Erro no login: " + raw;
    setStatus("error", isSpaError ? "URI de redirecionamento incorreto" : raw);
    if (isSpaError) {
      addLog("err", "AADSTS70002 — o app Azure está configurado como cliente confidencial.");
      addLog("warn", "Correção: Portal Azure → Registros de aplicativo → Autenticação");
      addLog("warn", "→ em 'URIs de redirecionamento', REMOVA o URI da seção 'Web'");
      addLog("warn", "→ ADICIONE o mesmo URI na seção 'Single-page application (SPA)'");
      addLog("warn", "→ Salve e tente novamente. Nenhuma alteração no código é necessária.");
    } else {
      addLog("err", raw);
    }
    showToast(userMsg, "warn");
  }
}

async function obterToken() {
  if (!msalInstance) return null;
  try {
    const accounts = msalInstance.getAllAccounts();
    if (!accounts.length) return null;
    const res = await msalInstance.acquireTokenSilent({
      scopes: SCOPES,
      account: accounts[0],
    });
    return res.accessToken;
  } catch (e) {
    const res = await msalInstance.acquireTokenPopup({ scopes: SCOPES });
    return res.accessToken;
  }
}

async function validarArquivo() {
  const caminho = document.getElementById("input-caminho").value.trim();
  if (!caminho) {
    showToast("Informe o caminho do arquivo", "warn");
    return;
  }
  const token = graphToken || (await obterToken());
  if (!token) {
    showToast("Faça login primeiro", "warn");
    return;
  }
  try {
    addLog("ok", `Buscando: ${caminho}`);
    const res = await fetch(`${GRAPH_BASE}/me/drive/root:${caminho}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!res.ok) throw new Error("Arquivo não encontrado");
    const data = await res.json();
    excelFileId = data.id;
    localStorage.setItem("mpm_file_id", excelFileId);
    localStorage.setItem("mpm_file_path", caminho);
    localStorage.setItem("mpm_file_name", data.name);
    document.getElementById("arquivo-selecionado").style.display = "";
    document.getElementById("arquivo-picker").style.display = "none";
    document.getElementById("arquivo-nome").textContent = data.name;
    document.getElementById("arquivo-path").textContent = caminho;
    document.getElementById("secao-sync").style.display = "";
    addLog(
      "ok",
      `Arquivo encontrado: ${data.name} (${(data.size / 1024).toFixed(1)} KB)`,
    );
    showToast("Arquivo encontrado! ✓", "success");
    atualizarPassos(4);
    await carregarDadosPlanilha();
  } catch (e) {
    addLog("err", "Arquivo não encontrado: " + caminho);
    showToast("Arquivo não encontrado. Verifique o caminho.", "warn");
  }
}

async function abrirFilePicker() {
  const token = graphToken || (await obterToken());
  if (!token) {
    showToast("Faça login primeiro", "warn");
    return;
  }
  // Lista raiz e deixa usuário navegar
  try {
    const res = await fetch(
      `${GRAPH_BASE}/me/drive/root/children?$filter=file ne null and endswith(name,'.xlsx')&$select=name,id,parentReference`,
      {
        headers: { Authorization: `Bearer ${token}` },
      },
    );
    const data = await res.json();
    const items = data.value || [];
    if (!items.length) {
      showToast("Nenhum .xlsx encontrado na raiz do OneDrive", "warn");
      return;
    }
    const lista = items.map((f, i) => `${i + 1}. ${f.name}`).join("\n");
    const escolha = prompt(
      `Arquivos .xlsx encontrados:\n${lista}\n\nDigite o número:`,
    );
    if (!escolha) return;
    const idx = parseInt(escolha) - 1;
    if (idx >= 0 && idx < items.length) {
      const f = items[idx];
      excelFileId = f.id;
      localStorage.setItem("mpm_file_id", excelFileId);
      localStorage.setItem("mpm_file_name", f.name);
      document.getElementById("arquivo-selecionado").style.display = "";
      document.getElementById("arquivo-picker").style.display = "none";
      document.getElementById("arquivo-nome").textContent = f.name;
      document.getElementById("secao-sync").style.display = "";
      showToast("Arquivo selecionado! ✓", "success");
      await carregarDadosPlanilha();
    }
  } catch (e) {
    showToast("Erro ao listar arquivos", "warn");
  }
}

function trocarArquivo() {
  document.getElementById("arquivo-selecionado").style.display = "none";
  document.getElementById("arquivo-picker").style.display = "";
}

// ── LEITURA COMPLETA DA PLANILHA ──────────────────────────────────────────
async function carregarDadosPlanilha() {
  const token = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");
  if (!token || !fileId) {
    showToast("Configure a planilha primeiro", "warn");
    return;
  }
  const btn = document.getElementById("btn-atualizar");
  if (btn) {
    btn.disabled = true;
    btn.innerHTML =
      '<i class="ti ti-loader" style="animation:spin 1s linear infinite" aria-hidden="true"></i> Carregando...';
  }
  addLog("ok", "Carregando dados da planilha...");
  try {
    // ── Diário de Aulas ───────────────────────────────────────────────────
    const resAulas = await fetch(
      `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Diário de Aulas')/usedRange`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    if (!resAulas.ok) throw new Error("Aba 'Diário de Aulas' não encontrada");
    const dAulas = await resAulas.json();
    const rowsAulas = (dAulas.values || []).slice(2); // pula 2 linhas de cabeçalho
    DB.aulas = rowsAulas
      .filter((r) => r[0] && r[1])
      .map((r) => ({
        data:     String(r[0]  || ""),
        aluno:    String(r[1]  || ""),
        presenca: String(r[2]  || ""),
        motivo:   String(r[3]  || ""),
        tipo:     String(r[4]  || ""),
        metodo:   String(r[5]  || ""),
        conteudo: String(r[6]  || ""),
        hino:     String(r[7]  || ""),
        licao:    String(r[8]  || ""),
        video:    String(r[9]  || ""),
        obs:      String(r[10] || ""),
      }));
    _aulaSheetRowCount = DB.aulas.length;
    addLog("ok", `${DB.aulas.length} aula(s) carregada(s)`);

    // ── Alunos (aba opcional) ─────────────────────────────────────────────
    const resAlunos = await fetch(
      `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Alunos')/usedRange`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    if (resAlunos.ok) {
      const dAlunos = await resAlunos.json();
      const rowsAlunos = (dAlunos.values || []).slice(2); // pula 2 linhas de cabeçalho
      const parsed = rowsAlunos
        .filter((r) => r[1])
        .map((r, i) => ({
          id:          Number(r[0])  || i + 1,
          nome:        String(r[1]  || ""),
          nasc:        String(r[2]  || ""),
          instrumento: String(r[3]  || ""),
          horario:     String(r[4]  || ""),
          mensalidade: Number(r[5]) || 0,
          inicio:      String(r[6]  || ""),
          estagio:     String(r[7]  || "Iniciante"),
          teoria:      Number(r[8]) || 0,
          metodo:      String(r[9]  || ""),
          pagina:      String(r[10] || ""),
          hinos:       Number(r[11]) || 0,
          listahinos:  String(r[12] || "—"),
          tecnica: {
            m1:  String(r[13] || "Não iniciado"),
            m2:  String(r[14] || "Não iniciado"),
            ped: String(r[15] || "Não iniciado"),
          },
          culto: {
            jovens: String(r[16] || "Não avaliado"),
            adulto: String(r[17] || "Não avaliado"),
            ofic:   String(r[18] || "Não avaliado"),
          },
          obs: String(r[19] || ""),
        }));
      if (parsed.length) {
        DB.alunos = parsed;
        addLog("ok", `${DB.alunos.length} aluno(s) carregado(s)`);
      } else {
        addLog("warn", "Aba 'Alunos' vazia — mantendo dados locais");
      }
    } else {
      addLog("warn", "Aba 'Alunos' não encontrada — mantendo dados locais");
    }

    DB.pending = [];
    renderHome();
    renderListaAlunos(DB.alunos);
    renderCalendario();
    initLancamento();
    const agora = new Date().toLocaleString("pt-BR");
    document.getElementById("ultimo-sync").textContent = `Última leitura: ${agora}`;
    localStorage.setItem("mpm_last_sync", agora);
    atualizarPassos(5);
    atualizarSyncIndicator(true);
    showToast("Dados carregados da planilha! ✓", "success");
  } catch (e) {
    addLog("err", "Erro ao carregar: " + e.message);
    showToast("Erro ao carregar dados: " + e.message, "warn");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-refresh" aria-hidden="true"></i> Atualizar tudo';
    }
  }
}

// ── ATUALIZAR TUDO (push pendentes + leitura completa) ────────────────────
async function atualizarTudo() {
  const token = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");
  if (!token || !fileId) {
    showToast("Configure a planilha primeiro", "warn");
    return;
  }
  if (DB.pending.length > 0) {
    addLog("ok", `Enviando ${DB.pending.length} registro(s) pendente(s)...`);
    try {
      const res = await fetch(
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Diário de Aulas')/usedRange`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      const d = await res.json();
      let nr = (d.values || []).length + 1;
      for (const aula of DB.pending) {
        const row = [[
          aula.data, aula.aluno, aula.presenca,
          aula.motivo || "", aula.tipo || "", aula.metodo || "",
          aula.conteudo || "", aula.hino || "", aula.licao || "",
          aula.video || "", aula.obs || "",
        ]];
        await fetch(
          `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Diário de Aulas')/range(address='A${nr}:K${nr}')`,
          {
            method: "PATCH",
            headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
            body: JSON.stringify({ values: row }),
          },
        );
        nr++;
      }
      DB.pending = [];
      addLog("ok", "Registros pendentes enviados");
    } catch (e) {
      addLog("err", "Erro ao enviar pendentes: " + e.message);
    }
  }
  await carregarDadosPlanilha();
}

function desconectar() {
  if (!confirm("Desconectar da conta Microsoft?")) return;
  graphToken = null;
  userInfo = null;
  excelFileId = null;
  msalInstance = null;
  localStorage.removeItem("mpm_account");
  localStorage.removeItem("mpm_file_id");
  localStorage.removeItem("mpm_file_path");
  setStatus("disconnected");
  document.getElementById("secao-arquivo").style.display = "none";
  document.getElementById("btn-conectar-wrap").style.display = "";
  document.getElementById("btn-desconectar-wrap").style.display = "none";
  atualizarPassos(1);
  atualizarSyncIndicator(false);
  appConectado = false;
  DB.alunos = [];
  DB.aulas = [];
  atualizarNavBloqueio();
  showToast("Conta desconectada");
}

function setStatus(estado, msg) {
  const card = document.getElementById("status-card");
  const icon = document.getElementById("status-icon");
  const title = document.getElementById("status-title");
  const sub = document.getElementById("status-sub");
  card.className = "status-card " + estado;
  if (estado === "connected") {
    const acc =
      userInfo || JSON.parse(localStorage.getItem("mpm_account") || "{}");
    icon.className = "status-icon ok";
    icon.innerHTML = '<i class="ti ti-cloud-check" aria-hidden="true"></i>';
    title.textContent = "Conectado";
    sub.textContent = acc.username || acc.name || "Conta Microsoft";
    document.getElementById("btn-conectar-wrap").style.display = "none";
    document.getElementById("btn-desconectar-wrap").style.display = "";
    document.getElementById("secao-arquivo").style.display = "";
  } else if (estado === "connecting") {
    icon.className = "status-icon spin";
    icon.innerHTML = '<i class="ti ti-loader" aria-hidden="true"></i>';
    title.textContent = "Conectando...";
    sub.textContent = "Aguarde a janela da Microsoft";
  } else if (estado === "error") {
    icon.className = "status-icon err";
    icon.innerHTML = '<i class="ti ti-cloud-off" aria-hidden="true"></i>';
    title.textContent = "Erro na conexão";
    sub.textContent = msg || "Tente novamente";
    document.getElementById("btn-conectar-wrap").style.display = "";
  } else {
    icon.className = "status-icon no";
    icon.innerHTML = '<i class="ti ti-cloud-off" aria-hidden="true"></i>';
    title.textContent = "Não conectado";
    sub.textContent = "Conecte sua conta Microsoft para sincronizar";
    document.getElementById("btn-conectar-wrap").style.display = "";
  }
}

function atualizarSyncIndicator(conectado) {
  const icon = document.getElementById("sync-icon");
  const txt = document.getElementById("sync-txt");
  icon.className = conectado ? "ti ti-cloud-check" : "ti ti-cloud-off";
  txt.textContent = conectado ? "Sync ativo" : "Offline";
}

function atualizarPassos(step) {
  for (let i = 1; i <= 5; i++) {
    const el = document.getElementById("step" + i + "-num");
    if (!el) continue;
    if (i < step) {
      el.className = "step-num done";
      el.innerHTML =
        '<i class="ti ti-check" aria-hidden="true" style="font-size:14px"></i>';
    } else if (i === step) el.className = "step-num active";
    else el.className = "step-num";
  }
}

function addLog(type, msg) {
  const log = document.getElementById("sync-log");
  log.classList.add("show");
  const t = new Date().toLocaleTimeString("pt-BR", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
  const cls =
    type === "ok" ? "log-ok" : type === "warn" ? "log-warn" : "log-err";
  log.innerHTML += `<div class="log-line"><span class="log-time">${t}</span><span class="${cls}">${msg}</span></div>`;
  log.scrollTop = log.scrollHeight;
}

// Restaura estado salvo
function restaurarEstado() {
  const acc = localStorage.getItem("mpm_account");
  document.getElementById("input-clientid").value = getClientId();
  if (acc) {
    appConectado = true;
    atualizarNavBloqueio();
    setStatus("connected");
    document.getElementById("secao-arquivo").style.display = "";
    atualizarSyncIndicator(true);
    const fileId = localStorage.getItem("mpm_file_id");
    const fileName = localStorage.getItem("mpm_file_name");
    const filePath = localStorage.getItem("mpm_file_path");
    if (fileId && fileName) {
      excelFileId = fileId;
      document.getElementById("arquivo-selecionado").style.display = "";
      document.getElementById("arquivo-picker").style.display = "none";
      document.getElementById("arquivo-nome").textContent = fileName;
      document.getElementById("arquivo-path").textContent = filePath || "";
      document.getElementById("secao-sync").style.display = "";
    }
    const lastSync = localStorage.getItem("mpm_last_sync");
    if (lastSync)
      document.getElementById("ultimo-sync").textContent =
        `Última sync: ${lastSync}`;
    atualizarPassos(fileId ? 5 : 2);
  }
}

// ── DADOS ─────────────────────────────────────────────────────────────────
const DB = {
  pending: [],
  alunos: [],
  aulas: [],
  listas: {
    metodos: [
      "Faber Vol.1",
      "Faber Vol.2",
      "Faber Vol.3",
      "Berens — 100 Estudos",
      "Hanon",
      "Czerny Op.599",
      "Apostila própria — Teoria",
      "Apostila própria — Prática",
      "Hinário CCB",
      "Hinário Batista",
      "Partituras avulsas",
    ],
  },
};

// ── LÓGICA APP ────────────────────────────────────────────────────────────
let alunoAtivo = null,
  calAno = new Date().getFullYear(),
  calMes = new Date().getMonth(),
  presencaSel = "",
  _aulaSheetRowCount = null; // nº de linhas de dados realmente na planilha

function atualizarNavBloqueio() {
  document.querySelectorAll(".nav-item").forEach((btn, i) => {
    btn.classList.toggle("bloqueado", !appConectado && i < 4);
  });
}

function navTo(id, btn) {
  if (!appConectado && id !== "config") {
    showToast("Conecte ao OneDrive primeiro", "warn");
    const configBtn = document.querySelectorAll(".nav-item")[4];
    navTo("config", configBtn);
    return;
  }
  document
    .querySelectorAll(".page")
    .forEach((p) => p.classList.remove("active"));
  document
    .querySelectorAll(".nav-item")
    .forEach((b) => b.classList.remove("active"));
  document.getElementById("page-" + id).classList.add("active");
  btn.classList.add("active");
  if (id === "alunos") renderListaAlunos(DB.alunos);
  if (id === "calendario") renderCalendario();
  if (id === "lancamento") initLancamento();
  if (id === "home") renderHome();
}

function renderHome() {
  const h = new Date();
  document.getElementById("home-date").textContent = h.toLocaleDateString(
    "pt-BR",
    { weekday: "long", day: "numeric", month: "long" },
  );
  const m = h.getMonth(),
    a = h.getFullYear();
  const aM = DB.aulas.filter((x) => {
    const d = new Date(x.data);
    return (
      d.getMonth() === m && d.getFullYear() === a && x.presenca === "Presente"
    );
  }).length;
  document.getElementById("kpi-aulas").textContent = aM;
  const dStr = h.toISOString().slice(0, 10);
  const el = document.getElementById("aulas-hoje");
  const aulasH = DB.aulas.filter((a) => a.data === dStr);
  el.innerHTML = aulasH.length
    ? aulasH
        .map(
          (a) =>
            `<div class="aluno-item">${avatarH(a.aluno)}<div class="aluno-info"><div class="aluno-nome">${a.aluno}</div><div class="aluno-meta">${a.tipo || "—"}</div></div>${pTag(a.presenca)}</div>`,
        )
        .join("")
    : `<div class="card" style="color:var(--text2);font-size:13px;text-align:center;padding:20px"><i class="ti ti-calendar-off" style="font-size:28px;display:block;margin-bottom:6px;opacity:.4" aria-hidden="true"></i>Nenhuma aula hoje</div>`;
  const alertas = [];
  DB.alunos.forEach((al) => {
    const u = DB.aulas
      .filter((a) => a.aluno === al.nome)
      .sort((a, b) => b.data.localeCompare(a.data));
    if (u.length) {
      const d = Math.floor((h - new Date(u[0].data)) / 86400000);
      if (d > 14)
        alertas.push({ t: "amber", m: `${al.nome} — sem aula há ${d} dias` });
    }
    if (
      al.hinos >= 4 &&
      (al.estagio === "Intermediário" || al.estagio === "Básico")
    )
      alertas.push({
        t: "teal",
        m: `${al.nome} — candidato à avaliação de culto`,
      });
  });
  document.getElementById("alertas-home").innerHTML = alertas.length
    ? alertas
        .map(
          (a) =>
            `<div class="card" style="border-left:4px solid var(--${a.t}-med);padding:12px 14px;font-size:13px;margin-bottom:8px"><i class="ti ti-alert-circle" aria-hidden="true" style="color:var(--${a.t}-med);margin-right:6px"></i>${a.m}</div>`,
        )
        .join("")
    : `<div class="card" style="color:var(--text2);font-size:13px;padding:12px">Nenhum alerta 👍</div>`;
  const cnt = {};
  DB.alunos.forEach((al) => {
    cnt[al.estagio] = (cnt[al.estagio] || 0) + 1;
  });
  const cores = {
    Iniciante: "g",
    Básico: "a",
    Intermediário: "p",
    "Apto — Culto Jovens": "t",
    "Apto — Culto Adulto": "t",
    Oficializado: "t",
  };
  document.getElementById("estagios-chart").innerHTML = Object.entries(cnt)
    .map(
      ([e, c]) =>
        `<div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>${e}</span><span style="font-weight:700">${c}</span></div><div class="prog-track"><div class="prog-fill" style="width:${(c / DB.alunos.length) * 100}%;background:var(--${cores[e] || "p"}-med)"></div></div></div>`,
    )
    .join("");
}

function avatarH(nome) {
  const c = ["#EEEDFE", "#E1F5EE", "#FAEEDA", "#FCEBEB", "#E6F1FB"],
    t = ["#3C3489", "#085041", "#633806", "#791F1F", "#0C447C"],
    h = [...nome].reduce((a, c2) => a + c2.charCodeAt(0), 0) % 5,
    i = nome
      .split(" ")
      .slice(0, 2)
      .map((n) => n[0])
      .join("");
  return `<div class="avatar" style="background:${c[h]};color:${t[h]}">${i}</div>`;
}
function badgeE(e) {
  const cls = e.includes("Oficial")
    ? "oficial"
    : e.includes("Apto")
      ? "apto"
      : e === "Intermediário"
        ? "intermediario"
        : e === "Básico"
          ? "basico"
          : "iniciante";
  return `<span class="badge ${cls}">${e}</span>`;
}
function pTag(p) {
  if (p === "Presente")
    return `<span class="badge" style="background:var(--t-light);color:var(--t-dark)"><i class="ti ti-check" aria-hidden="true"></i></span>`;
  if (p.includes("sem aviso"))
    return `<span class="badge" style="background:var(--r-light);color:var(--r-dark)"><i class="ti ti-x" aria-hidden="true"></i></span>`;
  return `<span class="badge" style="background:var(--a-light);color:var(--a-dark)"><i class="ti ti-minus" aria-hidden="true"></i></span>`;
}

function renderListaAlunos(lista) {
  const el = document.getElementById("lista-content");
  el.innerHTML = lista.length
    ? lista
        .map((al) => {
          const u = DB.aulas
            .filter((a) => a.aluno === al.nome)
            .sort((a, b) => b.data.localeCompare(a.data));
          const ult = u[0]
            ? new Date(u[0].data).toLocaleDateString("pt-BR")
            : "—";
          return `<div class="aluno-item" onclick="abrirFicha(${al.id})">${avatarH(al.nome)}<div class="aluno-info"><div class="aluno-nome">${al.nome}</div><div class="aluno-meta">${al.instrumento} · Última: ${ult}</div><div style="margin-top:4px">${badgeE(al.estagio)}</div></div><i class="ti ti-chevron-right" aria-hidden="true" style="color:var(--text2);font-size:18px;flex-shrink:0"></i></div>`;
        })
        .join("")
    : `<div class="empty-state"><i class="ti ti-users-off" aria-hidden="true"></i>Nenhum aluno encontrado</div>`;
}
function filtrarAlunos(q) {
  renderListaAlunos(
    DB.alunos.filter((a) => a.nome.toLowerCase().includes(q.toLowerCase())),
  );
}

function abrirFicha(id) {
  alunoAtivo = DB.alunos.find((a) => a.id === id);
  if (!alunoAtivo) return;
  const al = alunoAtivo;
  document.getElementById("ficha-nome").textContent = al.nome;
  document.getElementById("ficha-meta").textContent =
    `${al.instrumento} · ${al.horario} · Desde ${new Date(al.inicio).toLocaleDateString("pt-BR", { month: "short", year: "numeric" })}`;
  document.getElementById("ficha-estagio").innerHTML =
    `<i class="ti ti-award" aria-hidden="true"></i>${al.estagio}`;
  document.getElementById("ficha-progresso").innerHTML =
    [
      { lbl: "Teoria", val: al.teoria, cor: "var(--p-med)" },
      { lbl: "Hinos", val: Math.min(100, al.hinos * 12), cor: "var(--t-med)" },
      { lbl: "Método", val: al.teoria > 50 ? 62 : 35, cor: "var(--a-med)" },
    ]
      .map(
        (p) =>
          `<div class="prog-row"><span class="prog-label">${p.lbl}</span><div class="prog-track"><div class="prog-fill" style="width:${p.val}%;background:${p.cor}"></div></div><span class="prog-val">${p.val}%</span></div>`,
      )
      .join("") +
    `<div style="font-size:12px;color:var(--text2);margin-top:6px">Método: <strong style="color:var(--text)">${al.metodo}</strong> — ${al.pagina}</div>`;
  const tc = {
      Concluído: "done",
      "Em andamento": "going",
      "Não iniciado": "nope",
    },
    ti = { Concluído: "✓", "Em andamento": "⟳", "Não iniciado": "○" };
  document.getElementById("ficha-habilidades").innerHTML =
    `<div class="pill-row"><span class="pill ${tc[al.tecnica.m1]}">${ti[al.tecnica.m1]} 1 mão</span><span class="pill ${tc[al.tecnica.m2]}">${ti[al.tecnica.m2]} 2 mãos</span><span class="pill ${tc[al.tecnica.ped]}">${ti[al.tecnica.ped]} Pedaleira</span></div><div style="font-size:12px;color:var(--text2);margin-top:10px">${al.hinos} hino(s): ${al.listahinos}</div>`;
  const cc = {
    Apto: "done",
    "Em preparação": "going",
    "Não avaliado": "nope",
    "Não apto no momento": "nope",
  };
  document.getElementById("ficha-culto").innerHTML =
    `<div class="pill-row"><span class="pill ${cc[al.culto.jovens]}">Jovens</span><span class="pill ${cc[al.culto.adulto]}">Adulto</span><span class="pill ${cc[al.culto.ofic]}">Oficialização</span></div>`;
  const hist = DB.aulas
    .filter((a) => a.aluno === al.nome)
    .sort((a, b) => b.data.localeCompare(a.data))
    .slice(0, 4);
  document.getElementById("ficha-historico").innerHTML = hist.length
    ? hist
        .map((a) => {
          const tc2 =
            a.presenca === "Presente"
              ? "ht-p"
              : a.presenca.includes("sem aviso")
                ? "ht-f"
                : a.presenca.includes("Professora")
                  ? "ht-c"
                  : "ht-j";
          const tl =
            a.presenca === "Presente"
              ? "Presente"
              : a.presenca.includes("sem aviso")
                ? "Falta"
                : a.presenca.includes("Professora")
                  ? "Cancelada"
                  : "Justificada";
          return `<div class="hist-item"><div class="hist-date">${new Date(a.data).toLocaleDateString("pt-BR", { day: "numeric", month: "short", year: "numeric" })}</div><div class="hist-txt"><span class="hist-tag ${tc2}">${tl}</span>${a.conteudo || ""}${a.hino ? " · " + a.hino : ""}</div></div>`;
        })
        .join("")
    : '<div style="font-size:13px;color:var(--text2)">Nenhuma aula registrada.</div>';
  document.getElementById("lista-alunos").style.display = "none";
  document.getElementById("ficha-aluno").classList.add("active");
}
function voltarLista() {
  document.getElementById("ficha-aluno").classList.remove("active");
  document.getElementById("lista-alunos").style.display = "";
  alunoAtivo = null;
}
function irLancamento() {
  if (alunoAtivo) document.getElementById("f-aluno").value = alunoAtivo.nome;
  navTo("lancamento", document.querySelectorAll(".nav-item")[2]);
}

function initLancamento() {
  const s = document.getElementById("f-aluno");
  s.innerHTML =
    '<option value="">Selecione...</option>' +
    DB.alunos
      .map(
        (a) =>
          `<option${a === alunoAtivo ? " selected" : ""}>${a.nome}</option>`,
      )
      .join("");
  const m = document.getElementById("f-metodo");
  m.innerHTML =
    '<option value="">Selecione...</option>' +
    DB.listas.metodos.map((x) => `<option>${x}</option>`).join("");
  document.getElementById("f-data").value = new Date()
    .toISOString()
    .slice(0, 10);
  const n = new Date();
  document.getElementById("f-horario").value =
    `${String(n.getHours()).padStart(2, "0")}:${String(n.getMinutes()).padStart(2, "0")}`;
}
function setPresenca(v, cls) {
  presencaSel = v;
  document.getElementById("f-presenca").value = v;
  document.querySelectorAll(".pres-btn").forEach((b) => {
    b.className = "pres-btn";
    if (b.getAttribute("onclick").includes(cls)) b.classList.add("sel-" + cls);
  });
  document.getElementById("motivo-wrap").style.display =
    v !== "Presente" ? "" : "none";
  document.getElementById("conteudo-wrap").style.display =
    v === "Presente" || v === "Avaliação" ? "" : "none";
}
async function salvarAula() {
  const aluno = document.getElementById("f-aluno").value;
  const data = document.getElementById("f-data").value;
  if (!aluno) {
    showToast("Selecione um aluno", "warn");
    return;
  }
  if (!data) {
    showToast("Informe a data", "warn");
    return;
  }
  if (!presencaSel) {
    showToast("Marque a presença", "warn");
    return;
  }
  const nova = {
    data,
    aluno,
    presenca: presencaSel,
    motivo:   document.getElementById("f-motivo").value,
    tipo:     document.getElementById("f-tipo").value,
    metodo:   document.getElementById("f-metodo").value,
    conteudo: document.getElementById("f-conteudo").value,
    hino:     document.getElementById("f-hino").value,
    licao:    document.getElementById("f-licao").value,
    video:    document.getElementById("f-video").value,
    obs:      document.getElementById("f-obs").value,
  };
  DB.aulas.push(nova);

  const token = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");

  if (token && fileId) {
    try {
      // Se ainda não sincronizou nesta sessão, lê o total atual da planilha
      if (_aulaSheetRowCount === null) {
        const rCount = await fetch(
          `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Diário de Aulas')/usedRange`,
          { headers: { Authorization: `Bearer ${token}` } },
        );
        const dCount = await rCount.json();
        _aulaSheetRowCount = Math.max(0, (dCount.values || []).length - 2);
      }
      _aulaSheetRowCount++;
      const rowIdx = _aulaSheetRowCount + 2;
      const row = [[
        nova.data, nova.aluno, nova.presenca,
        nova.motivo || "", nova.tipo || "", nova.metodo || "",
        nova.conteudo || "", nova.hino || "", nova.licao || "",
        nova.video || "", nova.obs || "",
      ]];
      await fetch(
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Diário de Aulas')/range(address='A${rowIdx}:K${rowIdx}')`,
        {
          method: "PATCH",
          headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
          body: JSON.stringify({ values: row }),
        },
      );
      addLog("ok", `Aula salva — ${aluno} (${data})`);
      const agora = new Date().toLocaleString("pt-BR");
      document.getElementById("ultimo-sync").textContent = `Última escrita: ${agora}`;
    } catch (e) {
      _aulaSheetRowCount = null; // reset para re-ler na próxima tentativa
      addLog("err", "Falha ao salvar na planilha — ficou pendente: " + e.message);
      DB.pending.push(nova);
    }
  } else {
    DB.pending.push(nova);
    addLog("warn", `Sem conexão — aula salva localmente (${DB.pending.length} pendente(s))`);
  }

  showToast("Aula registrada! ✓", "success");
  ["f-conteudo", "f-hino", "f-licao", "f-obs", "f-motivo", "f-video"].forEach(
    (id) => (document.getElementById(id).value = ""),
  );
  presencaSel = "";
  document
    .querySelectorAll(".pres-btn")
    .forEach((b) => (b.className = "pres-btn"));
  document.getElementById("motivo-wrap").style.display = "none";
  document.getElementById("conteudo-wrap").style.display = "";
}

const MESES = [
  "Janeiro",
  "Fevereiro",
  "Março",
  "Abril",
  "Maio",
  "Junho",
  "Julho",
  "Agosto",
  "Setembro",
  "Outubro",
  "Novembro",
  "Dezembro",
];
const DS = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
function navMes(d) {
  calMes += d;
  if (calMes > 11) {
    calMes = 0;
    calAno++;
  }
  if (calMes < 0) {
    calMes = 11;
    calAno--;
  }
  renderCalendario();
}
function renderCalendario() {
  document.getElementById("cal-month-label").textContent =
    `${MESES[calMes]} ${calAno}`;
  const grid = document.getElementById("cal-grid");
  grid.innerHTML = DS.map((d) => `<div class="cal-head">${d}</div>`).join("");
  const p = new Date(calAno, calMes, 1).getDay();
  for (let i = 0; i < p; i++)
    grid.innerHTML += `<div class="cal-day empty"></div>`;
  const dias = new Date(calAno, calMes + 1, 0).getDate(),
    hoje = new Date();
  for (let d = 1; d <= dias; d++) {
    const dStr = `${calAno}-${String(calMes + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
    const aD = DB.aulas.filter((a) => a.data === dStr);
    const iH =
      hoje.getDate() === d &&
      hoje.getMonth() === calMes &&
      hoje.getFullYear() === calAno;
    let dots = aD
      .map((a) => {
        const c =
          a.presenca === "Presente"
            ? "var(--t-med)"
            : a.presenca.includes("sem aviso")
              ? "#E24B4A"
              : a.presenca.includes("Professora")
                ? "var(--g-med)"
                : "var(--a-med)";
        return `<div class="cal-dot" style="background:${c}"></div>`;
      })
      .join("");
    grid.innerHTML += `<div class="cal-day${iH ? " today" : ""}" onclick="abrirDia('${dStr}')"><div class="cal-num">${d}</div><div class="cal-dots">${dots}</div></div>`;
  }
  const aM = DB.aulas.filter((a) => {
    const d = new Date(a.data);
    return d.getMonth() === calMes && d.getFullYear() === calAno;
  });
  const pr = aM.filter((a) => a.presenca === "Presente").length,
    fa = aM.filter((a) => a.presenca.includes("Falta")).length,
    ca = aM.filter((a) => a.presenca.includes("Professora")).length;
  document.getElementById("cal-resumo").innerHTML =
    `<div class="kpi-grid"><div class="kpi teal"><div class="kpi-val">${pr}</div><div class="kpi-lbl">Presenças</div></div><div class="kpi amber"><div class="kpi-val">${fa}</div><div class="kpi-lbl">Faltas alunos</div></div><div class="kpi red"><div class="kpi-val">${ca}</div><div class="kpi-lbl">Canceladas</div></div><div class="kpi purple"><div class="kpi-val">${aM.length}</div><div class="kpi-lbl">Total registros</div></div></div>`;
}
function abrirDia(dStr) {
  const aD = DB.aulas.filter((a) => a.data === dStr);
  const d = new Date(dStr + "T12:00:00");
  document.getElementById("modal-dia-title").textContent = d.toLocaleDateString(
    "pt-BR",
    { weekday: "long", day: "numeric", month: "long" },
  );
  document.getElementById("modal-dia-content").innerHTML = aD.length
    ? aD
        .map(
          (a) =>
            `<div style="padding:12px 0;border-bottom:1px solid var(--g-light)"><div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">${avatarH(a.aluno)}<div><div style="font-size:14px;font-weight:700">${a.aluno}</div><div style="font-size:12px;color:var(--text2)">${a.tipo || "—"}</div></div>${pTag(a.presenca)}</div>${a.conteudo ? `<div style="font-size:13px;color:var(--text2)">${a.conteudo}</div>` : ""}${a.hino ? `<div style="font-size:12px;color:var(--t-dark);margin-top:4px"><i class="ti ti-music" aria-hidden="true"></i> ${a.hino}</div>` : ""}</div>`,
        )
        .join("")
    : `<div style="color:var(--text2);font-size:13px;padding:8px 0">Nenhuma aula neste dia.</div><button class="btn btn-primary" style="margin-top:12px" onclick="fecharModal();navTo('lancamento',document.querySelectorAll('.nav-item')[2])"><i class="ti ti-plus" aria-hidden="true"></i> Lançar aula</button>`;
  document.getElementById("modal-dia").classList.add("open");
}
function fecharModal() {
  document.getElementById("modal-dia").classList.remove("open");
}

function gerarRelatorio() {
  if (!alunoAtivo) return;
  const al = alunoAtivo;
  const aA = DB.aulas
    .filter((a) => a.aluno === al.nome)
    .sort((a, b) => b.data.localeCompare(a.data));
  const pr = aA.filter((a) => a.presenca === "Presente").length,
    tot = aA.length;
  const w = window.open("", "_blank");
  w.document
    .write(`<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Relatório — ${al.nome}</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"><style>body{font-family:'DM Sans',sans-serif;max-width:680px;margin:40px auto;color:#1A1A28;padding:0 24px}.header{border-bottom:3px solid #7F77DD;padding-bottom:16px;margin-bottom:24px}.title{font-size:24px;font-weight:800;color:#3C3489}.meta{font-size:13px;color:#6B6A80;margin-top:4px}.section{background:#F5F4FF;border-left:4px solid #7F77DD;padding:12px 16px;margin:16px 0;border-radius:0 10px 10px 0}.section h3{color:#3C3489;font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;margin-bottom:10px}.row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #E8E6F0;font-size:14px}.row:last-child{border-bottom:none}.b{display:inline-block;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:700}.bd{background:#E1F5EE;color:#085041}.bg{background:#FAEEDA;color:#633806}.bn{background:#F1EFE8;color:#888780}.footer{margin-top:40px;font-size:11px;color:#aaa;text-align:center;border-top:1px solid #eee;padding-top:16px}@media print{body{margin:20px}}</style></head><body>
    <div class="header"><div class="title">Relatório de Progresso</div><div class="meta">${al.nome} &nbsp;·&nbsp; ${al.instrumento} &nbsp;·&nbsp; Desde ${new Date(al.inicio).toLocaleDateString("pt-BR", { month: "long", year: "numeric" })}</div></div>
    <div class="section"><h3>Estágio e Progresso</h3>
      <div class="row"><span>Estágio atual</span><strong>${al.estagio}</strong></div>
      <div class="row"><span>Teoria musical</span><strong>${al.teoria}%</strong></div>
      <div class="row"><span>Método atual</span><strong>${al.metodo} — ${al.pagina}</strong></div>
      <div class="row"><span>Hinos dominados</span><strong>${al.hinos}</strong></div>
    </div>
    <div class="section"><h3>Técnica</h3>
      <div class="row"><span>1 mão</span><span class="b ${al.tecnica.m1 === "Concluído" ? "bd" : al.tecnica.m1 === "Em andamento" ? "bg" : "bn"}">${al.tecnica.m1}</span></div>
      <div class="row"><span>2 mãos</span><span class="b ${al.tecnica.m2 === "Concluído" ? "bd" : al.tecnica.m2 === "Em andamento" ? "bg" : "bn"}">${al.tecnica.m2}</span></div>
      <div class="row"><span>Pedaleira</span><span class="b ${al.tecnica.ped === "Concluído" ? "bd" : al.tecnica.ped === "Em andamento" ? "bg" : "bn"}">${al.tecnica.ped}</span></div>
    </div>
    <div class="section"><h3>Prontidão para Culto</h3>
      <div class="row"><span>Culto de jovens</span><span class="b ${al.culto.jovens === "Apto" ? "bd" : al.culto.jovens === "Em preparação" ? "bg" : "bn"}">${al.culto.jovens}</span></div>
      <div class="row"><span>Culto adulto</span><span class="b ${al.culto.adulto === "Apto" ? "bd" : al.culto.adulto === "Em preparação" ? "bg" : "bn"}">${al.culto.adulto}</span></div>
      <div class="row"><span>Oficialização</span><span class="b ${al.culto.ofic === "Apto" ? "bd" : al.culto.ofic === "Em preparação" ? "bg" : "bn"}">${al.culto.ofic}</span></div>
    </div>
    <div class="section"><h3>Frequência</h3>
      <div class="row"><span>Total de registros</span><strong>${tot}</strong></div>
      <div class="row"><span>Presenças</span><strong>${pr}</strong></div>
      <div class="row"><span>Taxa de frequência</span><strong>${tot > 0 ? Math.round((pr / tot) * 100) : 0}%</strong></div>
    </div>
    <div class="section"><h3>Hinos Trabalhados</h3><p style="font-size:14px;line-height:1.7">${al.listahinos}</p></div>
    <div class="section"><h3>Observações da Professora</h3><p style="font-size:14px;line-height:1.7">${al.obs}</p></div>
    <div class="footer">Gerado em ${new Date().toLocaleDateString("pt-BR", { day: "numeric", month: "long", year: "numeric" })} · Diário Pedagógico Musical</div>
    <script>window.print();<\/script></body></html>`);
  w.document.close();
}

function showToast(msg, type = "success") {
  const t = document.getElementById("toast"),
    i = document.getElementById("toast-icon");
  document.getElementById("toast-msg").textContent = msg;
  i.className = type === "success" ? "ti ti-check" : "ti ti-alert-circle";
  t.style.background = type === "success" ? "var(--t-dark)" : "var(--a-dark)";
  t.classList.add("show");
  setTimeout(() => t.classList.remove("show"), 2800);
}

// INIT
restaurarEstado();
atualizarNavBloqueio();
if (!appConectado) {
  document.querySelectorAll(".page").forEach((p) => p.classList.remove("active"));
  document.getElementById("page-config").classList.add("active");
  document.querySelectorAll(".nav-item").forEach((b, i) => {
    b.classList.toggle("active", i === 4);
  });
} else {
  renderHome();
  renderListaAlunos(DB.alunos);
  initLancamento();
  renderCalendario();
}

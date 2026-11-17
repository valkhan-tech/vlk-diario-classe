// ── MSAL / Microsoft Graph ────────────────────────────────────────────────
let msalInstance = null;
let msalLoaded   = false;
let graphToken   = null;
let userInfo     = null;
let excelFileId  = null;

const SCOPES          = ["Files.ReadWrite", "User.Read"];
const GRAPH_BASE      = "https://graph.microsoft.com/v1.0";
const DEFAULT_CLIENT_ID = "1e727060-da1f-4cc2-bb6c-9a77aa3132cc";

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
    s.src = "https://cdn.jsdelivr.net/npm/@azure/msal-browser@3.27.0/lib/msal-browser.min.js";
    s.onload = () => { msalLoaded = true; res(true); };
    s.onerror = () => rej(new Error("Falha ao carregar MSAL"));
    document.head.appendChild(s);
  });
}

async function iniciarLogin() {
  const clientId = document.getElementById("input-clientid").value.trim() || getClientId();
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
    userInfo   = result.account;
    localStorage.setItem("mpm_account", JSON.stringify({
      username: result.account.username,
      name:     result.account.name,
    }));
    appConectado = true;
    atualizarNavBloqueio();
    setStatus("connected");
    addLog("ok", `Login: ${result.account.username}`);
    showToast("Conectado com sucesso! ✓", "success");
    atualizarPassos(2);
  } catch (e) {
    const raw = e.message || "Erro desconhecido";
    const isSpaError = raw.includes("AADSTS70002") || raw.includes("client_secret");
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
    const res = await msalInstance.acquireTokenSilent({ scopes: SCOPES, account: accounts[0] });
    return res.accessToken;
  } catch (e) {
    const res = await msalInstance.acquireTokenPopup({ scopes: SCOPES });
    return res.accessToken;
  }
}

async function validarArquivo() {
  const caminho = document.getElementById("input-caminho").value.trim();
  if (!caminho) { showToast("Informe o caminho do arquivo", "warn"); return; }
  const token = graphToken || (await obterToken());
  if (!token) { showToast("Faça login primeiro", "warn"); return; }
  try {
    addLog("ok", `Buscando: ${caminho}`);
    const res = await fetch(`${GRAPH_BASE}/me/drive/root:${caminho}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!res.ok) throw new Error("Arquivo não encontrado");
    const data = await res.json();
    excelFileId = data.id;
    localStorage.setItem("mpm_file_id",   excelFileId);
    localStorage.setItem("mpm_file_path", caminho);
    localStorage.setItem("mpm_file_name", data.name);
    document.getElementById("arquivo-selecionado").style.display = "";
    document.getElementById("arquivo-picker").style.display      = "none";
    document.getElementById("arquivo-nome").textContent = data.name;
    document.getElementById("arquivo-path").textContent = caminho;
    document.getElementById("secao-sync").style.display = "";
    addLog("ok", `Arquivo encontrado: ${data.name} (${(data.size / 1024).toFixed(1)} KB)`);
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
  if (!token) { showToast("Faça login primeiro", "warn"); return; }
  try {
    const res = await fetch(
      `${GRAPH_BASE}/me/drive/root/children?$filter=file ne null and endswith(name,'.xlsx')&$select=name,id,parentReference`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    const data  = await res.json();
    const items = data.value || [];
    if (!items.length) { showToast("Nenhum .xlsx encontrado na raiz do OneDrive", "warn"); return; }
    const lista   = items.map((f, i) => `${i + 1}. ${f.name}`).join("\n");
    const escolha = prompt(`Arquivos .xlsx encontrados:\n${lista}\n\nDigite o número:`);
    if (!escolha) return;
    const idx = parseInt(escolha) - 1;
    if (idx >= 0 && idx < items.length) {
      const f = items[idx];
      excelFileId = f.id;
      localStorage.setItem("mpm_file_id",   excelFileId);
      localStorage.setItem("mpm_file_name", f.name);
      document.getElementById("arquivo-selecionado").style.display = "";
      document.getElementById("arquivo-picker").style.display      = "none";
      document.getElementById("arquivo-nome").textContent          = f.name;
      document.getElementById("secao-sync").style.display          = "";
      showToast("Arquivo selecionado! ✓", "success");
      await carregarDadosPlanilha();
    }
  } catch (e) {
    showToast("Erro ao listar arquivos", "warn");
  }
}

function trocarArquivo() {
  document.getElementById("arquivo-selecionado").style.display = "none";
  document.getElementById("arquivo-picker").style.display      = "";
}

function desconectar() {
  if (!confirm("Desconectar da conta Microsoft?")) return;
  graphToken   = null;
  userInfo     = null;
  excelFileId  = null;
  msalInstance = null;
  localStorage.removeItem("mpm_account");
  localStorage.removeItem("mpm_file_id");
  localStorage.removeItem("mpm_file_path");
  setStatus("disconnected");
  document.getElementById("secao-arquivo").style.display       = "none";
  document.getElementById("btn-conectar-wrap").style.display   = "";
  document.getElementById("btn-desconectar-wrap").style.display = "none";
  atualizarPassos(1);
  atualizarSyncIndicator(false);
  appConectado = false;
  DB.alunos    = [];
  DB.aulas     = [];
  atualizarNavBloqueio();
  showToast("Conta desconectada");
}

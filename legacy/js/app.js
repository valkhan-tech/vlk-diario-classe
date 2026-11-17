// ── ESTADO GLOBAL DA APLICAÇÃO ────────────────────────────────────────────
let appConectado        = false;
let alunoAtivo          = null;
let calAno              = new Date().getFullYear();
let calMes              = new Date().getMonth();
let presencaSel         = "";
let _aulaSheetRowCount  = null;

// ── NAVEGAÇÃO E BLOQUEIO ──────────────────────────────────────────────────
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
  document.querySelectorAll(".page").forEach((p) => p.classList.remove("active"));
  document.querySelectorAll(".nav-item").forEach((b) => b.classList.remove("active"));
  document.getElementById("page-" + id).classList.add("active");
  btn.classList.add("active");
  if (id === "alunos")      renderListaAlunos(DB.alunos);
  if (id === "calendario")  renderCalendario();
  if (id === "lancamento")  initLancamento();
  if (id === "home")        renderHome();
}

// ── RESTAURAR ESTADO DO LOCALSTORAGE ─────────────────────────────────────
function restaurarEstado() {
  const acc = localStorage.getItem("mpm_account");
  document.getElementById("input-clientid").value = getClientId();
  if (acc) {
    appConectado = true;
    atualizarNavBloqueio();
    setStatus("connected");
    document.getElementById("secao-arquivo").style.display = "";
    atualizarSyncIndicator(true);
    const fileId   = localStorage.getItem("mpm_file_id");
    const fileName = localStorage.getItem("mpm_file_name");
    const filePath = localStorage.getItem("mpm_file_path");
    if (fileId && fileName) {
      excelFileId = fileId;
      document.getElementById("arquivo-selecionado").style.display = "";
      document.getElementById("arquivo-picker").style.display      = "none";
      document.getElementById("arquivo-nome").textContent           = fileName;
      document.getElementById("arquivo-path").textContent           = filePath || "";
      document.getElementById("secao-sync").style.display           = "";
    }
    const lastSync = localStorage.getItem("mpm_last_sync");
    if (lastSync)
      document.getElementById("ultimo-sync").textContent = `Última sync: ${lastSync}`;
    atualizarPassos(fileId ? 5 : 2);
  }
}

// ── INICIALIZAÇÃO ─────────────────────────────────────────────────────────restaurarListas();restaurarEstado();
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

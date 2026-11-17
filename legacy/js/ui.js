// ── UI HELPERS ────────────────────────────────────────────────────────────

function showToast(msg, type = "success") {
  const t = document.getElementById("toast"),
        i = document.getElementById("toast-icon");
  document.getElementById("toast-msg").textContent = msg;
  i.className        = type === "success" ? "ti ti-check" : "ti ti-alert-circle";
  t.style.background = type === "success" ? "var(--t-dark)" : "var(--a-dark)";
  t.classList.add("show");
  setTimeout(() => t.classList.remove("show"), 2800);
}

function addLog(type, msg) {
  const log = document.getElementById("sync-log");
  log.classList.add("show");
  const t   = new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
  const cls = type === "ok" ? "log-ok" : type === "warn" ? "log-warn" : "log-err";
  log.innerHTML += `<div class="log-line"><span class="log-time">${t}</span><span class="${cls}">${msg}</span></div>`;
  log.scrollTop  = log.scrollHeight;
}

function setStatus(estado, msg) {
  const card  = document.getElementById("status-card");
  const icon  = document.getElementById("status-icon");
  const title = document.getElementById("status-title");
  const sub   = document.getElementById("status-sub");
  card.className = "status-card " + estado;
  if (estado === "connected") {
    const acc = userInfo || JSON.parse(localStorage.getItem("mpm_account") || "{}");
    icon.className = "status-icon ok";
    icon.innerHTML = '<i class="ti ti-cloud-check" aria-hidden="true"></i>';
    title.textContent = "Conectado";
    sub.textContent   = acc.username || acc.name || "Conta Microsoft";
    document.getElementById("btn-conectar-wrap").style.display    = "none";
    document.getElementById("btn-desconectar-wrap").style.display = "";
    document.getElementById("secao-arquivo").style.display        = "";
  } else if (estado === "connecting") {
    icon.className = "status-icon spin";
    icon.innerHTML = '<i class="ti ti-loader" aria-hidden="true"></i>';
    title.textContent = "Conectando...";
    sub.textContent   = "Aguarde a janela da Microsoft";
  } else if (estado === "error") {
    icon.className = "status-icon err";
    icon.innerHTML = '<i class="ti ti-cloud-off" aria-hidden="true"></i>';
    title.textContent = "Erro na conexão";
    sub.textContent   = msg || "Tente novamente";
    document.getElementById("btn-conectar-wrap").style.display    = "";
  } else {
    icon.className = "status-icon no";
    icon.innerHTML = '<i class="ti ti-cloud-off" aria-hidden="true"></i>';
    title.textContent = "Não conectado";
    sub.textContent   = "Conecte sua conta Microsoft para sincronizar";
    document.getElementById("btn-conectar-wrap").style.display    = "";
  }
}

function atualizarSyncIndicator(conectado) {
  const icon = document.getElementById("sync-icon");
  const txt  = document.getElementById("sync-txt");
  icon.className  = conectado ? "ti ti-cloud-check" : "ti ti-cloud-off";
  txt.textContent = conectado ? "Sync ativo" : "Offline";
}

function atualizarPassos(step) {
  for (let i = 1; i <= 5; i++) {
    const el = document.getElementById("step" + i + "-num");
    if (!el) continue;
    if (i < step) {
      el.className = "step-num done";
      el.innerHTML = '<i class="ti ti-check" aria-hidden="true" style="font-size:14px"></i>';
    } else if (i === step) {
      el.className = "step-num active";
    } else {
      el.className = "step-num";
    }
  }
}

function skeletonList(n = 4) {
  return Array(n).fill(
    `<div class="skeleton-item"><div class="skel skel-avatar"></div><div class="skel-lines"><div class="skel skel-line"></div><div class="skel skel-line short"></div></div></div>`
  ).join("");
}

function avatarH(nome) {
  const c = ["#EEEDFE", "#E1F5EE", "#FAEEDA", "#FCEBEB", "#E6F1FB"],
        t = ["#3C3489", "#085041", "#633806", "#791F1F", "#0C447C"],
        h = [...nome].reduce((a, c2) => a + c2.charCodeAt(0), 0) % 5,
        i = nome.split(" ").slice(0, 2).map((n) => n[0]).join("");
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

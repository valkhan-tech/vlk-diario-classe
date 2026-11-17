// ── HOME ──────────────────────────────────────────────────────────────────
function renderHome() {
  const h = new Date();
  document.getElementById("home-date").textContent = h.toLocaleDateString(
    "pt-BR",
    { weekday: "long", day: "numeric", month: "long" },
  );
  const m = h.getMonth(), a = h.getFullYear();
  const aM = DB.aulas.filter((x) => {
    const d = new Date(x.data);
    return d.getMonth() === m && d.getFullYear() === a && x.presenca === "Presente";
  }).length;
  document.getElementById("kpi-aulas").textContent   = aM;
  document.getElementById("kpi-alunos").textContent  = DB.alunos.length;
  const pend    = typeof calcularPendencias === "function" ? calcularPendencias() : 0;
  const kpiPend = document.getElementById("kpi-pend");
  if (kpiPend) kpiPend.textContent = `R$ ${pend.toFixed(2).replace(".", ",")}`;

  const dStr   = h.toISOString().slice(0, 10);
  const el     = document.getElementById("aulas-hoje");
  const aulasH = DB.aulas.filter((a) => a.data === dStr);
  el.innerHTML = aulasH.length
    ? aulasH.map((a) =>
        `<div class="aluno-item">${avatarH(a.aluno)}<div class="aluno-info"><div class="aluno-nome">${a.aluno}</div><div class="aluno-meta">${a.tipo || "—"}</div></div>${pTag(a.presenca)}</div>`
      ).join("")
    : `<div class="card" style="color:var(--text2);font-size:13px;text-align:center;padding:20px"><i class="ti ti-calendar-off" style="font-size:28px;display:block;margin-bottom:6px;opacity:.4" aria-hidden="true"></i>Nenhuma aula hoje</div>`;

  const alertas = [];
  DB.alunos.forEach((al) => {
    const u = DB.aulas.filter((a) => a.aluno === al.nome).sort((a, b) => b.data.localeCompare(a.data));
    if (u.length) {
      const d = Math.floor((h - new Date(u[0].data)) / 86400000);
      if (d > 14) alertas.push({ t: "amber", m: `${al.nome} — sem aula há ${d} dias` });
    }
    if (al.hinos >= 4 && (al.estagio === "Intermediário" || al.estagio === "Básico"))
      alertas.push({ t: "teal", m: `${al.nome} — candidato à avaliação de culto` });
  });
  document.getElementById("alertas-home").innerHTML = alertas.length
    ? alertas.map((a) =>
        `<div class="card" style="border-left:4px solid var(--${a.t}-med);padding:12px 14px;font-size:13px;margin-bottom:8px"><i class="ti ti-alert-circle" aria-hidden="true" style="color:var(--${a.t}-med);margin-right:6px"></i>${a.m}</div>`
      ).join("")
    : `<div class="card" style="color:var(--text2);font-size:13px;padding:12px">Nenhum alerta 👍</div>`;

  const cnt   = {};
  const cores = {
    Iniciante: "g", Básico: "a", Intermediário: "p",
    "Apto — Culto Jovens": "t", "Apto — Culto Adulto": "t", Oficializado: "t",
  };
  DB.alunos.forEach((al) => { cnt[al.estagio] = (cnt[al.estagio] || 0) + 1; });
  document.getElementById("estagios-chart").innerHTML = Object.entries(cnt)
    .map(([e, c]) =>
      `<div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>${e}</span><span style="font-weight:700">${c}</span></div><div class="prog-track"><div class="prog-fill" style="width:${(c / DB.alunos.length) * 100}%;background:var(--${cores[e] || "p"}-med)"></div></div></div>`
    ).join("");
}

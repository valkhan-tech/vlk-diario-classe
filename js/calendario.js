// ── CALENDÁRIO ────────────────────────────────────────────────────────────
const MESES = ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"];
const DS    = ["Dom","Seg","Ter","Qua","Qui","Sex","Sáb"];

function navMes(d) {
  calMes += d;
  if (calMes > 11) { calMes = 0;  calAno++; }
  if (calMes <  0) { calMes = 11; calAno--; }
  renderCalendario();
}

function renderCalendario() {
  document.getElementById("cal-month-label").textContent = `${MESES[calMes]} ${calAno}`;
  const grid = document.getElementById("cal-grid");
  grid.innerHTML = DS.map((d) => `<div class="cal-head">${d}</div>`).join("");
  const p = new Date(calAno, calMes, 1).getDay();
  for (let i = 0; i < p; i++) grid.innerHTML += `<div class="cal-day empty"></div>`;
  const dias = new Date(calAno, calMes + 1, 0).getDate(), hoje = new Date();
  for (let d = 1; d <= dias; d++) {
    const dStr = `${calAno}-${String(calMes + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
    const aD   = DB.aulas.filter((a) => a.data === dStr);
    const iH   = hoje.getDate() === d && hoje.getMonth() === calMes && hoje.getFullYear() === calAno;
    const dots = aD.map((a) => {
      const c = a.presenca === "Presente" ? "var(--t-med)"
        : a.presenca.includes("sem aviso") ? "#E24B4A"
        : a.presenca.includes("Professora") ? "var(--g-med)"
        : "var(--a-med)";
      return `<div class="cal-dot" style="background:${c}"></div>`;
    }).join("");
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
  const d  = new Date(dStr + "T12:00:00");
  document.getElementById("modal-dia-title").textContent = d.toLocaleDateString(
    "pt-BR", { weekday: "long", day: "numeric", month: "long" },
  );
  document.getElementById("modal-dia-content").innerHTML = aD.length
    ? aD.map((a) =>
        `<div style="padding:12px 0;border-bottom:1px solid var(--g-light)"><div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">${avatarH(a.aluno)}<div><div style="font-size:14px;font-weight:700">${a.aluno}</div><div style="font-size:12px;color:var(--text2)">${a.tipo || "—"}</div></div>${pTag(a.presenca)}</div>${a.conteudo ? `<div style="font-size:13px;color:var(--text2)">${a.conteudo}</div>` : ""}${a.hino ? `<div style="font-size:12px;color:var(--t-dark);margin-top:4px"><i class="ti ti-music" aria-hidden="true"></i> ${a.hino}</div>` : ""}</div>`
      ).join("")
    : `<div style="color:var(--text2);font-size:13px;padding:8px 0">Nenhuma aula neste dia.</div><button class="btn btn-primary" style="margin-top:12px" onclick="fecharModal();navTo('lancamento',document.querySelectorAll('.nav-item')[2])"><i class="ti ti-plus" aria-hidden="true"></i> Lançar aula</button>`;
  document.getElementById("modal-dia").classList.add("open");
}

function fecharModal() {
  document.getElementById("modal-dia").classList.remove("open");
}

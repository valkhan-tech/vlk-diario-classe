// ── LISTA E FICHA DE ALUNOS ───────────────────────────────────────────────
function renderListaAlunos(lista) {
  const el = document.getElementById("lista-content");
  if (!lista) { el.innerHTML = skeletonList(5); return; }
  el.innerHTML = lista.length
    ? lista.map((al) => {
        const u   = DB.aulas.filter((a) => a.aluno === al.nome).sort((a, b) => b.data.localeCompare(a.data));
        const ult = u[0] ? new Date(u[0].data).toLocaleDateString("pt-BR") : "—";
        return `<div class="aluno-item" onclick="abrirFicha(${al.id})">${avatarH(al.nome)}<div class="aluno-info"><div class="aluno-nome">${al.nome}</div><div class="aluno-meta">${al.instrumento} · Última: ${ult}</div><div style="margin-top:4px">${badgeE(al.estagio)}</div></div><i class="ti ti-chevron-right" aria-hidden="true" style="color:var(--text2);font-size:18px;flex-shrink:0"></i></div>`;
      }).join("")
    : `<div class="empty-state"><i class="ti ti-users-off" aria-hidden="true"></i>Nenhum aluno encontrado</div>`;
}

function filtrarAlunos(q) {
  renderListaAlunos(DB.alunos.filter((a) => a.nome.toLowerCase().includes(q.toLowerCase())));
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
      { lbl: "Teoria", val: al.teoria,                    cor: "var(--p-med)" },
      { lbl: "Hinos",  val: Math.min(100, al.hinos * 12), cor: "var(--t-med)" },
      { lbl: "Método", val: al.teoria > 50 ? 62 : 35,     cor: "var(--a-med)" },
    ].map((p) =>
      `<div class="prog-row"><span class="prog-label">${p.lbl}</span><div class="prog-track"><div class="prog-fill" style="width:${p.val}%;background:${p.cor}"></div></div><span class="prog-val">${p.val}%</span></div>`
    ).join("") +
    `<div style="font-size:12px;color:var(--text2);margin-top:6px">Método: <strong style="color:var(--text)">${al.metodo}</strong> — ${al.pagina}</div>`;

  const tc = { Concluído: "done", "Em andamento": "going", "Não iniciado": "nope" };
  const ti = { Concluído: "✓",   "Em andamento": "⟳",     "Não iniciado": "○"    };
  document.getElementById("ficha-habilidades").innerHTML =
    `<div class="pill-row"><span class="pill ${tc[al.tecnica.m1]}">${ti[al.tecnica.m1]} 1 mão</span><span class="pill ${tc[al.tecnica.m2]}">${ti[al.tecnica.m2]} 2 mãos</span><span class="pill ${tc[al.tecnica.ped]}">${ti[al.tecnica.ped]} Pedaleira</span></div><div style="font-size:12px;color:var(--text2);margin-top:10px">${al.hinos} hino(s): ${al.listahinos}</div>`;

  const cc = { Apto: "done", "Em preparação": "going", "Não avaliado": "nope", "Não apto no momento": "nope" };
  document.getElementById("ficha-culto").innerHTML =
    `<div class="pill-row"><span class="pill ${cc[al.culto.jovens]}">Jovens</span><span class="pill ${cc[al.culto.adulto]}">Adulto</span><span class="pill ${cc[al.culto.ofic]}">Oficialização</span></div>`;

  const hist = DB.aulas
    .filter((a) => a.aluno === al.nome)
    .sort((a, b) => b.data.localeCompare(a.data))
    .slice(0, 4);
  document.getElementById("ficha-historico").innerHTML = hist.length
    ? hist.map((a) => {
        const tc2 = a.presenca === "Presente" ? "ht-p" : a.presenca.includes("sem aviso") ? "ht-f" : a.presenca.includes("Professora") ? "ht-c" : "ht-j";
        const tl  = a.presenca === "Presente" ? "Presente" : a.presenca.includes("sem aviso") ? "Falta" : a.presenca.includes("Professora") ? "Cancelada" : "Justificada";
        return `<div class="hist-item"><div class="hist-date">${new Date(a.data).toLocaleDateString("pt-BR", { day: "numeric", month: "short", year: "numeric" })}</div><div class="hist-txt"><span class="hist-tag ${tc2}">${tl}</span>${a.conteudo || ""}${a.hino ? " · " + a.hino : ""}</div></div>`;
      }).join("")
    : '<div style="font-size:13px;color:var(--text2)">Nenhuma aula registrada.</div>';

  document.getElementById("lista-alunos").style.display = "none";
  document.getElementById("ficha-aluno").classList.add("active");
  if (typeof renderFinanceiro === "function") renderFinanceiro(al.nome);
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

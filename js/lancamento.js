// ── LANÇAMENTO DE AULAS ───────────────────────────────────────────────────
function initLancamento() {
  const s = document.getElementById("f-aluno");
  s.innerHTML =
    '<option value="">Selecione...</option>' +
    DB.alunos.map((a) =>
      `<option${a === alunoAtivo ? " selected" : ""}>${a.nome}</option>`
    ).join("");
  populateSelect("f-metodo", "MÉTODOS / APOSTILAS");
  populateSelect("f-tipo",   "TIPO DE AULA");
  document.getElementById("f-data").value    = new Date().toISOString().slice(0, 10);
  const n = new Date();
  document.getElementById("f-horario").value = `${String(n.getHours()).padStart(2, "0")}:${String(n.getMinutes()).padStart(2, "0")}`;
}

function setPresenca(v, cls) {
  presencaSel = v;
  document.getElementById("f-presenca").value = v;
  document.querySelectorAll(".pres-btn").forEach((b) => {
    b.className = "pres-btn";
    if (b.getAttribute("onclick").includes(cls)) b.classList.add("sel-" + cls);
  });
  document.getElementById("motivo-wrap").style.display   = v !== "Presente" ? "" : "none";
  document.getElementById("conteudo-wrap").style.display = v === "Presente" || v === "Avaliação" ? "" : "none";
}

async function salvarAula() {
  const aluno = document.getElementById("f-aluno").value;
  const data  = document.getElementById("f-data").value;
  if (!aluno)       { showToast("Selecione um aluno", "warn"); return; }
  if (!data)        { showToast("Informe a data", "warn");     return; }
  if (!presencaSel) { showToast("Marque a presença", "warn");  return; }

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

  const token  = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");

  if (token && fileId) {
    try {
      if (_aulaSheetRowCount === null) {
        const rCount = await fetch(
          `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Di%C3%A1rio%20de%20Aulas')/usedRange`,
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
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Di%C3%A1rio%20de%20Aulas')/range(address='A${rowIdx}:K${rowIdx}')`,
        {
          method:  "PATCH",
          headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
          body:    JSON.stringify({ values: row }),
        },
      );
      addLog("ok", `Aula salva — ${aluno} (${data})`);
      const agora = new Date().toLocaleString("pt-BR");
      document.getElementById("ultimo-sync").textContent = `Última escrita: ${agora}`;
    } catch (e) {
      _aulaSheetRowCount = null;
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
  document.querySelectorAll(".pres-btn").forEach((b) => (b.className = "pres-btn"));
  document.getElementById("motivo-wrap").style.display   = "none";
  document.getElementById("conteudo-wrap").style.display = "";
}

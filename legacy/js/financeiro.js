// ── FINANCEIRO ────────────────────────────────────────────────────────────

function calcularPendencias() {
  return (DB.financeiro || [])
    .filter((f) => f.status === "Pendente" || f.status === "Atrasado")
    .reduce((sum, f) => sum + Math.abs(f.saldo), 0);
}

function renderFinanceiro(alunoNome) {
  const el = document.getElementById("ficha-financeiro");
  if (!el) return;

  const reg = (DB.financeiro || [])
    .filter((f) => f.aluno === alunoNome)
    .sort((a, b) => b.mesAno.localeCompare(a.mesAno))
    .slice(0, 6);

  const anoAtual = new Date().getFullYear();
  const pagosAno = (DB.financeiro || [])
    .filter((f) => f.aluno === alunoNome && f.mesAno.includes(String(anoAtual)) && f.status === "Pago")
    .reduce((s, f) => s + f.pago, 0);
  const pendentes = (DB.financeiro || [])
    .filter((f) => f.aluno === alunoNome && (f.status === "Pendente" || f.status === "Atrasado"))
    .reduce((s, f) => s + Math.abs(f.saldo), 0);

  const fmt = (v) => `R$ ${(v || 0).toFixed(2).replace(".", ",")}`;
  const stCls = { Pago: "done", Pendente: "going", Atrasado: "nope" };

  el.innerHTML =
    `<div style="display:flex;gap:10px;margin-bottom:12px">
      <div class="kpi teal" style="flex:1;padding:10px 8px"><div class="kpi-val" style="font-size:15px">${fmt(pagosAno)}</div><div class="kpi-lbl">Pago em ${anoAtual}</div></div>
      <div class="kpi ${pendentes > 0 ? "red" : "teal"}" style="flex:1;padding:10px 8px"><div class="kpi-val" style="font-size:15px">${fmt(pendentes)}</div><div class="kpi-lbl">Pendências</div></div>
    </div>` +
    (reg.length
      ? reg.map((f) =>
          `<div style="padding:9px 0;border-bottom:1px solid var(--g-light);display:flex;align-items:center;gap:10px">
            <div style="flex:1"><div style="font-size:13px;font-weight:700">${f.mesAno}</div><div style="font-size:11px;color:var(--text2)">${f.forma || "—"}${f.dataPag ? " · " + new Date(f.dataPag + "T12:00:00").toLocaleDateString("pt-BR") : ""}</div></div>
            <div style="text-align:right"><div style="font-size:13px">${fmt(f.cobrado)}</div><span class="pill ${stCls[f.status] || "bn"}" style="font-size:11px">${f.status}</span></div>
          </div>`
        ).join("")
      : `<div style="font-size:13px;color:var(--text2);padding:6px 0">Nenhum registro financeiro.</div>`);
}

function toggleFinForm() {
  const el = document.getElementById("fin-form");
  if (!el) return;
  const abrir = el.style.display === "none" || el.style.display === "";
  el.style.display = abrir ? "block" : "none";
  if (abrir) {
    populateSelect("fin-forma",   "PAGAMENTO");
    populateSelect("fin-status-sel", "PAGAMENTO"); // statuses inline — fallback cobre
    document.getElementById("fin-datapag").value = new Date().toISOString().slice(0, 10);
    if (alunoAtivo) document.getElementById("fin-cobrado").value = alunoAtivo.mensalidade || "";
  }
}

async function salvarPagamento() {
  const al = alunoAtivo;
  if (!al) return;

  const mesAno  = document.getElementById("fin-mesano").value;
  const cobrado = Number(document.getElementById("fin-cobrado").value) || 0;
  const pago    = Number(document.getElementById("fin-pago").value) || 0;
  const status  = document.getElementById("fin-status-sel").value;
  const dataPag = document.getElementById("fin-datapag").value;
  const forma   = document.getElementById("fin-forma").value;
  const obs     = document.getElementById("fin-obs").value;
  const saldo   = pago - cobrado;

  if (!mesAno) { showToast("Informe o mês/ano de referência", "warn"); return; }
  if (!status) { showToast("Selecione o status", "warn");              return; }

  const btn    = document.getElementById("btn-salvar-pagamento");
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader" style="animation:spin 1s linear infinite" aria-hidden="true"></i> Salvando...';

  const novo = { mesAno, aluno: al.nome, cobrado, pago, status, dataPag, forma, obs, saldo };
  DB.financeiro = DB.financeiro || [];
  DB.financeiro.push(novo);

  const token  = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");

  if (token && fileId) {
    try {
      const rCount = await fetch(
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Financeiro')/usedRange`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      const dCount = await rCount.json();
      const rowIdx = (dCount.values || []).length + 1;
      const row = [[mesAno, al.nome, cobrado, pago, status, dataPag, forma, obs, saldo]];
      await fetch(
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Financeiro')/range(address='A${rowIdx}:I${rowIdx}')`,
        {
          method:  "PATCH",
          headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
          body:    JSON.stringify({ values: row }),
        },
      );
      addLog("ok", `Pagamento lançado — ${al.nome} (${mesAno})`);
      showToast("Pagamento registrado! ✓", "success");
    } catch (e) {
      addLog("err", "Erro ao salvar pagamento: " + e.message);
      showToast("Erro ao salvar: " + e.message, "warn");
    }
  } else {
    addLog("warn", "Sem conexão — pagamento salvo localmente");
    showToast("Salvo localmente (sem sync)", "warn");
  }

  document.getElementById("fin-form").style.display = "none";
  ["fin-mesano","fin-cobrado","fin-pago","fin-datapag","fin-obs"].forEach(
    (id) => (document.getElementById(id).value = ""),
  );
  renderFinanceiro(al.nome);
  // atualiza kpi-pend na home
  const kpiEl = document.getElementById("kpi-pend");
  if (kpiEl) kpiEl.textContent = `R$ ${calcularPendencias().toFixed(2).replace(".", ",")}`;

  btn.disabled  = false;
  btn.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Registrar';
}

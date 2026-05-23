// ── DADOS ─────────────────────────────────────────────────────────────────
const DB = {
  pending:    [],
  alunos:     [],
  aulas:      [],
  listas:     {},
  financeiro: [],
};

// ── LEITURA COMPLETA DA PLANILHA ──────────────────────────────────────────
async function carregarDadosPlanilha() {
  const token  = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");
  if (!token || !fileId) { showToast("Configure a planilha primeiro", "warn"); return; }
  const btn = document.getElementById("btn-atualizar");
  if (btn) {
    btn.disabled  = true;
    btn.innerHTML = '<i class="ti ti-loader" style="animation:spin 1s linear infinite" aria-hidden="true"></i> Carregando...';
  }
  addLog("ok", "Carregando dados da planilha...");
  // mostra skeleton enquanto carrega
  const lcEl = document.getElementById("lista-content");
  if (lcEl) lcEl.innerHTML = skeletonList(5);
  try {
    // ── Diário de Aulas ───────────────────────────────────────────────────
    const resAulas = await fetch(
      `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Di%C3%A1rio%20de%20Aulas')/usedRange`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    if (!resAulas.ok) throw new Error("Aba 'Diário de Aulas' não encontrada");
    const dAulas    = await resAulas.json();
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

    // ── Alunos ────────────────────────────────────────────────────────────
    const resAlunos = await fetch(
      `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Alunos')/usedRange`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    if (resAlunos.ok) {
      const dAlunos    = await resAlunos.json();
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

    // ── Financeiro ───────────────────────────────────────────────────
    const resFinanceiro = await fetch(
      `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Financeiro')/usedRange`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    if (resFinanceiro.ok) {
      const dFin    = await resFinanceiro.json();
      const rowsFin = (dFin.values || []).slice(2); // pula título e cabeçalho
      DB.financeiro = rowsFin
        .filter((r) => r[0] || r[1])
        .map((r) => {
          // datas podem vir como serial Excel ou string ISO
          const rawDate = r[5];
          const dataPag = rawDate
            ? (isNaN(rawDate) ? String(rawDate) : new Date((Number(rawDate) - 25569) * 86400000).toISOString().slice(0, 10))
            : "";
          return {
            mesAno:  String(r[0] || ""),
            aluno:   String(r[1] || ""),
            cobrado: Number(r[2]) || 0,
            pago:    Number(r[3]) || 0,
            status:  String(r[4] || ""),
            dataPag,
            forma:   String(r[6] || ""),
            obs:     String(r[7] || ""),
            saldo:   Number(r[8]) || 0,
          };
        });
      addLog("ok", `${DB.financeiro.length} registro(s) financeiro(s) carregado(s)`);
    } else {
      addLog("warn", "Aba 'Financeiro' não encontrada");
    }

    DB.pending = [];
    await carregarListasPlanilha(token, fileId);
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
      btn.disabled  = false;
      btn.innerHTML = '<i class="ti ti-refresh" aria-hidden="true"></i> Atualizar tudo';
    }
  }
}

// ── ATUALIZAR TUDO (push pendentes + leitura completa) ────────────────────
async function atualizarTudo() {
  const token  = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");
  if (!token || !fileId) { showToast("Configure a planilha primeiro", "warn"); return; }
  if (DB.pending.length > 0) {
    addLog("ok", `Enviando ${DB.pending.length} registro(s) pendente(s)...`);
    try {
      const res = await fetch(
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Di%C3%A1rio%20de%20Aulas')/usedRange`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      const d  = await res.json();
      let nr   = (d.values || []).length + 1;
      for (const aula of DB.pending) {
        const row = [[
          aula.data, aula.aluno, aula.presenca,
          aula.motivo || "", aula.tipo || "", aula.metodo || "",
          aula.conteudo || "", aula.hino || "", aula.licao || "",
          aula.video || "", aula.obs || "",
        ]];
        await fetch(
          `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Di%C3%A1rio%20de%20Aulas')/range(address='A${nr}:K${nr}')`,
          {
            method:  "PATCH",
            headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
            body:    JSON.stringify({ values: row }),
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

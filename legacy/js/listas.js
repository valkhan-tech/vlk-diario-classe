// ── FALLBACKS (usados quando a planilha ainda não foi carregada) ──────────
const LISTAS_FALLBACK = {
  "INSTRUMENTOS":      ["Piano", "Teclado", "Violão", "Guitarra", "Baixo", "Bateria", "Flauta", "Canto"],
  "ESTÁGIOS":          ["Iniciante", "Básico", "Intermediário", "Apto — Culto Jovens", "Apto — Culto Adulto", "Oficializado"],
  "TIPO DE AULA":      ["Teórica", "Prática", "Mista", "Musicalização", "Avaliação"],
  "PRESENÇA":          ["Presente", "Falta — Aluno (justificada)", "Falta — Aluno (sem aviso)", "Falta — Professora"],
  "TÉCNICA":           ["Não iniciado", "Em andamento", "Concluído"],
  "PRONTIDÃO CULTO":   ["Não avaliado", "Em preparação", "Apto", "Não apto no momento"],
  "MÉTODOS / APOSTILAS": [
    "Faber Vol.1", "Faber Vol.2", "Faber Vol.3",
    "Berens — 100 Estudos", "Hanon", "Czerny Op.599",
    "Apostila própria — Teoria", "Apostila própria — Prática",
    "Hinário CCB", "Hinário Batista", "Partituras avulsas",
  ],
  "PAGAMENTO":         ["Dinheiro", "Pix", "Transferência", "Cartão"],
  "STATUS ALUNO":      ["Ativo", "Pausado", "Inativo", "Concluído"],
};

// ── RESTAURAR DO LOCALSTORAGE (sem chamada de rede) ───────────────────────
function restaurarListas() {
  try {
    const raw = localStorage.getItem("mpm_listas");
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === "object") {
        DB.listas = parsed;
        addLog("ok", "Listas restauradas do cache local");
      }
    }
  } catch (_) { /* JSON inválido — ignora */ }
}

// ── LEITURA DA PLANILHA ───────────────────────────────────────────────────
async function carregarListasPlanilha(token, fileId) {
  try {
    const res = await fetch(
      `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Listas')/usedRange`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    if (!res.ok) { addLog("warn", "Aba 'Listas' não encontrada — usando fallback"); return; }
    const data   = await res.json();
    const values = data.values || [];
    if (values.length < 4) { addLog("warn", "Aba 'Listas' sem dados suficientes"); return; }
    const headers   = values[2];   // linha 3 (índice 2) = cabeçalhos
    const dataRows  = values.slice(3); // linha 4+ = valores
    const parsed    = {};
    headers.forEach((h, colIdx) => {
      if (!h) return;
      parsed[String(h).trim()] = dataRows
        .map((r) => (r[colIdx] !== undefined && r[colIdx] !== null && r[colIdx] !== "" ? String(r[colIdx]).trim() : null))
        .filter(Boolean);
    });
    DB.listas = parsed;
    localStorage.setItem("mpm_listas", JSON.stringify(parsed));
    addLog("ok", `Listas carregadas (${Object.keys(parsed).length} colunas)`);
  } catch (e) {
    addLog("err", "Erro ao carregar Listas: " + e.message);
  }
}

// ── ACESSO ────────────────────────────────────────────────────────────────
function getLista(nome) {
  const v = DB.listas && DB.listas[nome];
  return (v && v.length) ? v : (LISTAS_FALLBACK[nome] || []);
}

// ── POPULAR UM <SELECT> ───────────────────────────────────────────────────
function populateSelect(selectId, nomeLista, preserveValue = true) {
  const el = document.getElementById(selectId);
  if (!el) return;
  const cur  = preserveValue ? el.value : "";
  const vals = getLista(nomeLista);
  el.innerHTML =
    '<option value="">Selecione...</option>' +
    vals.map((v) => `<option>${v}</option>`).join("");
  if (cur) el.value = cur;
}

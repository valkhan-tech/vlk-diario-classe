// ── EDIÇÃO DE ALUNO ───────────────────────────────────────────────────────
function abrirEdicao() {
  const al = alunoAtivo;
  if (!al) return;

  // Dados gerais
  document.getElementById("ea-nome").value        = al.nome;
  document.getElementById("ea-nasc").value        = al.nasc;
  document.getElementById("ea-horario").value     = al.horario;
  document.getElementById("ea-mensalidade").value = al.mensalidade;
  document.getElementById("ea-inicio").value      = al.inicio;
  document.getElementById("ea-obs").value         = al.obs;

  // Progresso
  document.getElementById("ea-teoria").value     = al.teoria;
  document.getElementById("ea-pagina").value     = al.pagina;
  document.getElementById("ea-hinos").value      = al.hinos;
  document.getElementById("ea-listahinos").value = al.listahinos !== "—" ? al.listahinos : "";

  // Selects dinâmicos
  populateSelect("ea-instrumento",    "INSTRUMENTOS");
  populateSelect("ea-estagio",        "ESTÁGIOS");
  populateSelect("ea-metodo",         "MÉTODOS / APOSTILAS");
  populateSelect("ea-status",         "STATUS ALUNO");
  populateSelect("ea-tec-m1",        "TÉCNICA");
  populateSelect("ea-tec-m2",        "TÉCNICA");
  populateSelect("ea-tec-ped",       "TÉCNICA");
  populateSelect("ea-culto-jovens",  "PRONTIDÃO CULTO");
  populateSelect("ea-culto-adulto",  "PRONTIDÃO CULTO");
  populateSelect("ea-culto-ofic",    "PRONTIDÃO CULTO");

  // Selecionar valores atuais
  document.getElementById("ea-instrumento").value   = al.instrumento;
  document.getElementById("ea-estagio").value        = al.estagio;
  document.getElementById("ea-metodo").value         = al.metodo;
  document.getElementById("ea-status").value         = al.status || "";
  document.getElementById("ea-tec-m1").value        = al.tecnica.m1;
  document.getElementById("ea-tec-m2").value        = al.tecnica.m2;
  document.getElementById("ea-tec-ped").value       = al.tecnica.ped;
  document.getElementById("ea-culto-jovens").value  = al.culto.jovens;
  document.getElementById("ea-culto-adulto").value  = al.culto.adulto;
  document.getElementById("ea-culto-ofic").value    = al.culto.ofic;

  document.getElementById("ficha-aluno").classList.remove("active");
  document.getElementById("editar-aluno").classList.add("active");
}

function fecharEdicao() {
  document.getElementById("editar-aluno").classList.remove("active");
  document.getElementById("ficha-aluno").classList.add("active");
}

async function salvarEdicaoAluno() {
  const al = alunoAtivo;
  if (!al) return;

  const btn = document.getElementById("btn-salvar-edicao");
  btn.disabled  = true;
  btn.innerHTML = '<i class="ti ti-loader" style="animation:spin 1s linear infinite" aria-hidden="true"></i> Salvando...';

  // Ler campos do formulário
  al.nome        = document.getElementById("ea-nome").value.trim();
  al.nasc        = document.getElementById("ea-nasc").value;
  al.instrumento = document.getElementById("ea-instrumento").value;
  al.horario     = document.getElementById("ea-horario").value;
  al.mensalidade = Number(document.getElementById("ea-mensalidade").value) || 0;
  al.inicio      = document.getElementById("ea-inicio").value;
  al.estagio     = document.getElementById("ea-estagio").value;
  al.teoria      = Number(document.getElementById("ea-teoria").value) || 0;
  al.metodo      = document.getElementById("ea-metodo").value;
  al.pagina      = document.getElementById("ea-pagina").value;
  al.hinos       = Number(document.getElementById("ea-hinos").value) || 0;
  al.listahinos  = document.getElementById("ea-listahinos").value || "—";
  al.status      = document.getElementById("ea-status").value;
  al.tecnica     = {
    m1:  document.getElementById("ea-tec-m1").value,
    m2:  document.getElementById("ea-tec-m2").value,
    ped: document.getElementById("ea-tec-ped").value,
  };
  al.culto = {
    jovens: document.getElementById("ea-culto-jovens").value,
    adulto: document.getElementById("ea-culto-adulto").value,
    ofic:   document.getElementById("ea-culto-ofic").value,
  };
  al.obs = document.getElementById("ea-obs").value;

  // Montar array para a linha (colunas A–T = 20 colunas)
  const row = [[
    al.id,            // A
    al.nome,          // B
    al.nasc,          // C
    al.instrumento,   // D
    al.horario,       // E
    al.mensalidade,   // F
    al.inicio,        // G
    al.estagio,       // H
    al.teoria,        // I
    al.metodo,        // J
    al.pagina,        // K
    al.hinos,         // L
    al.listahinos,    // M
    al.tecnica.m1,    // N
    al.tecnica.m2,    // O
    al.tecnica.ped,   // P
    al.culto.jovens,  // Q
    al.culto.adulto,  // R
    al.culto.ofic,    // S
    al.obs,           // T
  ]];

  const token  = graphToken || (await obterToken());
  const fileId = excelFileId || localStorage.getItem("mpm_file_id");

  if (token && fileId) {
    try {
      // linha Excel: id + 2 (linha 1=título, linha 2=cabeçalho, linha 3=id 1)
      const rowIdx = al.id + 2;
      await fetch(
        `${GRAPH_BASE}/me/drive/items/${fileId}/workbook/worksheets('Alunos')/range(address='A${rowIdx}:T${rowIdx}')`,
        {
          method:  "PATCH",
          headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
          body:    JSON.stringify({ values: row }),
        },
      );
      addLog("ok", `Aluno "${al.nome}" atualizado na planilha`);
      showToast("Aluno salvo com sucesso! ✓", "success");
    } catch (e) {
      addLog("err", "Erro ao salvar aluno: " + e.message);
      showToast("Erro ao salvar: " + e.message, "warn");
    }
  } else {
    addLog("warn", "Sem conexão — alterações salvas apenas localmente");
    showToast("Salvo localmente (sem sync)", "warn");
  }

  // Atualizar ficha com novos dados
  abrirFicha(al.id);
  fecharEdicao();

  btn.disabled  = false;
  btn.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Salvar alterações';
}

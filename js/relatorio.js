// ── RELATÓRIO PDF ─────────────────────────────────────────────────────────
function gerarRelatorio() {
  if (!alunoAtivo) return;
  const al = alunoAtivo;
  const aA = DB.aulas.filter((a) => a.aluno === al.nome).sort((a, b) => b.data.localeCompare(a.data));
  const pr = aA.filter((a) => a.presenca === "Presente").length, tot = aA.length;
  const w  = window.open("", "_blank");
  w.document.write(`<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Relatório — ${al.nome}</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"><style>body{font-family:'DM Sans',sans-serif;max-width:680px;margin:40px auto;color:#1A1A28;padding:0 24px}.header{border-bottom:3px solid #7F77DD;padding-bottom:16px;margin-bottom:24px}.title{font-size:24px;font-weight:800;color:#3C3489}.meta{font-size:13px;color:#6B6A80;margin-top:4px}.section{background:#F5F4FF;border-left:4px solid #7F77DD;padding:12px 16px;margin:16px 0;border-radius:0 10px 10px 0}.section h3{color:#3C3489;font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;margin-bottom:10px}.row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #E8E6F0;font-size:14px}.row:last-child{border-bottom:none}.b{display:inline-block;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:700}.bd{background:#E1F5EE;color:#085041}.bg{background:#FAEEDA;color:#633806}.bn{background:#F1EFE8;color:#888780}.footer{margin-top:40px;font-size:11px;color:#aaa;text-align:center;border-top:1px solid #eee;padding-top:16px}@media print{body{margin:20px}}</style></head><body>
    <div class="header"><div class="title">Relatório de Progresso</div><div class="meta">${al.nome} &nbsp;·&nbsp; ${al.instrumento} &nbsp;·&nbsp; Desde ${new Date(al.inicio).toLocaleDateString("pt-BR", { month: "long", year: "numeric" })}</div></div>
    <div class="section"><h3>Estágio e Progresso</h3>
      <div class="row"><span>Estágio atual</span><strong>${al.estagio}</strong></div>
      <div class="row"><span>Teoria musical</span><strong>${al.teoria}%</strong></div>
      <div class="row"><span>Método atual</span><strong>${al.metodo} — ${al.pagina}</strong></div>
      <div class="row"><span>Hinos dominados</span><strong>${al.hinos}</strong></div>
    </div>
    <div class="section"><h3>Técnica</h3>
      <div class="row"><span>1 mão</span><span class="b ${al.tecnica.m1 === "Concluído" ? "bd" : al.tecnica.m1 === "Em andamento" ? "bg" : "bn"}">${al.tecnica.m1}</span></div>
      <div class="row"><span>2 mãos</span><span class="b ${al.tecnica.m2 === "Concluído" ? "bd" : al.tecnica.m2 === "Em andamento" ? "bg" : "bn"}">${al.tecnica.m2}</span></div>
      <div class="row"><span>Pedaleira</span><span class="b ${al.tecnica.ped === "Concluído" ? "bd" : al.tecnica.ped === "Em andamento" ? "bg" : "bn"}">${al.tecnica.ped}</span></div>
    </div>
    <div class="section"><h3>Prontidão para Culto</h3>
      <div class="row"><span>Culto de jovens</span><span class="b ${al.culto.jovens === "Apto" ? "bd" : al.culto.jovens === "Em preparação" ? "bg" : "bn"}">${al.culto.jovens}</span></div>
      <div class="row"><span>Culto adulto</span><span class="b ${al.culto.adulto === "Apto" ? "bd" : al.culto.adulto === "Em preparação" ? "bg" : "bn"}">${al.culto.adulto}</span></div>
      <div class="row"><span>Oficialização</span><span class="b ${al.culto.ofic === "Apto" ? "bd" : al.culto.ofic === "Em preparação" ? "bg" : "bn"}">${al.culto.ofic}</span></div>
    </div>
    <div class="section"><h3>Frequência</h3>
      <div class="row"><span>Total de registros</span><strong>${tot}</strong></div>
      <div class="row"><span>Presenças</span><strong>${pr}</strong></div>
      <div class="row"><span>Taxa de frequência</span><strong>${tot > 0 ? Math.round((pr / tot) * 100) : 0}%</strong></div>
    </div>
    <div class="section"><h3>Hinos Trabalhados</h3><p style="font-size:14px;line-height:1.7">${al.listahinos}</p></div>
    <div class="section"><h3>Observações da Professora</h3><p style="font-size:14px;line-height:1.7">${al.obs}</p></div>
    <div class="footer">Gerado em ${new Date().toLocaleDateString("pt-BR", { day: "numeric", month: "long", year: "numeric" })} · Diário Pedagógico Musical</div>
    <script>window.print();<\/script></body></html>`);
  w.document.close();
}

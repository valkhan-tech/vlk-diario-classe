// Interações do mockup (nenhuma chama API; tudo é estático).
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

/* ---------- Paleta ---------- */
function definirPaleta(nome) {
  document.documentElement.dataset.paleta = nome;
  $$('.paleta-btn[data-paleta]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.paleta === nome)));
  try { localStorage.setItem('mock-paleta', nome); } catch { /* sem storage */ }
}
$$('.paleta-btn[data-paleta]').forEach((b) => b.addEventListener('click', () => definirPaleta(b.dataset.paleta)));
try { const salva = localStorage.getItem('mock-paleta'); if (salva) definirPaleta(salva); } catch { /* ignore */ }
const doHash = new URLSearchParams(location.search).get('paleta');
if (doHash) definirPaleta(doHash);

/* ---------- Navegação entre telas ---------- */
const NAV = { inicio: 'inicio', alunos: 'alunos', ficha: 'alunos', aulas: 'aulas', lancar: 'aulas', financeiro: 'financeiro', metodos: 'metodos', admin: 'admin', componentes: 'componentes' };
function irPara(tela) {
  document.body.dataset.tela = tela;
  $$('.nav-item').forEach((n) => n.removeAttribute('aria-current'));
  const alvo = $(`.nav-item[data-ir="${NAV[tela]}"]`);
  if (alvo) alvo.setAttribute('aria-current', 'page');
  fecharModais();
  $('#painel-notif')?.classList.remove('aberto');
  window.scrollTo({ top: 0 });
  const q = new URLSearchParams(location.search); q.set('tela', tela);
  history.replaceState(null, '', `?${q}`);
}
document.addEventListener('click', (e) => {
  const ir = e.target.closest('[data-ir]');
  if (ir) { e.preventDefault(); irPara(ir.dataset.ir); }
});
irPara(new URLSearchParams(location.search).get('tela') || 'inicio');

/* ---------- Abas ---------- */
$$('[data-abas]').forEach((grupo) => {
  const nome = grupo.dataset.abas;
  $$('.aba', grupo).forEach((aba) => aba.addEventListener('click', () => {
    $$('.aba', grupo).forEach((a) => a.setAttribute('aria-selected', String(a === aba)));
    $$(`[data-painel^="${nome}:"]`).forEach((p) => p.classList.toggle('ativo', p.dataset.painel === `${nome}:${aba.dataset.aba}`));
  }));
});

/* ---------- Controles segmentados ---------- */
$$('.segmentos').forEach((seg) => seg.addEventListener('click', (e) => {
  const b = e.target.closest('button'); if (!b) return;
  $$('button', seg).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
  if (seg.dataset.seg === 'vista') {
    $('#vista-tabela').style.display = b.dataset.vista === 'tabela' ? '' : 'none';
    $('#vista-cartoes').style.display = b.dataset.vista === 'cartoes' ? '' : 'none';
  }
}));

/* ---------- Modais ---------- */
function fecharModais() { $$('.fundo.aberto').forEach((m) => m.classList.remove('aberto')); }
document.addEventListener('click', (e) => {
  const abrir = e.target.closest('[data-abrir]');
  if (abrir) { $('#' + abrir.dataset.abrir).classList.add('aberto'); $('#' + abrir.dataset.abrir + ' button, #' + abrir.dataset.abrir + ' input')?.focus(); return; }
  if (e.target.closest('[data-fechar]') || e.target.classList.contains('fundo')) fecharModais();
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { fecharModais(); $('#painel-notif').classList.remove('aberto'); } });

/* Alerta de pagamento acima do saldo */
const SALDO = 180;
$('#p-valor').addEventListener('input', (e) => {
  const exc = Number(e.target.value) - SALDO;
  $('#p-alerta').style.display = exc > 0 ? 'flex' : 'none';
  $('#p-exc').textContent = exc.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
});

/* ---------- Notificações e toasts ---------- */
$('#btn-notif').addEventListener('click', (e) => {
  e.stopPropagation();
  const p = $('#painel-notif'); const aberto = p.classList.toggle('aberto');
  $('#btn-notif').setAttribute('aria-expanded', String(aberto));
});
document.addEventListener('click', (e) => { if (!e.target.closest('#painel-notif') && !e.target.closest('#btn-notif')) $('#painel-notif').classList.remove('aberto'); });
function toast(texto, tipo = 'ok') {
  const t = document.createElement('div');
  t.className = `toast ${tipo === 'ok' ? '' : tipo}`; t.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
  t.innerHTML = `<span>${texto}</span><button aria-label="Fechar">×</button>`;
  t.querySelector('button').onclick = () => t.remove();
  $('#toasts').appendChild(t); setTimeout(() => t.remove(), 4200);
}

/* ---------- Calendário (novembro/2026 começa num domingo) ---------- */
(function calendario() {
  const el = $('#calendario'); if (!el) return;
  const cores = ['var(--ok)', 'var(--ok)', 'var(--ok)', 'var(--n2)', 'var(--bad)', 'var(--info)', 'var(--n3)'];
  let html = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].map((d) => `<div class="cab-dia">${d}</div>`).join('');
  for (let d = 1; d <= 30; d++) {
    const dow = (d - 1) % 7; // 1º/11/2026 = domingo
    const qtd = dow === 0 ? 0 : 1 + ((d * 7) % 4);
    const pts = Array.from({ length: qtd }, (_, i) => `<i style="background:${cores[(d * 3 + i * 5) % cores.length]}"></i>`).join('');
    html += `<button class="dia${d === 17 ? ' hoje' : ''}" aria-label="Dia ${d}: ${qtd} aula(s)"><b>${d}</b><span class="pts">${pts}</span></button>`;
  }
  el.innerHTML = html;
})();

/* ---------- Gráfico de barras do financeiro (SVG) ---------- */
(function barras() {
  const g = $('#barras-fin'); if (!g) return;
  const dados = [[2400, 2150, 150], [2600, 2000, 520], [2600, 2050, 480], [2300, 1900, 150], [2300, 1750, 230], [2700, 1650, 540]];
  const y = (v) => 210 - (v / 2600) * 190;
  g.innerHTML = dados.map(([prev, rec, atr], i) => {
    const x = 70 + i * 85;
    const barra = (dx, v, cor) => `<rect x="${x + dx}" y="${y(v)}" width="16" height="${210 - y(v)}" rx="4" fill="var(${cor})"/>`;
    return barra(-2, prev, '--n6') + barra(16, rec, '--n4') + barra(34, atr, '--n1');
  }).join('');
})();

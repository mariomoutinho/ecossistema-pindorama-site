<?php
// ============================================================================
// Suindá — Curso Preparatório para o ENEM (área privada). /suinda/curso-enem/
// Hub do curso: visão geral, disciplinas, progresso, filtros e banco de questões.
// Gate visual client-side; dados protegidos no backend (matrícula obrigatória).
// ============================================================================
$suindaPageTitle = 'Preparatório para o ENEM — Suindá';
$suindaPageDesc  = 'Banco de questões do ENEM por áreas, disciplinas, competências e habilidades, com repetição espaçada.';
$suindaActiveNav = 'estudar';
require __DIR__ . '/../inc/header.php';
?>
<style>
  .enem { padding: 1.6rem 0 3rem; }
  .enem h1 { color: var(--primary-dark); margin: 0 0 .2rem; }
  .enem__sub { color: var(--text-soft); margin: 0 0 1.4rem; max-width: 70ch; }
  .enem-stats { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); margin-bottom: 1.4rem; }
  .enem-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: 1.6rem; }
  .enem-disc { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
  .enem-disc a { text-decoration: none; }
  .enem-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem 1.1rem; box-shadow: var(--shadow-sm); }
  .enem-card strong { color: var(--primary-dark); display: block; }
  .enem-card span { color: var(--text-soft); font-size: .9rem; }
  .filter-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.1rem 1.2rem; box-shadow: var(--shadow-sm); margin: 1.4rem 0; }
  .filter-grid { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); align-items: end; }
  .filter-grid label { display: block; font-size: .85rem; font-weight: 700; margin-bottom: .3rem; }
  .filter-grid select { width: 100%; padding: .55rem .6rem; border: 1.5px solid var(--border); border-radius: 10px; font: inherit; background: #fff; min-height: 42px; }
  table.qbank { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: .92rem; }
  table.qbank th, table.qbank td { text-align: left; padding: .45rem .6rem; border-bottom: 1px solid var(--border); }
  table.qbank th { color: var(--text-soft); font-size: .78rem; text-transform: uppercase; }
  .tagchip { display: inline-block; font-size: .74rem; padding: .1rem .45rem; border-radius: 999px; background: var(--primary-soft); color: var(--primary); }
  .tagchip--anulada { background: #f6e3e0; color: #8a2f2f; }
</style>

<main id="conteudo" class="enem">
  <div class="container">
    <div id="enemLoading" class="loading">Carregando o curso…</div>
    <div id="enemDenied" class="restrito" hidden style="text-align:center;padding:3rem 1rem">
      <div style="font-size:2.6rem">🔒</div>
      <h2>Curso ainda não liberado</h2>
      <p>O Preparatório para o ENEM ainda não está liberado para a sua conta. Em caso de dúvida, fale com o Coletivo Pindorama.</p>
      <p><a class="btn btn--primary" href="/suinda/estudar/">Voltar ao painel</a></p>
    </div>
    <div id="enemRoot" hidden></div>
  </div>
</main>

<script>
(function () {
  var API = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var LOGIN = "/suinda/login/";
  var STUDY = "/suinda/curso-enem/estudar/";
  var token = localStorage.getItem("suinda_api_token");
  if (!token) { window.location.replace(LOGIN); return; }

  var loading = document.getElementById("enemLoading");
  var denied = document.getElementById("enemDenied");
  var root = document.getElementById("enemRoot");
  var taxonomy = {};
  var isAdmin = false;

  async function api(path) {
    var res = await fetch(API + path, { headers: { "Authorization": "Bearer " + token } });
    if (res.status === 401) { localStorage.removeItem("suinda_api_token"); window.location.replace(LOGIN); throw new Error("401"); }
    if (!res.ok) { var e = new Error("erro " + res.status); e.status = res.status; throw e; }
    return res.json();
  }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
  function studyUrl(params) { return STUDY + "?" + Object.keys(params).filter(function (k) { return params[k]; }).map(function (k) { return k + "=" + encodeURIComponent(params[k]); }).join("&"); }

  function statBox(v, label) { return '<div class="stat"><strong>' + v + '</strong><span>' + esc(label) + '</span></div>'; }

  function render(ov) {
    loading.hidden = true; root.hidden = false;
    var t = ov.totals;
    var html = '';
    html += '<h1>Preparatório para o ENEM</h1>';
    html += '<p class="enem__sub">' + esc(ov.course && ov.course.description ? ov.course.description : "Banco de questões do ENEM com repetição espaçada.") + '</p>';

    html += '<div class="enem-stats">'
      + statBox(t.questions, "Questões disponíveis")
      + statBox(t.newCards, "Novas")
      + statBox(t.dueCards, "Revisões pendentes")
      + statBox(t.answered, "Respondidas")
      + statBox(t.accuracy + "%", "Taxa de acertos")
      + statBox(t.annulled, "Anuladas (arquivo)")
      + '</div>';

    html += '<div class="enem-actions">'
      + '<a class="btn btn--accent btn--lg" href="' + studyUrl({ filter: "vencidas" }) + '">Continuar estudando</a>'
      + '<a class="btn btn--primary" href="' + studyUrl({ filter: "novas" }) + '">Estudar questões novas</a>'
      + '<a class="btn btn--ghost" href="' + studyUrl({ filter: "vencidas" }) + '">Revisar pendentes</a>'
      + '<a class="btn btn--ghost" href="' + studyUrl({ random: 1 }) + '">Simulado aleatório</a>'
      + (isAdmin ? '<a class="btn btn-mini" style="border-color:var(--accent-dark);color:var(--accent-dark)" href="/suinda/admin/questoes/">⚙ Editar questões (admin)</a>' : '')
      + '</div>';

    html += '<h2 class="section__head">Disciplinas</h2><div class="enem-disc">';
    (ov.byDiscipline || []).forEach(function (d) {
      var slug = (taxonomy.disciplines || []).find(function (x) { return x.name === d.discipline; });
      slug = slug ? slug.slug : "";
      html += '<a href="' + studyUrl({ discipline: slug }) + '"><div class="enem-card"><strong>' + esc(d.discipline) + '</strong><span>' + d.total + ' questões</span></div></a>';
    });
    html += '</div>';

    // Filtros + banco de questões
    html += '<div class="filter-panel"><h2 style="margin:0 0 .2rem">Banco de questões</h2>'
      + '<p class="hint" style="color:var(--text-soft);margin:0 0 .9rem">Filtre por disciplina, conteúdo, competência, habilidade ou status. Anuladas ficam no arquivo (sem contar acerto/erro).</p>'
      + '<div class="filter-grid">'
      + '<div><label>Disciplina</label><select id="fDiscipline"><option value="">Todas</option></select></div>'
      + '<div><label>Conteúdo</label><select id="fContent"><option value="">Todos</option></select></div>'
      + '<div><label>Competência</label><select id="fCompetency"><option value="">Todas</option></select></div>'
      + '<div><label>Habilidade</label><select id="fSkill"><option value="">Todas</option></select></div>'
      + '<div><label>Status / filtro</label><select id="fFilter">'
        + '<option value="">Todas ativas</option><option value="novas">Não estudadas</option><option value="vencidas">Revisão vencida</option><option value="erradas">Já erradas</option><option value="anulada">Anuladas (arquivo)</option>'
        + '</select></div>'
      + '<div class="actions" style="display:flex;gap:.4rem"><button class="btn btn--primary btn-mini" id="btnFilter" type="button">Buscar</button><a class="btn btn-mini" id="btnStudySel" href="#">Estudar seleção</a></div>'
      + '</div>'
      + '<div id="qbankResult"></div></div>';

    root.innerHTML = html;
    fillFilters();
    bind();
    loadBank();
  }

  function fillOptions(sel, items, valueKey, labelFn) {
    var el = document.getElementById(sel);
    items.forEach(function (it) { var o = document.createElement("option"); o.value = it[valueKey]; o.textContent = labelFn(it); el.appendChild(o); });
  }
  function fillFilters() {
    fillOptions("fDiscipline", taxonomy.disciplines || [], "slug", function (d) { return d.name; });
    fillOptions("fContent", taxonomy.contents || [], "id", function (c) { return c.name; });
    fillOptions("fCompetency", taxonomy.competencies || [], "code", function (c) { return c.code + " — " + c.statement.slice(0, 50) + "…"; });
    fillOptions("fSkill", taxonomy.skills || [], "code", function (s) { return s.code + " — " + s.statement.slice(0, 50) + "…"; });
  }
  function currentFilters() {
    var f = {
      discipline: val("fDiscipline"), content: val("fContent"),
      competency: val("fCompetency"), skill: val("fSkill"),
    };
    var sel = val("fFilter");
    if (sel === "anulada") { f.status = "anulada"; }
    else if (sel) { f.filter = sel; }
    return f;
  }
  function val(id) { return document.getElementById(id).value; }

  async function loadBank() {
    var f = currentFilters();
    f.limit = 200;
    var qs = Object.keys(f).filter(function (k) { return f[k]; }).map(function (k) { return k + "=" + encodeURIComponent(f[k]); }).join("&");
    var box = document.getElementById("qbankResult");
    box.innerHTML = '<p class="loading">Buscando…</p>';
    try {
      var d = await api("/enem/questions?" + qs);
      if (!d.count) { box.innerHTML = '<p style="color:var(--text-soft)">Nenhuma questão para este filtro.</p>'; return; }
      var rows = d.questions.map(function (q) {
        return '<tr><td><a href="' + STUDY + '?ids=' + q.id + '">Q' + q.number + '</a></td><td>' + esc(q.discipline || "—")
          + '</td><td>' + esc(q.content || "—") + '</td><td>' + (q.competency || "—") + ' / ' + (q.skill || "—")
          + '</td><td>' + (q.status === "anulada" ? '<span class="tagchip tagchip--anulada">anulada</span>' : '<span class="tagchip">' + q.attempts + ' tent.</span>') + '</td></tr>';
      }).join("");
      box.innerHTML = '<p style="color:var(--text-soft);margin:.6rem 0 0">' + d.count + ' questão(ões).</p>'
        + '<table class="qbank"><thead><tr><th>Questão</th><th>Disciplina</th><th>Conteúdo</th><th>Comp/Hab</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody></table>';
      // "Estudar seleção" usa o filtro atual
      document.getElementById("btnStudySel").setAttribute("href", studyUrl(currentFilters()));
    } catch (e) { box.innerHTML = '<p style="color:var(--danger)">Falha ao buscar questões.</p>'; }
  }

  function bind() {
    document.getElementById("btnFilter").addEventListener("click", loadBank);
    document.getElementById("btnStudySel").addEventListener("click", function (ev) {
      ev.preventDefault(); window.location.href = studyUrl(currentFilters());
    });
  }

  Promise.all([api("/enem/overview"), api("/enem/taxonomy"), api("/me").catch(function () { return { user: {} }; })]).then(function (res) {
    var ov = res[0]; taxonomy = res[1];
    isAdmin = ((res[2] && res[2].user ? res[2].user.role : "") === "admin");
    if (!ov.hasContent) { loading.hidden = true; denied.hidden = false; return; }
    render(ov);
  }).catch(function (e) {
    if (e && e.status === 401) return;
    if (e && e.status === 403) { loading.hidden = true; denied.hidden = false; return; }
    loading.textContent = "Não foi possível carregar o curso. Tente recarregar.";
  });
})();
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>

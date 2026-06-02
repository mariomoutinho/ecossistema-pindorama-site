<?php
// ============================================================================
// Suindá — Admin · Banco de questões ENEM. /suinda/admin/questoes/
// Lista/filtra questões (relatório), edita (gabarito, status, comentário,
// classificação, alternativas) e gerencia imagens (upload manual, principal,
// remover, alt). Gate de admin client-side; regra validada no backend.
// ============================================================================
$suindaPageTitle = 'Banco de questões — Admin Suindá';
$suindaPageDesc  = 'Edição de questões ENEM e gestão de imagens.';
$suindaActiveNav = 'admin';
require __DIR__ . '/../../inc/header.php';
?>
<style>
  .qadm { padding: 1.4rem 0 3rem; }
  .qadm h1 { color: var(--primary-dark); margin: 0 0 1rem; }
  .report { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); margin-bottom: 1rem; }
  .report .stat { cursor: pointer; }
  .report .stat.active { outline: 2px solid var(--accent); }
  .qadm-grid { display: grid; gap: 1.1rem; grid-template-columns: minmax(260px, 360px) 1fr; align-items: start; }
  @media (max-width: 820px) { .qadm-grid { grid-template-columns: 1fr; } }
  .qlist { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); max-height: 70vh; overflow: auto; }
  .qlist__filter { display: flex; gap: .4rem; padding: .7rem; border-bottom: 1px solid var(--border); }
  .qlist__filter input { flex: 1; padding: .5rem .6rem; border: 1.5px solid var(--border); border-radius: 8px; }
  .qrow { display: flex; gap: .5rem; align-items: center; padding: .55rem .8rem; border-bottom: 1px solid var(--border); cursor: pointer; font-size: .9rem; }
  .qrow:hover, .qrow.active { background: var(--bg-deep); }
  .qrow b { color: var(--primary); min-width: 2.6rem; }
  .qrow .flags { margin-left: auto; display: flex; gap: .25rem; }
  .flag { font-size: .68rem; padding: .05rem .35rem; border-radius: 999px; }
  .flag--noimg { background: #f6e3e0; color: #8a2f2f; }
  .flag--nocom { background: #f2e7cf; color: #8a6a1c; }
  .flag--rev { background: var(--primary-soft); color: var(--primary); }
  .flag--anu { background: #e7dcf2; color: #6b3fa0; }
  .editor { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 1.2rem; }
  .editor.empty { color: var(--text-soft); text-align: center; padding: 3rem 1rem; }
  .editor label { display: block; font-weight: 700; font-size: .85rem; margin: .7rem 0 .25rem; }
  .editor input, .editor select, .editor textarea { width: 100%; padding: .55rem .65rem; border: 1.5px solid var(--border); border-radius: 9px; font: inherit; background: #fff; }
  .editor textarea { min-height: 70px; resize: vertical; }
  .ed-row { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
  .alt-edit { display: flex; gap: .5rem; align-items: center; margin: .3rem 0; }
  .alt-edit input[type=radio] { width: auto; flex: none; }
  .alt-edit .letter { font-weight: 800; color: var(--primary); }
  .imgs { display: flex; gap: .6rem; flex-wrap: wrap; margin: .5rem 0; }
  .imgcard { border: 1.5px solid var(--border); border-radius: 10px; padding: .4rem; width: 150px; }
  .imgcard.primary { border-color: var(--ok); }
  .imgcard img { width: 100%; border-radius: 6px; cursor: zoom-in; }
  .imgcard__acts { display: flex; gap: .3rem; margin-top: .3rem; flex-wrap: wrap; }
  .ed-actions { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1rem; }
  .admin-msg { min-height: 1.2em; font-size: .9rem; }
  .admin-msg--ok { color: var(--ok); } .admin-msg--error { color: var(--danger); }
  .zoom-modal { position: fixed; inset: 0; background: rgba(10,20,18,.86); display: none; align-items: center; justify-content: center; z-index: 100; padding: 1rem; }
  .zoom-modal.open { display: flex; } .zoom-modal img { max-width: 100%; max-height: 92vh; border-radius: 10px; }
  .zoom-modal__close { position: absolute; top: 14px; right: 18px; width: 42px; height: 42px; border: none; border-radius: 50%; background: #fff; font-size: 1.2rem; cursor: pointer; }
</style>

<main id="conteudo" class="qadm">
  <div class="container">
    <div id="loading" class="loading">Carregando…</div>
    <div id="denied" hidden style="text-align:center;padding:3rem"><div style="font-size:2.6rem">🔒</div><h2>Área restrita</h2><p><a class="btn btn--primary" href="/suinda/estudar/">Painel</a></p></div>
    <div id="root" hidden>
      <h1>Banco de questões — ENEM</h1>
      <div class="report" id="report"></div>
      <div class="qadm-grid">
        <div class="qlist">
          <div class="qlist__filter"><input id="search" placeholder="Buscar nº ou texto…"><button class="btn btn-mini" id="searchBtn" type="button">Buscar</button></div>
          <div id="qlist"></div>
        </div>
        <div class="editor empty" id="editor">Selecione uma questão à esquerda para editar.</div>
      </div>
      <p style="margin-top:1rem"><a class="btn btn--ghost" href="/suinda/admin/">← Voltar à administração</a></p>
    </div>
  </div>
</main>

<script>
(function () {
  var API = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var LOGIN = "/suinda/login/";
  var token = localStorage.getItem("suinda_api_token");
  if (!token) { window.location.replace(LOGIN); return; }
  var loading = document.getElementById("loading"), denied = document.getElementById("denied"), root = document.getElementById("root");
  var tax = {}, currentFilter = "", currentId = null;

  function authH(extra) { return Object.assign({ "Authorization": "Bearer " + token }, extra || {}); }
  async function api(path, opts) {
    opts = opts || {};
    var res = await fetch(API + path, { method: opts.method || "GET", headers: authH(opts.json ? { "Content-Type": "application/json" } : {}), body: opts.json ? JSON.stringify(opts.json) : opts.body });
    if (res.status === 401) { localStorage.removeItem("suinda_api_token"); window.location.replace(LOGIN); throw new Error("401"); }
    var j = {}; try { j = await res.json(); } catch (e) {}
    if (!res.ok) { var er = new Error(j.error || ("erro " + res.status)); er.status = res.status; throw er; }
    return j;
  }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
  function openZoom(src) {
    var m = document.getElementById("zoomModal");
    if (!m) { m = document.createElement("div"); m.id = "zoomModal"; m.className = "zoom-modal"; m.innerHTML = '<button class="zoom-modal__close" type="button">✕</button><img>'; m.addEventListener("click", function (e) { if (e.target === m || e.target.classList.contains("zoom-modal__close")) m.classList.remove("open"); }); document.body.appendChild(m); }
    m.querySelector("img").src = src; m.classList.add("open");
  }

  function renderReport(s) {
    var tiles = [
      ["", "Total", s.total], ["sem_imagem", "Sem imagem", s.semImagem], ["sem_comentario", "Sem comentário", s.semComentario],
      ["pendente_revisao", "Pendente revisão", s.pendenteRevisao], ["anuladas", "Anuladas", s.anuladas], ["sem_classificacao", "Sem classificação", s.semClassificacao]
    ];
    document.getElementById("report").innerHTML = tiles.map(function (t) {
      return '<div class="stat' + (currentFilter === t[0] ? ' active' : '') + '" data-filter="' + t[0] + '"><strong>' + t[2] + '</strong><span>' + t[1] + '</span></div>';
    }).join("");
    document.querySelectorAll("#report .stat").forEach(function (el) { el.addEventListener("click", function () { currentFilter = el.getAttribute("data-filter"); loadList(); }); });
  }

  async function loadList() {
    var q = document.getElementById("search").value.trim();
    var params = [];
    if (currentFilter) params.push("filter=" + currentFilter);
    if (q) params.push("q=" + encodeURIComponent(q));
    params.push("limit=500");
    var d = await api("/admin/questions?" + params.join("&"));
    renderReport(d.summary);
    document.getElementById("qlist").innerHTML = d.questions.map(function (x) {
      var flags = "";
      if (!x.images) flags += '<span class="flag flag--noimg">img</span>';
      if (!x.hasComment) flags += '<span class="flag flag--nocom">com</span>';
      if (x.reviewNeeded) flags += '<span class="flag flag--rev">rev</span>';
      if (x.status === "anulada") flags += '<span class="flag flag--anu">anul</span>';
      return '<div class="qrow' + (x.id === currentId ? ' active' : '') + '" data-id="' + x.id + '"><b>Q' + x.number + '</b><span>' + esc(x.discipline || "—") + '</span><span class="flags">' + flags + '</span></div>';
    }).join("") || '<p style="padding:1rem;color:var(--text-soft)">Nenhuma questão.</p>';
    document.querySelectorAll(".qrow").forEach(function (el) { el.addEventListener("click", function () { openEditor(parseInt(el.getAttribute("data-id"), 10)); }); });
  }

  function opt(items, val, valueKey, labelFn) {
    return '<option value="">—</option>' + items.map(function (it) {
      return '<option value="' + it[valueKey] + '"' + (String(it[valueKey]) === String(val) ? " selected" : "") + '>' + esc(labelFn(it)) + '</option>';
    }).join("");
  }

  async function openEditor(id) {
    currentId = id;
    document.querySelectorAll(".qrow").forEach(function (e) { e.classList.toggle("active", parseInt(e.getAttribute("data-id"), 10) === id); });
    var ed = document.getElementById("editor");
    ed.className = "editor"; ed.innerHTML = '<p class="loading">Carregando questão…</p>';
    var d = (await api("/admin/questions/" + id)).question;

    var alts = ["A", "B", "C", "D", "E"].map(function (L) {
      var a = (d.alternatives || []).find(function (x) { return x.letter === L; }) || { letter: L, body: "" };
      return '<div class="alt-edit"><input type="radio" name="correct" value="' + L + '"' + (d.correctAlternative === L ? " checked" : "") + '>'
        + '<span class="letter">' + L + ')</span><input data-alt="' + L + '" value="' + esc(a.body) + '"></div>';
    }).join("");

    var imgs = (d.images || []).map(function (im) {
      return '<div class="imgcard' + (im.isPrimary ? ' primary' : '') + '" data-img="' + im.id + '">'
        + '<img src="' + esc(im.path) + '" alt="" data-zoom="' + esc(im.path) + '">'
        + '<input data-alttext="' + im.id + '" placeholder="texto alternativo" value="' + esc(im.altText || "") + '">'
        + '<div class="imgcard__acts">'
        + (im.isPrimary ? '<span class="flag flag--rev">principal</span>' : '<button class="btn btn-mini" data-primary="' + im.id + '" type="button">Tornar principal</button>')
        + '<button class="btn btn-mini btn-danger" data-delimg="' + im.id + '" type="button">Excluir</button>'
        + '</div></div>';
    }).join("") || '<p style="color:var(--text-soft)">Sem imagens. Faça upload abaixo (a transcrição é usada enquanto não houver imagem).</p>';

    ed.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">'
        + '<h3 style="margin:0;color:var(--primary-dark)">' + esc(d.exam || "ENEM") + ' · Q' + d.number + '</h3>'
        + '<a class="btn btn-mini btn--ghost" target="_blank" href="/suinda/curso-enem/estudar/?ids=' + d.id + '">Visualizar como estudante ↗</a></div>'
      + '<div class="ed-row">'
        + '<div><label>Status</label><select id="f_status">' + ["ativa", "anulada", "pendente_revisao", "revisada", "arquivada"].map(function (s) { return '<option' + (d.status === s ? " selected" : "") + '>' + s + '</option>'; }).join("") + '</select></div>'
        + '<div><label>Confiança</label><select id="f_conf">' + ["alta", "media", "baixa"].map(function (s) { return '<option' + (d.confidence === s ? " selected" : "") + '>' + s + '</option>'; }).join("") + '</select></div>'
        + '<div><label>Revisão pendente</label><select id="f_rev"><option value="1"' + (d.reviewNeeded ? " selected" : "") + '>Sim</option><option value="0"' + (!d.reviewNeeded ? " selected" : "") + '>Não</option></select></div>'
      + '</div>'
      + '<div class="ed-row">'
        + '<div><label>Disciplina</label><select id="f_disc">' + opt(tax.disciplines || [], d.disciplineId, "id", function (x) { return x.name; }) + '</select></div>'
        + '<div><label>Conteúdo</label><select id="f_content">' + opt(tax.contents || [], d.contentId, "id", function (x) { return x.name; }) + '</select></div>'
        + '<div><label>Competência</label><select id="f_comp">' + opt(tax.competencies || [], d.competencyId, "id", function (x) { return x.code; }) + '</select></div>'
        + '<div><label>Habilidade</label><select id="f_skill">' + opt(tax.skills || [], d.skillId, "id", function (x) { return x.code; }) + '</select></div>'
      + '</div>'
      + '<label>Alternativa correta + textos (selecione a correta)</label>' + alts
      + '<label>Enunciado / transcrição</label><textarea id="f_stmt">' + esc(d.statement || "") + '</textarea>'
      + '<label>Comentário / explicação</label><textarea id="f_expl">' + esc(d.explanation || "") + '</textarea>'
      + '<label>Observação interna (admin)</label><textarea id="f_notes">' + esc(d.notes || "") + '</textarea>'
      + '<label>Imagens da questão</label><div class="imgs">' + imgs + '</div>'
      + '<input type="file" id="f_file" accept=".jpg,.jpeg,.png,.webp"><button class="btn btn-mini" id="uploadBtn" type="button" style="margin-top:.4rem">Enviar imagem</button>'
      + '<div class="ed-actions"><button class="btn btn--primary" id="saveBtn" type="button">Salvar questão</button><span id="edMsg" class="admin-msg"></span></div>';

    bindEditor(d);
  }

  function bindEditor(d) {
    document.querySelectorAll("#editor [data-zoom]").forEach(function (img) { img.addEventListener("click", function () { openZoom(img.getAttribute("data-zoom")); }); });
    document.getElementById("saveBtn").addEventListener("click", async function () {
      var alts = {};
      document.querySelectorAll("#editor [data-alt]").forEach(function (i) { alts[i.getAttribute("data-alt")] = i.value; });
      var correct = (document.querySelector('#editor input[name="correct"]:checked') || {}).value || null;
      var body = {
        status: document.getElementById("f_status").value,
        correctAlternative: correct,
        confidence: document.getElementById("f_conf").value,
        reviewNeeded: document.getElementById("f_rev").value === "1",
        disciplineId: document.getElementById("f_disc").value || null,
        contentId: document.getElementById("f_content").value || null,
        competencyId: document.getElementById("f_comp").value || null,
        skillId: document.getElementById("f_skill").value || null,
        statement: document.getElementById("f_stmt").value,
        explanation: document.getElementById("f_expl").value,
        notes: document.getElementById("f_notes").value,
        alternatives: alts
      };
      msg("Salvando…");
      try { await api("/admin/questions/" + d.id, { method: "PUT", json: body }); msg("Salvo ✓", true); loadList(); }
      catch (e) { msg(e.message || "Falha ao salvar", false); }
    });
    document.getElementById("uploadBtn").addEventListener("click", async function () {
      var f = document.getElementById("f_file").files[0];
      if (!f) { msg("Escolha um arquivo de imagem.", false); return; }
      var fd = new FormData(); fd.append("file", f);
      msg("Enviando imagem…");
      try { await api("/admin/questions/" + d.id + "/images", { method: "POST", body: fd }); msg("Imagem enviada ✓", true); openEditor(d.id); }
      catch (e) { msg(e.message || "Falha no upload", false); }
    });
    document.querySelectorAll("#editor [data-primary]").forEach(function (b) { b.addEventListener("click", async function () { await api("/admin/question-images/" + b.getAttribute("data-primary"), { method: "PUT", json: { isPrimary: true } }); openEditor(d.id); }); });
    document.querySelectorAll("#editor [data-delimg]").forEach(function (b) { b.addEventListener("click", async function () { if (confirm("Excluir esta imagem?")) { await api("/admin/question-images/" + b.getAttribute("data-delimg"), { method: "DELETE" }); openEditor(d.id); loadList(); } }); });
    document.querySelectorAll("#editor [data-alttext]").forEach(function (i) { i.addEventListener("change", function () { api("/admin/question-images/" + i.getAttribute("data-alttext"), { method: "PUT", json: { altText: i.value } }); }); });
  }
  function msg(t, ok) { var m = document.getElementById("edMsg"); if (m) { m.textContent = t; m.className = "admin-msg " + (ok === undefined ? "" : ok ? "admin-msg--ok" : "admin-msg--error"); } }

  document.getElementById("searchBtn").addEventListener("click", loadList);
  document.getElementById("search").addEventListener("keydown", function (e) { if (e.key === "Enter") loadList(); });

  api("/me").then(function (r) {
    if ((r.user || {}).role !== "admin") { loading.hidden = true; denied.hidden = false; return; }
    return Promise.all([api("/enem/taxonomy"), Promise.resolve()]).then(function (res) {
      tax = res[0]; loading.hidden = true; root.hidden = false; loadList();
    });
  }).catch(function (e) { if (e && e.status === 401) return; loading.textContent = "Não foi possível carregar."; });
})();
</script>

<?php require __DIR__ . '/../../inc/footer.php'; ?>

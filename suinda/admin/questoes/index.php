<?php
// ============================================================================
// Suindá — Admin · Banco de questões ENEM. /suinda/admin/questoes/
// Editor estilo Anki: frente e verso ricos (contenteditable), colar imagem com
// Ctrl+V, upload tradicional, arrastar/soltar, anexos com indicação de uso,
// auto-crescimento sem rolagem interna, sincronização da alternativa correta no
// verso. Gate de admin client-side; regra real validada no backend (requireAdmin).
// ============================================================================
$suindaPageTitle = 'Banco de questões — Admin Suindá';
$suindaPageDesc  = 'Edição de questões ENEM e gestão de imagens.';
$suindaActiveNav = 'admin';
require __DIR__ . '/../../inc/header.php';
?>
<style>
  .qadm { padding: 1.4rem 0 4rem; }
  .qadm h1 { color: var(--primary-dark); margin: 0 0 1rem; }
  .report { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); margin-bottom: 1rem; }
  .report .stat { cursor: pointer; }
  .report .stat.active { outline: 2px solid var(--accent); }
  .qadm-grid { display: grid; gap: 1.1rem; grid-template-columns: minmax(240px, 320px) 1fr; align-items: start; }
  @media (max-width: 900px) { .qadm-grid { grid-template-columns: 1fr; } .qlist { max-height: 40vh; } }
  .qlist { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); max-height: 78vh; overflow: auto; position: sticky; top: 1rem; }
  .qlist__filter { display: flex; gap: .4rem; padding: .7rem; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--surface); z-index: 2; }
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
  /* Sem rolagem interna: textareas crescem com o conteúdo. */
  .editor textarea { min-height: 90px; height: auto; overflow-y: hidden; resize: vertical; }
  .ed-row { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
  .alt-edit { display: flex; gap: .5rem; align-items: center; margin: .3rem 0; }
  .alt-edit input[type=radio] { width: auto; flex: none; }
  .alt-edit .letter { font-weight: 800; color: var(--primary); }

  /* Cartões frente/verso destacados */
  .cardside { border: 2px solid var(--border); border-radius: 14px; padding: .2rem .7rem .8rem; margin: .9rem 0; }
  .cardside--front { border-color: var(--primary-soft); background: #fbfdfb; }
  .cardside--back { border-color: #e7dcf2; background: #fbf9fe; }
  .cardside__title { font-weight: 800; color: var(--primary-dark); margin: .6rem 0 .1rem; font-size: .95rem; }
  .cardside__hint { color: var(--text-soft); font-size: .8rem; margin: 0 0 .4rem; }
  .rte-toolbar { display: flex; flex-wrap: wrap; gap: .3rem; margin: .3rem 0; }
  .rte-toolbar button { border: 1.5px solid var(--border); background: #fff; border-radius: 7px; padding: .25rem .5rem; cursor: pointer; font-size: .85rem; line-height: 1; }
  .rte-toolbar button:hover { border-color: var(--accent); }
  /* Conteúdo todo exposto: cresce com o texto, sem rolagem interna. */
  .rte { min-height: 140px; height: auto; overflow-y: visible; border: 1.5px solid var(--border); border-radius: 9px; background: #fff; padding: .6rem .7rem; font: inherit; }
  .rte:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
  .rte:empty::before { content: attr(data-ph); color: var(--text-soft); }
  .rte img { max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--border); margin: .3rem 0; cursor: zoom-in; }
  .rte p { margin: .4rem 0; }
  .rte.dragover { outline: 2px dashed var(--accent); }
  .up-ph { display: inline-block; background: var(--bg-deep); border-radius: 6px; padding: .1rem .45rem; font-size: .8rem; color: var(--text-soft); }
  .sync-warn { color: #8a6a1c; background: #f7efd8; border-radius: 8px; padding: .35rem .6rem; font-size: .82rem; margin: .3rem 0 0; }

  .imgs { display: flex; gap: .6rem; flex-wrap: wrap; margin: .5rem 0; }
  .imgcard { border: 1.5px solid var(--border); border-radius: 10px; padding: .4rem; width: 160px; }
  .imgcard.primary { border-color: var(--ok); }
  .imgcard img { width: 100%; border-radius: 6px; cursor: zoom-in; }
  .imgcard__use { font-size: .68rem; padding: .05rem .4rem; border-radius: 999px; background: var(--bg-deep); color: var(--text-soft); display: inline-block; margin: .25rem 0; }
  .imgcard__use--front { background: var(--primary-soft); color: var(--primary); }
  .imgcard__use--back { background: #e7dcf2; color: #6b3fa0; }
  .imgcard__use--unused { background: #f6e3e0; color: #8a2f2f; }
  .imgcard__acts { display: flex; gap: .3rem; margin-top: .3rem; flex-wrap: wrap; }

  .ed-actions { display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; margin-top: 1rem; padding-top: .8rem; border-top: 1px solid var(--border); position: sticky; bottom: 0; background: var(--surface); }
  .admin-msg { min-height: 1.2em; font-size: .9rem; }
  .admin-msg--ok { color: var(--ok); } .admin-msg--error { color: var(--danger); }
  .dirty-dot { font-size: .85rem; font-weight: 700; }
  .dirty-dot.dirty { color: #b06a00; } .dirty-dot.clean { color: var(--text-soft); }

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
<input type="file" id="imgPicker" accept=".jpg,.jpeg,.png,.webp,.gif" hidden>

<script>
(function () {
  var API = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var LOGIN = "/suinda/login/";
  var token = localStorage.getItem("suinda_api_token");
  if (!token) { window.location.replace(LOGIN); return; }
  var loading = document.getElementById("loading"), denied = document.getElementById("denied"), root = document.getElementById("root");
  var tax = {}, currentFilter = "", currentId = null, listIds = [], dirty = false, phSeq = 0;
  var pickerTarget = null, pickerUsage = "attachment"; // alvo do seletor de arquivo

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
  function msg(t, ok) { var m = document.getElementById("edMsg"); if (m) { m.textContent = t; m.className = "admin-msg " + (ok === undefined ? "" : ok ? "admin-msg--ok" : "admin-msg--error"); } }
  function markDirty() { dirty = true; var d = document.getElementById("dirtyDot"); if (d) { d.textContent = "● Alterações não salvas"; d.className = "dirty-dot dirty"; } }
  function markClean() { dirty = false; var d = document.getElementById("dirtyDot"); if (d) { d.textContent = "✓ Tudo salvo"; d.className = "dirty-dot clean"; } }

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
    listIds = d.questions.map(function (x) { return x.id; });
    document.getElementById("qlist").innerHTML = d.questions.map(function (x) {
      var flags = "";
      if (!x.images) flags += '<span class="flag flag--noimg">img</span>';
      if (!x.hasComment) flags += '<span class="flag flag--nocom">com</span>';
      if (x.reviewNeeded) flags += '<span class="flag flag--rev">rev</span>';
      if (x.status === "anulada") flags += '<span class="flag flag--anu">anul</span>';
      return '<div class="qrow' + (x.id === currentId ? ' active' : '') + '" data-id="' + x.id + '"><b>Q' + x.number + '</b><span>' + esc(x.discipline || "—") + '</span><span class="flags">' + flags + '</span></div>';
    }).join("") || '<p style="padding:1rem;color:var(--text-soft)">Nenhuma questão.</p>';
    document.querySelectorAll(".qrow").forEach(function (el) { el.addEventListener("click", function () { tryOpen(parseInt(el.getAttribute("data-id"), 10)); }); });
  }

  function tryOpen(id) {
    if (dirty && !confirm("Há alterações não salvas nesta questão. Descartar e abrir outra?")) return;
    openEditor(id);
  }

  function opt(items, val, valueKey, labelFn) {
    return '<option value="">—</option>' + items.map(function (it) {
      return '<option value="' + it[valueKey] + '"' + (String(it[valueKey]) === String(val) ? " selected" : "") + '>' + esc(labelFn(it)) + '</option>';
    }).join("");
  }

  // ---- helpers de seeding da frente/verso ----
  function textToHtml(txt) {
    return String(txt || "").split(/\n{2,}/).map(function (para) {
      return "<p>" + esc(para).replace(/\n/g, "<br>") + "</p>";
    }).join("") || "<p></p>";
  }
  function seedFront(d) {
    if (d.frontHtml && d.frontHtml.trim()) return d.frontHtml;
    var imgs = (d.images || []).filter(function (im) { return im.usageType !== "back"; })
      .map(function (im) { return '<p><img src="' + esc(im.path) + '" alt="' + esc(im.altText || "") + '"></p>'; }).join("");
    return imgs + textToHtml(d.statement);
  }
  function seedBack(d) {
    if (d.backHtml && d.backHtml.trim()) return d.backHtml;
    var html = "";
    if (d.status !== "anulada" && d.correctAlternative) {
      var body = ((d.alternatives || []).find(function (a) { return a.letter === d.correctAlternative; }) || {}).body || "";
      html += '<p><strong>Resposta correta:</strong> ' + esc(d.correctAlternative) + ') ' + esc(body) + '</p>';
    } else if (d.status === "anulada") {
      html += '<p><strong>⚠ Questão anulada</strong> no gabarito oficial.</p>';
    }
    if (d.explanation && d.explanation.trim()) {
      html += '<p><strong>Comentário:</strong></p>' + textToHtml(d.explanation);
    }
    return html || "<p></p>";
  }

  function toolbar(target) {
    return '<div class="rte-toolbar" data-tb="' + target + '">'
      + '<button type="button" data-cmd="bold" title="Negrito"><b>B</b></button>'
      + '<button type="button" data-cmd="italic" title="Itálico"><i>I</i></button>'
      + '<button type="button" data-cmd="insertUnorderedList" title="Lista">• Lista</button>'
      + '<button type="button" data-img="' + target + '" title="Adicionar imagem">🖼 Imagem</button>'
      + '<button type="button" data-cmd="removeFormat" title="Limpar formatação">⌫ Limpar</button>'
      + '<button type="button" data-cmd="undo" title="Desfazer">↶</button>'
      + '<button type="button" data-cmd="redo" title="Refazer">↷</button>'
      + '</div>';
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

    ed.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">'
        + '<h3 style="margin:0;color:var(--primary-dark)">' + esc(d.exam || "ENEM") + ' · Q' + d.number + '</h3>'
        + '<a class="btn btn-mini btn--ghost" id="previewBtn" target="_blank" href="/suinda/curso-enem/estudar/?ids=' + d.id + '">Visualizar como estudante ↗</a></div>'
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
      + '<label>Alternativas (selecione a correta)</label>' + alts
      + '<div class="cardside cardside--front">'
        + '<p class="cardside__title">Frente do cartão — enunciado da questão</p>'
        + '<p class="cardside__hint">O que o estudante vê antes de responder. Cole imagens com Ctrl+V, arraste arquivos ou use 🖼 Imagem.</p>'
        + toolbar("front")
        + '<div class="rte" id="f_front" contenteditable="true" data-ph="Digite o enunciado, transcrição, textos de apoio…"></div>'
      + '</div>'
      + '<div class="cardside cardside--back">'
        + '<p class="cardside__title">Verso do cartão — resposta e explicação</p>'
        + '<p class="cardside__hint">Exibido após o estudante responder. Resposta correta, comentário, resolução, dicas e imagens complementares.</p>'
        + toolbar("back")
        + '<div class="rte" id="f_back" contenteditable="true" data-ph="Resposta correta, comentário, resolução…"></div>'
      + '</div>'
      + '<label>Observação interna (admin)</label><textarea id="f_notes">' + esc(d.notes || "") + '</textarea>'
      + '<label>Imagens anexadas à questão</label><div class="imgs" id="attachArea"></div>'
      + '<button class="btn btn-mini" id="addImgBtn" type="button" style="margin-top:.3rem">＋ Adicionar imagem</button>'
      + '<div class="ed-actions">'
        + '<button class="btn btn--primary" id="saveBtn" type="button">Salvar alterações</button>'
        + '<button class="btn" id="saveNextBtn" type="button">Salvar e abrir próxima →</button>'
        + '<button class="btn btn--ghost" id="archiveBtn" type="button">Arquivar questão</button>'
        + '<span id="dirtyDot" class="dirty-dot clean">✓ Tudo salvo</span>'
        + '<span id="edMsg" class="admin-msg"></span>'
      + '</div>';

    document.getElementById("f_front").innerHTML = seedFront(d);
    document.getElementById("f_back").innerHTML = seedBack(d);
    renderAttachments(d.images || []);
    bindEditor(d);
    markClean();
  }

  function renderAttachments(images) {
    var frontHtml = (document.getElementById("f_front") || {}).innerHTML || "";
    var backHtml = (document.getElementById("f_back") || {}).innerHTML || "";
    var area = document.getElementById("attachArea");
    if (!images.length) { area.innerHTML = '<p style="color:var(--text-soft)">Sem imagens. Cole (Ctrl+V), arraste ou use “Adicionar imagem”. Enquanto não houver imagem nem frente personalizada, o estudante vê a transcrição.</p>'; return; }
    area.innerHTML = images.map(function (im) {
      var inFront = frontHtml.indexOf(im.path) >= 0, inBack = backHtml.indexOf(im.path) >= 0;
      var use, useCls;
      if (inFront) { use = "frente do cartão"; useCls = "front"; }
      else if (inBack) { use = "verso do cartão"; useCls = "back"; }
      else if (im.usageType === "front") { use = "frente"; useCls = "front"; }
      else if (im.usageType === "back") { use = "verso"; useCls = "back"; }
      else { use = "não inserida no conteúdo"; useCls = "unused"; }
      return '<div class="imgcard' + (im.isPrimary ? ' primary' : '') + '" data-img="' + im.id + '">'
        + '<img src="' + esc(im.path) + '" alt="" data-zoom="' + esc(im.path) + '">'
        + '<span class="imgcard__use imgcard__use--' + useCls + '">' + use + '</span>'
        + '<input data-alttext="' + im.id + '" placeholder="texto alternativo" value="' + esc(im.altText || "") + '">'
        + '<div class="imgcard__acts">'
        + (im.isPrimary ? '<span class="flag flag--rev">principal</span>' : '<button class="btn btn-mini" data-primary="' + im.id + '" type="button">Principal</button>')
        + '<button class="btn btn-mini btn-danger" data-delimg="' + im.id + '" data-delpath="' + esc(im.path) + '" type="button">Excluir</button>'
        + '</div></div>';
    }).join("");
    area.querySelectorAll("[data-zoom]").forEach(function (img) { img.addEventListener("click", function () { openZoom(img.getAttribute("data-zoom")); }); });
    area.querySelectorAll("[data-primary]").forEach(function (b) { b.addEventListener("click", async function () { await api("/admin/question-images/" + b.getAttribute("data-primary"), { method: "PUT", json: { isPrimary: true } }); await refreshAttachments(); }); });
    area.querySelectorAll("[data-alttext]").forEach(function (i) { i.addEventListener("change", function () { api("/admin/question-images/" + i.getAttribute("data-alttext"), { method: "PUT", json: { altText: i.value } }); }); });
    area.querySelectorAll("[data-delimg]").forEach(function (b) {
      b.addEventListener("click", async function () {
        var path = b.getAttribute("data-delpath");
        var inUse = frontHtml.indexOf(path) >= 0 || backHtml.indexOf(path) >= 0;
        var warn = inUse ? "Esta imagem está inserida na frente ou no verso. Excluir mesmo assim? (ela some do conteúdo)" : "Excluir esta imagem?";
        if (!confirm(warn)) return;
        await api("/admin/question-images/" + b.getAttribute("data-delimg"), { method: "DELETE" });
        await refreshAttachments(); loadList();
      });
    });
  }

  async function refreshAttachments() {
    try { var d = (await api("/admin/questions/" + currentId)).question; renderAttachments(d.images || []); } catch (e) {}
  }

  // ---- inserção de imagem no editor (paste / drag / botão) ----
  function insertHtmlAtCursor(editor, html) {
    editor.focus();
    var sel = window.getSelection();
    if (!sel.rangeCount || !editor.contains(sel.anchorNode)) {
      var range = document.createRange(); range.selectNodeContents(editor); range.collapse(false);
      sel.removeAllRanges(); sel.addRange(range);
    }
    document.execCommand("insertHTML", false, html);
  }
  function usageForEditor(editor) { return editor.id === "f_back" ? "back" : "front"; }
  function uploadAndInsert(editor, file, usage) {
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { msg("Imagem maior que 5 MB.", false); return; }
    var phId = "ph" + (phSeq++);
    insertHtmlAtCursor(editor, '<span class="up-ph" data-ph="' + phId + '">⏳ enviando imagem…</span>&nbsp;');
    var fd = new FormData(); fd.append("file", file); fd.append("usageType", usage);
    api("/admin/questions/" + currentId + "/images", { method: "POST", body: fd }).then(function (r) {
      var ph = editor.querySelector('[data-ph="' + phId + '"]');
      var img = document.createElement("img"); img.src = r.url || r.path; img.alt = "";
      if (ph) { ph.replaceWith(img); } else { editor.appendChild(img); }
      markDirty(); refreshAttachments();
    }).catch(function (err) {
      var ph = editor.querySelector('[data-ph="' + phId + '"]'); if (ph) ph.remove();
      msg("Falha no upload da imagem: " + (err.message || ""), false);
    });
  }
  function wireEditorImages(editor) {
    editor.addEventListener("input", markDirty);
    editor.addEventListener("paste", function (e) {
      var items = (e.clipboardData && e.clipboardData.items) || [];
      var imgItem = null;
      for (var i = 0; i < items.length; i++) { if (items[i].type && items[i].type.indexOf("image/") === 0) { imgItem = items[i]; break; } }
      if (!imgItem) return; // texto: colagem normal
      e.preventDefault();
      uploadAndInsert(editor, imgItem.getAsFile(), usageForEditor(editor));
    });
    editor.addEventListener("dragover", function (e) { e.preventDefault(); editor.classList.add("dragover"); });
    editor.addEventListener("dragleave", function () { editor.classList.remove("dragover"); });
    editor.addEventListener("drop", function (e) {
      editor.classList.remove("dragover");
      var files = (e.dataTransfer && e.dataTransfer.files) || [];
      var f = null;
      for (var i = 0; i < files.length; i++) { if (files[i].type && files[i].type.indexOf("image/") === 0) { f = files[i]; break; } }
      if (!f) return;
      e.preventDefault();
      uploadAndInsert(editor, f, usageForEditor(editor));
    });
  }

  // ---- sincronização da alternativa correta no verso ----
  function syncCorrectIntoVerso(letter) {
    var verso = document.getElementById("f_back");
    if (!verso) return;
    var body = (document.querySelector('#editor [data-alt="' + letter + '"]') || {}).value || "";
    var blocks = verso.querySelectorAll("p, div");
    var target = null;
    for (var i = 0; i < blocks.length; i++) {
      if (blocks[i].textContent.trim().toLowerCase().indexOf("resposta correta:") === 0) { target = blocks[i]; break; }
    }
    var warnEl = document.getElementById("syncWarn");
    function warn(t) { if (warnEl) { warnEl.textContent = t; warnEl.hidden = false; } }
    function clearWarn() { if (warnEl) warnEl.hidden = true; }
    var newLine = '<strong>Resposta correta:</strong> ' + esc(letter) + ') ' + esc(body);
    if (target) {
      if (/^resposta correta:\s*[a-e]\)/i.test(target.textContent.trim())) { target.innerHTML = newLine; clearWarn(); markDirty(); }
      else { warn('A alternativa correta mudou para ' + letter + '. Atualize a linha “Resposta correta” do verso manualmente.'); }
    } else {
      warn('Alternativa correta agora é ' + letter + '. O verso não tem uma linha “Resposta correta:” — adicione-a se desejar.');
    }
  }

  function bindEditor(d) {
    ["f_status", "f_conf", "f_rev", "f_disc", "f_content", "f_comp", "f_skill"].forEach(function (id) {
      var el = document.getElementById(id); if (el) el.addEventListener("change", markDirty);
    });
    var notes = document.getElementById("f_notes");
    autoGrow(notes); notes.addEventListener("input", function () { autoGrow(notes); markDirty(); });
    document.querySelectorAll("#editor [data-alt]").forEach(function (i) { i.addEventListener("input", markDirty); });

    // aviso de sincronização logo após as alternativas
    var altsLabel = document.querySelector(".cardside--back .cardside__hint");
    if (altsLabel && !document.getElementById("syncWarn")) {
      var w = document.createElement("p"); w.id = "syncWarn"; w.className = "sync-warn"; w.hidden = true; altsLabel.insertAdjacentElement("afterend", w);
    }
    document.querySelectorAll('#editor input[name="correct"]').forEach(function (r) {
      r.addEventListener("change", function () { markDirty(); syncCorrectIntoVerso(r.value); });
    });

    wireEditorImages(document.getElementById("f_front"));
    wireEditorImages(document.getElementById("f_back"));
    document.getElementById("editor").querySelectorAll(".rte img").forEach(function (img) { img.addEventListener("click", function () { openZoom(img.getAttribute("src")); }); });

    // toolbar
    document.querySelectorAll("#editor .rte-toolbar button").forEach(function (b) {
      b.addEventListener("mousedown", function (e) { e.preventDefault(); }); // não perder seleção
      b.addEventListener("click", function () {
        var img = b.getAttribute("data-img");
        if (img) { pickerTarget = document.getElementById(img === "back" ? "f_back" : "f_front"); pickerUsage = img; document.getElementById("imgPicker").click(); return; }
        var ed = b.closest(".cardside").querySelector(".rte"); ed.focus();
        document.execCommand(b.getAttribute("data-cmd"), false, null); markDirty();
      });
    });

    document.getElementById("addImgBtn").addEventListener("click", function () { pickerTarget = "attachment"; pickerUsage = "attachment"; document.getElementById("imgPicker").click(); });
    document.getElementById("saveBtn").addEventListener("click", function () { saveQuestion(); });
    document.getElementById("saveNextBtn").addEventListener("click", function () { saveQuestion(true); });
    document.getElementById("archiveBtn").addEventListener("click", function () {
      if (!confirm("Arquivar esta questão? Ela sai do fluxo de estudo (status = arquivada). Nada é apagado.")) return;
      document.getElementById("f_status").value = "arquivada"; saveQuestion();
    });
  }

  function collect() {
    var alts = {};
    document.querySelectorAll("#editor [data-alt]").forEach(function (i) { alts[i.getAttribute("data-alt")] = i.value; });
    var correct = (document.querySelector('#editor input[name="correct"]:checked') || {}).value || null;
    return {
      status: document.getElementById("f_status").value,
      correctAlternative: correct,
      confidence: document.getElementById("f_conf").value,
      reviewNeeded: document.getElementById("f_rev").value === "1",
      disciplineId: document.getElementById("f_disc").value || null,
      contentId: document.getElementById("f_content").value || null,
      competencyId: document.getElementById("f_comp").value || null,
      skillId: document.getElementById("f_skill").value || null,
      frontHtml: document.getElementById("f_front").innerHTML,
      backHtml: document.getElementById("f_back").innerHTML,
      notes: document.getElementById("f_notes").value,
      alternatives: alts
    };
  }

  async function saveQuestion(openNext) {
    var id = currentId;
    msg("Salvando…");
    try {
      await api("/admin/questions/" + id, { method: "PUT", json: collect() });
      markClean(); msg("Salvo ✓", true); loadList();
      if (openNext) {
        var i = listIds.indexOf(id);
        if (i >= 0 && i + 1 < listIds.length) { openEditor(listIds[i + 1]); }
        else { msg("Salvo ✓ — não há próxima questão na lista atual.", true); }
      }
    } catch (e) { msg(e.message || "Falha ao salvar", false); }
  }

  function autoGrow(el) { if (!el) return; el.style.height = "auto"; el.style.height = el.scrollHeight + "px"; }

  // seletor de arquivo único (imagem) — alvo definido por pickerTarget/pickerUsage
  document.getElementById("imgPicker").addEventListener("change", function () {
    var f = this.files[0]; this.value = "";
    if (!f) return;
    if (pickerTarget === "attachment") {
      var fd = new FormData(); fd.append("file", f); fd.append("usageType", "attachment");
      msg("Enviando imagem…");
      api("/admin/questions/" + currentId + "/images", { method: "POST", body: fd }).then(function () { msg("Imagem enviada ✓", true); refreshAttachments(); loadList(); }).catch(function (e) { msg(e.message || "Falha no upload", false); });
    } else if (pickerTarget) {
      uploadAndInsert(pickerTarget, f, pickerUsage);
    }
  });

  document.getElementById("searchBtn").addEventListener("click", loadList);
  document.getElementById("search").addEventListener("keydown", function (e) { if (e.key === "Enter") loadList(); });
  window.addEventListener("beforeunload", function (e) { if (dirty) { e.preventDefault(); e.returnValue = ""; } });

  api("/me").then(function (r) {
    if ((r.user || {}).role !== "admin") { loading.hidden = true; denied.hidden = false; return; }
    return Promise.all([api("/enem/taxonomy"), Promise.resolve()]).then(function (res) {
      tax = res[0]; loading.hidden = true; root.hidden = false; loadList();
    });
  }).catch(function (e) { if (e && e.status === 401) return; loading.textContent = "Não foi possível carregar."; });
})();
</script>

<?php require __DIR__ . '/../../inc/footer.php'; ?>

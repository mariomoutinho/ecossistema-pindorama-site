<?php
// ============================================================================
// Suindá — Estudo de questões ENEM (área privada). /suinda/curso-enem/estudar/
// Fluxo: a questão (imagem oficial ou transcrição provisória) com alternativas
// A–E → "Confirmar resposta" → resultado + gabarito + explicação → botões de
// repetição espaçada (Errei/Difícil/Fácil/Muito fácil), que gravam em
// card_progress (a mesma tabela do app, mantendo o SRS consistente).
// ============================================================================
$suindaPageTitle = 'Estudar questões ENEM — Suindá';
$suindaPageDesc  = 'Resolva questões do ENEM com gabarito, explicação e repetição espaçada.';
$suindaActiveNav = 'estudar';
require __DIR__ . '/../../inc/header.php';
?>
<style>
  .run { padding: 1.4rem 0 3rem; max-width: 820px; margin-inline: auto; }
  .run__bar { height: 8px; background: var(--bg-deep); border-radius: 999px; overflow: hidden; margin-bottom: 1rem; }
  .run__bar i { display: block; height: 100%; background: linear-gradient(90deg, var(--leaf), var(--primary)); }
  .run__meta { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: space-between; color: var(--text-soft); font-size: .9rem; margin-bottom: .6rem; }
  .qcard { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.3rem; box-shadow: var(--shadow-sm); }
  .qcard__head { font-size: .82rem; color: var(--accent-dark); font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
  .qcard__pending { background: var(--bg-deep); border-radius: 10px; padding: .5rem .7rem; font-size: .85rem; color: var(--text-soft); margin: .6rem 0; }
  .qcard__statement { margin: .6rem 0 1rem; white-space: pre-line; }
  .qcard__img { width: 100%; border-radius: 10px; border: 1px solid var(--border); margin: .4rem 0 1rem; }
  .alts { list-style: none; padding: 0; margin: 0 0 1rem; display: grid; gap: .5rem; }
  .alt { display: flex; gap: .7rem; align-items: flex-start; border: 1.5px solid var(--border); border-radius: 12px; padding: .7rem .85rem; cursor: pointer; background: #fff; }
  .alt:hover { border-color: var(--accent); }
  .alt input { margin-top: .2rem; }
  .alt .letter { font-weight: 800; color: var(--primary); }
  .alt--correct { border-color: var(--ok); background: #eef8f1; }
  .alt--wrong { border-color: var(--danger); background: var(--danger-soft); }
  .alt--muted { opacity: .7; cursor: default; }
  .result { border-radius: 12px; padding: .8rem 1rem; margin-bottom: 1rem; font-weight: 700; }
  .result--ok { background: #dcefe2; color: var(--ok); }
  .result--no { background: var(--danger-soft); color: #8a2f2f; }
  .result--anulada { background: #f6ecd4; color: #7a5d16; }
  .explain { background: var(--bg-deep); border-radius: 12px; padding: .9rem 1.1rem; margin-bottom: 1rem; }
  .explain h4 { margin: 0 0 .3rem; color: var(--primary-dark); }
  .explain__pending { color: var(--text-soft); font-style: italic; }
  .qmeta { font-size: .85rem; color: var(--text-soft); margin-bottom: 1rem; }
  .qmeta b { color: var(--primary); }
  .srs { display: flex; flex-wrap: wrap; gap: .5rem; }
  .srs button { flex: 1 1 120px; }
  .end { text-align: center; padding: 2.4rem 1rem; }
  .end__owl { font-size: 2.6rem; }
</style>

<main id="conteudo" class="run">
  <div class="container">
    <div id="loading" class="loading">Preparando suas questões…</div>
    <div id="runRoot" hidden></div>
  </div>
</main>

<script>
(function () {
  var API = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var LOGIN = "/suinda/login/";
  var token = localStorage.getItem("suinda_api_token");
  if (!token) { window.location.replace(LOGIN); return; }

  var qs = new URLSearchParams(location.search);
  var loading = document.getElementById("loading");
  var root = document.getElementById("runRoot");

  var session = { ids: [], idx: 0, correct: 0, answered: 0, progress: {} };
  var startTime = 0;

  async function api(path, opts) {
    opts = opts || {};
    var res = await fetch(API + path, {
      method: opts.method || "GET",
      headers: Object.assign({ "Authorization": "Bearer " + token }, opts.body ? { "Content-Type": "application/json" } : {}),
      body: opts.body ? JSON.stringify(opts.body) : undefined
    });
    if (res.status === 401) { localStorage.removeItem("suinda_api_token"); window.location.replace(LOGIN); throw new Error("401"); }
    if (!res.ok) { var e = new Error("erro " + res.status); e.status = res.status; throw e; }
    return res.json();
  }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
  function el(html) { var d = document.createElement("div"); d.innerHTML = html; return d.firstElementChild; }

  // ---- repetição espaçada (grava em card_progress, formato do app) ----
  function schedule(prev, rating) {
    var ease = prev && prev.easeFactor ? prev.easeFactor : 2.5;
    var interval = prev && prev.intervalDays ? prev.intervalDays : 0;
    var reps = prev && prev.repetitions ? prev.repetitions : 0;
    var lapses = prev && prev.lapses ? prev.lapses : 0;
    var state = "review";
    if (rating === "errei") { ease = Math.max(1.3, ease - 0.2); interval = 1; reps = 0; lapses += 1; state = "relearning"; }
    else {
      reps += 1;
      if (rating === "dificil") { ease = Math.max(1.3, ease - 0.15); interval = interval <= 0 ? 1 : Math.max(1, Math.round(interval * 1.2)); }
      else if (rating === "facil") { interval = interval <= 0 ? 2 : Math.max(2, Math.round(interval * ease)); }
      else { ease = ease + 0.15; interval = interval <= 0 ? 4 : Math.max(4, Math.round(interval * ease * 1.3)); } // muito_facil
    }
    var due = new Date(Date.now() + interval * 86400000).toISOString();
    return {
      state: state, dueAt: due, easeFactor: Math.round(ease * 100) / 100, intervalDays: interval,
      repetitions: reps, lapses: lapses,
      introducedAt: (prev && prev.introducedAt) ? prev.introducedAt : new Date().toISOString(),
      lastRating: rating
    };
  }
  async function rate(cardId, rating) {
    var next = schedule(session.progress[cardId], rating);
    next.cardId = cardId;
    session.progress[cardId] = next;
    try { await api("/cards/" + cardId + "/progress", { method: "PUT", body: next }); } catch (e) {}
  }

  function renderQuestion(q) {
    startTime = Date.now();
    var imgs = (q.images || []);
    var visual = imgs.length
      ? imgs.map(function (im) { return '<img class="qcard__img" alt="Questão ' + q.number + '" src="' + esc(im.path) + '">'; }).join("")
      : '<p class="qcard__pending">🖼️ Imagem oficial pendente de recorte — exibindo a transcrição.</p><div class="qcard__statement">' + esc(q.statement || "") + '</div>';

    var altsHtml = (q.alternatives || []).map(function (a) {
      return '<li><label class="alt"><input type="radio" name="alt" value="' + a.letter + '"><span class="letter">' + a.letter + ')</span><span>' + esc(a.body) + '</span></label></li>';
    }).join("");

    var meta = [q.discipline, q.content].filter(Boolean).map(esc).join(" · ");
    var html = '<div class="qcard">'
      + '<p class="qcard__head">' + esc(q.exam || "ENEM") + ' · Questão ' + q.number + (meta ? ' · ' + meta : "") + '</p>'
      + visual
      + '<ul class="alts">' + altsHtml + '</ul>'
      + '<button class="btn btn--primary btn--block" id="confirmBtn" type="button">Confirmar resposta</button>'
      + '<p id="confirmMsg" class="form-msg"></p>'
      + '</div>';
    root.innerHTML = progressBar() + html;
    document.getElementById("confirmBtn").addEventListener("click", function () { confirmAnswer(q); });
  }

  function progressBar() {
    var pct = session.ids.length ? Math.round((session.idx) / session.ids.length * 100) : 0;
    return '<div class="run__meta"><span>Questão ' + (session.idx + 1) + ' de ' + session.ids.length + '</span>'
      + '<span>Acertos: ' + session.correct + '/' + session.answered + '</span></div>'
      + '<div class="run__bar"><i style="width:' + pct + '%"></i></div>';
  }

  async function confirmAnswer(q) {
    var sel = document.querySelector('input[name="alt"]:checked');
    if (!sel && !q.annulled) { document.getElementById("confirmMsg").textContent = "Selecione uma alternativa (ou pule, se anulada)."; return; }
    var selected = sel ? sel.value : "";
    var timeSpent = Math.round((Date.now() - startTime) / 1000);
    var back;
    try { back = await api("/enem/questions/" + q.id + "/answer", { method: "POST", body: { selected: selected, timeSpent: timeSpent, origin: "curso-enem" } }); }
    catch (e) { document.getElementById("confirmMsg").textContent = "Falha ao enviar. Tente novamente."; return; }
    renderResult(q, back);
  }

  function renderResult(q, back) {
    var annulled = back.annulled;
    if (!annulled) { session.answered++; if (back.isCorrect) session.correct++; }

    // alternativas com marcação
    var altsHtml = (back.alternatives || []).map(function (a) {
      var cls = "alt alt--muted";
      if (a.isCorrect) cls = "alt alt--correct";
      else if (a.letter === back.selected) cls = "alt alt--wrong";
      return '<li><span class="' + cls + '" style="cursor:default"><span class="letter">' + a.letter + ')</span><span>' + esc(a.body)
        + (a.isCorrect ? ' ✓' : (a.letter === back.selected ? ' ✗' : "")) + '</span></span></li>';
    }).join("");

    var banner = annulled
      ? '<div class="result result--anulada">⚠ Questão anulada no gabarito oficial — não conta acerto nem erro.</div>'
      : (back.isCorrect ? '<div class="result result--ok">✓ Você acertou! Gabarito: ' + back.correct + '</div>'
                        : '<div class="result result--no">✗ Resposta incorreta. Gabarito oficial: ' + back.correct + (back.selected ? ' (você marcou ' + back.selected + ')' : '') + '</div>');

    var explain = back.explanation
      ? '<div class="explain"><h4>Comentário</h4><div>' + esc(back.explanation) + '</div></div>'
      : '<div class="explain"><h4>Comentário</h4><p class="explain__pending">Explicação pedagógica pendente de revisão para esta questão.</p></div>';

    var metaParts = [];
    if (back.discipline) metaParts.push("<b>Disciplina:</b> " + esc(back.discipline));
    if (back.content) metaParts.push("<b>Conteúdo:</b> " + esc(back.content));
    if (back.competency) metaParts.push("<b>Competência:</b> " + esc(back.competency));
    if (back.skill) metaParts.push("<b>Habilidade:</b> " + esc(back.skill));

    var srs = annulled
      ? '<div class="srs"><button class="btn btn--primary" id="nextBtn" type="button">Próxima questão →</button></div>'
      : '<p class="qmeta" style="margin-top:.4rem">Como foi lembrar? (define a próxima revisão)</p>'
        + '<div class="srs">'
        + '<button class="btn btn-danger" data-rate="errei" type="button">Errei</button>'
        + '<button class="btn" data-rate="dificil" type="button">Difícil</button>'
        + '<button class="btn" data-rate="facil" type="button">Fácil</button>'
        + '<button class="btn btn--primary" data-rate="muito_facil" type="button">Muito fácil</button>'
        + '</div>';

    root.innerHTML = progressBar() + '<div class="qcard">'
      + '<p class="qcard__head">Questão ' + q.number + '</p>'
      + banner
      + '<ul class="alts">' + altsHtml + '</ul>'
      + explain
      + (metaParts.length ? '<p class="qmeta">' + metaParts.join(" · ") + '</p>' : "")
      + srs + '</div>';

    if (annulled) {
      document.getElementById("nextBtn").addEventListener("click", next);
    } else {
      root.querySelectorAll("[data-rate]").forEach(function (b) {
        b.addEventListener("click", async function () {
          root.querySelectorAll("[data-rate]").forEach(function (x) { x.disabled = true; });
          await rate(back.cardId, b.getAttribute("data-rate"));
          next();
        });
      });
    }
  }

  function next() {
    session.idx++;
    if (session.idx >= session.ids.length) { return finish(); }
    loadCurrent();
  }

  async function loadCurrent() {
    try {
      var d = await api("/enem/questions/" + session.ids[session.idx]);
      renderQuestion(d.question);
    } catch (e) {
      if (e && e.status === 403) { next(); return; } // pula sem acesso
      root.innerHTML = '<p style="color:var(--danger)">Falha ao carregar a questão.</p>';
    }
  }

  function finish() {
    var pct = session.answered ? Math.round(session.correct / session.answered * 100) : 0;
    root.innerHTML = '<div class="end"><div class="end__owl">🦉</div>'
      + '<h2>Sessão concluída!</h2>'
      + '<p>Você respondeu ' + session.answered + ' questão(ões) com ' + session.correct + ' acerto(s) (' + pct + '%).</p>'
      + '<div class="dash__actions" style="justify-content:center">'
      + '<a class="btn btn--primary" href="/suinda/curso-enem/">Voltar ao curso</a>'
      + '<a class="btn btn--ghost" href="/suinda/curso-enem/estudar/' + location.search + '">Estudar mais</a>'
      + '</div></div>';
  }

  async function buildSession() {
    // ids explícitos?
    var idsParam = qs.get("ids");
    if (idsParam) {
      session.ids = idsParam.split(",").map(function (x) { return parseInt(x, 10); }).filter(Boolean);
    } else {
      var allowed = ["discipline", "content", "competency", "skill", "status", "filter", "random", "limit"];
      var p = allowed.filter(function (k) { return qs.get(k); }).map(function (k) { return k + "=" + encodeURIComponent(qs.get(k)); }).join("&");
      var d = await api("/enem/questions?" + p);
      session.ids = d.questions.map(function (q) { return q.id; });
    }
    // carrega progresso atual para o agendamento incremental
    try { var pr = await api("/cards/progress"); (pr.progress || []).forEach(function (x) { session.progress[x.cardId] = x; }); } catch (e) {}

    loading.hidden = true; root.hidden = false;
    if (!session.ids.length) {
      root.innerHTML = '<div class="end"><div class="end__owl">🌱</div><h2>Nada para estudar agora</h2>'
        + '<p>Não há questões para este filtro. Tente outro filtro ou volte ao curso.</p>'
        + '<p><a class="btn btn--primary" href="/suinda/curso-enem/">Voltar ao curso</a></p></div>';
      return;
    }
    loadCurrent();
  }

  buildSession().catch(function (e) {
    if (e && e.status === 401) return;
    loading.textContent = "Não foi possível iniciar a sessão de estudo.";
  });
})();
</script>

<?php require __DIR__ . '/../../inc/footer.php'; ?>

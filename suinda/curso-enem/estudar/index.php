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
  .qcard__html { margin: .6rem 0 1rem; }
  .qcard__html img { max-width: 100%; height: auto; border-radius: 10px; border: 1px solid var(--border); margin: .4rem 0; }
  .qcard__html p { margin: .5rem 0; }
  .qcard__html table { border-collapse: collapse; }
  .qcard__html td, .qcard__html th { border: 1px solid var(--border); padding: .3rem .5rem; }
  .comentario__body .qcard__html { margin: 0; }
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
  .end-stats { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); max-width: 580px; margin: 1rem auto 1.4rem; }

  /* alternativas: seleção (estado 1) x resultado (estado 2) */
  .alt--select { cursor: pointer; }
  .alt--result { cursor: default; }
  .alt--result:hover { border-color: var(--border); }
  .alt__tag { font-size: .72rem; padding: .06rem .45rem; border-radius: 999px; margin-left: .35rem; font-weight: 800; }
  .alt__tag--ok { background: #dcefe2; color: var(--ok); }
  .alt__tag--no { background: var(--danger-soft); color: #8a2f2f; }

  /* imagens amplia­ veis + botão de questão completa */
  .qcard__img { cursor: zoom-in; }
  .qcard__full { margin: .1rem 0 .6rem; }

  /* comentário expansível */
  details.comentario { background: var(--bg-deep); border-radius: 12px; padding: .6rem .9rem; margin: .5rem 0 1rem; }
  details.comentario > summary { cursor: pointer; font-weight: 800; color: var(--primary-dark); list-style: revert; }
  .comentario__body { margin-top: .5rem; }
  .comentario__body p { margin: .4rem 0; }
  .explain__pending { color: var(--text-soft); font-style: italic; }

  /* botões SRS com intervalo */
  .srs-btn { flex-direction: column; gap: .12rem; line-height: 1.15; padding: .55rem .5rem; min-height: 0; }
  .srs-btn__label { font-weight: 800; }
  .srs-btn__when { font-size: .72rem; font-weight: 600; opacity: .85; }
  .srs-confirm { margin-top: .7rem; color: var(--ok); font-weight: 700; }

  /* modal de imagem ampliada */
  .zoom-modal { position: fixed; inset: 0; background: rgba(10,20,18,.86); display: none; align-items: center; justify-content: center; z-index: 100; padding: 1rem; }
  .zoom-modal.open { display: flex; }
  .zoom-modal img { max-width: 100%; max-height: 92vh; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,.5); }
  .zoom-modal__close { position: absolute; top: 14px; right: 18px; width: 42px; height: 42px; border: none; border-radius: 50%; background: #fff; font-size: 1.2rem; cursor: pointer; }
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

  var session = { ids: [], idx: 0, correct: 0, answered: 0, annulled: 0, scheduled: 0, progress: {} };
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

  // ---- repetição espaçada (grava em card_progress; minutos p/ intervalos curtos) ----
  function schedule(prev, rating) {
    var ease = prev && prev.easeFactor ? prev.easeFactor : 2.5;
    var prevDays = prev && prev.intervalDays ? prev.intervalDays : 0;
    var reps = prev && prev.repetitions ? prev.repetitions : 0;
    var lapses = prev && prev.lapses ? prev.lapses : 0;
    var state = "review", minutes;
    if (rating === "errei") { ease = Math.max(1.3, ease - 0.2); minutes = 10; reps = 0; lapses += 1; state = "relearning"; }
    else {
      reps += 1;
      var days;
      if (rating === "dificil") { ease = Math.max(1.3, ease - 0.15); days = prevDays <= 0 ? 1 : Math.max(1, Math.round(prevDays * 1.2)); }
      else if (rating === "facil") { days = prevDays <= 0 ? 2 : Math.max(2, Math.round(prevDays * ease)); }
      else { ease = ease + 0.15; days = prevDays <= 0 ? 4 : Math.max(4, Math.round(prevDays * ease * 1.3)); } // muito_facil
      minutes = days * 1440;
    }
    return {
      state: state, dueAt: new Date(Date.now() + minutes * 60000).toISOString(),
      easeFactor: Math.round(ease * 100) / 100, intervalDays: Math.round(minutes / 1440), intervalMinutes: minutes,
      repetitions: reps, lapses: lapses,
      introducedAt: (prev && prev.introducedAt) ? prev.introducedAt : new Date().toISOString(),
      lastRating: rating
    };
  }

  // ---- formatação amigável de intervalos/datas ----
  function formatDate(d) {
    var meses = ["jan.", "fev.", "mar.", "abr.", "mai.", "jun.", "jul.", "ago.", "set.", "out.", "nov.", "dez."];
    return d.getDate() + " " + meses[d.getMonth()] + " " + d.getFullYear();
  }
  function formatInterval(minutes) {
    if (minutes < 60) return "em " + minutes + " min";
    if (minutes < 1440) return "em " + Math.round(minutes / 60) + " h";
    var days = Math.round(minutes / 1440);
    if (days === 1) return "amanhã";
    if (days < 14) return "em " + days + " dias";
    if (days < 60) { var w = Math.round(days / 7); return "em " + w + (w === 1 ? " semana" : " semanas"); }
    if (days < 365) { var mo = Math.round(days / 30); return "em " + mo + (mo === 1 ? " mês" : " meses"); }
    return "em " + formatDate(new Date(Date.now() + minutes * 60000));
  }
  function dueLabel(minutes) {
    var days = Math.round(minutes / 1440);
    return (days <= 14) ? formatInterval(minutes) : ("em " + formatDate(new Date(Date.now() + minutes * 60000)));
  }

  // ---- visual da questão (imagens oficiais OU transcrição) — usado nos 2 estados ----
  function questionVisual(q) {
    // Frente rica (editor admin estilo Anki): HTML já sanitizado no servidor,
    // pode conter imagens inline. Tem prioridade sobre o legado.
    if (q.frontHtml && String(q.frontHtml).trim()) {
      return '<div class="qcard__html">' + q.frontHtml + '</div>';
    }
    var imgs = (q.images || []);
    if (imgs.length) {
      var multi = imgs.length > 1
        ? '<button class="btn btn-mini qcard__full" type="button" data-full="1">Ver questão completa (' + imgs.length + ' imagens)</button>'
        : "";
      return imgs.map(function (im, i) {
        var alt = esc(im.altText || ("Questão " + q.number + " — imagem " + (i + 1)));
        return '<img class="qcard__img" data-zoom="' + esc(im.path) + '" alt="' + alt + '" src="' + esc(im.path) + '">';
      }).join("") + multi;
    }
    return '<p class="qcard__pending">🖼️ Imagem oficial pendente de recorte — exibindo a transcrição.</p>'
      + '<div class="qcard__statement">' + esc(q.statement || "") + '</div>';
  }

  // ---- modal de ampliação de imagem ----
  function openZoom(src) {
    var m = document.getElementById("zoomModal");
    if (!m) {
      m = document.createElement("div"); m.id = "zoomModal"; m.className = "zoom-modal";
      m.innerHTML = '<button class="zoom-modal__close" type="button" aria-label="Fechar">✕</button><img alt="Imagem ampliada da questão">';
      m.addEventListener("click", function (e) { if (e.target === m || e.target.classList.contains("zoom-modal__close")) m.classList.remove("open"); });
      document.body.appendChild(m);
    }
    m.querySelector("img").src = src;
    m.classList.add("open");
  }
  function wireZoom() {
    root.querySelectorAll("[data-zoom]").forEach(function (img) {
      img.addEventListener("click", function () { openZoom(img.getAttribute("data-zoom")); });
    });
    // Imagens inline da frente/verso rica também ampliam ao clique.
    root.querySelectorAll(".qcard__html img").forEach(function (img) {
      img.style.cursor = "zoom-in";
      img.addEventListener("click", function () { openZoom(img.getAttribute("src")); });
    });
    var full = root.querySelector("[data-full]");
    if (full) {
      full.addEventListener("click", function () {
        var first = root.querySelector("[data-zoom]");
        if (first) openZoom(first.getAttribute("data-zoom"));
      });
    }
  }
  async function rate(cardId, rating) {
    var next = schedule(session.progress[cardId], rating);
    next.cardId = cardId;
    session.progress[cardId] = next;
    try { await api("/cards/" + cardId + "/progress", { method: "PUT", body: next }); } catch (e) {}
    return next;
  }

  function renderQuestion(q) {
    startTime = Date.now();
    var altsHtml = (q.alternatives || []).map(function (a) {
      return '<li><label class="alt alt--select"><input type="radio" name="alt" value="' + a.letter + '"><span class="letter">' + a.letter + ')</span><span>' + esc(a.body) + '</span></label></li>';
    }).join("");

    var meta = [q.discipline, q.content].filter(Boolean).map(esc).join(" · ");
    var html = '<div class="qcard">'
      + '<p class="qcard__head">' + esc(q.exam || "ENEM") + ' · Questão ' + q.number + (meta ? ' · ' + meta : "") + '</p>'
      + questionVisual(q)
      + '<ul class="alts">' + altsHtml + '</ul>'
      + '<button class="btn btn--primary btn--block" id="confirmBtn" type="button" disabled>Responder</button>'
      + '<p id="confirmMsg" class="form-msg"></p>'
      + '</div>';
    root.innerHTML = progressBar() + html;
    wireZoom();

    var confirmBtn = document.getElementById("confirmBtn");
    if (q.annulled) { confirmBtn.disabled = false; } // anulada: seleção é opcional
    root.querySelectorAll('input[name="alt"]').forEach(function (r) {
      r.addEventListener("change", function () { confirmBtn.disabled = false; });
    });
    confirmBtn.addEventListener("click", function () { confirmAnswer(q); });
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
    if (annulled) { session.annulled++; }
    else { session.answered++; if (back.isCorrect) session.correct++; }

    // alternativas marcadas (a questão CONTINUA visível, acima)
    var altsHtml = (back.alternatives || []).map(function (a) {
      var cls = "alt alt--result alt--muted";
      var tag = "";
      if (a.isCorrect) { cls = "alt alt--result alt--correct"; tag = ' <span class="alt__tag alt__tag--ok">correta</span>'; }
      else if (a.letter === back.selected) { cls = "alt alt--result alt--wrong"; tag = ' <span class="alt__tag alt__tag--no">sua resposta</span>'; }
      return '<li><span class="' + cls + '"><span class="letter">' + a.letter + ')</span><span>' + esc(a.body) + tag + '</span></span></li>';
    }).join("");

    var banner = annulled
      ? '<div class="result result--anulada">⚠ Questão anulada no gabarito oficial — não conta acerto nem erro.</div>'
      : (back.isCorrect ? '<div class="result result--ok">✓ Você acertou! Gabarito oficial: ' + back.correct + '</div>'
                        : '<div class="result result--no">✗ Resposta incorreta. Gabarito oficial: ' + back.correct + (back.selected ? ' · você marcou ' + back.selected : ' · você não marcou') + '</div>');

    var metaRows = [];
    if (back.discipline) metaRows.push("<b>Disciplina:</b> " + esc(back.discipline));
    if (back.content) metaRows.push("<b>Conteúdo:</b> " + esc(back.content));
    if (back.competency) metaRows.push("<b>Competência:</b> " + esc(back.competency) + (back.competencyStatement ? " — " + esc(back.competencyStatement) : ""));
    if (back.skill) metaRows.push("<b>Habilidade:</b> " + esc(back.skill) + (back.skillStatement ? " — " + esc(back.skillStatement) : ""));

    // Verso rico (HTML sanitizado no servidor) tem prioridade sobre o texto legado.
    var explanationHtml = (back.backHtml && String(back.backHtml).trim())
      ? '<div class="qcard__html">' + back.backHtml + '</div>'
      : (back.explanation
          ? '<p>' + esc(back.explanation) + '</p>'
          : '<p class="explain__pending">Explicação pedagógica pendente de revisão para esta questão.</p>');

    var comentario = '<details class="comentario" open><summary>Comentário da resposta</summary>'
      + '<div class="comentario__body">'
      + '<p><b>Sua resposta:</b> ' + (back.selected || "—") + ' &nbsp;·&nbsp; <b>Gabarito oficial:</b> ' + (annulled ? "anulada" : back.correct) + '</p>'
      + explanationHtml
      + (metaRows.length ? '<p class="qmeta">' + metaRows.join("<br>") + '</p>' : "")
      + '</div></details>';

    var srs;
    if (annulled) {
      srs = '<div class="srs"><button class="btn btn--primary" id="nextBtn" type="button">Próxima questão →</button></div>';
    } else {
      var prev = session.progress[back.cardId];
      var ratings = [
        { k: "errei", label: "Errei", cls: "btn-danger" },
        { k: "dificil", label: "Difícil", cls: "" },
        { k: "facil", label: "Fácil", cls: "" },
        { k: "muito_facil", label: "Muito fácil", cls: "btn--primary" }
      ];
      srs = '<p class="qmeta" style="margin-top:.4rem">Como foi lembrar? (define quando a questão volta a aparecer)</p>'
        + '<div class="srs">' + ratings.map(function (r) {
            var sc = schedule(prev, r.k);
            return '<button class="btn srs-btn ' + r.cls + '" data-rate="' + r.k + '" type="button">'
              + '<span class="srs-btn__label">' + r.label + '</span>'
              + '<span class="srs-btn__when">' + formatInterval(sc.intervalMinutes) + '</span></button>';
          }).join("") + '</div>'
        + '<p id="srsConfirm" class="srs-confirm" hidden></p>';
    }

    // Card permanece visível: cabeçalho + resultado + IMAGEM/ENUNCIADO + alternativas + comentário + SRS
    root.innerHTML = progressBar() + '<div class="qcard">'
      + '<p class="qcard__head">' + esc(q.exam || "ENEM") + ' · Questão ' + q.number + '</p>'
      + banner
      + questionVisual(q)
      + '<ul class="alts">' + altsHtml + '</ul>'
      + comentario
      + srs + '</div>';
    wireZoom();

    if (annulled) {
      document.getElementById("nextBtn").addEventListener("click", next);
    } else {
      root.querySelectorAll("[data-rate]").forEach(function (b) {
        b.addEventListener("click", async function () {
          root.querySelectorAll("[data-rate]").forEach(function (x) { x.disabled = true; });
          var sched = await rate(back.cardId, b.getAttribute("data-rate"));
          session.scheduled++;
          if (b.getAttribute("data-rate") === "errei") { session.ids.push(q.id); } // reaparece nesta sessão (mesmo dia)
          var conf = document.getElementById("srsConfirm");
          conf.hidden = false;
          conf.textContent = "Próxima revisão programada para: " + (sched ? dueLabel(sched.intervalMinutes) : "—");
          setTimeout(next, 950);
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

  function statTile(v, label) { return '<div class="stat"><strong>' + v + '</strong><span>' + esc(label) + '</span></div>'; }
  function finish() {
    var pct = session.answered ? Math.round(session.correct / session.answered * 100) : 0;
    var errors = session.answered - session.correct;
    root.innerHTML = '<div class="end"><div class="end__owl">🦉</div>'
      + '<h2>Sessão concluída!</h2>'
      + '<div class="end-stats">'
      + statTile(session.answered, "Respondidas") + statTile(session.correct, "Acertos") + statTile(errors, "Erros")
      + statTile(session.annulled, "Anuladas") + statTile(session.scheduled, "Revisões programadas") + statTile(pct + "%", "Aproveitamento")
      + '</div>'
      + '<div class="dash__actions" style="justify-content:center">'
      + '<a class="btn btn--primary" href="/suinda/curso-enem/estudar/' + location.search + '">Continuar</a>'
      + '<a class="btn btn--ghost" href="/suinda/curso-enem/">Voltar ao curso</a>'
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

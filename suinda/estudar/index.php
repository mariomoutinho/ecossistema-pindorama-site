<?php
// ============================================================================
// Suindá — Painel de estudos (/suinda/estudar/).
// Área autenticada. O gate visual é client-side (redireciona ao login sem
// token); a PROTEÇÃO REAL dos dados é no backend: /me/dashboard e os baralhos
// só retornam o que a matrícula do estudante libera (HTTP 401/403 caso contrário).
// ============================================================================
$suindaPageTitle = 'Painel de estudos — Suindá';
$suindaPageDesc  = 'Seus cursos, trilhas e baralhos liberados no Suindá.';
$suindaActiveNav = 'estudar';
$suindaBodyClass = 'page-dash';
require __DIR__ . '/../inc/header.php';
?>

<main id="conteudo" class="dash">
  <div class="container">
    <div id="dashLoading" class="loading">Carregando seu painel de estudos…</div>
    <div id="dashRoot" hidden></div>
  </div>
</main>

<script>
(function () {
  var API_BASE = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var APP = "/suinda/app/pages";
  var LOGIN = "/suinda/login/";

  var token = localStorage.getItem("suinda_api_token");
  if (!token) { window.location.replace(LOGIN); return; }

  var loading = document.getElementById("dashLoading");
  var root = document.getElementById("dashRoot");

  function signOut() {
    localStorage.removeItem("suinda_api_token");
    localStorage.removeItem("suinda_current_user");
    window.location.replace(LOGIN);
  }

  // Helpers de criação de DOM (textContent evita injeção a partir de títulos).
  function el(tag, opts) {
    opts = opts || {};
    var node = document.createElement(tag);
    if (opts.class) node.className = opts.class;
    if (opts.text != null) node.textContent = opts.text;
    if (opts.html != null) node.innerHTML = opts.html;
    if (opts.attrs) Object.keys(opts.attrs).forEach(function (k) { node.setAttribute(k, opts.attrs[k]); });
    (opts.children || []).forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }

  function statBox(value, label) {
    return el("div", { class: "stat", children: [
      el("strong", { text: String(value) }),
      el("span", { text: label })
    ]});
  }

  function deckRow(deck) {
    var meta = el("div", { class: "deck-row__meta", children: [
      el("span", { class: "chip", text: deck.totalCards + " cartões" }),
      el("span", { class: "chip chip--new", text: deck.newCards + " novos" }),
      el("span", { class: "chip chip--due", text: deck.dueCards + " a revisar" })
    ]});
    var title = el("div", { children: [
      el("strong", { text: deck.title.trim() || "Baralho" }),
      meta
    ]});
    var study = el("a", { class: "btn btn--primary", text: "Estudar",
      attrs: { href: APP + "/study.html?id=" + encodeURIComponent(deck.id) } });
    return el("div", { class: "deck-row", children: [title, study] });
  }

  function courseCard(course) {
    var statusBadge = course.status === "available"
      ? el("span", { class: "badge badge--open", text: "Disponível" })
      : el("span", { class: "badge badge--soon", text: "Em breve" });

    var head = el("div", { class: "course__head", children: [
      el("h3", { text: course.title }),
      statusBadge
    ]});

    var pct = (course.progress && course.progress.percent) || 0;
    var bar = el("div", { class: "progress", children: [
      el("div", { class: "progress__bar", attrs: { style: "width:" + pct + "%" } })
    ]});
    var progressLabel = el("div", { class: "progress__label",
      text: pct + "% concluído · " + course.progress.studiedCards + "/" + course.progress.totalCards + " cartões · "
            + course.modules + (course.modules === 1 ? " módulo" : " módulos") });

    var children = [head];
    if (course.description) children.push(el("p", { class: "course__desc", text: course.description }));
    children.push(bar, progressLabel);

    if (course.slug === "preparatorio-enem") {
      children.push(el("div", { class: "dash__actions", attrs: { style: "margin:.4rem 0" }, children: [
        el("a", { class: "btn btn--accent", text: "Abrir curso ENEM →", attrs: { href: "/suinda/curso-enem/" } })
      ]}));
    }

    if (course.decks && course.decks.length) {
      course.decks.forEach(function (d) { children.push(deckRow(d)); });
    } else {
      children.push(el("p", { class: "progress__label", text: "Nenhum baralho liberado neste curso ainda." }));
    }

    return el("article", { class: "course", children: children });
  }

  function pathCard(path) {
    var list = el("div", { class: "card__foot" });
    (path.courses || []).forEach(function (c) {
      var cls = c.enrolled ? "chip chip--new" : "chip";
      var label = c.title + (c.enrolled ? "" : (c.status === "coming_soon" ? " (em breve)" : ""));
      list.appendChild(el("span", { class: cls, text: label }));
    });
    return el("article", { class: "card", children: [
      el("h3", { text: path.title }),
      path.description ? el("p", { text: path.description }) : null,
      list
    ]});
  }

  function render(data) {
    loading.hidden = true;
    root.hidden = false;
    root.innerHTML = "";

    var user = data.user || {};
    var firstName = (user.name || "estudante").split(" ")[0];

    var logoutBtn = el("button", { class: "btn btn--ghost", text: "Sair", attrs: { type: "button", id: "logoutBtn" } });
    var actions = [];
    if (user.role === "admin") {
      actions.push(el("a", { class: "btn btn--ghost", text: "⚙ Administração", attrs: { href: "/suinda/admin/" } }));
    }
    actions.push(logoutBtn);
    var greeting = el("div", { class: "dash__greeting", children: [
      el("div", { children: [
        el("h1", { text: "Olá, " + firstName + " 👋" }),
        el("p", { text: "Bem-vindo(a) de volta ao seu espaço de estudos." })
      ]}),
      el("div", { class: "dash__actions", attrs: { style: "margin:0" }, children: actions })
    ]});
    root.appendChild(greeting);
    logoutBtn.addEventListener("click", signOut);

    var totals = data.totals || { courses: 0, decks: 0, newCards: 0, dueCards: 0 };
    root.appendChild(el("div", { class: "stat-row", children: [
      statBox(totals.courses, "Cursos matriculados"),
      statBox(totals.decks, "Baralhos liberados"),
      statBox(totals.newCards, "Cartões novos"),
      statBox(totals.dueCards, "Revisões pendentes")
    ]}));

    if (!data.hasContent) {
      root.appendChild(el("div", { class: "empty-state", children: [
        el("div", { class: "empty-state__owl", text: "🦉" }),
        el("h2", { text: "Sua conta ainda não tem cursos liberados" }),
        el("p", { text: "Assim que você for matriculado(a) em um curso, seus baralhos de estudo aparecerão aqui. Em caso de dúvida, fale com o Coletivo Pindorama." }),
        el("div", { class: "dash__actions", children: [
          el("a", { class: "btn btn--primary", text: "Voltar ao Suindá", attrs: { href: "/suinda/" } })
        ]})
      ]}));
      return;
    }

    // Cursos
    root.appendChild(el("h2", { class: "section__head", text: "Seus cursos", attrs: { style: "margin-top:1.4rem" } }));
    (data.courses || []).forEach(function (c) { root.appendChild(courseCard(c)); });

    // Trilhas liberadas
    if (data.paths && data.paths.length) {
      root.appendChild(el("h2", { class: "section__head", text: "Trilhas liberadas", attrs: { style: "margin-top:1.4rem" } }));
      var grid = el("div", { class: "cards" });
      data.paths.forEach(function (p) { grid.appendChild(pathCard(p)); });
      root.appendChild(grid);
    }

    // Acesso ao app de revisão
    root.appendChild(el("div", { class: "dash__actions", children: [
      el("a", { class: "btn btn--accent btn--lg", text: "Continuar estudando", attrs: { href: APP + "/decks.html" } }),
      el("a", { class: "btn btn--ghost", text: "Abrir app de revisão", attrs: { href: APP + "/decks.html" } })
    ]}));
  }

  fetch(API_BASE + "/me/dashboard", { headers: { "Authorization": "Bearer " + token } })
    .then(function (response) {
      if (response.status === 401) { signOut(); throw new Error("unauthorized"); }
      if (!response.ok) throw new Error("erro " + response.status);
      return response.json();
    })
    .then(render)
    .catch(function (err) {
      if (err && err.message === "unauthorized") return;
      loading.innerHTML = "";
      loading.appendChild(el("div", { class: "empty-state", children: [
        el("div", { class: "empty-state__owl", text: "🌧️" }),
        el("h2", { text: "Não foi possível carregar seu painel" }),
        el("p", { text: "Verifique sua conexão e tente novamente." }),
        el("div", { class: "dash__actions", children: [
          el("button", { class: "btn btn--primary", text: "Tentar de novo", attrs: { type: "button", onclick: "location.reload()" } }),
          el("button", { class: "btn btn--ghost", text: "Sair", attrs: { type: "button", id: "retryLogout" } })
        ]})
      ]}));
      var rl = document.getElementById("retryLogout");
      if (rl) rl.addEventListener("click", signOut);
    });
})();
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>

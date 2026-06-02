<?php
// ============================================================================
// Suindá — Administração (/suinda/admin/).
// Mini-CMS: cadastra áreas, trilhas, cursos, módulos, vincula baralhos a cursos,
// cria estudantes e gerencia matrículas. UI fina; toda regra é validada no
// backend (/suinda/api/admin/*), acessível apenas a usuários com role=admin.
// ============================================================================
$suindaPageTitle = 'Administração — Suindá';
$suindaPageDesc  = 'Gestão de áreas, trilhas, cursos, baralhos e matrículas do Suindá.';
$suindaActiveNav = 'admin';
$suindaBodyClass = 'page-admin';
require __DIR__ . '/../inc/header.php';
?>

<style>
  .admin { padding: 1.6rem 0 3rem; }
  .admin h1 { color: var(--primary-dark); margin: 0 0 .2rem; }
  .admin__sub { color: var(--text-soft); margin: 0 0 1.4rem; }
  .panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.2rem 1.3rem; box-shadow: var(--shadow-sm); margin-bottom: 1.1rem; }
  .panel > h2 { margin: 0 0 .2rem; font-size: 1.15rem; color: var(--primary-dark); }
  .panel > p.hint { margin: 0 0 .9rem; color: var(--text-soft); font-size: .9rem; }
  .admin-form { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); align-items: end; }
  .admin-form .field { margin: 0; }
  .admin-form label { font-size: .85rem; }
  .admin-form input, .admin-form select, .admin-form textarea {
    width: 100%; padding: .6rem .7rem; border: 1.5px solid var(--border); border-radius: 10px;
    font: inherit; background: #fff; color: var(--text); min-height: 42px;
  }
  .admin-form input:focus-visible, .admin-form select:focus-visible, .admin-form textarea:focus-visible { outline: 3px solid var(--accent); outline-offset: 1px; border-color: var(--accent); }
  .admin-form .span2 { grid-column: span 2; }
  .admin-form .actions { display: flex; align-items: end; }
  table.list { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: .92rem; }
  table.list th, table.list td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid var(--border); vertical-align: top; }
  table.list th { color: var(--text-soft); font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
  table.list tbody tr:hover { background: var(--bg-deep); }
  .pill { display: inline-block; font-size: .75rem; padding: .12rem .5rem; border-radius: 999px; background: var(--primary-soft); color: var(--primary); }
  .pill--soon { background: #f2e7cf; color: #8a6a1c; }
  .pill--admin { background: #e7dcf2; color: #6b3fa0; }
  .btn-mini { padding: .3rem .7rem; min-height: 0; font-size: .85rem; border-radius: 8px; }
  .btn-danger { color: var(--danger); border-color: #e7c3bd; background: var(--danger-soft); }
  .admin-msg { margin: .6rem 0 0; min-height: 1.2em; font-size: .9rem; }
  .admin-msg--ok { color: var(--ok); }
  .admin-msg--error { color: var(--danger); }
  .admin-cols { display: grid; gap: 1.1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
  .restrito { text-align: center; padding: 3rem 1rem; }
</style>

<main id="conteudo" class="admin">
  <div class="container">
    <div id="adminLoading" class="loading">Carregando administração…</div>
    <div id="adminDenied" class="restrito" hidden>
      <div style="font-size:2.6rem">🔒</div>
      <h2>Área restrita</h2>
      <p>Esta página é exclusiva para administradores do Suindá.</p>
      <p><a class="btn btn--primary" href="/suinda/estudar/">Ir para o painel</a></p>
    </div>
    <div id="adminRoot" hidden>
      <h1>Administração do Suindá</h1>
      <p class="admin__sub">Cadastre a estrutura pedagógica e gerencie matrículas. Tudo é validado no servidor.</p>
      <p id="adminTopMsg" class="admin-msg"></p>

      <div class="admin-cols">
        <!-- ÁREAS -->
        <section class="panel">
          <h2>Áreas de conhecimento</h2>
          <p class="hint">Agrupam trilhas e cursos.</p>
          <form class="admin-form" data-endpoint="/admin/areas">
            <div class="field"><label>Nome*<input name="name" required></label></div>
            <div class="field span2"><label>Descrição<input name="description"></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Adicionar</button></div>
          </form>
          <table class="list"><thead><tr><th>Área</th><th>Slug</th></tr></thead><tbody data-list="areas"></tbody></table>
        </section>

        <!-- CURSOS -->
        <section class="panel">
          <h2>Cursos</h2>
          <p class="hint">Um curso pode liberar um ou mais baralhos. Use “Editar” para ajustar ou inativar (inativar esconde os baralhos dos estudantes).</p>
          <form class="admin-form" data-endpoint="/admin/courses">
            <div class="field span2"><label>Título*<input name="title" required></label></div>
            <div class="field"><label>Área<select name="areaId" data-options="areas"></select></label></div>
            <div class="field"><label>Nível<input name="level" value="introdutorio"></label></div>
            <div class="field"><label>Status
              <select name="status"><option value="available">Disponível</option><option value="coming_soon">Em breve</option></select>
            </label></div>
            <div class="field span2"><label>Descrição<input name="description"></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Adicionar</button></div>
          </form>

          <form class="admin-form" id="courseEditForm" hidden style="margin-top:.8rem;background:var(--bg-deep);padding:.9rem;border-radius:12px">
            <input type="hidden" name="id">
            <div class="field span2"><label>Editar título*<input name="title" required></label></div>
            <div class="field"><label>Área<select name="areaId" data-options="areas"></select></label></div>
            <div class="field"><label>Nível<input name="level"></label></div>
            <div class="field"><label>Status
              <select name="status"><option value="available">Disponível</option><option value="coming_soon">Em breve</option></select>
            </label></div>
            <div class="field"><label>Ativo
              <select name="active"><option value="1">Sim</option><option value="0">Não (inativo)</option></select>
            </label></div>
            <div class="field span2"><label>Descrição<input name="description"></label></div>
            <div class="actions" style="gap:.5rem">
              <button class="btn btn--primary btn-mini" type="submit">Salvar</button>
              <button class="btn btn-mini" type="button" id="courseEditCancel">Cancelar</button>
            </div>
          </form>

          <table class="list"><thead><tr><th>Curso</th><th>Status</th><th>Área</th><th>Ações</th></tr></thead><tbody data-list="courses"></tbody></table>
        </section>

        <!-- TRILHAS -->
        <section class="panel">
          <h2>Trilhas de aprendizagem</h2>
          <p class="hint">Reúnem cursos em ordem.</p>
          <form class="admin-form" data-endpoint="/admin/paths">
            <div class="field span2"><label>Título*<input name="title" required></label></div>
            <div class="field"><label>Área<select name="areaId" data-options="areas"></select></label></div>
            <div class="field span2"><label>Descrição<input name="description"></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Adicionar</button></div>
          </form>
          <form class="admin-form" data-endpoint="/admin/path-courses" style="margin-top:.8rem">
            <div class="field"><label>Trilha<select name="pathId" data-options="paths" required></select></label></div>
            <div class="field"><label>Curso<select name="courseId" data-options="courses" required></select></label></div>
            <div class="field"><label>Ordem<input name="position" type="number" value="1" min="0"></label></div>
            <div class="actions"><button class="btn btn-mini" type="submit">Vincular curso à trilha</button></div>
          </form>
          <table class="list"><thead><tr><th>Trilha</th><th>Cursos (em ordem)</th></tr></thead><tbody data-list="paths"></tbody></table>
        </section>

        <!-- MÓDULOS -->
        <section class="panel">
          <h2>Módulos</h2>
          <p class="hint">Etapas dentro de um curso (opcional).</p>
          <form class="admin-form" data-endpoint="/admin/modules">
            <div class="field"><label>Curso*<select name="courseId" data-options="courses" required></select></label></div>
            <div class="field span2"><label>Título*<input name="title" required></label></div>
            <div class="field"><label>Ordem<input name="position" type="number" value="1" min="0"></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Adicionar</button></div>
          </form>
          <table class="list"><thead><tr><th>Curso</th><th>Módulo</th><th>Ordem</th></tr></thead><tbody data-list="modules"></tbody></table>
        </section>

        <!-- BARALHOS x CURSOS -->
        <section class="panel">
          <h2>Baralhos liberados por curso</h2>
          <p class="hint">Define o que cada curso disponibiliza para estudo.</p>
          <form class="admin-form" data-endpoint="/admin/course-decks">
            <div class="field"><label>Curso*<select name="courseId" data-options="courses" required></select></label></div>
            <div class="field"><label>Baralho*<select name="deckId" data-options="decks" required></select></label></div>
            <div class="field"><label>Módulo<select name="moduleId" data-options="modules"></select></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Vincular</button></div>
          </form>
          <table class="list"><thead><tr><th>Curso</th><th>Baralho</th><th></th></tr></thead><tbody data-list="courseDecks"></tbody></table>
        </section>

        <!-- ESTUDANTES -->
        <section class="panel">
          <h2>Estudantes</h2>
          <p class="hint">A senha é guardada com hash seguro.</p>
          <form class="admin-form" data-endpoint="/admin/users">
            <div class="field"><label>Nome*<input name="name" required></label></div>
            <div class="field"><label>E-mail*<input name="email" type="email" required></label></div>
            <div class="field"><label>Senha*<input name="password" type="password" minlength="6" required></label></div>
            <div class="field"><label>Papel
              <select name="role"><option value="student">Estudante</option><option value="admin">Admin</option></select>
            </label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Criar</button></div>
          </form>
          <table class="list"><thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th></tr></thead><tbody data-list="users"></tbody></table>
        </section>

        <!-- MATRÍCULAS -->
        <section class="panel">
          <h2>Matrículas</h2>
          <p class="hint">O estudante só vê baralhos dos cursos em que está matriculado.</p>
          <form class="admin-form" data-endpoint="/admin/enrollments">
            <div class="field"><label>Estudante*<select name="userId" data-options="users" required></select></label></div>
            <div class="field"><label>Curso*<select name="courseId" data-options="courses" required></select></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Matricular</button></div>
          </form>
          <table class="list"><thead><tr><th>Estudante</th><th>Curso</th><th>Status</th><th></th></tr></thead><tbody data-list="enrollments"></tbody></table>
        </section>

        <!-- MATRÍCULA EM LOTE -->
        <section class="panel">
          <h2>Matrícula em lote (turma)</h2>
          <p class="hint">Cole uma linha por estudante: <code>nome,email,senha</code> (a senha é opcional). Estudantes que ainda não existem são criados; sem senha na linha, usam a “senha padrão”.</p>
          <form id="bulkForm" class="admin-form">
            <div class="field"><label>Curso*<select name="courseId" data-options="courses" required></select></label></div>
            <div class="field"><label>Senha padrão (novos)<input name="defaultPassword" type="text" placeholder="ex.: turma2026"></label></div>
            <div class="field" style="grid-column:1/-1"><label>Lista CSV (nome,email,senha)
              <textarea name="csv" rows="5" placeholder="Maria Silva,maria@escola.com&#10;João Souza,joao@escola.com,senhaDele"></textarea></label></div>
            <div class="actions"><button class="btn btn--primary btn-mini" type="submit">Matricular turma</button></div>
          </form>
          <div id="bulkResult" class="admin-msg" aria-live="polite"></div>
        </section>
      </div>

      <div class="dash__actions" style="margin-top:1rem">
        <a class="btn btn--ghost" href="/suinda/estudar/">← Voltar ao painel</a>
        <button class="btn btn--ghost" type="button" id="adminLogout">Sair</button>
      </div>
    </div>
  </div>
</main>

<script>
(function () {
  var API = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var LOGIN = "/suinda/login/";
  var token = localStorage.getItem("suinda_api_token");
  if (!token) { window.location.replace(LOGIN); return; }

  var loading = document.getElementById("adminLoading");
  var denied = document.getElementById("adminDenied");
  var rootEl = document.getElementById("adminRoot");
  var topMsg = document.getElementById("adminTopMsg");
  var data = {};

  function authHeaders(extra) {
    return Object.assign({ "Authorization": "Bearer " + token }, extra || {});
  }
  async function api(path, method, body) {
    var res = await fetch(API + path, {
      method: method || "GET",
      headers: authHeaders(body ? { "Content-Type": "application/json" } : {}),
      body: body ? JSON.stringify(body) : undefined
    });
    if (res.status === 401) { localStorage.removeItem("suinda_api_token"); window.location.replace(LOGIN); throw new Error("401"); }
    var json = {}; try { json = await res.json(); } catch (e) {}
    if (!res.ok) { var err = new Error(json.error || ("Erro " + res.status)); err.status = res.status; throw err; }
    return json;
  }
  function esc(s) { return String(s == null ? "" : s); }
  function flash(msg, ok) {
    topMsg.textContent = msg;
    topMsg.className = "admin-msg " + (ok ? "admin-msg--ok" : "admin-msg--error");
    if (ok) setTimeout(function () { if (topMsg.textContent === msg) topMsg.textContent = ""; }, 3500);
  }

  function courseTitle(id) { var c = data.courses.find(function (x) { return x.id === id; }); return c ? c.title : ("#" + id); }

  function fillSelects() {
    document.querySelectorAll("select[data-options]").forEach(function (sel) {
      var kind = sel.getAttribute("data-options");
      var current = sel.value;
      var optional = !sel.hasAttribute("required");
      var opts = optional ? '<option value="">—</option>' : "";
      (data[kind] || []).forEach(function (item) {
        var label;
        if (kind === "areas") label = item.name;
        else if (kind === "courses") label = item.title;
        else if (kind === "paths") label = item.title;
        else if (kind === "decks") label = item.title.trim() + " (" + item.totalCards + ")";
        else if (kind === "modules") label = courseTitle(item.course_id) + " — " + item.title;
        else if (kind === "users") label = item.name + " (" + item.email + ")";
        else label = item.title || item.name || item.id;
        opts += '<option value="' + item.id + '">' + esc(label) + "</option>";
      });
      sel.innerHTML = opts;
      if (current) sel.value = current;
    });
  }

  function row(cells) { return "<tr>" + cells.map(function (c) { return "<td>" + c + "</td>"; }).join("") + "</tr>"; }

  function renderLists() {
    var L = {};
    document.querySelectorAll("[data-list]").forEach(function (tb) { L[tb.getAttribute("data-list")] = tb; });

    L.areas.innerHTML = data.areas.map(function (a) { return row([esc(a.name) + (a.description ? '<br><small style="color:#5d6b6a">' + esc(a.description) + "</small>" : ""), '<span class="pill">' + esc(a.slug) + "</span>"]); }).join("") || row(["—", ""]);

    L.courses.innerHTML = data.courses.map(function (c) {
      var area = data.areas.find(function (a) { return a.id === c.area_id; });
      var st = c.active === 0
        ? '<span class="pill pill--soon">inativo</span>'
        : (c.status === "available" ? '<span class="pill">Disponível</span>' : '<span class="pill pill--soon">Em breve</span>');
      var acts =
        '<button class="btn btn-mini" data-edit-course="' + c.id + '">Editar</button> ' +
        '<button class="btn btn-mini ' + (c.active === 0 ? '' : 'btn-danger') + '" data-toggle-course="' + c.id + '" data-active="' + c.active + '">' +
        (c.active === 0 ? 'Ativar' : 'Inativar') + '</button>';
      return row([esc(c.title), st, area ? esc(area.name) : "—", acts]);
    }).join("") || row(["—", "", "", ""]);

    L.paths.innerHTML = data.paths.map(function (p) {
      var cs = data.pathCourses.filter(function (pc) { return pc.path_id === p.id; }).map(function (pc) { return esc(pc.course_title); });
      return row([esc(p.title), cs.length ? cs.join(" → ") : "<small>(sem cursos)</small>"]);
    }).join("") || row(["—", ""]);

    L.modules.innerHTML = data.modules.map(function (m) { return row([esc(courseTitle(m.course_id)), esc(m.title), m.position]); }).join("") || row(["—", "", ""]);

    L.courseDecks.innerHTML = data.courseDecks.map(function (cd) {
      return row([esc(cd.course_title), esc(cd.deck_title.trim()), '<button class="btn btn-mini btn-danger" data-del="course-decks" data-id="' + cd.id + '">Remover</button>']);
    }).join("") || row(["—", "", ""]);

    L.users.innerHTML = data.users.map(function (u) {
      var r = u.role === "admin" ? '<span class="pill pill--admin">admin</span>' : '<span class="pill">estudante</span>';
      return row([esc(u.name), esc(u.email), r]);
    }).join("") || row(["—", "", ""]);

    L.enrollments.innerHTML = data.enrollments.map(function (e) {
      return row([esc(e.user_name), esc(e.course_title), esc(e.status), '<button class="btn btn-mini btn-danger" data-del="enrollments" data-id="' + e.id + '">Cancelar</button>']);
    }).join("") || row(["—", "", "", ""]);
  }

  async function reload() {
    data = await api("/admin/overview");
    fillSelects();
    renderLists();
  }

  function formBody(form) {
    var body = {};
    form.querySelectorAll("input,select,textarea").forEach(function (inp) {
      if (!inp.name) return;
      var v = (inp.value || "").trim();
      if (v !== "") body[inp.name] = (inp.type === "number") ? Number(v) : v;
    });
    return body;
  }

  function openCourseEdit(course) {
    var f = document.getElementById("courseEditForm");
    f.elements.id.value = course.id;
    f.elements.title.value = course.title || "";
    f.elements.level.value = course.level || "introdutorio";
    f.elements.status.value = course.status || "available";
    f.elements.active.value = String(course.active);
    f.elements.areaId.value = course.area_id ? String(course.area_id) : "";
    f.elements.description.value = course.description || "";
    f.hidden = false;
    f.scrollIntoView({ behavior: "smooth", block: "center" });
    f.elements.title.focus();
  }

  function parseCsv(text) {
    return (text || "").split(/\r?\n/).map(function (line) {
      var parts = line.split(",");
      return { name: (parts[0] || "").trim(), email: (parts[1] || "").trim(), password: (parts[2] || "").trim() };
    }).filter(function (r) { return r.email !== "" || r.name !== ""; });
  }

  function bindForms() {
    // Formulários de criação simples (POST data-endpoint).
    document.querySelectorAll("form.admin-form[data-endpoint]").forEach(function (form) {
      form.addEventListener("submit", async function (ev) {
        ev.preventDefault();
        try {
          await api(form.getAttribute("data-endpoint"), "POST", formBody(form));
          form.reset();
          await reload();
          flash("Salvo com sucesso.", true);
        } catch (err) {
          flash(err.message || "Não foi possível salvar.", false);
        }
      });
    });

    // Editar curso (PUT) + cancelar.
    var editForm = document.getElementById("courseEditForm");
    editForm.addEventListener("submit", async function (ev) {
      ev.preventDefault();
      var body = formBody(editForm);
      var id = body.id; delete body.id;
      // garante envio de campos que podem ter ficado vazios (descrição/ativo)
      body.active = Number(editForm.elements.active.value);
      body.description = editForm.elements.description.value.trim();
      try {
        await api("/admin/courses/" + id, "PUT", body);
        editForm.hidden = true;
        await reload();
        flash("Curso atualizado.", true);
      } catch (err) { flash(err.message || "Falha ao atualizar o curso.", false); }
    });
    document.getElementById("courseEditCancel").addEventListener("click", function () { editForm.hidden = true; });

    // Matrícula em lote (CSV).
    var bulkForm = document.getElementById("bulkForm");
    var bulkResult = document.getElementById("bulkResult");
    bulkForm.addEventListener("submit", async function (ev) {
      ev.preventDefault();
      var students = parseCsv(bulkForm.elements.csv.value);
      if (!students.length) { bulkResult.className = "admin-msg admin-msg--error"; bulkResult.textContent = "Cole ao menos uma linha (nome,email)."; return; }
      var body = {
        courseId: Number(bulkForm.elements.courseId.value),
        defaultPassword: bulkForm.elements.defaultPassword.value.trim(),
        students: students
      };
      bulkResult.className = "admin-msg"; bulkResult.textContent = "Processando…";
      try {
        var res = await api("/admin/enrollments/bulk", "POST", body);
        var msg = "Matriculados: " + res.enrolled + " · Criados: " + res.created + " · Já matriculados: " + res.alreadyEnrolled;
        if (res.errors && res.errors.length) {
          msg += " · Erros: " + res.errors.length + " (" + res.errors.map(function (e) { return "linha " + e.line + ": " + e.reason; }).join("; ") + ")";
          bulkResult.className = "admin-msg admin-msg--error";
        } else {
          bulkResult.className = "admin-msg admin-msg--ok";
        }
        bulkResult.textContent = msg;
        bulkForm.elements.csv.value = "";
        await reload();
      } catch (err) {
        bulkResult.className = "admin-msg admin-msg--error";
        bulkResult.textContent = err.message || "Falha na matrícula em lote.";
      }
    });

    // Ações por linha: remover vínculo, editar/ativar curso.
    document.addEventListener("click", async function (ev) {
      var del = ev.target.closest("[data-del]");
      if (del) {
        if (!confirm("Confirmar remoção?")) return;
        try { await api("/admin/" + del.getAttribute("data-del") + "/" + del.getAttribute("data-id"), "DELETE"); await reload(); flash("Removido.", true); }
        catch (err) { flash(err.message || "Falha ao remover.", false); }
        return;
      }
      var edit = ev.target.closest("[data-edit-course]");
      if (edit) {
        var course = data.courses.find(function (c) { return c.id === Number(edit.getAttribute("data-edit-course")); });
        if (course) openCourseEdit(course);
        return;
      }
      var toggle = ev.target.closest("[data-toggle-course]");
      if (toggle) {
        var newActive = Number(toggle.getAttribute("data-active")) === 1 ? 0 : 1;
        if (newActive === 0 && !confirm("Inativar o curso? Os estudantes deixarão de ver seus baralhos.")) return;
        try { await api("/admin/courses/" + toggle.getAttribute("data-toggle-course"), "PUT", { active: newActive }); await reload(); flash(newActive ? "Curso ativado." : "Curso inativado.", true); }
        catch (err) { flash(err.message || "Falha ao alterar o curso.", false); }
        return;
      }
    });

    document.getElementById("adminLogout").addEventListener("click", function () {
      localStorage.removeItem("suinda_api_token");
      localStorage.removeItem("suinda_current_user");
      window.location.replace(LOGIN);
    });
  }

  // Gate de admin: confirma o papel no backend antes de mostrar a tela.
  api("/me").then(function (res) {
    var user = res.user || {};
    if (user.role !== "admin") { loading.hidden = true; denied.hidden = false; return; }
    return reload().then(function () {
      loading.hidden = true;
      rootEl.hidden = false;
      bindForms();
    });
  }).catch(function (err) {
    if (err && err.status === 401) return;
    loading.textContent = "Não foi possível carregar a administração. Tente recarregar a página.";
  });
})();
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>

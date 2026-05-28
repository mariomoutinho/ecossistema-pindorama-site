/* Organizador de Estudos — orquestrador da página.
   Responsável por: renderização, eventos da UI, modais e feedback. */
(function () {
  "use strict";

  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  // ----- Estado de UI -----
  const ui = {
    view: "semana",         // "semana" | "dia"
    refData: SuindaAgenda.hojeISO(),
    filtros: { materiaId: "", categoria: "", data: "", prioridade: "", status: "", busca: "" }
  };

  // ----- Inicialização -----
  document.addEventListener("DOMContentLoaded", () => {
    montarOpcoesCategorias();
    bindHeader();
    bindEstudante();
    bindMateriaForm();
    bindTarefaForm();
    bindAgendaControles();
    bindFiltros();
    bindGerais();
    renderTudo();
  });

  // ----- Toast -----
  function toast(msg, tipo) {
    const host = $("#toast-host");
    const el = document.createElement("div");
    el.className = "toast" + (tipo ? " toast--" + tipo : "");
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  // ----- Modais -----
  function abrirModal(id) { $("#" + id).classList.remove("hidden"); }
  function fecharModal(id) { $("#" + id).classList.add("hidden"); }

  // ----- Opções dinâmicas em selects -----
  function montarOpcoesCategorias() {
    const selects = $$("[data-fill='categorias']");
    selects.forEach(sel => {
      const incluiVazio = sel.dataset.optional === "1";
      sel.innerHTML = (incluiVazio ? "<option value=''>Todas</option>" : "") +
        SuindaCategorias.all().map(c => `<option value="${c.id}">${c.icone} ${c.nome}</option>`).join("");
    });
  }

  function montarOpcoesMaterias() {
    const selects = $$("[data-fill='materias']");
    const mats = SuindaMaterias.listar();
    selects.forEach(sel => {
      const incluiVazio = sel.dataset.optional === "1";
      const placeholder = sel.dataset.placeholder || "Selecione…";
      sel.innerHTML =
        (incluiVazio ? "<option value=''>" + placeholder + "</option>" : "") +
        mats.map(m => `<option value="${m.id}">${m.nome}</option>`).join("");
    });
  }

  // ============================================================
  //  ESTUDANTE
  // ============================================================
  function bindEstudante() {
    $("#form-estudante").addEventListener("submit", (e) => {
      e.preventDefault();
      const f = e.target;
      try {
        SuindaEstudante.salvar({
          nome: f.nome.value,
          objetivo: f.objetivo.value,
          cargaSemanal: f.cargaSemanal.value
        });
        toast("Perfil do(a) estudante salvo.", "ok");
        renderEstudante();
        renderResumo();
      } catch (err) { toast(err.message, "danger"); }
    });
  }

  function renderEstudante() {
    const e = SuindaEstudante.obter();
    $("#estudante-nome-topo").textContent = e.nome;
    $("#estudante-objetivo-topo").textContent = e.objetivo || "Sem objetivo definido";
    const f = $("#form-estudante");
    f.nome.value = e.nome === "Estudante" ? "" : e.nome;
    f.objetivo.value = e.objetivo;
    f.cargaSemanal.value = e.cargaSemanal || "";
  }

  // ============================================================
  //  MATÉRIAS
  // ============================================================
  function bindMateriaForm() {
    $("#btn-nova-materia").addEventListener("click", () => abrirFormMateria(null));
    const form = $("#form-materia");
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      try {
        SuindaMaterias.salvar({
          id: form.id.value || null,
          nome: form.nome.value,
          cor: form.cor.value,
          prioridade: form.prioridade.value,
          cargaSemanal: form.cargaSemanal.value,
          observacoes: form.observacoes.value,
          status: form.status.value
        });
        toast("Matéria salva.", "ok");
        fecharModal("modal-materia");
        montarOpcoesMaterias();
        renderMaterias();
        renderResumo();
        renderTarefas();
        renderAgenda();
      } catch (err) { toast(err.message, "danger"); }
    });
    $("#modal-materia .modal-close").addEventListener("click", () => fecharModal("modal-materia"));
    $("#modal-materia").addEventListener("click", (e) => { if (e.target.id === "modal-materia") fecharModal("modal-materia"); });
  }

  function abrirFormMateria(id) {
    const m = id ? SuindaMaterias.obter(id) : null;
    const f = $("#form-materia");
    f.reset();
    f.id.value = m ? m.id : "";
    f.nome.value = m ? m.nome : "";
    f.cor.value = m ? m.cor : "#4f7a4a";
    f.prioridade.value = m ? m.prioridade : "media";
    f.cargaSemanal.value = m ? m.cargaSemanal : "";
    f.observacoes.value = m ? m.observacoes : "";
    f.status.value = m ? m.status : "ativa";
    $("#modal-materia-titulo").textContent = m ? "Editar matéria" : "Nova matéria";
    abrirModal("modal-materia");
  }

  function renderMaterias() {
    const lista = SuindaMaterias.listar();
    const host = $("#lista-materias");
    if (!lista.length) {
      host.innerHTML = `<div class="empty">Nenhuma matéria cadastrada ainda.<br/><button class="btn btn--primary" id="empty-nova-materia" style="margin-top:8px;">Cadastrar primeira matéria</button></div>`;
      $("#empty-nova-materia").addEventListener("click", () => abrirFormMateria(null));
      return;
    }
    const minutosMat = SuindaTarefas.minutosPorMateria();
    host.innerHTML = lista.map(m => {
      const planejado = (minutosMat[m.id] || 0) / 60;
      const meta = m.cargaSemanal || 0;
      const excesso = meta > 0 && planejado > meta;
      return `
        <article class="materia-card" style="--mat-cor:${m.cor};">
          <header>
            <div class="materia-cor"></div>
            <div class="materia-nome">${escapeHTML(m.nome)}</div>
            <span class="status-pill status-${m.status === "concluida" ? "concluida" : m.status}">${rotStatusMat(m.status)}</span>
          </header>
          <div class="materia-meta">
            <span class="tag tag--prio-${m.prioridade}">${rotPrio(m.prioridade)}</span>
            ${meta > 0 ? `<span class="tag ${excesso ? "tag--prio-alta" : ""}" title="Planejado vs meta">${planejado.toFixed(1)}h / ${meta}h</span>` : ""}
          </div>
          ${m.observacoes ? `<p class="muted small-obs">${escapeHTML(m.observacoes)}</p>` : ""}
          ${excesso ? `<div class="aviso aviso--warn">⚠ Carga planejada acima da meta</div>` : ""}
          <div class="row tight">
            <button class="btn btn--sm btn--ghost" data-act="edit-mat" data-id="${m.id}">Editar</button>
            <button class="btn btn--sm btn--danger" data-act="del-mat" data-id="${m.id}">Remover</button>
          </div>
        </article>`;
    }).join("");
    host.addEventListener("click", onMateriaClick);
  }

  function onMateriaClick(e) {
    const btn = e.target.closest("button[data-act]");
    if (!btn) return;
    const id = btn.dataset.id;
    if (btn.dataset.act === "edit-mat") abrirFormMateria(id);
    if (btn.dataset.act === "del-mat") {
      if (confirm("Remover esta matéria? Tarefas vinculadas ficarão sem matéria.")) {
        SuindaMaterias.remover(id);
        toast("Matéria removida.", "warn");
        montarOpcoesMaterias();
        renderMaterias(); renderTarefas(); renderAgenda(); renderResumo();
      }
    }
  }

  // ============================================================
  //  TAREFAS
  // ============================================================
  function bindTarefaForm() {
    $("#btn-nova-tarefa").addEventListener("click", () => abrirFormTarefa(null));
    const form = $("#form-tarefa");
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      try {
        SuindaTarefas.salvar({
          id: form.id.value || null,
          titulo: form.titulo.value,
          descricao: form.descricao.value,
          materiaId: form.materiaId.value || null,
          categoria: form.categoria.value,
          data: form.data.value,
          horaInicio: form.horaInicio.value,
          horaFim: form.horaFim.value,
          duracaoMin: form.duracaoMin.value,
          prioridade: form.prioridade.value,
          status: form.status.value
        });
        toast("Tarefa salva.", "ok");
        fecharModal("modal-tarefa");
        renderTarefas(); renderAgenda(); renderResumo(); renderMaterias();
      } catch (err) { toast(err.message, "danger"); }
    });
    $("#modal-tarefa .modal-close").addEventListener("click", () => fecharModal("modal-tarefa"));
    $("#modal-tarefa").addEventListener("click", (e) => { if (e.target.id === "modal-tarefa") fecharModal("modal-tarefa"); });

    // a categoria define se matéria é obrigatória ou não
    form.categoria.addEventListener("change", atualizarRequiredMateria);
  }

  function atualizarRequiredMateria() {
    const form = $("#form-tarefa");
    const sem = SuindaCategorias.permiteSemMateria(form.categoria.value);
    form.materiaId.required = !sem;
    $("#tarefa-materia-hint").textContent = sem ? "(opcional para esta categoria)" : "(obrigatória)";
  }

  function abrirFormTarefa(idOuPrefill) {
    montarOpcoesMaterias();
    const form = $("#form-tarefa");
    form.reset();
    const isId = typeof idOuPrefill === "string";
    const isPrefill = !!idOuPrefill && typeof idOuPrefill === "object";
    const t = isId ? SuindaTarefas.obter(idOuPrefill) : null;

    form.id.value = t ? t.id : "";
    form.titulo.value = t ? t.titulo : "";
    form.descricao.value = t ? t.descricao : "";
    form.materiaId.value = t ? (t.materiaId || "") : "";
    form.categoria.value = t ? t.categoria : "estudo";
    form.data.value = t ? t.data : (isPrefill ? idOuPrefill.data || SuindaAgenda.hojeISO() : SuindaAgenda.hojeISO());
    form.horaInicio.value = t ? t.horaInicio : (isPrefill ? idOuPrefill.horaInicio || "" : "");
    form.horaFim.value = t ? t.horaFim : "";
    form.duracaoMin.value = t ? (t.duracaoMin || "") : "";
    form.prioridade.value = t ? t.prioridade : "media";
    form.status.value = t ? t.status : "pendente";

    $("#modal-tarefa-titulo").textContent = t ? "Editar tarefa" : "Nova tarefa";
    atualizarRequiredMateria();
    abrirModal("modal-tarefa");
  }

  function renderTarefas() {
    const lista = SuindaTarefas.filtrar(ui.filtros)
      .slice()
      .sort(ordenarTarefas);
    const host = $("#lista-tarefas");
    if (!lista.length) {
      host.innerHTML = `<div class="empty">Nenhuma tarefa corresponde aos filtros.</div>`;
      return;
    }
    const conflitos = SuindaTarefas.detectarConflitos(SuindaTarefas.listar());
    host.innerHTML = lista.map(t => tarefaItemHTML(t, conflitos.has(t.id))).join("");
    host.addEventListener("click", onTarefaListaClick);
  }

  function tarefaItemHTML(t, emConflito) {
    const cat = SuindaCategorias.get(t.categoria);
    const mat = t.materiaId ? SuindaMaterias.obter(t.materiaId) : null;
    const horario = t.horaInicio ? `${t.horaInicio}${t.horaFim ? " – " + t.horaFim : ""}` : "sem horário";
    const dataFmt = t.data ? new Date(t.data + "T00:00").toLocaleDateString("pt-BR") : "sem data";
    return `
      <li class="tarefa-item status-${t.status} ${emConflito ? "em-conflito" : ""}" style="--cat-cor:${cat.cor};--mat-cor:${mat ? mat.cor : cat.cor};">
        <div class="tarefa-marc"><span class="cat-ic" title="${cat.nome}">${cat.icone}</span></div>
        <div class="tarefa-corpo">
          <div class="tarefa-titulo">${escapeHTML(t.titulo)}</div>
          <div class="tarefa-meta">
            ${mat ? `<span class="tag" style="background:${mat.cor}20;color:${mat.cor};border-color:transparent;"><span class="dot" style="background:${mat.cor}"></span>${escapeHTML(mat.nome)}</span>` : `<span class="tag muted">sem matéria</span>`}
            <span class="tag">${cat.icone} ${cat.nome}</span>
            <span class="tag tag--prio-${t.prioridade}">${rotPrio(t.prioridade)}</span>
            <span class="tag">${dataFmt} · ${horario}</span>
            ${emConflito ? `<span class="tag tag--prio-alta">⚠ Conflito de horário</span>` : ""}
          </div>
          ${t.descricao ? `<p class="muted small-obs">${escapeHTML(t.descricao)}</p>` : ""}
        </div>
        <div class="tarefa-acoes">
          ${t.status !== "concluida"
            ? `<button class="btn btn--sm btn--primary" data-act="concluir" data-id="${t.id}" title="Concluir">✓</button>`
            : `<button class="btn btn--sm btn--ghost" data-act="reabrir" data-id="${t.id}" title="Reabrir">↺</button>`}
          <button class="btn btn--sm btn--ghost" data-act="edit-tar" data-id="${t.id}" title="Editar">✎</button>
          <button class="btn btn--sm btn--ghost" data-act="dup-tar" data-id="${t.id}" title="Duplicar">⎘</button>
          <button class="btn btn--sm btn--danger" data-act="del-tar" data-id="${t.id}" title="Remover">×</button>
        </div>
      </li>`;
  }

  function onTarefaListaClick(e) {
    const btn = e.target.closest("button[data-act]");
    if (!btn) return;
    const id = btn.dataset.id;
    const act = btn.dataset.act;
    if (act === "concluir") SuindaTarefas.mudarStatus(id, "concluida");
    if (act === "reabrir") SuindaTarefas.mudarStatus(id, "pendente");
    if (act === "edit-tar") return abrirFormTarefa(id);
    if (act === "dup-tar") SuindaTarefas.duplicar(id);
    if (act === "del-tar") {
      if (!confirm("Remover esta tarefa?")) return;
      SuindaTarefas.remover(id);
    }
    renderTarefas(); renderAgenda(); renderResumo(); renderMaterias();
  }

  function ordenarTarefas(a, b) {
    const da = (a.data || "9999") + (a.horaInicio || "99:99");
    const db = (b.data || "9999") + (b.horaInicio || "99:99");
    return da.localeCompare(db);
  }

  // ============================================================
  //  AGENDA
  // ============================================================
  function bindAgendaControles() {
    $$("[data-view]").forEach(b => b.addEventListener("click", () => {
      ui.view = b.dataset.view;
      $$("[data-view]").forEach(x => x.classList.toggle("active", x === b));
      renderAgenda();
    }));
    $("#agenda-anterior").addEventListener("click", () => {
      ui.refData = SuindaAgenda.adicionarDias(ui.refData, ui.view === "dia" ? -1 : -7);
      renderAgenda();
    });
    $("#agenda-proximo").addEventListener("click", () => {
      ui.refData = SuindaAgenda.adicionarDias(ui.refData, ui.view === "dia" ? 1 : 7);
      renderAgenda();
    });
    $("#agenda-hoje").addEventListener("click", () => {
      ui.refData = SuindaAgenda.hojeISO();
      renderAgenda();
    });
  }

  function renderAgenda() {
    const cfg = SuindaStorage.read().configAgenda;
    const horas = [];
    for (let h = cfg.horaInicio; h <= cfg.horaFim; h++) horas.push(h);
    const dias = ui.view === "dia" ? [ui.refData] : SuindaAgenda.semanaDe(ui.refData);

    $("#agenda-titulo").textContent = ui.view === "dia"
      ? SuindaAgenda.rotuloLongo(ui.refData)
      : `${SuindaAgenda.rotuloCurto(dias[0])} — ${SuindaAgenda.rotuloCurto(dias[6])}`;

    const conflitos = SuindaTarefas.detectarConflitos(SuindaTarefas.listar());
    const tarefasPorDia = agruparTarefas(dias);

    const host = $("#agenda");
    host.className = "agenda agenda--" + ui.view;
    host.style.setProperty("--n-dias", dias.length);

    let html = `<div class="agenda-head"><div class="agenda-corner"></div>`;
    dias.forEach(iso => {
      const isHoje = iso === SuindaAgenda.hojeISO();
      html += `<div class="agenda-dia-head ${isHoje ? "hoje" : ""}">${SuindaAgenda.rotuloCurto(iso)}</div>`;
    });
    html += `</div>`;
    html += `<div class="agenda-grid">`;

    // coluna de horas
    html += `<div class="col-horas">`;
    horas.forEach(h => html += `<div class="hora-row">${String(h).padStart(2, "0")}:00</div>`);
    html += `</div>`;

    // colunas de dias
    dias.forEach(iso => {
      html += `<div class="col-dia" data-data="${iso}">`;
      horas.forEach(h => {
        html += `<div class="slot" data-data="${iso}" data-hora="${String(h).padStart(2, "0")}:00" title="Clique para adicionar tarefa às ${h}h"></div>`;
      });
      // blocos das tarefas absolutos sobre os slots
      (tarefasPorDia[iso] || []).forEach(t => {
        const bloco = blocoTarefaHTML(t, cfg, conflitos.has(t.id));
        if (bloco) html += bloco;
      });
      html += `</div>`;
    });
    html += `</div>`;
    host.innerHTML = html;

    // clique em slot vazio → criar tarefa pré-preenchida
    $$(".slot", host).forEach(s => s.addEventListener("click", () => {
      abrirFormTarefa({ data: s.dataset.data, horaInicio: s.dataset.hora });
    }));
    // clique em bloco existente → editar
    $$(".bloco-tarefa", host).forEach(b => b.addEventListener("click", (e) => {
      e.stopPropagation();
      abrirFormTarefa(b.dataset.id);
    }));
  }

  function agruparTarefas(diasISO) {
    const set = new Set(diasISO);
    const por = {};
    SuindaTarefas.listar().forEach(t => {
      if (!set.has(t.data)) return;
      (por[t.data] = por[t.data] || []).push(t);
    });
    return por;
  }

  function blocoTarefaHTML(t, cfg, emConflito) {
    if (!t.horaInicio || !t.horaFim) return "";
    const startMin = SuindaAgenda.hhmmParaMin(t.horaInicio);
    const endMin = SuindaAgenda.hhmmParaMin(t.horaFim);
    const gridStart = cfg.horaInicio * 60;
    const gridEnd = (cfg.horaFim + 1) * 60;
    if (endMin <= gridStart || startMin >= gridEnd) return "";
    const top = Math.max(0, startMin - gridStart);
    const altura = Math.min(gridEnd, endMin) - Math.max(gridStart, startMin);
    const cat = SuindaCategorias.get(t.categoria);
    const mat = t.materiaId ? SuindaMaterias.obter(t.materiaId) : null;
    const cor = mat ? mat.cor : cat.cor;
    return `<div class="bloco-tarefa ${emConflito ? "conflito" : ""} status-${t.status}"
              style="--top:${top}px;--altura:${Math.max(altura, 22)}px;--bloco-cor:${cor};"
              data-id="${t.id}"
              title="${escapeHTML(t.titulo)} (${t.horaInicio}–${t.horaFim})">
              <div class="bloco-titulo">${cat.icone} ${escapeHTML(t.titulo)}</div>
              <div class="bloco-meta">${t.horaInicio} – ${t.horaFim}${mat ? " · " + escapeHTML(mat.nome) : ""}</div>
            </div>`;
  }

  // ============================================================
  //  FILTROS
  // ============================================================
  function bindFiltros() {
    $$("#filtros [data-filter]").forEach(el => {
      el.addEventListener("input", () => {
        ui.filtros[el.dataset.filter] = el.value;
        renderTarefas();
      });
    });
    $("#btn-limpar-filtros").addEventListener("click", () => {
      Object.keys(ui.filtros).forEach(k => ui.filtros[k] = "");
      $$("#filtros [data-filter]").forEach(el => el.value = "");
      renderTarefas();
    });
  }

  // ============================================================
  //  RESUMO + DISPONIBILIDADE
  // ============================================================
  function renderResumo() {
    const tarefas = SuindaTarefas.listar();
    const totalPlanejado = tarefas
      .filter(t => t.status !== "cancelada" && t.horaInicio && t.horaFim)
      .reduce((s, t) => s + SuindaTarefas.diffMin(t.horaInicio, t.horaFim), 0);
    const pendentes = tarefas.filter(t => t.status === "pendente" || t.status === "em_andamento").length;
    const concluidas = tarefas.filter(t => t.status === "concluida").length;

    $("#resumo-horas").textContent = (totalPlanejado / 60).toFixed(1) + "h";
    $("#resumo-pendentes").textContent = pendentes;
    $("#resumo-concluidas").textContent = concluidas;

    // aviso global de carga
    const meta = SuindaEstudante.obter().cargaSemanal;
    const aviso = $("#aviso-carga");
    if (meta > 0 && totalPlanejado / 60 > meta) {
      aviso.classList.remove("hidden");
      aviso.textContent = `⚠ Você planejou ${(totalPlanejado / 60).toFixed(1)}h, acima da meta semanal de ${meta}h.`;
    } else {
      aviso.classList.add("hidden");
    }

    renderResumoMatYCat();
  }

  function renderResumoMatYCat() {
    const minutosMat = SuindaTarefas.minutosPorMateria();
    const minutosCat = SuindaTarefas.minutosPorCategoria();
    const mats = SuindaMaterias.listar();

    $("#resumo-materias").innerHTML = mats.length === 0
      ? `<div class="muted">Cadastre matérias para ver o resumo.</div>`
      : mats.map(m => {
          const h = (minutosMat[m.id] || 0) / 60;
          return `<div class="resumo-linha"><span class="dot" style="background:${m.cor}"></span><span>${escapeHTML(m.nome)}</span><strong>${h.toFixed(1)}h</strong></div>`;
        }).join("");

    $("#resumo-categorias").innerHTML = Object.keys(minutosCat).length === 0
      ? `<div class="muted">Sem horários planejados ainda.</div>`
      : SuindaCategorias.all()
          .filter(c => minutosCat[c.id])
          .map(c => `<div class="resumo-linha"><span>${c.icone} ${c.nome}</span><strong>${(minutosCat[c.id] / 60).toFixed(1)}h</strong></div>`)
          .join("");
  }

  // ============================================================
  //  Header / Exportação / Reset
  // ============================================================
  function bindHeader() {
    $("#btn-export").addEventListener("click", () => {
      const blob = new Blob([SuindaStorage.exportJSON()], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "suinda-organizador.json";
      a.click();
      URL.revokeObjectURL(url);
      toast("Backup gerado.", "ok");
    });
    $("#btn-import").addEventListener("click", () => $("#input-import").click());
    $("#input-import").addEventListener("change", (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const r = new FileReader();
      r.onload = () => {
        try {
          SuindaStorage.importJSON(r.result);
          toast("Backup restaurado.", "ok");
          renderTudo();
        } catch (err) { toast("Arquivo inválido.", "danger"); }
      };
      r.readAsText(file);
    });
    $("#btn-reset").addEventListener("click", () => {
      if (!confirm("Apagar TODOS os dados do Organizador? Esta ação não pode ser desfeita.")) return;
      SuindaStorage.reset();
      toast("Dados apagados.", "warn");
      renderTudo();
    });
  }

  function bindGerais() {
    // ESC fecha modais abertos
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        $$(".modal-backdrop:not(.hidden)").forEach(m => m.classList.add("hidden"));
      }
    });
  }

  // ============================================================
  //  Render tudo
  // ============================================================
  function renderTudo() {
    montarOpcoesMaterias();
    renderEstudante();
    renderMaterias();
    renderTarefas();
    renderAgenda();
    renderResumo();
  }

  // ----- Helpers -----
  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c]));
  }
  function rotPrio(p) { return p === "alta" ? "Alta" : p === "media" ? "Média" : "Baixa"; }
  function rotStatusMat(s) { return s === "pausada" ? "Pausada" : s === "concluida" ? "Concluída" : "Ativa"; }
})();

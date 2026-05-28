/* Tarefas: CRUD, filtros, validação de matéria obrigatória e conflitos. */
(function (global) {
  "use strict";

  const PRIORIDADES = ["baixa", "media", "alta"];
  const STATUS = ["pendente", "em_andamento", "concluida", "adiada", "cancelada"];

  function listar() { return SuindaStorage.read().tarefas; }
  function obter(id) { return listar().find(t => t.id === id) || null; }

  function normalizar(input) {
    const dur = Number(input.duracaoMin);
    return {
      id: input.id || SuindaStorage.uid("tar"),
      titulo: (input.titulo || "").trim(),
      descricao: (input.descricao || "").trim(),
      materiaId: input.materiaId || null,
      categoria: input.categoria || "estudo",
      data: input.data || "",         // yyyy-mm-dd
      horaInicio: input.horaInicio || "",  // hh:mm
      horaFim: input.horaFim || "",        // hh:mm — derivado se vier duracao
      duracaoMin: Number.isFinite(dur) && dur > 0 ? dur : null,
      prioridade: PRIORIDADES.includes(input.prioridade) ? input.prioridade : "media",
      status: STATUS.includes(input.status) ? input.status : "pendente"
    };
  }

  function derivarHoraFim(t) {
    if (t.horaFim) return t.horaFim;
    if (!t.horaInicio || !t.duracaoMin) return "";
    const [h, m] = t.horaInicio.split(":").map(Number);
    const totalMin = h * 60 + m + t.duracaoMin;
    const fh = Math.floor(totalMin / 60) % 24;
    const fm = totalMin % 60;
    return String(fh).padStart(2, "0") + ":" + String(fm).padStart(2, "0");
  }

  function salvar(input) {
    const s = SuindaStorage.read();
    const t = normalizar(input);
    if (!t.titulo) throw new Error("Informe o título da tarefa.");
    if (!SuindaCategorias.permiteSemMateria(t.categoria) && !t.materiaId) {
      throw new Error("Esta categoria exige uma matéria vinculada.");
    }
    t.horaFim = derivarHoraFim(t);
    const idx = s.tarefas.findIndex(x => x.id === t.id);
    if (idx >= 0) s.tarefas[idx] = t; else s.tarefas.push(t);
    SuindaStorage.write(s);
    return t;
  }

  function remover(id) {
    const s = SuindaStorage.read();
    s.tarefas = s.tarefas.filter(t => t.id !== id);
    SuindaStorage.write(s);
  }

  function duplicar(id) {
    const orig = obter(id);
    if (!orig) return null;
    const copia = Object.assign({}, orig, { id: null, titulo: orig.titulo + " (cópia)", status: "pendente" });
    return salvar(copia);
  }

  function mudarStatus(id, status) {
    const t = obter(id);
    if (!t) return null;
    return salvar(Object.assign({}, t, { status }));
  }

  function filtrar({ materiaId, categoria, data, prioridade, status, busca } = {}) {
    return listar().filter(t => {
      if (materiaId && t.materiaId !== materiaId) return false;
      if (categoria && t.categoria !== categoria) return false;
      if (data && t.data !== data) return false;
      if (prioridade && t.prioridade !== prioridade) return false;
      if (status && t.status !== status) return false;
      if (busca) {
        const q = busca.toLowerCase();
        if (!t.titulo.toLowerCase().includes(q) && !t.descricao.toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }

  /* Conflitos: tarefas no mesmo dia cujos intervalos se sobrepõem. */
  function detectarConflitos(tarefas) {
    const porDia = {};
    tarefas.forEach(t => {
      if (!t.data || !t.horaInicio || !t.horaFim) return;
      if (t.status === "concluida" || t.status === "cancelada") return;
      (porDia[t.data] = porDia[t.data] || []).push(t);
    });
    const conflitos = new Set();
    Object.values(porDia).forEach(arr => {
      for (let i = 0; i < arr.length; i++) {
        for (let j = i + 1; j < arr.length; j++) {
          if (intersecta(arr[i], arr[j])) { conflitos.add(arr[i].id); conflitos.add(arr[j].id); }
        }
      }
    });
    return conflitos;
  }

  function intersecta(a, b) {
    return a.horaInicio < b.horaFim && b.horaInicio < a.horaFim;
  }

  /* Soma de minutos planejados por matéria, considerando apenas tarefas com início e fim definidos. */
  function minutosPorMateria() {
    const totais = {};
    listar().forEach(t => {
      if (!t.materiaId || !t.horaInicio || !t.horaFim) return;
      if (t.status === "cancelada") return;
      totais[t.materiaId] = (totais[t.materiaId] || 0) + diffMin(t.horaInicio, t.horaFim);
    });
    return totais;
  }

  function minutosPorCategoria() {
    const totais = {};
    listar().forEach(t => {
      if (!t.horaInicio || !t.horaFim) return;
      if (t.status === "cancelada") return;
      totais[t.categoria] = (totais[t.categoria] || 0) + diffMin(t.horaInicio, t.horaFim);
    });
    return totais;
  }

  function diffMin(ini, fim) {
    const [hi, mi] = ini.split(":").map(Number);
    const [hf, mf] = fim.split(":").map(Number);
    return (hf * 60 + mf) - (hi * 60 + mi);
  }

  global.SuindaTarefas = {
    listar, obter, salvar, remover, duplicar, mudarStatus, filtrar,
    detectarConflitos, minutosPorMateria, minutosPorCategoria, diffMin,
    PRIORIDADES, STATUS
  };
})(window);

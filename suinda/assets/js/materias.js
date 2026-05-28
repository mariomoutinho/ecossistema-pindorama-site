/* Matérias: CRUD. */
(function (global) {
  "use strict";

  const PRIORIDADES = ["baixa", "media", "alta"];
  const STATUS = ["ativa", "pausada", "concluida"];

  function listar() {
    return SuindaStorage.read().materias;
  }

  function obter(id) {
    return listar().find(m => m.id === id) || null;
  }

  function normalizar(input) {
    return {
      id: input.id || SuindaStorage.uid("mat"),
      nome: (input.nome || "").trim(),
      cor: input.cor || "#4f7a4a",
      prioridade: PRIORIDADES.includes(input.prioridade) ? input.prioridade : "media",
      cargaSemanal: Math.max(0, Number(input.cargaSemanal) || 0),
      observacoes: (input.observacoes || "").trim(),
      status: STATUS.includes(input.status) ? input.status : "ativa"
    };
  }

  function salvar(input) {
    const s = SuindaStorage.read();
    const mat = normalizar(input);
    if (!mat.nome) throw new Error("Informe o nome da matéria.");
    const idx = s.materias.findIndex(m => m.id === mat.id);
    if (idx >= 0) s.materias[idx] = mat; else s.materias.push(mat);
    SuindaStorage.write(s);
    return mat;
  }

  function remover(id) {
    const s = SuindaStorage.read();
    s.materias = s.materias.filter(m => m.id !== id);
    // tarefas vinculadas perdem a matéria — viram tarefas livres
    s.tarefas.forEach(t => { if (t.materiaId === id) t.materiaId = null; });
    SuindaStorage.write(s);
  }

  global.SuindaMaterias = { listar, obter, salvar, remover, PRIORIDADES, STATUS };
})(window);

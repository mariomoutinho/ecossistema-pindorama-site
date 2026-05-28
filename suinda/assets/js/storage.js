/* Camada de persistência do Organizador de Estudos.
   Hoje grava em localStorage; amanhã basta trocar a implementação
   destes 4 métodos por chamadas fetch() ao backend. */
(function (global) {
  "use strict";

  const STORAGE_KEY = "suinda.organizador.v1";

  const defaultState = () => ({
    estudante: { nome: "", objetivo: "", cargaSemanal: 0 },
    materias: [],
    tarefas: [],
    disponibilidade: {
      // minutos por dia da semana, 0=domingo … 6=sábado
      0: 0, 1: 120, 2: 120, 3: 120, 4: 120, 5: 120, 6: 0
    },
    configAgenda: {
      horaInicio: 6,    // hora cheia em que a agenda começa
      horaFim: 23,      // hora cheia em que a agenda termina
      slotMinutos: 30
    }
  });

  function read() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return defaultState();
      const parsed = JSON.parse(raw);
      // mescla defensiva — campos novos do esquema não quebram dados antigos
      return Object.assign(defaultState(), parsed, {
        estudante: Object.assign(defaultState().estudante, parsed.estudante || {}),
        disponibilidade: Object.assign(defaultState().disponibilidade, parsed.disponibilidade || {}),
        configAgenda: Object.assign(defaultState().configAgenda, parsed.configAgenda || {})
      });
    } catch (e) {
      console.warn("Storage corrompido, resetando.", e);
      return defaultState();
    }
  }

  function write(state) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  }

  function reset() {
    localStorage.removeItem(STORAGE_KEY);
  }

  function exportJSON() {
    return JSON.stringify(read(), null, 2);
  }

  function importJSON(text) {
    const data = JSON.parse(text);
    write(Object.assign(defaultState(), data));
  }

  function uid(prefix) {
    return (prefix || "id") + "_" + Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-4);
  }

  global.SuindaStorage = { read, write, reset, exportJSON, importJSON, uid, STORAGE_KEY };
})(window);

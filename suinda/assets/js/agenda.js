/* Agenda: visualização semanal e diária com blocos de horário. */
(function (global) {
  "use strict";

  const DIAS = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
  const DIAS_LONGOS = ["Domingo", "Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado"];

  function semanaDe(data) {
    const d = parseISO(data);
    const dia = d.getDay();
    const inicio = new Date(d);
    inicio.setDate(d.getDate() - dia);
    const dias = [];
    for (let i = 0; i < 7; i++) {
      const dt = new Date(inicio);
      dt.setDate(inicio.getDate() + i);
      dias.push(formatISO(dt));
    }
    return dias;
  }

  function parseISO(s) {
    if (!s) return new Date();
    const [y, m, d] = s.split("-").map(Number);
    return new Date(y, m - 1, d);
  }

  function formatISO(d) {
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const dia = String(d.getDate()).padStart(2, "0");
    return d.getFullYear() + "-" + m + "-" + dia;
  }

  function rotuloCurto(iso) {
    const d = parseISO(iso);
    return DIAS[d.getDay()] + " " + String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0");
  }

  function rotuloLongo(iso) {
    const d = parseISO(iso);
    return DIAS_LONGOS[d.getDay()] + ", " + String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0") + "/" + d.getFullYear();
  }

  function hojeISO() { return formatISO(new Date()); }

  function adicionarDias(iso, n) {
    const d = parseISO(iso);
    d.setDate(d.getDate() + n);
    return formatISO(d);
  }

  function minutosParaHHMM(min) {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
  }

  function hhmmParaMin(hhmm) {
    if (!hhmm) return 0;
    const [h, m] = hhmm.split(":").map(Number);
    return h * 60 + m;
  }

  global.SuindaAgenda = {
    DIAS, DIAS_LONGOS, semanaDe, parseISO, formatISO,
    rotuloCurto, rotuloLongo, hojeISO, adicionarDias,
    minutosParaHHMM, hhmmParaMin
  };
})(window);

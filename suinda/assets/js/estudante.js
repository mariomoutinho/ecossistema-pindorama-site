/* Estudante: cadastro do perfil. */
(function (global) {
  "use strict";

  function obter() {
    const s = SuindaStorage.read();
    const nome = (s.estudante.nome || "").trim() || "Estudante";
    return { nome, objetivo: s.estudante.objetivo || "", cargaSemanal: Number(s.estudante.cargaSemanal) || 0 };
  }

  function salvar(dados) {
    const s = SuindaStorage.read();
    s.estudante.nome = (dados.nome || "").trim();
    s.estudante.objetivo = (dados.objetivo || "").trim();
    s.estudante.cargaSemanal = Math.max(0, Number(dados.cargaSemanal) || 0);
    SuindaStorage.write(s);
    return obter();
  }

  global.SuindaEstudante = { obter, salvar };
})(window);

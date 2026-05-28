/* Catálogo de categorias de tarefa.
   Lista fácil de expandir — para adicionar uma nova categoria,
   basta acrescentar um objeto aqui. */
(function (global) {
  "use strict";

  const CATEGORIAS = [
    { id: "estudo",       nome: "Estudo teórico",           cor: "#4f7a4a", icone: "📘" },
    { id: "revisao",      nome: "Revisão",                  cor: "#3f8a5d", icone: "🔁" },
    { id: "exercicios",   nome: "Exercícios",               cor: "#2f7da6", icone: "✏️" },
    { id: "simulado",     nome: "Simulado",                 cor: "#6b4ca0", icone: "🧪" },
    { id: "leitura",      nome: "Leitura",                  cor: "#8a6d3b", icone: "📖" },
    { id: "aula",         nome: "Aula / vídeo-aula",        cor: "#1f6f8b", icone: "🎥" },
    { id: "producao",     nome: "Produção / resumo",        cor: "#a36b2b", icone: "📝" },
    { id: "trabalho",     nome: "Trabalho / entrega",       cor: "#b25e1f", icone: "📤" },
    { id: "prova",        nome: "Prova / prazo importante", cor: "#b94545", icone: "🚨" },
    { id: "pausa",        nome: "Pausa / descanso",         cor: "#7fa37f", icone: "🌿", semMateria: true },
    { id: "recuperacao",  nome: "Recuperação de conteúdo",  cor: "#c98a1e", icone: "🔧" },
    { id: "personalizada",nome: "Tarefa personalizada",     cor: "#888888", icone: "✨", semMateria: true }
  ];

  function get(id) {
    return CATEGORIAS.find(c => c.id === id) || CATEGORIAS[CATEGORIAS.length - 1];
  }

  function all() { return CATEGORIAS.slice(); }

  function permiteSemMateria(id) {
    const c = get(id);
    return !!c.semMateria;
  }

  global.SuindaCategorias = { all, get, permiteSemMateria };
})(window);

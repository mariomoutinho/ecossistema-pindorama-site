// ============================================================
// Acupuntura — interações leves da página.
//
// O mapa corporal foi removido (versão visual anterior não ficou satisfatória).
// Este arquivo agora cuida apenas de:
//   - rolar a página para o resultado quando o usuário envia a ficha em mobile.
// O autocomplete vive em assets/autocomplete.js (carregado antes deste).
// ============================================================
(function () {
  'use strict';

  // Se o POST foi feito e estamos no mobile, rolar até a área de resultados
  // para o terapeuta ver os cards sem precisar deslizar manualmente.
  if (window.matchMedia('(max-width: 980px)').matches) {
    var url = new URL(window.location.href);
    // Heurística: existe um <h2 id="rec-h2"> e há cards renderizados
    var rec = document.getElementById('rec-h2');
    var firstCard = document.querySelector('.acup-recs .acup-rec');
    if (rec && firstCard) {
      // Adia um pouco para deixar o layout assentar
      setTimeout(function () { rec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80);
    }
  }
})();

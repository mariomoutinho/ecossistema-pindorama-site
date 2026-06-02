<?php
// ================================
// Banner dinâmico da página de TERAPIAS — usado só em terapias.php.
// Reaproveita o motor e o CSS do banner da home (.hhc). Os slides NÃO são
// renderizados aqui: são montados por assets/js/terapias.js a partir do mesmo
// array `services` que alimenta o catálogo (fonte única, sem duplicação).
// O JS chama window.initHeroCarousel() depois de preencher o trilho.
// Depende de $whatsLink (partials/bootstrap.php).
// ================================
?>
<section
  class="hhc hhc--terapias"
  id="inicio"
  aria-roledescription="carrossel"
  aria-label="Terapias em destaque do Espaço Pindorama"
>
  <div class="container">
    <div class="hhc__frame">
      <!-- Trilho preenchido por terapias.js (slides + clones) -->
      <div class="hhc__track" id="hhcTrack" aria-busy="true"></div>

      <!-- Degradê para legibilidade (paleta Pindorama) -->
      <div class="hhc__overlay" aria-hidden="true"></div>

      <!-- Conteúdo: info por slide (discreta) + texto fixo + ações -->
      <div class="hhc__content">
        <div class="hhc__slideinfo">
          <span class="hhc__label" id="hhcLabel"></span>
          <span class="hhc__name" id="hhcName"></span>
          <span class="hhc__dur" id="hhcDur"></span>
        </div>

        <p class="hhc__title">Terapias integrativas</p>
        <p class="hhc__tagline">Cuidado que começa pela escuta.</p>
        <p class="hhc__text">Conheça nossas práticas e encontre o atendimento que dialoga com o seu momento.</p>

        <div class="hhc__actions">
          <a class="btn primary hhc__cta" href="#catalogo">Ver terapias</a>
          <a class="btn ghost" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">Agendar no WhatsApp</a>
        </div>
      </div>

      <!-- Setas (só desktop, escondidas no mobile via CSS) -->
      <button class="hhc__arrow hhc__arrow--prev" type="button" aria-label="Slide anterior">&lsaquo;</button>
      <button class="hhc__arrow hhc__arrow--next" type="button" aria-label="Próximo slide">&rsaquo;</button>

      <!-- Indicadores -->
      <div class="hhc__dots" id="hhcDots" role="tablist" aria-label="Selecionar terapia"></div>

      <!-- Descrição do slide ativo para leitores de tela -->
      <p class="hhc__sr" aria-live="polite" id="hhcLive"></p>
    </div>
  </div>
</section>

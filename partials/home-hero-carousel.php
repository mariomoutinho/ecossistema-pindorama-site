<?php
// ================================
// Banner dinâmico (carrossel) da home — usado SOMENTE em coletivopindorama.php.
// Texto e botões são estáveis; só a imagem e o rótulo discreto mudam por slide.
// O JS (assets/js/home-hero-carousel.js) cuida de autoplay, gestos, clones de
// loop infinito e carregamento sob demanda. Sem este JS o 1º slide ainda aparece.
// Depende de $terapiasUrl (partials/bootstrap.php).
// ================================

$bannerBase = 'assets/img/home/banner/';

// Ordem dos slides e textos alternativos (acessibilidade). Para reordenar o
// banner no futuro, basta reordenar os itens deste array.
$bannerSlides = [
  ['file' => '01-acolhimento-pindorama.webp',     'label' => 'Acolhimento',           'alt' => 'Terapeutas do Coletivo Pindorama em ambiente acolhedor diante da mandala do espaço.'],
  ['file' => '02-acupuntura.webp',                'label' => 'Acupuntura',            'alt' => 'Atendimento de acupuntura no Espaço Pindorama.'],
  ['file' => '03-preparo-do-espaco.webp',         'label' => 'Espaço Pindorama',      'alt' => 'Preparação da maca para atendimento terapêutico.'],
  ['file' => '04-acompanhamento-integrativo.webp','label' => 'Terapias integrativas', 'alt' => 'Atendimento integrativo com escuta e acolhimento.'],
  ['file' => '05-consulta-terapeutica.webp',      'label' => 'Escuta e cuidado',      'alt' => 'Consulta terapêutica individual.'],
  ['file' => '06-ventosaterapia.webp',            'label' => 'Ventosaterapia',        'alt' => 'Sessão de ventosaterapia no Espaço Pindorama.'],
  ['file' => '07-massagem-ayurvedica.webp',       'label' => 'Massagem ayurvédica',   'alt' => 'Sessão de massagem ayurvédica com aplicação de óleo.'],
];
$bannerTotal = count($bannerSlides);
?>
<section
  class="hhc"
  id="inicio"
  aria-roledescription="carrossel"
  aria-label="Destaques do Coletivo Pindorama"
>
  <div class="container">
    <div class="hhc__frame">
      <!-- Trilho de imagens (clones de loop são adicionados pelo JS) -->
      <div class="hhc__track" id="hhcTrack">
        <?php foreach ($bannerSlides as $i => $s):
          $first = ($i === 0);
          $src   = $bannerBase . $s['file'];
        ?>
        <div
          class="hhc__slide"
          role="group"
          aria-roledescription="slide"
          aria-label="<?= ($i + 1) ?> de <?= $bannerTotal ?>"
          data-label="<?= htmlspecialchars($s['label']) ?>"
        >
          <img
            class="hhc__img"
            <?php if ($first): ?>
              src="<?= htmlspecialchars($src) ?>"
              fetchpriority="high"
              loading="eager"
            <?php else: ?>
              data-src="<?= htmlspecialchars($src) ?>"
              loading="lazy"
            <?php endif; ?>
            decoding="async"
            width="1600"
            height="900"
            alt="<?= htmlspecialchars($s['alt']) ?>"
          >
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Degradê para legibilidade (paleta Pindorama) -->
      <div class="hhc__overlay" aria-hidden="true"></div>

      <!-- Conteúdo estável (não muda a cada slide) -->
      <div class="hhc__content">
        <p class="hhc__label" id="hhcLabel"><?= htmlspecialchars($bannerSlides[0]['label']) ?></p>
        <p class="hhc__title">Coletivo Pindorama</p>
        <p class="hhc__tagline">Onde o cuidado cria raízes.</p>
        <p class="hhc__text">Terapias, formações, vivências e projetos para fortalecer pessoas e comunidades.</p>
        <div class="hhc__actions">
          <a class="btn primary hhc__cta" href="#ecossistema">Conhecer o Pindorama</a>
          <a class="btn ghost hhc__cta--ghost" href="<?= htmlspecialchars($terapiasUrl) ?>">Ver terapias</a>
        </div>
      </div>

      <!-- Setas (apenas desktop, escondidas no mobile via CSS) -->
      <button class="hhc__arrow hhc__arrow--prev" type="button" aria-label="Slide anterior">&lsaquo;</button>
      <button class="hhc__arrow hhc__arrow--next" type="button" aria-label="Próximo slide">&rsaquo;</button>

      <!-- Indicadores -->
      <div class="hhc__dots" id="hhcDots" role="tablist" aria-label="Selecionar slide"></div>

      <!-- Descrição do slide ativo para leitores de tela -->
      <p class="hhc__sr" aria-live="polite" id="hhcLive"></p>
    </div>
  </div>
</section>

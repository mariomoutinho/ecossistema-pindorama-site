/* ============================================================
   MOTOR DO BANNER DINÂMICO — hero carousel (.hhc)
   Vanilla JS, sem dependências. Loop infinito por clones, autoplay 3s
   (direita -> esquerda), gestos de arrastar, setas, indicadores, teclado,
   pausas (hover/foco/aba oculta/interação) e carregamento sob demanda.

   Reutilizável: initHeroCarousel(rootEl) inicializa um banner. Todo o estado
   fica no escopo da função, então vários banners podem coexistir. É usado:
   - na home (slides renderizados em PHP no partial home-hero-carousel.php);
   - em terapias (slides montados via JS a partir do catálogo — terapias.js).
   ============================================================ */
(function () {
  'use strict';

  function initHeroCarousel(root) {
    if (!root || root.getAttribute('data-hero-init') === '1') return;
    var track = root.querySelector('.hhc__track');
    if (!track) return;

    var slides = Array.prototype.slice.call(track.querySelectorAll('.hhc__slide'));
    var n = slides.length;
    if (!n) return; // sem slides ainda (ex.: serão montados depois) — não marca init

    root.setAttribute('data-hero-init', '1');

    var labelEl = root.querySelector('.hhc__label');
    var nameEl  = root.querySelector('.hhc__name'); // opcional (terapias)
    var durEl   = root.querySelector('.hhc__dur');  // opcional (terapias)
    var liveEl  = root.querySelector('.hhc__sr');
    var dotsEl  = root.querySelector('.hhc__dots');
    var prevBtn = root.querySelector('.hhc__arrow--prev');
    var nextBtn = root.querySelector('.hhc__arrow--next');
    var frame   = root.querySelector('.hhc__frame');

    var INTERVAL = 3000; // AUTOPLAY_INTERVAL — troca automática
    var RESUME   = 6000; // retoma o autoplay após interação manual

    var prefersReduced = window.matchMedia
      ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
      : false;

    var pos = 1; // posição no DOM (1 = primeiro slide real, por causa do clone à esquerda)
    var hovering = false;
    var autoTimer = null;
    var resumeTimer = null;

    // ---- Carregamento sob demanda (1ª já vem; demais via data-src) ----
    function markError(img) {
      if (!img) return;
      img.addEventListener('error', function () { img.classList.add('hhc__img--err'); });
    }
    function ensureLoaded(realIndex) {
      var sel = track.querySelectorAll('.hhc__slide[data-real="' + realIndex + '"] .hhc__img');
      Array.prototype.forEach.call(sel, function (img) {
        var ds = img.getAttribute('data-src');
        if (ds) { img.setAttribute('src', ds); img.removeAttribute('data-src'); }
      });
    }

    // Marca cada slide real com seu índice e prepara tratamento de erro.
    slides.forEach(function (s, i) {
      s.setAttribute('data-real', i);
      markError(s.querySelector('.hhc__img'));
    });

    // ---- Clones para loop infinito (só se houver mais de um slide) ----
    if (n > 1) {
      var cloneFirst = slides[0].cloneNode(true);
      var cloneLast  = slides[n - 1].cloneNode(true);
      cloneFirst.setAttribute('aria-hidden', 'true');
      cloneLast.setAttribute('aria-hidden', 'true');
      cloneFirst.setAttribute('data-clone', '1');
      cloneLast.setAttribute('data-clone', '1');
      markError(cloneFirst.querySelector('.hhc__img'));
      markError(cloneLast.querySelector('.hhc__img'));
      track.appendChild(cloneFirst);            // ...real(n-1), cloneOf(0)
      track.insertBefore(cloneLast, slides[0]);  // cloneOf(n-1), real0...
    } else {
      pos = 0;
    }

    function realFromPos(p) { return ((p - 1) % n + n) % n; }
    function applyTransform() { track.style.transform = 'translate3d(' + (-pos * 100) + '%,0,0)'; }
    function setPos(p, animate) {
      pos = p;
      if (!animate || prefersReduced) {
        var prev = track.style.transition;
        track.style.transition = 'none';
        applyTransform();
        void track.offsetWidth; // força reflow p/ o "salto" sem animação
        track.style.transition = prev;
      } else {
        applyTransform();
      }
      updateActive();
    }

    // ---- Estado ativo (indicadores, rótulos, leitor de tela, lazy) ----
    var dotButtons = [];
    function buildDots() {
      if (!dotsEl || n <= 1) { if (dotsEl) dotsEl.style.display = 'none'; return; }
      slides.forEach(function (s, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'hhc__dot';
        b.setAttribute('role', 'tab');
        b.setAttribute('aria-label', 'Ir para slide ' + (i + 1) + ': ' + (s.getAttribute('data-label') || ''));
        b.addEventListener('click', function () { goToReal(i, true); });
        dotsEl.appendChild(b);
        dotButtons.push(b);
      });
    }
    function updateActive() {
      var real = realFromPos(pos);

      dotButtons.forEach(function (b, i) {
        var on = i === real;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });

      var s = slides[real];
      var label = s.getAttribute('data-label') || '';
      var name  = s.getAttribute('data-name');
      var dur   = s.getAttribute('data-dur');

      if (labelEl) labelEl.textContent = label;
      if (nameEl && name != null) nameEl.textContent = name;
      if (durEl) { durEl.textContent = dur || ''; durEl.hidden = !dur; }
      if (liveEl) liveEl.textContent = 'Slide ' + (real + 1) + ' de ' + n + ': ' + (name || label);

      // marca apenas o slide real ativo como visível para leitores de tela
      slides.forEach(function (sl, i) { sl.setAttribute('aria-hidden', i === real ? 'false' : 'true'); });

      // carrega o atual e pré-carrega só o próximo
      ensureLoaded(real);
      ensureLoaded((real + 1) % n);
    }

    // ---- Navegação ----
    // Resolve um clone para o slide real equivalente ANTES de mover, evitando
    // que cliques rápidos ultrapassem os limites do trilho.
    function normalize() {
      if (n <= 1) return;
      if (pos === 0) setPos(n, false);
      else if (pos === n + 1) setPos(1, false);
    }
    function go(dir, user) { normalize(); setPos(pos + dir, true); if (user) manualInteract(); }
    function goToReal(i, user) { normalize(); setPos(i + 1, true); if (user) manualInteract(); }

    // Loop: ao chegar num clone, salta sem animação para o slide real equivalente.
    track.addEventListener('transitionend', function (e) {
      if (e.propertyName !== 'transform') return;
      if (n <= 1) return;
      if (pos === 0) setPos(n, false);
      else if (pos === n + 1) setPos(1, false);
    });

    // ---- Autoplay e pausas ----
    function canPlay() { return !prefersReduced && !document.hidden && !hovering && n > 1; }
    function startAuto() {
      stopAuto();
      if (!canPlay()) return;
      autoTimer = window.setInterval(function () { go(1, false); }, INTERVAL);
    }
    function stopAuto() { if (autoTimer) { window.clearInterval(autoTimer); autoTimer = null; } }
    function manualInteract() {
      stopAuto();
      if (resumeTimer) window.clearTimeout(resumeTimer);
      resumeTimer = window.setTimeout(startAuto, RESUME);
    }

    // ---- Eventos ----
    if (prevBtn) prevBtn.addEventListener('click', function () { go(-1, true); });
    if (nextBtn) nextBtn.addEventListener('click', function () { go(1, true); });

    root.addEventListener('mouseenter', function () { hovering = true; stopAuto(); });
    root.addEventListener('mouseleave', function () { hovering = false; startAuto(); });
    root.addEventListener('focusin',  function () { hovering = true; stopAuto(); });
    root.addEventListener('focusout', function () { hovering = false; startAuto(); });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stopAuto(); else startAuto();
    });

    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft')  { e.preventDefault(); go(-1, true); }
      if (e.key === 'ArrowRight') { e.preventDefault(); go(1, true); }
    });

    // ---- Gestos de arrastar (pointer events: mouse + toque) ----
    var dragging = false, locked = false, startX = 0, startY = 0;

    function onDown(e) {
      if (n <= 1) return;
      if (e.target.closest('a, button')) return; // preserva cliques em links/controles
      dragging = true; locked = false;
      startX = e.clientX; startY = e.clientY;
      try { frame.setPointerCapture(e.pointerId); } catch (err) {}
    }
    function onMove(e) {
      if (!dragging) return;
      var dx = e.clientX - startX;
      var dy = e.clientY - startY;
      if (!locked) {
        if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
        if (Math.abs(dy) > Math.abs(dx)) { dragging = false; return; } // gesto vertical = scroll
        locked = true;
        track.classList.add('is-grabbing');
        stopAuto();
      }
      e.preventDefault();
      track.style.transform = 'translate3d(calc(' + (-pos * 100) + '% + ' + dx + 'px),0,0)';
    }
    function onUp(e) {
      if (!dragging) return;
      dragging = false;
      if (!locked) return;
      locked = false;
      track.classList.remove('is-grabbing');
      var dx = e.clientX - startX;
      var threshold = Math.min(120, (frame ? frame.offsetWidth : 300) * 0.18);
      if (dx <= -threshold) go(1, true);
      else if (dx >= threshold) go(-1, true);
      else { setPos(pos, true); manualInteract(); } // volta ao lugar
    }

    if (frame && window.PointerEvent) {
      frame.addEventListener('pointerdown', onDown);
      frame.addEventListener('pointermove', onMove, { passive: false });
      frame.addEventListener('pointerup', onUp);
      frame.addEventListener('pointercancel', function () { dragging = false; locked = false; track.classList.remove('is-grabbing'); });
    }

    // ---- Init ----
    buildDots();
    setPos(pos, false); // posiciona no 1º slide real, sem animação
    startAuto();
  }

  // Exposto para inicialização manual (ex.: terapias monta os slides e chama).
  window.initHeroCarousel = initHeroCarousel;

  // Auto-inicializa qualquer .hhc que JÁ tenha slides no HTML (caso da home).
  function autoInit() {
    document.querySelectorAll('.hhc').forEach(function (el) {
      if (el.querySelector('.hhc__slide')) initHeroCarousel(el);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }
})();

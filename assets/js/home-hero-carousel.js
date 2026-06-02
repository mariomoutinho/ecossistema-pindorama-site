/* ============================================================
   BANNER DINÂMICO DA HOME — home-hero-carousel
   Vanilla JS, sem dependências. Loop infinito por clones, autoplay 3s
   (direita -> esquerda), gestos de arrastar, setas, indicadores, teclado,
   pausas (hover/foco/aba oculta/interação) e carregamento sob demanda.
   ============================================================ */
(function () {
  'use strict';

  var root  = document.querySelector('.hhc');
  var track = document.getElementById('hhcTrack');
  if (!root || !track) return;

  var slides = Array.prototype.slice.call(track.querySelectorAll('.hhc__slide'));
  var n = slides.length;
  if (!n) return;

  var labelEl = document.getElementById('hhcLabel');
  var liveEl  = document.getElementById('hhcLive');
  var dotsEl  = document.getElementById('hhcDots');
  var prevBtn = root.querySelector('.hhc__arrow--prev');
  var nextBtn = root.querySelector('.hhc__arrow--next');

  var INTERVAL = 3000; // troca automática
  var RESUME   = 6000; // retoma o autoplay após interação manual

  var prefersReduced = window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false;

  var pos = 1;          // posição no DOM (1 = primeiro slide real, por causa do clone à esquerda)
  var hovering = false;
  var autoTimer = null;
  var resumeTimer = null;

  // ---- Carregamento sob demanda (1ª já vem; demais via data-src) ----
  function markError(img) {
    img.addEventListener('error', function () {
      img.classList.add('hhc__img--err');
    });
  }
  function ensureLoaded(realIndex) {
    var sel = track.querySelectorAll('.hhc__slide[data-real="' + realIndex + '"] .hhc__img');
    Array.prototype.forEach.call(sel, function (img) {
      var ds = img.getAttribute('data-src');
      if (ds) {
        img.setAttribute('src', ds);
        img.removeAttribute('data-src');
      }
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
    track.appendChild(cloneFirst);          // ...real(n-1), cloneOf(0)
    track.insertBefore(cloneLast, slides[0]); // cloneOf(n-1), real0...
  } else {
    pos = 0;
  }

  function realFromPos(p) {
    return ((p - 1) % n + n) % n;
  }
  function applyTransform() {
    track.style.transform = 'translate3d(' + (-pos * 100) + '%,0,0)';
  }
  function setPos(p, animate) {
    pos = p;
    if (!animate || prefersReduced) {
      var prev = track.style.transition;
      track.style.transition = 'none';
      applyTransform();
      // força reflow para o "salto" sem animação valer antes de reativar
      void track.offsetWidth;
      track.style.transition = prev;
    } else {
      applyTransform();
    }
    updateActive();
  }

  // ---- Estado ativo (indicadores, rótulo, leitor de tela, lazy) ----
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

    var label = slides[real].getAttribute('data-label') || '';
    if (labelEl) labelEl.textContent = label;
    if (liveEl)  liveEl.textContent = 'Slide ' + (real + 1) + ' de ' + n + ': ' + label;

    // marca apenas o slide real ativo como visível para leitores de tela
    slides.forEach(function (s, i) {
      s.setAttribute('aria-hidden', i === real ? 'false' : 'true');
    });

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
  function go(dir, user) {
    normalize();
    setPos(pos + dir, true);
    if (user) manualInteract();
  }
  function goToReal(i, user) {
    normalize();
    setPos(i + 1, true);
    if (user) manualInteract();
  }

  // Loop: ao chegar num clone, salta sem animação para o slide real equivalente.
  track.addEventListener('transitionend', function (e) {
    if (e.propertyName !== 'transform') return;
    if (n <= 1) return;
    if (pos === 0) setPos(n, false);
    else if (pos === n + 1) setPos(1, false);
  });

  // ---- Autoplay e pausas ----
  function canPlay() {
    return !prefersReduced && !document.hidden && !hovering && n > 1;
  }
  function startAuto() {
    stopAuto();
    if (!canPlay()) return;
    autoTimer = window.setInterval(function () { go(1, false); }, INTERVAL);
  }
  function stopAuto() {
    if (autoTimer) { window.clearInterval(autoTimer); autoTimer = null; }
  }
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
  var frame = root.querySelector('.hhc__frame');

  function onDown(e) {
    if (n <= 1) return;
    if (e.target.closest('a, button')) return; // preserva cliques em links/controles
    dragging = true; locked = false;
    startX = e.clientX; startY = e.clientY;
    // captura o ponteiro para receber o pointerup mesmo se soltar fora do frame
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
  setPos(pos, false);   // posiciona no 1º slide real, sem animação
  startAuto();
})();

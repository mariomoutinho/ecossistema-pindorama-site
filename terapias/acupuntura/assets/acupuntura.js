// ============================================================
// Acupuntura — interações do mapa (zoom, tooltip, troca de vista)
// Vanilla JS puro. Sem dependências externas.
// ============================================================
(function () {
  'use strict';

  // ---------------- Zoom ----------------
  var scroller = document.querySelector('.acup-map__scroller');
  var zoomVal  = document.querySelector('[data-acup-zoom-val]');
  var zoomMin = 0.7, zoomMax = 2.6, zoomStep = 0.15;
  var zoom = 1;

  function applyZoom() {
    if (!scroller) return;
    scroller.style.transform = 'scale(' + zoom.toFixed(2) + ')';
    if (zoomVal) zoomVal.textContent = Math.round(zoom * 100) + '%';
  }
  function setZoom(z) {
    zoom = Math.max(zoomMin, Math.min(zoomMax, z));
    applyZoom();
  }
  document.querySelectorAll('[data-acup-zoom]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dir = btn.getAttribute('data-acup-zoom');
      if (dir === 'in')      setZoom(zoom + zoomStep);
      else if (dir === 'out') setZoom(zoom - zoomStep);
      else if (dir === 'reset') setZoom(1);
    });
  });

  // Zoom por wheel + Ctrl/Cmd dentro do mapa (não atrapalha scroll da página)
  var mapWrap = document.querySelector('.acup-map');
  if (mapWrap) {
    mapWrap.addEventListener('wheel', function (e) {
      if (!(e.ctrlKey || e.metaKey)) return;
      e.preventDefault();
      setZoom(zoom + (e.deltaY < 0 ? zoomStep : -zoomStep));
    }, { passive: false });
  }

  applyZoom();

  // ---------------- Troca de vista (frente/costas/perfil) ----------------
  var allFiguras = document.querySelectorAll('[data-acup-vista]');
  var viewBtns   = document.querySelectorAll('[data-acup-view-toggle]');

  function aplicarVista(modo) {
    allFiguras.forEach(function (f) {
      var v = f.getAttribute('data-acup-vista');
      f.style.display = (modo === 'todas' || modo === v) ? '' : 'none';
    });
    viewBtns.forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-acup-view-toggle') === modo);
    });
  }
  viewBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      aplicarVista(b.getAttribute('data-acup-view-toggle'));
    });
  });

  // ---------------- Tooltip ----------------
  var tip = document.createElement('div');
  tip.className = 'acup-tooltip';
  tip.setAttribute('role', 'tooltip');
  tip.setAttribute('aria-hidden', 'true');
  document.body.appendChild(tip);

  function showTip(target, evt) {
    var codigo     = target.getAttribute('data-codigo');
    var nome       = target.getAttribute('data-nome');
    var meridiano  = target.getAttribute('data-meridiano');
    var score      = target.getAttribute('data-score');
    var descartado = target.getAttribute('data-descartado') === '1';
    var motivos    = target.getAttribute('data-motivos') || '';

    var meta = meridiano || '';
    if (score && !descartado) meta += (meta ? ' · ' : '') + 'score ' + score;
    if (descartado) meta += (meta ? ' · ' : '') + 'descartado por restrição';

    tip.innerHTML = '<strong>' + codigo + ' — ' + nome + '</strong>' +
                    '<span class="acup-tooltip__meta">' + meta + '</span>' +
                    (motivos ? '<div class="acup-tooltip__meta">' + motivos + '</div>' : '');

    tip.style.left = (evt.clientX + 14) + 'px';
    tip.style.top  = (evt.clientY + 14) + 'px';
    tip.classList.add('is-visible');
  }
  function hideTip() { tip.classList.remove('is-visible'); }

  function bindTooltip(el) {
    el.addEventListener('mouseenter', function (e) { showTip(el, e); });
    el.addEventListener('mousemove',  function (e) {
      tip.style.left = (e.clientX + 14) + 'px';
      tip.style.top  = (e.clientY + 14) + 'px';
    });
    el.addEventListener('mouseleave', hideTip);
    el.addEventListener('focus', function (e) {
      var r = el.getBoundingClientRect();
      showTip(el, { clientX: r.left + r.width / 2, clientY: r.top });
    });
    el.addEventListener('blur', hideTip);
    // Touch: tap mostra o tooltip; tap fora esconde.
    el.addEventListener('click', function (e) {
      e.stopPropagation();
      showTip(el, { clientX: e.clientX || 0, clientY: e.clientY || 0 });
      scrollToRec(el.getAttribute('data-codigo'));
    });
  }
  document.querySelectorAll('.acup-point').forEach(bindTooltip);
  document.addEventListener('click', hideTip);

  // ---------------- Sincroniza ponto -> card de recomendação ----------------
  function scrollToRec(codigo) {
    if (!codigo) return;
    var alvo = document.querySelector('[data-rec-codigo="' + codigo + '"]');
    if (!alvo) return;
    document.querySelectorAll('[data-rec-codigo]').forEach(function (x) { x.classList.remove('is-focus'); });
    alvo.classList.add('is-focus');
    alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
})();

/* ============================================================
   Poderes concedidos interativos (tabela de Divindades).
   - Hover/foco (desktop): tooltip rápido com a descrição.
   - Clique/toque (todos): modal com nome + descrição completa.
   Dados: window.PODERES_DIVINDADES (fonte central: poderes-gerais.json).
   Vanilla JS, sem dependências. Tooltip/modal vivem no <body> para
   não serem cortados pelo overflow da tabela.
   ============================================================ */
(function () {
    'use strict';

    var DATA = window.PODERES_DIVINDADES || {};
    var FALLBACK = 'Descrição ainda não cadastrada.';
    var hoverCapaz = !!(window.matchMedia && window.matchMedia('(hover: hover)').matches);

    function info(id) {
        var p = DATA[id] || {};
        var desc = (p.descricao && String(p.descricao).trim()) ? p.descricao : FALLBACK;
        return { nome: p.nome || id, descricao: desc };
    }

    /* ---------------- Tooltip ---------------- */
    var tip = null;

    function garantirTip() {
        if (tip) return tip;
        tip = document.createElement('div');
        tip.className = 'poder-tooltip';
        tip.id = 'poderTooltip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }

    function posicionarTip(btn, t) {
        var r = btn.getBoundingClientRect();
        var margem = 8;
        var tw = t.offsetWidth;
        var th = t.offsetHeight;
        var left = r.left + r.width / 2 - tw / 2;
        left = Math.max(margem, Math.min(left, window.innerWidth - tw - margem));
        var top = r.top - th - 8;
        if (top < margem) { top = r.bottom + 8; } // sem espaço acima → abaixo
        t.style.left = Math.round(left) + 'px';
        t.style.top = Math.round(top) + 'px';
    }

    function mostrarTip(btn) {
        var t = garantirTip();
        t.textContent = info(btn.getAttribute('data-poder-id')).descricao;
        t.hidden = false;
        btn.setAttribute('aria-describedby', 'poderTooltip');
        posicionarTip(btn, t);
    }

    function esconderTip(btn) {
        if (tip) { tip.hidden = true; }
        if (btn) { btn.removeAttribute('aria-describedby'); }
    }

    /* ---------------- Modal ---------------- */
    var modal = null, mTitulo = null, mDesc = null, mFechar = null, ultimoFoco = null;

    function garantirModal() {
        if (modal) return;
        modal = document.createElement('div');
        modal.className = 'poder-modal';
        modal.hidden = true;
        modal.innerHTML =
            '<div class="poder-modal__backdrop" data-close></div>' +
            '<div class="poder-modal__panel" role="dialog" aria-modal="true" aria-labelledby="poderModalTitulo">' +
                '<button type="button" class="poder-modal__fechar" data-close aria-label="Fechar">&times;</button>' +
                '<h2 class="poder-modal__titulo" id="poderModalTitulo"></h2>' +
                '<div class="poder-modal__desc"></div>' +
            '</div>';
        document.body.appendChild(modal);
        mTitulo = modal.querySelector('.poder-modal__titulo');
        mDesc = modal.querySelector('.poder-modal__desc');
        mFechar = modal.querySelector('.poder-modal__fechar');

        modal.addEventListener('click', function (e) {
            if (e.target && e.target.hasAttribute('data-close')) { fecharModal(); }
        });
        document.addEventListener('keydown', function (e) {
            if (modal.hidden) return;
            if (e.key === 'Escape') { fecharModal(); }
            else if (e.key === 'Tab') { prenderFoco(e); }
        });
    }

    function focaveis() {
        return modal.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])');
    }

    function prenderFoco(e) {
        var f = focaveis();
        if (!f.length) return;
        var primeiro = f[0], ultimo = f[f.length - 1];
        if (e.shiftKey && document.activeElement === primeiro) {
            e.preventDefault(); ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
            e.preventDefault(); primeiro.focus();
        }
    }

    function abrirModal(btn) {
        garantirModal();
        var d = info(btn.getAttribute('data-poder-id'));
        mTitulo.textContent = d.nome;
        mDesc.textContent = d.descricao;
        ultimoFoco = btn;
        esconderTip(btn);
        modal.hidden = false;
        document.documentElement.classList.add('poder-modal-aberto');
        mFechar.focus();
    }

    function fecharModal() {
        if (!modal || modal.hidden) return;
        modal.hidden = true;
        document.documentElement.classList.remove('poder-modal-aberto');
        if (ultimoFoco && ultimoFoco.focus) { ultimoFoco.focus(); }
        ultimoFoco = null;
    }

    /* ---------------- Ligações ---------------- */
    function ligar() {
        var links = document.querySelectorAll('.poder-link');
        Array.prototype.forEach.call(links, function (btn) {
            if (hoverCapaz) {
                btn.addEventListener('mouseenter', function () { mostrarTip(btn); });
                btn.addEventListener('mouseleave', function () { esconderTip(btn); });
            }
            btn.addEventListener('focus', function () { mostrarTip(btn); });
            btn.addEventListener('blur', function () { esconderTip(btn); });
            btn.addEventListener('click', function () { abrirModal(btn); });
        });
        window.addEventListener('scroll', function () { esconderTip(); }, true);
        window.addEventListener('resize', function () { esconderTip(); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ligar);
    } else {
        ligar();
    }
})();

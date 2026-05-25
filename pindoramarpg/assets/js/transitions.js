/**
 * Transições de página — fade-out ao clicar em link interno antes de
 * navegar, e GUARDA GLOBAL contra overlays/modais travados.
 *
 * Estrutura: dois IIFEs.
 *
 *   1) Guard de overlay (sempre roda). Limpa classes residuais do
 *      body, restaura overflow, e oferece window.closeAllOverlays().
 *
 *   2) Animação de transição (só se reduced-motion não estiver ligado).
 */

/* =====================================================================
   1) GUARD GLOBAL — sempre roda
   ===================================================================== */
(function () {
    'use strict';

    // Classes que historicamente foram usadas em projetos PHP simples
    // para sinalizar estado "aberto". Se sobrarem no body sem modal
    // realmente aberto, viram bug visual (página travada/escurecida).
    const STUCK_BODY_CLASSES = [
        'page-leaving',
        'modal-open',
        'drawer-open',
        'actions-open',
        'overlay-active',
        'is-loading',
        'no-scroll'
    ];

    function temAlgumModalAberto() {
        return !!document.querySelector(
            '.cb-modal-backdrop:not([hidden]),' +
            '.sheet-modal-backdrop:not([hidden]),' +
            '.poder-modal-backdrop:not([hidden]),' +
            '.dice-overlay.active,' +
            '.cb-action-panel:not([hidden]),' +
            '.anc-picker-backdrop:not([hidden])'
        );
    }

    /**
     * Limpeza DEFENSIVA de estado preso. Cuida apenas do body/html
     * (classes residuais e overflow/opacity inline).
     *
     * NÃO toca em modais reais (não muda classes de abertura, aria-hidden
     * nem pointer-events inline) — fazer isso quebrava cliques quando o
     * usuário abria o modal depois. Cada módulo é responsável por abrir
     * e fechar seu próprio modal; o guard cuida só do body.
     *
     * Adicionalmente faz CURA: remove `style="pointer-events: ..."`
     * inline de backdrops que tenham sido danificados por uma versão
     * anterior desta função — garante que páginas com cache antigo
     * não fiquem com modais inertes.
     */
    function closeAllOverlays() {
        for (const cls of STUCK_BODY_CLASSES) {
            document.body.classList.remove(cls);
            document.documentElement.classList.remove(cls);
        }
        document.querySelectorAll('a.is-leaving').forEach(a => a.classList.remove('is-leaving'));
        if (document.body.style.overflow === 'hidden') document.body.style.overflow = '';
        if (document.body.style.opacity) document.body.style.opacity = '';
        if (document.body.style.filter)  document.body.style.filter  = '';
        if (document.documentElement.style.overflow === 'hidden') document.documentElement.style.overflow = '';
        if (document.documentElement.style.filter) document.documentElement.style.filter = '';

        // Limpa styles inline em <main> (filhos diretos do body) que
        // possam ter ficado de uma animação antiga (opacity:0, blur).
        const mains = document.body.querySelectorAll(':scope > main');
        mains.forEach(m => {
            if (m.style.opacity)   m.style.opacity   = '';
            if (m.style.filter)    m.style.filter    = '';
            if (m.style.transform) m.style.transform = '';
        });

        // CURA: tira pointer-events inline de qualquer backdrop/modal
        // que tenha sido danificado pelo guard antigo. Não mexe em
        // classes de abertura nem em aria-hidden — esses são do módulo.
        const SELS = '.modal-backdrop, .overlay, .backdrop, .action-overlay, ' +
                     '.battle-backdrop, .mesa-backdrop, .anc-picker-backdrop, ' +
                     '.poder-modal-backdrop, .sheet-modal-backdrop, ' +
                     '.cb-modal-backdrop, .dice-overlay, .cb-modal, .poder-modal, ' +
                     '.sheet-modal';
        document.querySelectorAll(SELS).forEach(el => {
            if (el.style.pointerEvents) el.style.pointerEvents = '';
        });
    }

    function limparSeNaoHaModal() {
        if (!temAlgumModalAberto()) closeAllOverlays();
    }

    // Exposto para uso manual no console (debug) e por outros scripts.
    window.closeAllOverlays = closeAllOverlays;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', limparSeNaoHaModal);
    } else {
        limparSeNaoHaModal();
    }
    window.addEventListener('pageshow',  limparSeNaoHaModal);
    window.addEventListener('popstate',  limparSeNaoHaModal);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') limparSeNaoHaModal();
    });

    // ESC global: se NADA estiver aberto, garante body limpo. Páginas
    // com handlers próprios de ESC (campo-batalha) não conflitam.
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (temAlgumModalAberto()) return;
        closeAllOverlays();
    });
})();

/* =====================================================================
   1b) DIAGNÓSTICO ON-DEMAND — abra a página com ?debug=overlay para
       imprimir no console o que está cobrindo a tela. Útil para
       investigar o bug "tela escurecida/travada" quando ele aparece.
   ===================================================================== */
(function () {
    'use strict';
    try {
        if (!new URLSearchParams(location.search).has('debug')) return;
        if (new URLSearchParams(location.search).get('debug') !== 'overlay') return;
    } catch (_) { return; }

    function dump() {
        const W = window.innerWidth, H = window.innerHeight;
        const center = document.elementFromPoint(W/2, H/2);
        const stack  = document.elementsFromPoint(W/2, H/2);

        const suspects = [...document.querySelectorAll('body *')]
            .map(el => {
                const s = getComputedStyle(el);
                const r = el.getBoundingClientRect();
                return { el, tag: el.tagName, id: el.id,
                         cls: String(el.className).slice(0, 80),
                         pos: s.position, z: s.zIndex,
                         pe: s.pointerEvents, op: s.opacity,
                         disp: s.display, vis: s.visibility,
                         bg: s.backgroundColor, filter: s.filter,
                         backdropFilter: s.backdropFilter,
                         w: Math.round(r.width), h: Math.round(r.height),
                         t: Math.round(r.top), l: Math.round(r.left) };
            })
            .filter(x => (
                (x.pos === 'fixed' || x.pos === 'absolute') &&
                x.w >= W * 0.7 && x.h >= H * 0.7
            ));

        console.group('%c[Pindorama overlay-debug]', 'background:#76547c;color:#fff;padding:2px 6px;border-radius:4px');
        console.log('center el:', center);
        console.log('stack at center:', stack);
        console.log('html.className:', document.documentElement.className);
        console.log('body.className :', document.body.className);
        console.log('body::before   :', getComputedStyle(document.body, '::before').cssText);
        console.log('body::after    :', getComputedStyle(document.body, '::after').cssText);
        console.log('html::before   :', getComputedStyle(document.documentElement, '::before').cssText);
        console.log('html::after    :', getComputedStyle(document.documentElement, '::after').cssText);
        console.table(suspects.map(({el, ...r}) => r));
        console.log('suspect elements:', suspects.map(s => s.el));
        console.groupEnd();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(dump, 200));
    } else {
        setTimeout(dump, 200);
    }
})();

/* =====================================================================
   2) ANIMAÇÃO DE TRANSIÇÃO — só se motion permitido
   ===================================================================== */
(function () {
    'use strict';

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    // Como `body > main { opacity:1 !important }` força visibilidade total,
    // o fade-out visual não é mais perceptível — o delay vira só atraso
    // sem benefício. Mantemos um valor pequeno só para o glow do link
    // (linkFlash) ser visto antes da navegação.
    const FADE_OUT_MS = 60;

    // Links que NUNCA devem usar a transição (fluxos críticos onde
    // qualquer estado residual em body.page-leaving pode atrapalhar).
    const NO_TRANSITION_PATHS = new Set([
        '/logout.php',
        'logout.php'
    ]);

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;

        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (e.button !== 0) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;
        if (link.hasAttribute('data-no-transition')) return;

        const href = link.getAttribute('href');
        if (!href) return;
        if (href.startsWith('#') ||
            href.startsWith('javascript:') ||
            href.startsWith('mailto:') ||
            href.startsWith('tel:')) return;

        let url;
        try { url = new URL(href, window.location.href); } catch (_) { return; }

        if (url.origin !== window.location.origin) return;
        if (url.href === window.location.href) return;
        if (url.pathname === window.location.pathname &&
            url.search === window.location.search &&
            url.hash) return;

        // Pula transição em fluxos críticos (logout etc.) — navegação
        // direta evita qualquer chance de page-leaving residual em
        // login.php/painel.php depois.
        const path = url.pathname.split('/').pop() || '';
        if (NO_TRANSITION_PATHS.has(path) || NO_TRANSITION_PATHS.has(url.pathname)) {
            return;
        }

        e.preventDefault();
        link.classList.add('is-leaving');
        document.body.classList.add('page-leaving');

        // Failsafe duplo: se a navegação não acontecer (iOS gesture, popup
        // blocker, erro de rede), volta a página ao normal em 1.5s.
        const limpeza = setTimeout(() => window.closeAllOverlays(), 1500);

        setTimeout(() => {
            clearTimeout(limpeza);
            window.location.href = url.href;
        }, FADE_OUT_MS);
    });
})();

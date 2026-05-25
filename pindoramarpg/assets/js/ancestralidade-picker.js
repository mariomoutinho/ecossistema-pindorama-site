/**
 * Tech-style picker de ancestralidades.
 *
 * Substitui visualmente o <select id="ancestralidadeSelect"> mas mantém o
 * próprio elemento no DOM para que toda a lógica reativa existente
 * (ancestralidades-ficha.js, ficha.js) que ouve `change`/`input` continue
 * funcionando normalmente.
 *
 * Pré-requisitos no HTML:
 *   <select id="ancestralidadeSelect" name="ancestralidade">...</select>
 *
 * Carrega data/ancestralidades.json e gera:
 *   - Botão de gatilho com o item selecionado
 *   - Modal com lista (esquerda) + preview com imagem + descrição (direita)
 */

(function () {
    'use strict';

    const STATE = {
        select: null,           // <select> nativo
        items: [],              // [{ id, nome, descricao, imagem }]
        triggerBtn: null,
        triggerLabel: null,
        backdrop: null,
        listEl: null,
        previewEl: null,
        confirmBtn: null,
        previewKey: null,       // chave da ancestralidade em preview
        ready: false,
    };

    const IMG_BASE = 'assets/img/ancestralidades/';
    const IMG_EXTS = ['webp', 'png', 'jpg', 'jpeg'];

    function imagePathFor(id) {
        // não fazemos HEAD aqui — a imagem aparece com onerror pra esconder
        return IMG_BASE + id + '.webp';
    }

    /* ----------------------------------------------------------------
     * Resumos curados por ancestralidade — escritos a partir da
     * descrição completa em ancestralidades.json. Cada resumo destaca:
     *  • essência cultural / origem mítica;
     *  • marca visual ou física distinta;
     *  • função no grupo / dica pro jogador.
     * ---------------------------------------------------------------- */
    const RESUMOS = {
        arajuba:
            'Povo-pássaro vindo de além-mar — humanoides aviários com cabeça de papagaio, plumagem colorida e garras afiadas. Vivem nas copas das montanhas e detestam confinamento; em batalha mergulham do céu e voam para longe. Para quem busca um batedor aéreo ágil, vaidoso e com instinto irrefreável de liberdade.',
        candango:
            'Encantados nascidos da Batalha do Esquecimento, quando a sacerdotisa Nzinga rogou a Kiantomerê para salvar seu povo. Pequeninos, com orelhas e cauda de roedor, ágeis e prestativos — sua aparência inofensiva esconde uma brutalidade surpreendente. Indicados para quem quer um companheiro acolhedor, persistente e que será sempre subestimado pelos inimigos.',
        curinqueas:
            'Gigantes pacíficos de pele cor de cobre que crescem por toda a vida (3m e além), dominando magia, diplomacia e a arte da guerra. Vivem em aldeias ocultas por magias ancestrais e só lutam quando provocados, mas então são implacáveis. Para quem quer um colosso sábio: presença imponente em combate ou em mesa de negociação.',
        florata:
            'Guardiões-vegetais despertados pela essência de Wesuirã, orixá das plantas sagradas. Pele esverdeada, beleza etérea, vestes de cipó e folha — tratam cada planta como família ainda em sono. São embaixadores entre civilização e natureza; perfeitos para quem deseja um druida nato, defensor das matas e voz da Grande Fraternidade Verdejante.',
        goiazi:
            'Inventores risonhos de menos de um metro que falam mais rápido do que pensam e celebram a vida com gargalhadas, engrenagens e pequenas explosões. Perderam seu reino aos caucazis e hoje aparecem como lapidários, sábios, engenheiros e professores espalhados pelos demais reinos. Indicados para quem quer um artífice extrovertido com energia caótica e otimismo inabalável.',
        iakare:
            'Répteis bípedes territoriais com escamas verde-acinzentadas, presas e garras — mestres das emboscadas em rios e pântanos. A sociedade tribal é dura e hierárquica, mas há os dissidentes que partem para o mundo provando seu valor como xamãs, sacerdotes ou fanfarrões. Para quem quer um caçador letal ou o "renegado nobre" buscando lugar entre aventureiros estranhos.',
        'kai-porah':
            'Caiporas: pequeninos mágicos de cabelo vermelho que se camuflam na floresta com roupas de folha e cascas. Conversam com animais, criam ilusões, montam javalis e aceitam fumo como gesto de amizade. Pacíficos por natureza, mas vulneráveis a magias mentais — escolha ideal para um explorador silencioso, trickster folclórico ou companheiro da fauna selvagem.',
        muiraquita:
            'Filhos do casamento entre Fogo e Metal, forjados por Gumedé com um fragmento do sol roubado de Kuarasy. Pele metálica (rubra, prateada, dourada) com cristais incrustados; construíram cidades-fortaleza nas montanhas e ensinam metalurgia desde Alagbedê. Corações generosos e teimosia de rocha — para quem quer um durão leal, ferreiro de essência divina, guerreiro ou artífice com convicção inabalável.',
        oiara:
            'Híbridos de Iemanjá: cauda de animal marinho na água, pernas em terra firme. Comunicam-se com criaturas aquáticas, respiram embaixo d\'água e ergueram reinos nos abismos do mar; Oxum lhes deu beleza e voz encantadora. Para quem busca um conjurador das águas, encantador de sereias e protetor de ecossistemas marinhos.',
        orumere:
            'Seres nascidos da união entre uma divindade e a energia cósmica, aparecem em Pindorama como crianças abandonadas para corrigir injustiças. Corpos atléticos, olhos brilhando com a essência divina; são acolhidos como sinal de proximidade dos deuses pelos unhabás e caçados como ameaça pelos caucazis. Para quem quer um paladino/inquisidor com missão clara e narrativa de propósito.',
        saci:
            'Encantados travessos de meio metro, pele e cabelo negros, com um redemoinho mágico nos pés que lhes permite flutuar. Cultura oral festiva: dançam, contam histórias e o gorro vermelho marca a passagem para a maturidade. Vivem em pequenas comunidades em harmonia com o Grande Verdejante — perfeitos para um trickster ágil, lutador acrobático ou bardo de raízes folclóricas profundas.',
        humano:
            'A ancestralidade mais numerosa de Pindorama, vinda do mar em cobras-canoas pela vontade dos Deuses Criadores. Adaptáveis e contraditórios, capazes de plantar e queimar, acolher e ferir; pindorins se vestem de fibras naturais, caucazis em couraças sóbrias. Para quem quer versatilidade total: encaixa em qualquer classe, qualquer região e qualquer tom narrativo.',
    };

    function summaryFor(item) {
        if (item && item.id && RESUMOS[item.id]) return RESUMOS[item.id];
        const desc = item.descricao;
        if (!desc) return '';
        if (Array.isArray(desc)) return desc.slice(0, 2).join(' ');
        return String(desc);
    }

    function findByNome(nome) {
        if (!nome) return null;
        return STATE.items.find(i => i.nome === nome) || null;
    }

    /* ---------- carregamento ---------- */

    async function carregarDados() {
        try {
            const res = await fetch('data/ancestralidades.json');
            const json = await res.json();
            STATE.items = (json.ancestralidades || []).map(a => ({
                id: a.id,
                nome: a.nome,
                descricao: a.descricao,
                imagem: imagePathFor(a.id),
            }));
        } catch (err) {
            console.error('[anc-picker] falha ao carregar ancestralidades.json', err);
            STATE.items = [];
        }
    }

    /* ---------- construção do DOM ---------- */

    function construirGatilho() {
        const select = STATE.select;
        const wrap = select.parentElement;

        // Esconde o select original
        select.classList.add('anc-picker-hidden-select');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        // Cria botão de gatilho
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'anc-picker-trigger';
        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-expanded', 'false');

        const label = document.createElement('span');
        label.className = 'anc-picker-trigger-label';
        btn.appendChild(label);

        const arrow = document.createElement('span');
        arrow.className = 'anc-picker-trigger-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '▼';
        btn.appendChild(arrow);

        wrap.appendChild(btn);

        STATE.triggerBtn = btn;
        STATE.triggerLabel = label;

        btn.addEventListener('click', abrir);

        atualizarLabelGatilho();
    }

    function atualizarLabelGatilho() {
        const valor = STATE.select.value;
        const lbl = STATE.triggerLabel;
        if (valor) {
            lbl.textContent = valor;
            lbl.classList.remove('is-empty');
        } else {
            lbl.textContent = '(nenhum)';
            lbl.classList.add('is-empty');
        }
    }

    function construirModal() {
        const backdrop = document.createElement('div');
        backdrop.className = 'anc-picker-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'ancPickerTitle');

        backdrop.innerHTML = `
            <div class="anc-picker-panel" role="document">
                <header class="anc-picker-header">
                    <h2 class="anc-picker-title" id="ancPickerTitle">Selecionar Ancestralidade</h2>
                    <button type="button" class="anc-picker-close" aria-label="Fechar">×</button>
                </header>

                <div class="anc-picker-body">
                    <div class="anc-picker-list" role="listbox" aria-label="Ancestralidades disponíveis"></div>
                    <div class="anc-picker-preview" aria-live="polite">
                        <div class="anc-picker-preview-empty">Passe o mouse sobre uma ancestralidade para ver detalhes.</div>
                    </div>
                </div>

                <footer class="anc-picker-footer">
                    <button type="button" class="anc-picker-btn anc-picker-btn-cancel">Cancelar</button>
                    <button type="button" class="anc-picker-btn anc-picker-btn-primary anc-picker-btn-confirm" disabled>Confirmar</button>
                </footer>
            </div>
        `;

        document.body.appendChild(backdrop);

        STATE.backdrop = backdrop;
        STATE.listEl = backdrop.querySelector('.anc-picker-list');
        STATE.previewEl = backdrop.querySelector('.anc-picker-preview');
        STATE.confirmBtn = backdrop.querySelector('.anc-picker-btn-confirm');

        // Eventos do modal
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) fechar();
        });
        backdrop.querySelector('.anc-picker-close').addEventListener('click', fechar);
        backdrop.querySelector('.anc-picker-btn-cancel').addEventListener('click', fechar);
        STATE.confirmBtn.addEventListener('click', confirmar);

        document.addEventListener('keydown', (e) => {
            if (!isAberto()) return;
            if (e.key === 'Escape') {
                fechar();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                navegar(+1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navegar(-1);
            } else if (e.key === 'Enter' && STATE.previewKey) {
                e.preventDefault();
                confirmar();
            }
        });

        renderizarLista();
    }

    function renderizarLista() {
        const list = STATE.listEl;
        list.innerHTML = '';

        // opção "(nenhum)"
        const optNone = montarOpcao({ id: '__none__', nome: '(nenhum)', _none: true });
        list.appendChild(optNone);

        STATE.items.forEach(item => {
            list.appendChild(montarOpcao(item));
        });
    }

    function montarOpcao(item) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'anc-picker-option';
        btn.setAttribute('role', 'option');
        btn.dataset.nome = item._none ? '' : item.nome;
        btn.dataset.id = item.id || '';

        btn.innerHTML = `
            <span class="anc-picker-radio" aria-hidden="true"></span>
            <span class="anc-picker-option-label">${escapeHtml(item.nome)}</span>
        `;

        btn.addEventListener('mouseenter', () => previewItem(item));
        btn.addEventListener('focus',      () => previewItem(item));
        btn.addEventListener('click',      () => {
            previewItem(item);
            STATE.confirmBtn.focus();
        });
        btn.addEventListener('dblclick',   () => {
            previewItem(item);
            confirmar();
        });

        return btn;
    }

    function previewItem(item) {
        STATE.previewKey = item._none ? '__none__' : item.nome;
        STATE.confirmBtn.disabled = false;

        // marcação visual na lista
        STATE.listEl.querySelectorAll('.anc-picker-option').forEach(b => {
            const isPreview = (b.dataset.id || '') === (item.id || '');
            b.classList.toggle('is-preview', isPreview);
        });

        renderizarPreview(item);
    }

    function renderizarPreview(item) {
        const el = STATE.previewEl;

        if (item._none) {
            el.innerHTML = `
                <div class="anc-picker-preview-empty">
                    Limpa a seleção de ancestralidade.
                </div>
            `;
            return;
        }

        const summary = escapeHtml(summaryFor(item));
        el.innerHTML = `
            <div class="anc-picker-preview-text">
                <h3 class="anc-picker-preview-name">${escapeHtml(item.nome)}</h3>
                <p class="anc-picker-preview-summary">${summary || '<em>(sem descrição)</em>'}</p>
            </div>
            <div class="anc-picker-preview-image">
                <img src="${escapeHtml(item.imagem)}"
                     alt="${escapeHtml(item.nome)}"
                     onerror="this.style.display='none';this.parentElement.innerHTML='<span class=&quot;placeholder&quot;>?</span>';" />
            </div>
        `;
    }

    /* ---------- abrir / fechar ---------- */

    function isAberto() {
        return STATE.backdrop && STATE.backdrop.classList.contains('is-open');
    }

    function abrir() {
        if (!STATE.ready) return;
        STATE.backdrop.classList.add('is-open');
        STATE.triggerBtn.setAttribute('aria-expanded', 'true');

        // pré-seleciona o atual
        const atual = STATE.select.value;
        marcarSelecionado(atual);
        const itemAtual = findByNome(atual);
        if (itemAtual) {
            previewItem(itemAtual);
        } else {
            STATE.previewKey = null;
            STATE.confirmBtn.disabled = true;
            STATE.previewEl.innerHTML = '<div class="anc-picker-preview-empty">Passe o mouse sobre uma ancestralidade para ver detalhes.</div>';
        }
    }

    function fechar() {
        if (!STATE.backdrop) return;
        STATE.backdrop.classList.remove('is-open');
        STATE.triggerBtn.setAttribute('aria-expanded', 'false');
        STATE.triggerBtn.focus();
    }

    function marcarSelecionado(nome) {
        STATE.listEl.querySelectorAll('.anc-picker-option').forEach(b => {
            b.classList.toggle('is-selected', (b.dataset.nome || '') === (nome || ''));
        });
    }

    function navegar(delta) {
        const opts = Array.from(STATE.listEl.querySelectorAll('.anc-picker-option'));
        if (!opts.length) return;
        const atual = opts.findIndex(o => o.classList.contains('is-preview'));
        const idx = ((atual < 0 ? 0 : atual) + delta + opts.length) % opts.length;
        const next = opts[idx];
        next.focus();
    }

    function confirmar() {
        if (!STATE.previewKey) return;
        const novo = STATE.previewKey === '__none__' ? '' : STATE.previewKey;

        if (STATE.select.value !== novo) {
            STATE.select.value = novo;
            STATE.select.dispatchEvent(new Event('input',  { bubbles: true }));
            STATE.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        atualizarLabelGatilho();
        marcarSelecionado(novo);
        fechar();
    }

    /* ---------- utilitários ---------- */

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ---------- bootstrap ---------- */

    async function init() {
        STATE.select = document.getElementById('ancestralidadeSelect');
        if (!STATE.select) return;

        await carregarDados();
        construirGatilho();
        construirModal();
        STATE.ready = true;

        // Quando outro código (ex.: carregar ficha) altera o select, sincroniza
        STATE.select.addEventListener('change', () => {
            atualizarLabelGatilho();
            if (isAberto()) marcarSelecionado(STATE.select.value);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

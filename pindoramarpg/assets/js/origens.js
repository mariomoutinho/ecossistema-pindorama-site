(function () {
    'use strict';

    const NOMES_ATRIBUTO = {
        for: 'Força', des: 'Destreza', con: 'Constituição',
        int: 'Inteligência', sab: 'Sabedoria', car: 'Carisma',
    };

    let origemAtual = null;        // objeto da origem carregada do endpoint
    let escolhasAtuais = [];       // [{tipo:'pericia',nome:'X'} | {tipo:'poder',id:'..'}]
    let poderEmFoco = null;        // {id, nome, descricao} mostrado no modal

    function escaparHtml(t) {
        if (t == null) return '';
        return String(t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function lerOrigemSelecionada() {
        const sel = document.getElementById('origemSelect');
        return sel ? sel.value : '';
    }

    function persistirHidden() {
        const hidden = document.getElementById('origemBeneficiosJson');
        if (hidden) hidden.value = JSON.stringify(escolhasAtuais);
    }

    function temEscolha(tipo, idOuNome) {
        return escolhasAtuais.some(b =>
            (tipo === 'pericia' && b.tipo === 'pericia' && b.nome === idOuNome) ||
            (tipo === 'poder'   && b.tipo === 'poder'   && b.id   === idOuNome)
        );
    }

    function normalizarNomePericia(nome) {
        return String(nome || '').replace(/\s*\([^)]*\)/g, '').trim();
    }

    function acharCheckboxPericia(nome, preferirDesmarcado) {
        const exato = document.querySelector(`[data-skill="${nome}"][data-field="treinada"]`);
        if (exato) return exato;

        const base = normalizarNomePericia(nome);
        if (!base) return null;

        const baseExato = document.querySelector(`[data-skill="${base}"][data-field="treinada"]`);
        if (baseExato) return baseExato;

        // Fuzzy pra perícias com sufixos (ex: "Ofício 1" / "Ofício 2" quando o nome é "Ofício" ou "Ofício (cozinheiro)")
        const candidatos = Array.from(document.querySelectorAll('[data-field="treinada"]'))
            .filter(c => {
                const s = c.dataset.skill || '';
                return s === base || s.startsWith(base + ' ');
            });
        if (!candidatos.length) return null;
        if (preferirDesmarcado) return candidatos.find(c => !c.checked) || candidatos[0];
        return candidatos.find(c => c.checked) || candidatos[0];
    }

    function marcarPericiaTreinada(nome) {
        const cb = acharCheckboxPericia(nome, true);
        if (cb && !cb.checked) {
            cb.checked = true;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function desmarcarPericiaTreinada(nome) {
        const cb = acharCheckboxPericia(nome, false);
        if (cb && cb.checked) {
            cb.checked = false;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function adicionarEscolha(escolha) {
        if (escolhasAtuais.length >= 2) return false;
        if (temEscolha(escolha.tipo, escolha.tipo === 'pericia' ? escolha.nome : escolha.id)) return false;
        escolhasAtuais.push(escolha);
        return true;
    }

    function removerEscolha(tipo, idOuNome) {
        escolhasAtuais = escolhasAtuais.filter(b =>
            !(tipo === 'pericia' && b.tipo === 'pericia' && b.nome === idOuNome) &&
            !(tipo === 'poder'   && b.tipo === 'poder'   && b.id   === idOuNome)
        );
    }

    async function carregarOrigem(idOrigem) {
        if (!idOrigem) {
            origemAtual = null;
            escolhasAtuais = [];
            renderizar();
            persistirHidden();
            return;
        }
        try {
            const resp = await fetch(`origens-ui.php?id=${encodeURIComponent(idOrigem)}`);
            const json = await resp.json();
            if (!json.success) {
                console.error('Erro carregando origem:', json);
                origemAtual = null;
            } else {
                origemAtual = json.origem;
            }
        } catch (e) {
            console.error('Falha origens-ui:', e);
            origemAtual = null;
        }
        renderizar();
    }

    /**
     * Resumo (read-only) dos benefícios de origem já escolhidos —
     * exibido no topo da ficha, na linha logo abaixo de Traços
     * Ancestrais. Reaproveita as classes visuais
     * `.ancestralidade-tags / .ancestralidade-empty` para manter
     * exatamente o mesmo padrão dos Traços. Consome o estado já
     * existente `escolhasAtuais` — sem duplicar regra de negócio.
     */
    function renderResumoTopo() {
        const tags  = document.getElementById('origemBeneficiosResumo');
        const empty = document.getElementById('origemBeneficiosResumoEmpty');
        if (!tags || !empty) return;

        if (!escolhasAtuais.length) {
            tags.innerHTML = '';
            empty.style.display = '';
            return;
        }

        empty.style.display = 'none';
        tags.innerHTML = escolhasAtuais.map(escolha => {
            if (escolha.tipo === 'pericia') {
                return `<span class="ancestralidade-tag origem-resumo-tag origem-resumo-tag--pericia">${escaparHtml(escolha.nome)}</span>`;
            }
            // Poder: tenta resolver o nome via origemAtual; fallback p/ id
            // (caso renderResumoTopo rode antes do fetch da origem terminar
            // ao carregar uma ficha salva).
            const poder = ((origemAtual && origemAtual.poderes) || []).find(p => p.id === escolha.id);
            const nome = poder?.nome || escolha.id;
            return `<span class="ancestralidade-tag origem-resumo-tag origem-resumo-tag--poder">${escaparHtml(nome)}</span>`;
        }).join('');
    }

    function renderizar() {
        const empty = document.getElementById('origemEmpty');
        const conteudo = document.getElementById('origemConteudo');
        const contador = document.getElementById('origemContador');

        if (!origemAtual) {
            if (empty) empty.style.display = '';
            if (conteudo) conteudo.hidden = true;
            if (contador) contador.textContent = '0 / 2';
            renderEscolhidos();
            renderResumoTopo();
            return;
        }

        if (empty) empty.style.display = 'none';
        if (conteudo) conteudo.hidden = false;

        const nome = document.getElementById('origemNome');
        if (nome) nome.textContent = origemAtual.nome;

        const atrs = document.getElementById('origemAtributos');
        if (atrs) {
            const sigs = (origemAtual.atributos || [])
                .map(a => NOMES_ATRIBUTO[a] || a.toUpperCase());
            const textoAtrs = sigs.length === 6 ? 'Qualquer atributo' : sigs.join(' / ');
            atrs.innerHTML = `<strong>Atributos:</strong> ${escaparHtml(textoAtrs)}`;
        }

        const desc = document.getElementById('origemDescricao');
        if (desc) desc.textContent = origemAtual.descricao || '';

        const itens = document.getElementById('origemItens');
        if (itens) {
            itens.innerHTML = (origemAtual.itens || [])
                .map(i => `<li>${escaparHtml(i)}</li>`)
                .join('');
        }

        const obs = document.getElementById('origemObservacao');
        if (obs) {
            if (origemAtual.observacao) {
                obs.hidden = false;
                obs.innerHTML = `<em>${escaparHtml(origemAtual.observacao)}</em>`;
            } else {
                obs.hidden = true;
                obs.innerHTML = '';
            }
        }

        renderPericias();
        renderPoderes();
        renderEscolhidos();
        renderResumoTopo();

        if (contador) contador.textContent = `${escolhasAtuais.length} / 2`;
        persistirHidden();
    }

    function renderEscolhidos() {
        const cont = document.getElementById('origemEscolhidosLista');
        if (!cont) return;

        if (!escolhasAtuais.length) {
            cont.innerHTML = '<div class="origem-vazio">Nenhum benefício escolhido ainda.</div>';
            return;
        }

        cont.innerHTML = escolhasAtuais.map(escolha => {
            if (escolha.tipo === 'pericia') {
                return `<button type="button" class="pindorama-tag origem-tag-escolhida origem-tag-selecionada" data-tipo="pericia" data-valor="${escaparHtml(escolha.nome)}">${escaparHtml(escolha.nome)}</button>`;
            }

            const poder = ((origemAtual && origemAtual.poderes) || []).find(p => p.id === escolha.id);
            const nome = poder?.nome || escolha.id;
            return `<button type="button" class="pindorama-tag origem-tag-escolhida origem-tag-selecionada" data-tipo="poder" data-valor="${escaparHtml(escolha.id)}">${escaparHtml(nome)}</button>`;
        }).join('');

        cont.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.tipo === 'pericia') {
                    alternarEscolhaPericia(btn.dataset.valor);
                } else {
                    abrirModalPoder(btn.dataset.valor);
                }
            });
        });
    }

    function renderPericias() {
        const cont = document.getElementById('origemPericiasLista');
        if (!cont) return;

        const pericias = origemAtual.pericias || [];
        if (!pericias.length) {
            cont.innerHTML = '<div class="origem-vazio">Nenhuma perícia listada para esta origem.</div>';
            return;
        }

        cont.innerHTML = pericias.map(p => {
            const escolhida = temEscolha('pericia', p);
            const cls = 'origem-tag origem-tag-pericia' + (escolhida ? ' origem-tag-escolhida' : '');
            return `<button type="button" class="${cls}" data-tipo="pericia" data-valor="${escaparHtml(p)}">
                ${escolhida ? '✓ ' : ''}${escaparHtml(p)}
            </button>`;
        }).join('');

        cont.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => alternarEscolhaPericia(btn.dataset.valor));
        });
    }

    function renderPoderes() {
        const cont = document.getElementById('origemPoderesLista');
        if (!cont) return;

        const poderes = origemAtual.poderes || [];
        if (!poderes.length) {
            cont.innerHTML = '<div class="origem-vazio">Nenhum poder listado para esta origem.</div>';
            return;
        }

        cont.innerHTML = poderes.map(p => {
            const escolhido = temEscolha('poder', p.id);
            const ehUnico = (p.categoria === 'origem');
            const cls = 'origem-card-poder'
                + (escolhido ? ' origem-card-escolhido' : '')
                + (ehUnico ? ' origem-card-unico' : '');
            const tag = ehUnico ? '<span class="origem-tag-unico">único</span>' : '';
            return `
                <div class="${cls}" data-poder-id="${escaparHtml(p.id)}">
                    <div class="origem-card-poder-header">
                        <span class="origem-card-poder-nome">${escaparHtml(p.nome)}</span>
                        ${tag}
                        ${escolhido ? '<span class="origem-card-poder-marca">✓</span>' : ''}
                    </div>
                    <div class="origem-card-poder-resumo">${escaparHtml(p.descricao)}</div>
                </div>
            `;
        }).join('');

        cont.querySelectorAll('.origem-card-poder').forEach(card => {
            card.addEventListener('click', () => abrirModalPoder(card.dataset.poderId));
        });
    }

    function alternarEscolhaPericia(nome) {
        if (temEscolha('pericia', nome)) {
            removerEscolha('pericia', nome);
            desmarcarPericiaTreinada(nome);
        } else {
            if (escolhasAtuais.length >= 2) {
                mostrarAvisoFlutuante('Você já escolheu 2 benefícios. Remova um primeiro.');
                return;
            }
            adicionarEscolha({ tipo: 'pericia', nome });
            marcarPericiaTreinada(nome);
        }
        renderizar();
    }

    /* ----------- Validação genérica de pré-requisitos -----------
       Consulta os dados atuais da ficha para verificar se o personagem
       satisfaz cada item de poder.prerequisitos. Suporta os tipos
       encontrados no dataset (data/poderes-gerais.json):
         - 'atributo' { atributo, valor }
         - 'poder'    { id }
         - 'nivel_classe' { classe, nivel }
         - 'manual'   { texto }  → não validável; tratamos como aviso. */
    const ATRIBUTO_FIELD = {
        for: 'forca', des: 'destreza', con: 'constituicao',
        int: 'inteligencia', sab: 'sabedoria', car: 'carisma',
    };
    function valorAtributoAtual(abrev) {
        const field = ATRIBUTO_FIELD[String(abrev || '').toLowerCase()];
        if (!field) return null;
        const el = document.querySelector(`[name="${field}"]`);
        if (!el) return null;
        const n = parseInt(el.value, 10);
        return Number.isFinite(n) ? n : 0;
    }
    function nivelAtual() {
        const el = document.querySelector('[name="nivel"]');
        const n = el ? parseInt(el.value, 10) : 1;
        return Number.isFinite(n) ? n : 1;
    }
    function classeAtual() {
        const sel = document.getElementById('classeSelect');
        if (!sel) return '';
        const opt = sel.options ? sel.options[sel.selectedIndex] : null;
        return (opt && opt.dataset && opt.dataset.classeId)
            ? String(opt.dataset.classeId).toLowerCase()
            : String(sel.value || '').toLowerCase();
    }
    function temPoderAdquirido(idPoder) {
        if (escolhasAtuais.some(b => b.tipo === 'poder' && b.id === idPoder)) return true;
        return !!document.querySelector(`.poderes-tag-adquirido[data-poder-id="${idPoder}"]`);
    }
    function validarPrereqsPoder(poder) {
        const reqs = Array.isArray(poder && poder.prerequisitos) ? poder.prerequisitos : [];
        const faltando = [];
        for (const r of reqs) {
            const t = String(r.tipo || '').toLowerCase();
            if (t === 'atributo') {
                const atual = valorAtributoAtual(r.atributo);
                if (atual === null || atual < (parseInt(r.valor, 10) || 0)) {
                    faltando.push(r.texto || `${String(r.atributo || '').toUpperCase()} ${r.valor}`);
                }
            } else if (t === 'nivel_classe') {
                if (classeAtual() !== String(r.classe || '').toLowerCase() || nivelAtual() < (parseInt(r.nivel, 10) || 0)) {
                    faltando.push(r.texto || `${r.classe} nível ${r.nivel}`);
                }
            } else if (t === 'poder') {
                if (!temPoderAdquirido(r.id)) {
                    faltando.push(r.texto || `Poder: ${r.id}`);
                }
            } else if (t === 'manual') {
                // Não validável automaticamente — o jogador é responsável por confirmar.
                // Não bloqueia, só mantém o texto à mostra no body do modal.
            }
        }
        return { ok: faltando.length === 0, faltando };
    }

    /** Exibe um aviso inline DENTRO do próprio modal de detalhes,
        sempre acima do conteúdo. Substitui alert() e garante que o
        usuário veja o feedback sem precisar fechar a janela. */
    function mostrarAvisoModal(mensagem, variante /* 'erro' | 'info' */) {
        const body = document.getElementById('origemModalBody');
        if (!body) return;
        let aviso = body.querySelector('.origem-modal-aviso');
        if (!aviso) {
            aviso = document.createElement('div');
            aviso.className = 'origem-modal-aviso';
            body.prepend(aviso);
        }
        aviso.dataset.variant = variante || 'erro';
        aviso.textContent = mensagem;
        aviso.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    function limparAvisoModal() {
        const aviso = document.querySelector('#origemModalBody .origem-modal-aviso');
        if (aviso) aviso.remove();
    }

    /** Toast flutuante para avisos fora do modal de detalhes do poder
        (ex.: clique direto numa perícia já com 2 benefícios). Aparece
        acima de tudo (z-index 10001), centralizado, auto-dismiss 3s. */
    let _avisoToastTimer = null;
    function mostrarAvisoFlutuante(mensagem) {
        let host = document.getElementById('origemAvisoToast');
        if (!host) {
            host = document.createElement('div');
            host.id = 'origemAvisoToast';
            host.className = 'origem-aviso-toast';
            host.setAttribute('role', 'status');
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        host.textContent = mensagem;
        host.classList.add('is-visible');
        if (_avisoToastTimer) clearTimeout(_avisoToastTimer);
        _avisoToastTimer = setTimeout(() => host.classList.remove('is-visible'), 3000);
    }

    function abrirModalPoder(idPoder) {
        const poder = (origemAtual.poderes || []).find(p => p.id === idPoder);
        if (!poder) return;
        poderEmFoco = poder;

        const titulo = document.getElementById('origemModalTitulo');
        const body   = document.getElementById('origemModalBody');
        const btnAdq = document.getElementById('origemModalAdquirir');
        const btnRem = document.getElementById('origemModalRemover');

        if (titulo) titulo.textContent = poder.nome;

        const prereqHtml = poder.prerequisito_texto
            ? `<div class="origem-modal-prereq"><strong>Pré-requisito:</strong> ${escaparHtml(poder.prerequisito_texto)}</div>`
            : '';
        const tagUnico = poder.categoria === 'origem'
            ? '<div class="origem-modal-unico">Habilidade exclusiva desta origem</div>'
            : '';

        if (body) {
            body.innerHTML = `
                ${tagUnico}
                <p class="origem-modal-descricao">${escaparHtml(poder.descricao)}</p>
                ${prereqHtml}
            `;
        }

        const escolhido = temEscolha('poder', poder.id);
        if (btnAdq) {
            btnAdq.style.display = escolhido ? 'none' : '';
            btnAdq.disabled = (escolhasAtuais.length >= 2 && !escolhido);
        }
        if (btnRem) {
            btnRem.style.display = escolhido ? '' : 'none';
        }

        const modal = document.getElementById('origemModal');
        if (modal) modal.hidden = false;
    }

    function fecharModal() {
        const modal = document.getElementById('origemModal');
        if (modal) modal.hidden = true;
        poderEmFoco = null;
    }

    function adquirirPoderEmFoco() {
        if (!poderEmFoco) return;
        limparAvisoModal();

        if (escolhasAtuais.length >= 2) {
            mostrarAvisoModal('Você já escolheu 2 benefícios. Remova um primeiro.', 'erro');
            return;
        }

        const check = validarPrereqsPoder(poderEmFoco);
        if (!check.ok) {
            const msg = 'Você não pode adquirir este benefício: exige ' + check.faltando.join(' e ') + '.';
            mostrarAvisoModal(msg, 'erro');
            return;
        }

        adicionarEscolha({ tipo: 'poder', id: poderEmFoco.id, nome: poderEmFoco.nome });
        fecharModal();
        renderizar();
    }

    function removerPoderEmFoco() {
        if (!poderEmFoco) return;
        removerEscolha('poder', poderEmFoco.id);
        fecharModal();
        renderizar();
    }

    function aplicarOrigemDeFicha(ficha) {
        const sel = document.getElementById('origemSelect');
        const idOrigem = ficha.origem || '';
        if (sel) sel.value = idOrigem;

        try {
            const beneficios = ficha.origem_beneficios
                ? (typeof ficha.origem_beneficios === 'string'
                    ? JSON.parse(ficha.origem_beneficios)
                    : ficha.origem_beneficios)
                : [];
            escolhasAtuais = Array.isArray(beneficios) ? beneficios : [];
        } catch (e) {
            console.warn('origem_beneficios inválido:', e);
            escolhasAtuais = [];
        }

        carregarOrigem(idOrigem);
    }

    function init() {
        const sel = document.getElementById('origemSelect');
        if (sel) sel.addEventListener('change', () => {
            // Desmarca perícias que foram marcadas pela origem anterior
            escolhasAtuais
                .filter(b => b.tipo === 'pericia')
                .forEach(b => desmarcarPericiaTreinada(b.nome));
            escolhasAtuais = [];
            renderResumoTopo();    // limpa o resumo do topo antes do fetch
            carregarOrigem(sel.value);
        });

        // Estado inicial: garante o resumo vazio mesmo antes do primeiro
        // change/load (ex.: ficha em branco recém-criada).
        renderResumoTopo();

        // Botão "editar" do resumo no topo dispara o trigger do picker
        // de Origem (o painel embarcado aparece junto). Sem JS extra
        // fora deste módulo.
        const editarBtn = document.getElementById('origemEditarBtn');
        if (editarBtn) {
            editarBtn.addEventListener('click', () => {
                const field = document.getElementById('origemSelect')?.parentElement;
                const trigger = field?.querySelector('.anc-picker-trigger');
                if (trigger) trigger.click();
            });
        }

        const fechar  = document.getElementById('origemModalFechar');
        const adquirir = document.getElementById('origemModalAdquirir');
        const remover = document.getElementById('origemModalRemover');
        if (fechar)  fechar.addEventListener('click', fecharModal);
        if (adquirir) adquirir.addEventListener('click', adquirirPoderEmFoco);
        if (remover) remover.addEventListener('click', removerPoderEmFoco);

        const recolherBtn = document.getElementById('origemRecolherBtn');
        const painel = document.getElementById('origemPanel');
        if (recolherBtn && painel) {
            recolherBtn.addEventListener('click', () => {
                const recolhido = painel.classList.toggle('origem-panel-recolhido');
                recolherBtn.setAttribute('aria-expanded', String(!recolhido));
                recolherBtn.setAttribute('aria-label', recolhido ? 'Expandir seção' : 'Recolher seção');
            });
        }

        const modal = document.getElementById('origemModal');
        if (modal) modal.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });

        const form = document.getElementById('fichaForm');
        if (form) form.addEventListener('submit', persistirHidden, true);

        // Carrega ao iniciar (se já houver valor selecionado)
        if (sel && sel.value) carregarOrigem(sel.value);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.OrigensPindorama = { aplicarOrigemDeFicha };
})();

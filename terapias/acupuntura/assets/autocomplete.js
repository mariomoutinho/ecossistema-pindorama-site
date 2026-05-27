// ============================================================
// AcupAutocomplete — componente de busca + seleção múltipla.
//
// Uso (vê-se no index.php):
//   <div class="acup-ac"
//        data-name="sintomas"
//        data-placeholder="Buscar sintomas..."
//        data-options='["dor de cabeça","ansiedade",...]'
//        data-selected='["ansiedade"]'></div>
//
// Para o PHP, gera inputs ocultos com name="<nome>[]". Compatível com o
// $_POST['sintomas'] que o backend já consome — zero mudança no servidor.
//
// Vanilla JS, sem dependências, sem build.
// ============================================================
(function () {
  'use strict';

  function normaliza(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function highlight(label, query) {
    if (!query) return escapeHtml(label);
    var ln = normaliza(label);
    var qn = normaliza(query);
    var idx = ln.indexOf(qn);
    if (idx < 0) return escapeHtml(label);
    // a normalização não muda comprimento (NFD remove acentos como char separado, mas removemos com regex)
    // o offset em chars do original pode diferir; uso aproximação simples baseada no índice.
    var end = idx + qn.length;
    return escapeHtml(label.slice(0, idx))
         + '<span class="acup-ac__option__hl">' + escapeHtml(label.slice(idx, end)) + '</span>'
         + escapeHtml(label.slice(end));
  }

  function createComponent(root) {
    if (root.__acupAcInit) return;
    root.__acupAcInit = true;

    var name        = root.getAttribute('data-name') || 'campo';
    var placeholder = root.getAttribute('data-placeholder') || 'Buscar...';
    var allowEmpty  = root.getAttribute('data-allow-empty') === '1';

    var options = [];
    try { options = JSON.parse(root.getAttribute('data-options') || '[]') || []; }
    catch (e) { options = []; }

    var selected = [];
    try { selected = JSON.parse(root.getAttribute('data-selected') || '[]') || []; }
    catch (e) { selected = []; }

    // Suporte a opções como {value,label} ou só strings
    var allOptions = options.map(function (o) {
      if (typeof o === 'string') return { value: o, label: o };
      return { value: o.value, label: o.label || o.value };
    });
    // Ordena alfabeticamente por label (ignora acentos / caixa)
    allOptions.sort(function (a, b) {
      return normaliza(a.label).localeCompare(normaliza(b.label), 'pt-BR');
    });

    var sel = new Set(selected.map(String));
    var activeIdx = -1;
    var currentQuery = '';

    // Estrutura DOM
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'acup-ac__input';
    input.placeholder = placeholder;
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('role', 'combobox');

    var list = document.createElement('div');
    list.className = 'acup-ac__list';
    list.setAttribute('role', 'listbox');

    root.appendChild(input);
    root.appendChild(list);

    // Inputs ocultos (form submit)
    function syncHiddenInputs() {
      // remove anteriores
      root.querySelectorAll('.acup-ac__hidden').forEach(function (n) { n.remove(); });
      if (sel.size === 0 && allowEmpty) {
        // mantém um hidden vazio só para sinalizar presença do campo (opcional)
        return;
      }
      sel.forEach(function (v) {
        var h = document.createElement('input');
        h.type = 'hidden';
        h.className = 'acup-ac__hidden';
        h.name = name + '[]';
        h.value = v;
        root.appendChild(h);
      });
    }

    // Renderiza chips selecionados
    function renderChips() {
      root.querySelectorAll('.acup-ac__chip').forEach(function (c) { c.remove(); });
      // preserva ordem das seleções existentes
      var toRender = [];
      sel.forEach(function (v) {
        var opt = allOptions.find(function (o) { return String(o.value) === String(v); });
        toRender.push(opt ? opt : { value: v, label: v });
      });
      toRender.forEach(function (opt) {
        var chip = document.createElement('span');
        chip.className = 'acup-ac__chip';
        chip.dataset.value = opt.value;
        chip.innerHTML = escapeHtml(opt.label) +
          ' <button type="button" class="acup-ac__chip__remove" aria-label="Remover ' + escapeHtml(opt.label) + '">×</button>';
        // insere antes do input
        root.insertBefore(chip, input);
      });
    }

    // Renderiza lista filtrada
    function renderList() {
      var q = normaliza(currentQuery);
      var filtered = allOptions.filter(function (o) {
        if (sel.has(String(o.value))) return false; // não repete
        if (!q) return true;
        return normaliza(o.label).indexOf(q) >= 0;
      });

      if (filtered.length === 0) {
        list.innerHTML = '<div class="acup-ac__option acup-ac__option--empty">Nenhuma opção encontrada</div>';
        activeIdx = -1;
        return;
      }

      var html = '';
      filtered.forEach(function (o, i) {
        html += '<div class="acup-ac__option' + (i === activeIdx ? ' is-active' : '') + '"'
              + ' role="option" data-value="' + escapeHtml(o.value) + '">'
              + highlight(o.label, currentQuery)
              + '</div>';
      });
      list.innerHTML = html;
    }

    function open() {
      root.classList.add('is-open');
      root.classList.add('is-focused');
      input.setAttribute('aria-expanded', 'true');
      renderList();
    }
    function close() {
      root.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
      activeIdx = -1;
    }

    function visibleOptions() {
      return Array.prototype.slice.call(list.querySelectorAll('.acup-ac__option:not(.acup-ac__option--empty)'));
    }
    function setActive(idx) {
      var opts = visibleOptions();
      if (!opts.length) { activeIdx = -1; return; }
      activeIdx = (idx + opts.length) % opts.length;
      opts.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIdx); });
      var el = opts[activeIdx];
      if (el && el.scrollIntoView) {
        el.scrollIntoView({ block: 'nearest' });
      }
    }
    function pickActive() {
      var opts = visibleOptions();
      if (activeIdx < 0 || !opts[activeIdx]) {
        if (opts.length === 1) activeIdx = 0;
        else return;
      }
      var val = opts[activeIdx].dataset.value;
      addValue(val);
    }

    function addValue(value) {
      sel.add(String(value));
      currentQuery = '';
      input.value = '';
      renderChips();
      syncHiddenInputs();
      renderList();
      input.focus();
    }
    function removeValue(value) {
      sel.delete(String(value));
      renderChips();
      syncHiddenInputs();
      renderList();
    }

    // ---- Eventos ----
    root.addEventListener('click', function (e) {
      // se clicou no botão de remover chip
      var rm = e.target.closest('.acup-ac__chip__remove');
      if (rm) {
        var chip = rm.closest('.acup-ac__chip');
        if (chip) removeValue(chip.dataset.value);
        e.stopPropagation();
        return;
      }
      // clique na opção
      var opt = e.target.closest('.acup-ac__option:not(.acup-ac__option--empty)');
      if (opt) {
        addValue(opt.dataset.value);
        e.stopPropagation();
        return;
      }
      // clique dentro do container fora de chip/opção: foca input + abre
      input.focus();
      open();
    });

    input.addEventListener('focus', open);
    input.addEventListener('input', function () {
      currentQuery = input.value;
      activeIdx = -1;
      open();
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); open(); setActive(activeIdx + 1); }
      else if (e.key === 'ArrowUp')   { e.preventDefault(); open(); setActive(activeIdx - 1); }
      else if (e.key === 'Enter')     {
        if (root.classList.contains('is-open')) {
          e.preventDefault();
          pickActive();
        }
      }
      else if (e.key === 'Escape')    { close(); input.blur(); }
      else if (e.key === 'Backspace' && input.value === '' && sel.size > 0) {
        // remove última tag
        var last = Array.from(sel).pop();
        if (last !== undefined) removeValue(last);
      }
      else if (e.key === 'Tab') {
        close();
      }
    });

    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) {
        close();
        root.classList.remove('is-focused');
      }
    }, true);

    // Estado inicial
    renderChips();
    syncHiddenInputs();
    renderList();
  }

  function bootAll() {
    document.querySelectorAll('.acup-ac').forEach(createComponent);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAll);
  } else {
    bootAll();
  }

  window.AcupAutocomplete = { boot: bootAll };
})();

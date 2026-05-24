// =========================
// Página de Terapias — catálogo completo
// (estrutura idêntica à de home.js para manter manutenção simples;
// duplicação intencional, conforme combinado).
// Preço exibido = valor por sessão "a partir de". "Sob consulta" para
// práticas com valor que muda por formato/turma.
// =========================

// ---- Menu mobile ----
const btnMenu = document.getElementById('btnMenu');
const drawer  = document.getElementById('drawer');

if (btnMenu && drawer) {
  btnMenu.addEventListener('click', () => {
    const open = drawer.classList.toggle('open');
    btnMenu.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  drawer.querySelectorAll('a').forEach(a =>
    a.addEventListener('click', () => {
      drawer.classList.remove('open');
      btnMenu.setAttribute('aria-expanded', 'false');
    })
  );
}

// ---- Catálogo completo ----
const services = [
  // Massagens corporais
  { cat: 'Massagens corporais', title: 'Massagem Ayurvédica',                duration: '80 min', priceFrom: 'R$ 160,00',     desc: 'Cuidado profundo para relaxamento, vitalidade e equilíbrio.',                              bg: './assets/img/massagem-ayurvedica-bg.png' },
  { cat: 'Massagens corporais', title: 'Massoterapia (diversas técnicas)',   duration: '60 min', priceFrom: 'R$ 120,00',     desc: 'Atendimento adaptado às necessidades do corpo e do momento.',                              bg: './assets/img/massoterapia-bg.png' },
  { cat: 'Massagens corporais', title: 'Quick Massage',                       duration: '20 min', priceFrom: 'R$ 40,00',      desc: 'Massagem rápida e revigorante, ideal para alívio imediato de tensões.',                     bg: './assets/img/terapias/quick-massage.png' },
  { cat: 'Massagens corporais', title: 'Massagem com pedras quentes',         duration: '60 min', priceFrom: 'R$ 96,00',      desc: 'Calor terapêutico que dissolve tensões profundas e aprofunda o relaxamento.',                bg: './assets/img/terapias/pedras-quentes.png' },
  { cat: 'Massagens corporais', title: 'Manipulação Vertebral',               duration: '50 min', priceFrom: 'R$ 96,00',      desc: 'Ajustes precisos para restabelecer mobilidade e aliviar tensões na coluna.',                 bg: './assets/img/terapias/manipulacao-vertebral.png' },
  { cat: 'Massagens corporais', title: 'Liberação Miofascial',                duration: '60 min', priceFrom: 'R$ 96,00',      desc: 'Técnica para soltar tensões profundas das fáscias e restaurar o equilíbrio corporal.',       bg: './assets/img/terapias/massagem-relaxante.png' },
  { cat: 'Massagens corporais', title: 'Shantala (massagem para bebês)',      duration: '40 min', priceFrom: 'Sob consulta',  desc: 'Tradição indiana de toque entre quem cuida e o bebê — vínculo, sono e bem-estar.',         bg: './assets/img/shantala-bg.png' },
  { cat: 'Massagens corporais', title: 'Oficina de Automassagem',             duration: '60 min', priceFrom: 'Sob consulta',  desc: 'Aprenda gestos simples para cuidar de si no dia a dia.',                                   bg: './assets/img/automassagem-bg.png' },

  // Terapias orientais
  { cat: 'Terapias orientais',  title: 'Acupuntura',                         duration: '60 min', priceFrom: 'R$ 120,00',     desc: 'Prática integrativa para dores, estresse e regulação do organismo.',                       bg: './assets/img/acupuntura-bg.png', bgPos: '75% center', bgSize: '140% auto' },
  { cat: 'Terapias orientais',  title: 'Ventosaterapia',                      duration: '45 min', priceFrom: 'R$ 64,00',      desc: 'Apoio para tensão muscular, circulação e bem-estar.',                                      bg: './assets/img/ventosaterapia-bg.png' },
  { cat: 'Terapias orientais',  title: 'Moxabustão',                          duration: '40 min', priceFrom: 'R$ 56,00',      desc: 'Calor terapêutico aplicado em pontos energéticos para equilíbrio vital.',                    bg: './assets/img/terapias/moxabustao.png' },
  { cat: 'Terapias orientais',  title: 'Auriculoterapia',                     duration: '30 min', priceFrom: 'R$ 56,00',      desc: 'Estímulos na orelha para apoiar equilíbrio e sintomas.',                                    bg: './assets/img/auriculoterapia-bg.png' },
  { cat: 'Terapias orientais',  title: 'Consulta em MTC',                     duration: '60 min', priceFrom: 'Sob consulta',  desc: 'Diagnóstico energético e plano de cuidado pela ótica da Medicina Tradicional Chinesa.',    bg: './assets/img/mtc-bg.png' },

  // Cuidado integrativo
  { cat: 'Cuidado integrativo', title: 'Reflexologia Podal',                  duration: '45 min', priceFrom: 'R$ 64,00',      desc: 'Massagem nos pés que repercute bem-estar para todo o corpo.',                                bg: './assets/img/terapias/reflexologia-podal.png' },
  { cat: 'Cuidado integrativo', title: 'Reiki',                                duration: '40 min', priceFrom: 'R$ 48,00',      desc: 'Cuidado energético para acolher emoções e relaxar.',                                       bg: './assets/img/reiki-bg.png' },
  { cat: 'Cuidado integrativo', title: 'Meditação guiada',                     duration: '45 min', priceFrom: 'Sob consulta',  desc: 'Práticas para presença, foco e regulação emocional.',                                       bg: './assets/img/meditacao-bg.png' },
  { cat: 'Cuidado integrativo', title: 'Consulta Terapêutica',                 duration: '60 min', priceFrom: 'Sob consulta',  desc: 'Escuta qualificada para mapear demandas e construir um caminho de cuidado.',                bg: './assets/img/consulta-terapeutica-bg.png' },

  // Arte e movimento
  { cat: 'Arte e movimento',    title: 'Arteterapia',                         duration: '60 min', priceFrom: 'Sob consulta',  desc: 'Processos criativos como caminho de expressão e elaboração.',                              bg: './assets/img/arteterapia-bg.png' },
  { cat: 'Arte e movimento',    title: 'SoulCollage®',                        duration: '90 min', priceFrom: 'Sob consulta',  desc: 'Vivência expressiva com colagens e imagens internas.',                                      bg: './assets/img/soulcollage-bg.png' },
  { cat: 'Arte e movimento',    title: 'Dança Circular',                      duration: '90 min', priceFrom: 'Sob consulta',  desc: 'Roda de danças tradicionais — corpo, presença e comunidade.',                              bg: './assets/img/danca-circular-bg.png' },
  { cat: 'Arte e movimento',    title: 'Tai Chi',                             duration: '60 min', priceFrom: 'Sob consulta',  desc: 'Movimentos lentos para equilíbrio, energia e atenção plena.',                                bg: './assets/img/thai-chi-bg.png' },
];

const categories = ['Todos', ...Array.from(new Set(services.map(s => s.cat)))];

const filtersEl = document.getElementById('serviceFilters');
const gridEl    = document.getElementById('servicesGrid');

function renderFilters(active = 'Todos') {
  if (!filtersEl) return;
  filtersEl.innerHTML = '';
  categories.forEach(cat => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'chip' + (cat === active ? ' active' : '');
    b.textContent = cat;
    b.setAttribute('aria-pressed', cat === active ? 'true' : 'false');
    b.addEventListener('click', () => {
      renderFilters(cat);
      renderServices(cat);
    });
    filtersEl.appendChild(b);
  });
}

function renderServices(filter = 'Todos') {
  if (!gridEl) return;

  const list = (filter === 'Todos') ? services : services.filter(s => s.cat === filter);

  gridEl.innerHTML = list.map(s => {
    const hasBg  = !!s.bg;
    const bgPos  = s.bgPos  || 'center';
    const bgSize = s.bgSize || 'cover';

    const bgStyle = hasBg
      ? `style="background-image: url('${escapeAttr(s.bg)}'); background-position: ${escapeAttr(bgPos)}; background-size: ${escapeAttr(bgSize)};"`
      : '';

    const cardClass = 'serviceCard' + (hasBg ? '' : ' serviceCard--noImage');

    const isUnderConsult = /sob\s*consulta/i.test(s.priceFrom);
    const priceBlock = isUnderConsult
      ? `<div class="serviceCard__price" aria-label="Valor sob consulta">
           <strong class="serviceCard__price-value serviceCard__price-value--sm">Valor sob consulta</strong>
         </div>`
      : `<div class="serviceCard__price" aria-label="Preço a partir de ${escapeAttr(s.priceFrom)} por sessão">
           <span class="serviceCard__price-prefix">A partir de</span>
           <strong class="serviceCard__price-value">${escapeHtml(s.priceFrom)}</strong>
           <span class="serviceCard__price-suffix">por sessão</span>
         </div>`;

    return `
      <article class="${cardClass}" tabindex="0">
        <div class="serviceCard__bg" ${bgStyle} aria-hidden="true"></div>
        <div class="serviceCard__overlay" aria-hidden="true"></div>

        <div class="serviceCard__inner">
          <div class="serviceCard__top">
            <span class="serviceCard__tag">${escapeHtml(s.cat)}</span>
            ${s.duration ? `<span class="serviceCard__duration">${escapeHtml(s.duration)}</span>` : ''}
          </div>

          <div class="serviceCard__body">
            <h4 class="serviceCard__title">${escapeHtml(s.title)}</h4>
            <p class="serviceCard__desc">${escapeHtml(s.desc)}</p>
            ${priceBlock}
          </div>
        </div>
      </article>
    `;
  }).join('');
}

function escapeHtml(str){
  return String(str).replace(/[&<>"']/g, (m) => ({
    '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
  }[m]));
}
function escapeAttr(str){
  return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

renderFilters('Todos');
renderServices('Todos');

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
const whatsappNumber = '5581995216450';

const therapyDetails = {
  'Massagem Ayurvédica': {
    what: 'Massagem corporal de origem indiana, feita com movimentos ritmados, óleo vegetal e abordagem integral do corpo.',
    purpose: 'Ajuda a relaxar profundamente, aliviar tensões, favorecer vitalidade e apoiar equilíbrio físico e emocional.',
    indications: ['Estresse e cansaço', 'Tensões musculares', 'Busca por relaxamento profundo', 'Rotina de autocuidado'],
    duration: '80 min',
    single: 'R$ 200,00',
    pack4: { perSession: 'R$ 180,00 por sessão', total: 'Total R$ 720,00' },
    pack10: { perSession: 'R$ 160,00 por sessão', total: 'Total R$ 1.600,00' },
  },
  'Acupuntura': {
    what: 'Técnica da Medicina Tradicional Chinesa que estimula pontos específicos do corpo com agulhas finas e seguras.',
    purpose: 'Ajuda a regular o organismo, aliviar dores, reduzir estresse e apoiar equilíbrio físico e emocional.',
    indications: ['Dores musculares e articulares', 'Ansiedade e estresse', 'Sono irregular', 'Cefaleias e enxaquecas'],
    duration: '60 min',
    single: 'R$ 150',
    pack4: 'R$ 540',
    pack10: 'R$ 1.200',
  },
  'Ventosaterapia': {
    what: 'Uso terapêutico de ventosas para mobilizar tecidos, estimular circulação local e soltar tensões.',
    purpose: 'Favorece relaxamento muscular, sensação de leveza corporal e recuperação após sobrecarga física.',
    indications: ['Tensão nas costas e ombros', 'Dores musculares', 'Rigidez corporal', 'Cansaço físico'],
    duration: '45 min',
    single: 'R$ 80',
    pack4: 'R$ 280',
    pack10: 'R$ 640',
  },
  'Moxabustão': {
    what: 'Aplicação de calor terapêutico com moxa em pontos energéticos usados pela Medicina Tradicional Chinesa.',
    purpose: 'Aquece, tonifica e ajuda a movimentar energia vital em quadros de frio, tensão e baixa vitalidade.',
    indications: ['Sensação de frio no corpo', 'Cansaço e baixa energia', 'Tensões persistentes', 'Cólicas e desconfortos'],
    duration: '40 min',
    single: 'R$ 70',
    pack4: 'R$ 250',
    pack10: 'R$ 560',
  },
  'Auriculoterapia': {
    what: 'Estimulação de pontos reflexos na orelha com sementes, esferas ou outros recursos não invasivos.',
    purpose: 'Apoia o equilíbrio do corpo e pode complementar cuidados para dores, ansiedade e hábitos de saúde.',
    indications: ['Ansiedade e estresse', 'Dores e tensões', 'Compulsões e hábitos', 'Apoio ao sono'],
    duration: '30 min',
    single: 'R$ 70',
    pack4: 'R$ 250',
    pack10: 'R$ 560',
  },
  'Reflexologia Podal': {
    what: 'Massagem terapêutica nos pés que trabalha pontos reflexos relacionados a diferentes regiões do corpo.',
    purpose: 'Promove relaxamento profundo, circulação, aterramento e sensação geral de bem-estar.',
    indications: ['Cansaço nas pernas e pés', 'Estresse', 'Tensão corporal', 'Busca por relaxamento'],
    duration: '45 min',
    single: 'R$ 80',
    pack4: 'R$ 280',
    pack10: 'R$ 640',
  },
  'Reiki': {
    what: 'Prática energética de toque suave ou aproximação das mãos para acolhimento, relaxamento e harmonização.',
    purpose: 'Cria um espaço de pausa e presença, apoiando regulação emocional e tranquilidade.',
    indications: ['Ansiedade e agitação', 'Cansaço emocional', 'Estresse', 'Necessidade de acolhimento'],
    duration: '40 min',
    single: 'R$ 60',
    pack4: 'R$ 220',
    pack10: 'R$ 480',
  },
};

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
      <article class="${cardClass}" tabindex="0" role="button" aria-label="Ver detalhes de ${escapeAttr(s.title)}" data-service-index="${services.indexOf(s)}">
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

  gridEl.querySelectorAll('.serviceCard').forEach(card => {
    card.addEventListener('click', () => openServiceModal(Number(card.dataset.serviceIndex)));
    card.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openServiceModal(Number(card.dataset.serviceIndex));
      }
    });
  });
}

function getServiceDetail(service) {
  return therapyDetails[service.title] || {
    what: service.desc,
    purpose: 'A sessão é conduzida conforme sua necessidade do momento, com escuta e orientação da equipe do Espaço Pindorama.',
    indications: ['Relaxamento e bem-estar', 'Cuidado corporal', 'Escuta terapêutica', 'Construção de rotina de autocuidado'],
    duration: service.duration || 'Sob consulta',
    single: service.priceFrom || 'Sob consulta',
    pack4: 'Sob consulta',
    pack10: 'Sob consulta',
  };
}

function getWhatsAppLink(serviceName) {
  const text = `Olá, vim pelo site do Coletivo Pindorama e gostaria de agendar uma sessão de ${serviceName}.`;
  return `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(text)}`;
}

function renderPriceValue(value) {
  if (value && typeof value === 'object') {
    return `
      <strong>${escapeHtml(value.perSession)}</strong>
      <em>${escapeHtml(value.total)}</em>
    `;
  }

  return `<strong>${escapeHtml(value)}</strong>`;
}

let modalEl = null;
let lastFocusedEl = null;

function ensureServiceModal() {
  if (modalEl) return modalEl;

  modalEl = document.createElement('div');
  modalEl.className = 'therapyModal';
  modalEl.setAttribute('aria-hidden', 'true');
  modalEl.innerHTML = `
    <div class="therapyModal__backdrop" data-modal-close></div>
    <section class="therapyModal__dialog" role="dialog" aria-modal="true" aria-labelledby="therapyModalTitle" tabindex="-1">
      <button class="therapyModal__close" type="button" aria-label="Fechar detalhes" data-modal-close>&times;</button>
      <div class="therapyModal__hero" aria-hidden="true"></div>
      <div class="therapyModal__content">
        <span class="therapyModal__tag"></span>
        <h3 id="therapyModalTitle"></h3>
        <p class="therapyModal__intro"></p>
        <div class="therapyModal__sections"></div>
        <div class="therapyModal__prices"></div>
        <a class="btn primary therapyModal__cta" target="_blank" rel="noopener">Agendar pelo WhatsApp</a>
      </div>
    </section>
  `;

  modalEl.addEventListener('click', (event) => {
    if (event.target.closest('[data-modal-close]')) closeServiceModal();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modalEl.classList.contains('open')) {
      closeServiceModal();
    }
  });

  document.body.appendChild(modalEl);
  return modalEl;
}

function openServiceModal(serviceIndex) {
  const service = services[serviceIndex];
  if (!service) return;

  const detail = getServiceDetail(service);
  const modal = ensureServiceModal();
  const hero = modal.querySelector('.therapyModal__hero');
  const tag = modal.querySelector('.therapyModal__tag');
  const title = modal.querySelector('#therapyModalTitle');
  const intro = modal.querySelector('.therapyModal__intro');
  const sections = modal.querySelector('.therapyModal__sections');
  const prices = modal.querySelector('.therapyModal__prices');
  const cta = modal.querySelector('.therapyModal__cta');
  const dialog = modal.querySelector('.therapyModal__dialog');

  hero.style.backgroundImage = service.bg ? `url('${escapeAttr(service.bg)}')` : '';
  hero.style.backgroundPosition = service.bgPos || 'center';
  hero.style.backgroundSize = service.bgSize || 'cover';

  tag.textContent = service.cat;
  title.textContent = service.title;
  intro.textContent = detail.what;
  sections.innerHTML = `
    <div class="therapyModal__section">
      <h4>Para que serve</h4>
      <p>${escapeHtml(detail.purpose)}</p>
    </div>
    <div class="therapyModal__section">
      <h4>Indicações principais</h4>
      <ul>
        ${detail.indications.map(item => `<li>${escapeHtml(item)}</li>`).join('')}
      </ul>
    </div>
  `;
  prices.innerHTML = `
    <div class="therapyModal__price"><span>Duração</span>${renderPriceValue(detail.duration)}</div>
    <div class="therapyModal__price"><span>Valor avulso</span>${renderPriceValue(detail.single)}</div>
    <div class="therapyModal__price"><span>Pacote 4 sessões</span>${renderPriceValue(detail.pack4)}</div>
    <div class="therapyModal__price"><span>Pacote 10 sessões</span>${renderPriceValue(detail.pack10)}</div>
  `;
  cta.href = getWhatsAppLink(service.title);

  lastFocusedEl = document.activeElement;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modalOpen');
  requestAnimationFrame(() => dialog.focus());
}

function closeServiceModal() {
  if (!modalEl) return;
  modalEl.classList.remove('open');
  modalEl.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modalOpen');
  if (lastFocusedEl && typeof lastFocusedEl.focus === 'function') {
    lastFocusedEl.focus();
  }
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

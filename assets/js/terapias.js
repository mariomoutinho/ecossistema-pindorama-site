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
    category: 'Terapias Integrativas',
    what: 'Massagem corporal de origem indiana, feita com movimentos ritmados, óleo vegetal e abordagem integral do corpo.',
    purpose: 'Ajuda a relaxar profundamente, aliviar tensões, favorecer vitalidade e apoiar equilíbrio físico e emocional.',
    indications: ['Estresse e cansaço', 'Tensões musculares', 'Busca por relaxamento profundo', 'Rotina de autocuidado'],
    benefits: ['Relaxamento profundo', 'Sensação de vitalidade', 'Melhora da percepção corporal', 'Pausa restauradora para a rotina'],
    duration: '80 min',
    single: 200,
    pack4: 720,
    pack10: 1600,
  },
  'Massoterapia (diversas técnicas)': {
    category: 'Terapias Integrativas',
    what: 'Atendimento corporal que combina diferentes técnicas manuais conforme a necessidade de cada pessoa.',
    purpose: 'Ajuda a aliviar tensões, soltar a musculatura e oferecer um cuidado direcionado para corpo e rotina.',
    indications: ['Tensão muscular', 'Dores nas costas e ombros', 'Cansaço físico', 'Necessidade de relaxamento'],
    benefits: ['Alívio de tensões', 'Mais mobilidade', 'Relaxamento muscular', 'Cuidado personalizado'],
    duration: '60 min',
    single: 150,
    pack4: 540,
    pack10: 1200,
  },
  'Acupuntura': {
    category: 'Terapias Integrativas',
    what: 'Técnica da Medicina Tradicional Chinesa que estimula pontos específicos do corpo com agulhas finas e seguras.',
    purpose: 'Ajuda a regular o organismo, aliviar dores, reduzir estresse e apoiar equilíbrio físico e emocional.',
    indications: ['Dores musculares e articulares', 'Ansiedade e estresse', 'Sono irregular', 'Cefaleias e enxaquecas'],
    benefits: ['Regulação do organismo', 'Apoio ao alívio de dores', 'Redução de tensão', 'Mais equilíbrio corporal'],
    duration: '60 min',
    single: 150,
    pack4: 540,
    pack10: 1200,
  },
  'Quick Massage': {
    category: 'Terapias Integrativas',
    what: 'Massagem rápida feita em cadeira própria, focada em regiões de maior tensão como costas, ombros, pescoço e braços.',
    purpose: 'Oferece alívio imediato para tensões do dia a dia em uma sessão objetiva e revigorante.',
    indications: ['Rotina intensa', 'Tensão em ombros e pescoço', 'Pausas de autocuidado', 'Cansaço no trabalho'],
    benefits: ['Alívio rápido', 'Mais disposição', 'Relaxamento localizado', 'Praticidade'],
    duration: '20 min',
    single: 50,
    pack4: 200,
    pack10: 400,
  },
  'Massagem Relaxante com Pedras Quentes': {
    category: 'Terapias Integrativas',
    what: 'Massagem relaxante associada ao calor das pedras, que ajuda a aprofundar a soltura muscular e o descanso.',
    purpose: 'Favorece relaxamento intenso, conforto corporal e redução de tensões acumuladas.',
    indications: ['Tensão muscular', 'Estresse', 'Insônia por agitação', 'Necessidade de relaxamento profundo'],
    benefits: ['Relaxamento profundo', 'Conforto térmico', 'Soltura muscular', 'Sensação de acolhimento'],
    duration: '60 min',
    single: 120,
    pack4: 430,
    pack10: 960,
  },
  'Manipulação Vertebral': {
    category: 'Terapias Integrativas',
    what: 'Técnica manual focada em mobilidade da coluna e articulações, realizada com avaliação e cuidado individual.',
    purpose: 'Ajuda a reduzir rigidez, melhorar mobilidade e aliviar desconfortos relacionados a tensão e postura.',
    indications: ['Rigidez na coluna', 'Desconfortos posturais', 'Tensão cervical ou lombar', 'Mobilidade reduzida'],
    benefits: ['Mais mobilidade', 'Alívio de rigidez', 'Melhor consciência postural', 'Sensação de leveza'],
    duration: '60 min',
    single: 120,
    pack4: 430,
    pack10: 960,
  },
  'Liberação Miofascial': {
    category: 'Terapias Integrativas',
    what: 'Técnica manual que trabalha fáscias e pontos de tensão para liberar restrições nos tecidos corporais.',
    purpose: 'Ajuda a soltar tensões profundas, melhorar mobilidade e apoiar recuperação corporal.',
    indications: ['Tensões persistentes', 'Dor muscular', 'Sobrecarga física', 'Restrição de movimento'],
    benefits: ['Soltura de fáscias', 'Melhora da mobilidade', 'Redução de desconfortos', 'Recuperação corporal'],
    duration: '60 min',
    single: 120,
    pack4: 430,
    pack10: 960,
  },
  'Reflexologia Podal': {
    category: 'Terapias Integrativas',
    what: 'Massagem terapêutica nos pés que trabalha pontos reflexos relacionados a diferentes regiões do corpo.',
    purpose: 'Promove relaxamento profundo, circulação, aterramento e sensação geral de bem-estar.',
    indications: ['Cansaço nas pernas e pés', 'Estresse', 'Tensão corporal', 'Busca por relaxamento'],
    benefits: ['Relaxamento profundo', 'Sensação de aterramento', 'Alívio para pés cansados', 'Bem-estar geral'],
    duration: '45 min',
    single: 80,
    pack4: 280,
    pack10: 640,
  },
  'Ventosaterapia': {
    category: 'Terapias Integrativas',
    what: 'Uso terapêutico de ventosas para mobilizar tecidos, estimular circulação local e soltar tensões.',
    purpose: 'Favorece relaxamento muscular, sensação de leveza corporal e recuperação após sobrecarga física.',
    indications: ['Tensão nas costas e ombros', 'Dores musculares', 'Rigidez corporal', 'Cansaço físico'],
    benefits: ['Soltura muscular', 'Estímulo circulatório local', 'Sensação de leveza', 'Apoio à recuperação'],
    duration: '45 min',
    single: 80,
    pack4: 280,
    pack10: 640,
  },
  'Moxabustão': {
    category: 'Terapias Integrativas',
    what: 'Aplicação de calor terapêutico com moxa em pontos energéticos usados pela Medicina Tradicional Chinesa.',
    purpose: 'Aquece, tonifica e ajuda a movimentar energia vital em quadros de frio, tensão e baixa vitalidade.',
    indications: ['Sensação de frio no corpo', 'Cansaço e baixa energia', 'Tensões persistentes', 'Cólicas e desconfortos'],
    benefits: ['Aquecimento terapêutico', 'Mais vitalidade', 'Relaxamento corporal', 'Sensação de conforto'],
    duration: '40 min',
    single: 70,
    pack4: 250,
    pack10: 560,
  },
  'Auriculoterapia': {
    category: 'Terapias Integrativas',
    what: 'Estimulação de pontos reflexos na orelha com sementes, esferas ou outros recursos não invasivos.',
    purpose: 'Apoia o equilíbrio do corpo e pode complementar cuidados para dores, ansiedade e hábitos de saúde.',
    indications: ['Ansiedade e estresse', 'Dores e tensões', 'Compulsões e hábitos', 'Apoio ao sono'],
    benefits: ['Apoio à autorregulação', 'Cuidado complementar', 'Praticidade', 'Estímulo contínuo entre sessões'],
    duration: '30 min',
    single: 70,
    pack4: 250,
    pack10: 560,
  },
  'Reiki': {
    category: 'Terapias Integrativas',
    what: 'Prática energética de toque suave ou aproximação das mãos para acolhimento, relaxamento e harmonização.',
    purpose: 'Cria um espaço de pausa e presença, apoiando regulação emocional e tranquilidade.',
    indications: ['Ansiedade e agitação', 'Cansaço emocional', 'Estresse', 'Necessidade de acolhimento'],
    benefits: ['Relaxamento energético', 'Acolhimento emocional', 'Mais tranquilidade', 'Pausa restauradora'],
    duration: '40 min',
    single: 60,
    pack4: 220,
    pack10: 480,
  },
};

const services = [
  { cat: 'Terapias Integrativas', title: 'Massagem Ayurvédica', duration: '80 min', priceFrom: 'R$ 160,00', desc: 'Cuidado profundo para relaxamento, vitalidade e equilíbrio.', bg: './assets/img/massagem-ayurvedica-bg.png' },
  { cat: 'Terapias Integrativas', title: 'Massoterapia (diversas técnicas)', duration: '60 min', priceFrom: 'R$ 120,00', desc: 'Atendimento adaptado às necessidades do corpo e do momento.', bg: './assets/img/massoterapia-bg.png' },
  { cat: 'Terapias Integrativas', title: 'Acupuntura', duration: '60 min', priceFrom: 'R$ 120,00', desc: 'Prática integrativa para dores, estresse e regulação do organismo.', bg: './assets/img/acupuntura-bg.png', bgPos: '75% center', bgSize: '140% auto' },
  { cat: 'Terapias Integrativas', title: 'Quick Massage', duration: '20 min', priceFrom: 'R$ 40,00', desc: 'Massagem rápida e revigorante, ideal para alívio imediato de tensões.', bg: './assets/img/terapias/quick-massage.png' },
  { cat: 'Terapias Integrativas', title: 'Massagem Relaxante com Pedras Quentes', duration: '60 min', priceFrom: 'R$ 96,00', desc: 'Calor terapêutico que dissolve tensões profundas e aprofunda o relaxamento.', bg: './assets/img/terapias/pedras-quentes.png' },
  { cat: 'Terapias Integrativas', title: 'Manipulação Vertebral', duration: '60 min', priceFrom: 'R$ 96,00', desc: 'Ajustes precisos para restabelecer mobilidade e aliviar tensões na coluna.', bg: './assets/img/terapias/manipulacao-vertebral.png' },
  { cat: 'Terapias Integrativas', title: 'Liberação Miofascial', duration: '60 min', priceFrom: 'R$ 96,00', desc: 'Técnica para soltar tensões profundas das fáscias e restaurar o equilíbrio corporal.', bg: './assets/img/terapias/massagem-relaxante.png' },
  { cat: 'Terapias Integrativas', title: 'Reflexologia Podal', duration: '45 min', priceFrom: 'R$ 64,00', desc: 'Massagem nos pés que repercute bem-estar para todo o corpo.', bg: './assets/img/terapias/reflexologia-podal.png' },
  { cat: 'Terapias Integrativas', title: 'Ventosaterapia', duration: '45 min', priceFrom: 'R$ 64,00', desc: 'Apoio para tensão muscular, circulação e bem-estar.', bg: './assets/img/ventosaterapia-bg.png' },
  { cat: 'Terapias Integrativas', title: 'Moxabustão', duration: '40 min', priceFrom: 'R$ 56,00', desc: 'Calor terapêutico aplicado em pontos energéticos para equilíbrio vital.', bg: './assets/img/terapias/moxabustao.png' },
  { cat: 'Terapias Integrativas', title: 'Auriculoterapia', duration: '30 min', priceFrom: 'R$ 56,00', desc: 'Estímulos na orelha para apoiar equilíbrio e sintomas.', bg: './assets/img/auriculoterapia-bg.png' },
  { cat: 'Terapias Integrativas', title: 'Reiki', duration: '40 min', priceFrom: 'R$ 48,00', desc: 'Cuidado energético para acolher emoções e relaxar.', bg: './assets/img/reiki-bg.png' },
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
    category: service.cat,
    what: service.desc,
    purpose: 'A sessão é conduzida conforme sua necessidade do momento, com escuta e orientação da equipe do Espaço Pindorama.',
    indications: ['Relaxamento e bem-estar', 'Cuidado corporal', 'Escuta terapêutica', 'Construção de rotina de autocuidado'],
    benefits: ['Mais presença no corpo', 'Relaxamento', 'Bem-estar', 'Autocuidado orientado'],
    duration: service.duration || 'Sob consulta',
    single: null,
    pack4: null,
    pack10: null,
  };
}

function getWhatsAppLink(serviceName) {
  const text = `Olá, vim pelo site do Coletivo Pindorama e gostaria de agendar uma sessão de ${serviceName}.`;
  return `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(text)}`;
}

function formatCurrency(value) {
  if (typeof value !== 'number') return 'Sob consulta';
  return value.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });
}

function formatPerSession(total, sessions) {
  if (typeof total !== 'number') return 'Sob consulta';
  return `${formatCurrency(total / sessions).replace(',00', '')}/sessão`;
}

function getSavings(single, total, sessions) {
  if (typeof single !== 'number' || typeof total !== 'number') return null;
  return (single * sessions) - total;
}

function renderPackagePrice(label, total, sessions, single) {
  const savings = getSavings(single, total, sessions);
  const savingsHtml = savings > 0
    ? `<em>Economia de ${escapeHtml(formatCurrency(savings))}</em>`
    : `<em>Ideal para continuidade do cuidado</em>`;

  return `
    <div class="therapyModal__price therapyModal__price--package">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(formatCurrency(total))}</strong>
      <small>${escapeHtml(formatPerSession(total, sessions))}</small>
      ${savingsHtml}
    </div>
  `;
}

function renderIconList(items) {
  return items.map(item => `<li><span aria-hidden="true">+</span>${escapeHtml(item)}</li>`).join('');
}

function scrollToTherapies() {
  const section = document.getElementById('catalogo') || gridEl;
  closeServiceModal();
  if (section && typeof section.scrollIntoView === 'function') {
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
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
        <div class="therapyModal__actions">
          <a class="btn primary therapyModal__cta" target="_blank" rel="noopener">Agendar pelo WhatsApp</a>
          <button class="btn ghost therapyModal__secondary" type="button">Conhecer outras terapias</button>
        </div>
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
  const secondary = modal.querySelector('.therapyModal__secondary');
  const dialog = modal.querySelector('.therapyModal__dialog');

  hero.style.backgroundImage = service.bg ? `url('${escapeAttr(service.bg)}')` : '';
  hero.style.backgroundPosition = service.bgPos || 'center';
  hero.style.backgroundSize = service.bgSize || 'cover';

  tag.textContent = detail.category || service.cat;
  title.textContent = service.title;
  intro.textContent = detail.what;
  sections.innerHTML = `
    <div class="therapyModal__section">
      <h4><span aria-hidden="true">i</span> Para que serve</h4>
      <p>${escapeHtml(detail.purpose)}</p>
    </div>
    <div class="therapyModal__section">
      <h4><span aria-hidden="true">+</span> Indicações principais</h4>
      <ul>
        ${renderIconList(detail.indications)}
      </ul>
    </div>
    <div class="therapyModal__section therapyModal__section--wide">
      <h4><span aria-hidden="true">*</span> Benefícios esperados</h4>
      <ul>
        ${renderIconList(detail.benefits)}
      </ul>
    </div>
  `;
  prices.innerHTML = `
    <div class="therapyModal__price">
      <span>Duração</span>
      <strong>${escapeHtml(detail.duration)}</strong>
      <small>sessão individual</small>
    </div>
    <div class="therapyModal__price">
      <span>Sessão avulsa</span>
      <strong>${escapeHtml(formatCurrency(detail.single))}</strong>
      <small>pagamento por sessão</small>
    </div>
    ${renderPackagePrice('Pacote 4 sessões', detail.pack4, 4, detail.single)}
    ${renderPackagePrice('Pacote 10 sessões', detail.pack10, 10, detail.single)}
  `;
  cta.href = getWhatsAppLink(service.title);
  secondary.onclick = scrollToTherapies;

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

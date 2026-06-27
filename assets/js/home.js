// =========================
// Mobile menu
// =========================
const btnMenu = document.getElementById('btnMenu');
const drawer = document.getElementById('drawer');

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

// =========================
// Serviços (dados + filtro)
// Preço exibido = valor por sessão do pacote de 10 ("a partir de").
// =========================
const services = [
  // ---------- Massagens corporais ----------
  {
    cat: 'Massagens corporais',
    title: 'Massagem Ayurvédica',
    duration: '80 min',
    priceFrom: 'R$ 160,00',
    desc: 'Cuidado profundo para relaxamento, vitalidade e equilíbrio.',
    bg: './assets/img/massagem-ayurvedica-bg.webp'
  },
  {
    cat: 'Massagens corporais',
    title: 'Massoterapia (diversas técnicas)',
    duration: '60 min',
    priceFrom: 'R$ 120,00',
    desc: 'Atendimento adaptado às necessidades do corpo e do momento.',
    bg: './assets/img/massoterapia-bg.webp'
  },
  {
    cat: 'Massagens corporais',
    title: 'Quick Massage',
    duration: '20 min',
    priceFrom: 'R$ 40,00',
    desc: 'Massagem rápida e revigorante, ideal para alívio imediato de tensões.',
    bg: './assets/img/terapias/quick-massage.webp'
  },
  {
    cat: 'Massagens corporais',
    title: 'Massagem Relaxante com pedras quentes',
    duration: '60 min',
    priceFrom: 'R$ 96,00',
    desc: 'Calor terapêutico que dissolve tensões profundas e aprofunda o relaxamento.',
    bg: './assets/img/terapias/pedras-quentes.webp'
  },
  {
    cat: 'Massagens corporais',
    title: 'Manipulação Vertebral',
    duration: '50 min',
    priceFrom: 'R$ 96,00',
    desc: 'Ajustes precisos para restabelecer mobilidade e aliviar tensões na coluna.',
    bg: './assets/img/terapias/manipulacao-vertebral.webp'
  },
  {
    cat: 'Massagens corporais',
    title: 'Liberação Miofascial',
    duration: '60 min',
    priceFrom: 'R$ 96,00',
    desc: 'Técnica para soltar tensões profundas das fáscias e restaurar o equilíbrio corporal.',
    bg: './assets/img/terapias/massagem-relaxante.webp'
  },

  // ---------- Terapias orientais ----------
  {
    cat: 'Terapias orientais',
    title: 'Acupuntura',
    duration: '60 min',
    priceFrom: 'R$ 120,00',
    desc: 'Prática integrativa para dores, estresse e regulação do organismo.',
    bg: './assets/img/acupuntura-bg.webp',
    bgPos: '75% center',
    bgSize: '140% auto'
  },
  {
    cat: 'Terapias orientais',
    title: 'Ventosaterapia',
    duration: '45 min',
    priceFrom: 'R$ 64,00',
    desc: 'Apoio para tensão muscular, circulação e bem-estar.',
    bg: './assets/img/ventosaterapia-bg.webp'
  },
  {
    cat: 'Terapias orientais',
    title: 'Moxabustão',
    duration: '40 min',
    priceFrom: 'R$ 56,00',
    desc: 'Calor terapêutico aplicado em pontos energéticos para equilíbrio vital.',
    bg: './assets/img/terapias/moxabustao.webp'
  },
  {
    cat: 'Terapias orientais',
    title: 'Auriculoterapia',
    duration: '30 min',
    priceFrom: 'R$ 56,00',
    desc: 'Estímulos na orelha para apoiar equilíbrio e sintomas.',
    bg: './assets/img/auriculoterapia-bg.webp'
  },

  // ---------- Cuidado integrativo ----------
  {
    cat: 'Cuidado integrativo',
    title: 'Reflexologia Podal',
    duration: '45 min',
    priceFrom: 'R$ 64,00',
    desc: 'Massagem nos pés que repercute bem-estar para todo o corpo.',
    bg: './assets/img/terapias/reflexologia-podal.webp'
  },
  {
    cat: 'Cuidado integrativo',
    title: 'Reiki',
    duration: '40 min',
    priceFrom: 'R$ 48,00',
    desc: 'Cuidado energético para acolher emoções e relaxar.',
    bg: './assets/img/reiki-bg.webp'
  }
];

const categories = ['Todos', ...Array.from(new Set(services.map(s => s.cat)))];

const filtersEl = document.getElementById('serviceFilters');
const gridEl = document.getElementById('servicesGrid');
const carouselEl = document.getElementById('therapyCarousel');
const whatsappNumber = '5581995216450';

let currentSlide = 0;
let carouselTimer = null;

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

  const list = (filter === 'Todos')
    ? services
    : services.filter(s => s.cat === filter);

  gridEl.innerHTML = list.map(s => {
    const hasBg = !!s.bg;
    const bgPos = s.bgPos || 'center';
    const bgSize = s.bgSize || 'cover';

    const bgStyle = hasBg
      ? `style="background-image: url('${escapeAttr(s.bg)}'); background-position: ${escapeAttr(bgPos)}; background-size: ${escapeAttr(bgSize)};"`
      : '';

    const cardClass = 'serviceCard' + (hasBg ? '' : ' serviceCard--noImage');

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

            <div class="serviceCard__price" aria-label="Preço a partir de ${escapeAttr(s.priceFrom)} por sessão">
              <span class="serviceCard__price-prefix">A partir de</span>
              <strong class="serviceCard__price-value">${escapeHtml(s.priceFrom)}</strong>
              <span class="serviceCard__price-suffix">por sessão</span>
            </div>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

function renderCarousel() {
  if (!carouselEl || !services.length) return;

  carouselEl.innerHTML = `
    <div class="therapyCarousel__viewport"></div>
    <button class="therapyCarousel__arrow therapyCarousel__arrow--prev" type="button" aria-label="Serviço anterior">&lsaquo;</button>
    <button class="therapyCarousel__arrow therapyCarousel__arrow--next" type="button" aria-label="Próximo serviço">&rsaquo;</button>
    <div class="therapyCarousel__dots" aria-label="Selecionar terapia em destaque"></div>
  `;

  carouselEl.querySelector('.therapyCarousel__arrow--prev').addEventListener('click', () => {
    goToSlide(currentSlide - 1, true);
  });

  carouselEl.querySelector('.therapyCarousel__arrow--next').addEventListener('click', () => {
    goToSlide(currentSlide + 1, true);
  });

  carouselEl.addEventListener('mouseenter', stopCarousel);
  carouselEl.addEventListener('mouseleave', startCarousel);
  carouselEl.addEventListener('focusin', stopCarousel);
  carouselEl.addEventListener('focusout', startCarousel);

  goToSlide(0);
  startCarousel();
}

function initCarouselWhenVisible() {
  if (!carouselEl) return;

  if (!('IntersectionObserver' in window)) {
    renderCarousel();
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    if (!entries.some(entry => entry.isIntersecting)) return;
    observer.disconnect();
    renderCarousel();
  }, { rootMargin: '220px 0px' });

  observer.observe(carouselEl);
}

function goToSlide(index, userAction = false) {
  if (!carouselEl || !services.length) return;

  currentSlide = (index + services.length) % services.length;
  const service = services[currentSlide];
  const viewport = carouselEl.querySelector('.therapyCarousel__viewport');
  const dots = carouselEl.querySelector('.therapyCarousel__dots');
  const bgPos = service.bgPos || 'center';
  const bgSize = service.bgSize || 'cover';

  viewport.innerHTML = `
    <article class="therapyCarousel__slide" aria-live="polite">
      <div class="therapyCarousel__image" style="background-image: url('${escapeAttr(service.bg)}'); background-position: ${escapeAttr(bgPos)}; background-size: ${escapeAttr(bgSize)};" aria-hidden="true"></div>
      <div class="therapyCarousel__shade" aria-hidden="true"></div>
      <div class="therapyCarousel__content">
        <span class="therapyCarousel__tag">${escapeHtml(service.cat)}</span>
        <h3>${escapeHtml(service.title)}</h3>
        <p>${escapeHtml(service.desc)}</p>
        <div class="therapyCarousel__meta" aria-label="Resumo do serviço">
          <span><b>Duração</b>${escapeHtml(service.duration)}</span>
          <span><b>A partir de</b>${escapeHtml(service.priceFrom)}</span>
        </div>
        <div class="therapyCarousel__actions">
          <a class="btn primary therapyCarousel__details" href="terapias.php">Ver terapias</a>
          <a class="btn therapyCarousel__whats" href="${escapeAttr(getWhatsAppLink(service.title))}" target="_blank" rel="noopener">Agendar pelo WhatsApp</a>
        </div>
      </div>
    </article>
  `;

  dots.innerHTML = services.map((item, itemIndex) => `
    <button class="therapyCarousel__dot${itemIndex === currentSlide ? ' active' : ''}" type="button" aria-label="Mostrar ${escapeAttr(item.title)}" aria-current="${itemIndex === currentSlide ? 'true' : 'false'}" data-slide-index="${itemIndex}"></button>
  `).join('');

  dots.querySelectorAll('.therapyCarousel__dot').forEach(dot => {
    dot.addEventListener('click', () => {
      goToSlide(Number(dot.dataset.slideIndex), true);
    });
  });

  if (userAction) startCarousel();
}

function startCarousel() {
  stopCarousel();
  carouselTimer = window.setInterval(() => {
    goToSlide(currentSlide + 1);
  }, 5000);
}

function stopCarousel() {
  if (!carouselTimer) return;
  window.clearInterval(carouselTimer);
  carouselTimer = null;
}

function getWhatsAppLink(serviceName) {
  const text = `Olá, vim pelo site do Coletivo Pindorama e gostaria de agendar uma sessão de ${serviceName}.`;
  return `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(text)}`;
}

function escapeHtml(str){
  return String(str).replace(/[&<>"']/g, (m) => ({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;',
    "'":'&#039;'
  }[m]));
}

function escapeAttr(str){
  return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

renderFilters('Todos');
initCarouselWhenVisible();
renderServices('Todos');

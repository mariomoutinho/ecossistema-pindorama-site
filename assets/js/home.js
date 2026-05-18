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
// =========================
const services = [
  // Práticas terapêuticas
  {
    cat: 'Práticas terapêuticas',
    title: 'Massagem Ayurvédica',
    duration: '80 min',
    price: 'R$ 200',
    desc: 'Cuidado profundo para relaxamento, vitalidade e equilíbrio.',
    bg: './assets/img/massagem-ayurvedica-bg.png',
    bgPos: 'center',
    bgSize: 'cover'
  },
  {
    cat: 'Práticas terapêuticas',
    title: 'Massoterapia (diversas técnicas)',
    duration: '60 min',
    price: 'R$ 150',
    desc: 'Atendimento adaptado às necessidades do corpo e do momento.',
    bg: './assets/img/massoterapia-bg.png',
    bgPos: 'center',
    bgSize: 'cover'
  },
  {
    cat: 'Práticas terapêuticas',
    title: 'Acupuntura',
    duration: '60 min',
    price: 'R$ 150',
    desc: 'Prática integrativa para dores, estresse e regulação do organismo.',
    bg: './assets/img/acupuntura-bg.png',
    bgPos: '75% center',
    // 👇 isso “aproxima” e remove aquela moldura escura que vem na imagem
    bgSize: '140% auto'
  },

  { cat: 'Práticas terapêuticas', 
    title: 'Ventosaterapia', 
    duration: '45 min', 
    price: 'R$ 80', 
    desc: 'Apoio para tensão muscular, circulação e bem-estar.',
    bg: './assets/img/ventosaterapia-bg.png',


},
  { cat: 'Práticas terapêuticas', 
    title: 'Auriculoterapia', 
    duration: '30 min', 
    price: 'R$ 70', 
    desc: 'Estímulos na orelha para apoiar equilíbrio e sintomas.',
    bg: './assets/img/auriculoterapia-bg.png',
},

  { cat: 'Práticas terapêuticas', 
    title: 'Reiki', 
    duration: '40 min', 
    price: 'R$ 60', 
    desc: 'Cuidado energético para acolher emoções e relaxar.',
    bg: './assets/img/reiki-bg.png',
 },

  // Atividades coletivas
  { cat: 'Atividades coletivas', 
    title: 'Dança Circular', 
    duration: '80 min', 
    price: 'R$ 40', 
    desc: 'Movimento e conexão em roda para bem-estar e presença.',
    bg: './assets/img/danca-circular-bg.png',
},
  { cat: 'Atividades coletivas', 
    title: 'Thai Chi', 
    duration: '40 min', 
    price: 'R$ 40', 
    desc: 'Movimentos suaves para energia, equilíbrio e foco.',
    bg: './assets/img/thai-chi-bg.png',
},
  { cat: 'Atividades coletivas', 
    title: 'Meditação', 
    duration: '40 min', 
    price: 'R$ 20', 
    desc: 'Prática guiada para tranquilidade e autoconsciência.',
    bg: './assets/img/meditacao-bg.png',
},

  { cat: 'Atividades coletivas', 
    title: 'Automassagem', 
    duration: '60 min', 
    price: 'R$ 30', 
    desc: 'Aprender a cuidar do próprio corpo com técnicas simples.',
    bg: './assets/img/automassagem-bg.png',
},

  // Consultorias
  { cat: 'Consultorias', 
    title: 'Consulta Terapêutica', 
    duration: '50 min', 
    price: 'R$ 120', 
    desc: 'Escuta e plano de cuidado integrativo.',
    bg: './assets/img/consulta-terapeutica-bg.png',
},
  { cat: 'Consultorias', 
    title: 'Atendimento em Arteterapia', 
    duration: '50 min', 
    price: 'R$ 120', 
    desc: 'Expressão criativa como caminho de cuidado.',
    bg: './assets/img/arteterapia-bg.png',
  },
  { cat: 'Consultorias', 
    title: 'Consulta em MTC', 
    duration: '50 min', 
    price: 'R$ 120', 
    desc: 'Avaliação e orientação pela Medicina Tradicional Chinesa.',
    bg: './assets/img/mtc-bg.png',
  },

  // Cursos/Oficinas/Workshops
  { cat: 'Cursos e oficinas', 
    title: 'Shantala', 
    duration: '—', 
    price: 'Consultar', 
    desc: 'Vivência de toque e vínculo (agenda/formatos sob consulta).',
    bg: './assets/img/shantala-bg.png',
},

  { cat: 'Cursos e oficinas', 
    title: 'SoulCollage (introdução/oficina)', 
    duration: '—', 
    price: 'Consultar', 
    desc: 'Processo criativo e simbólico para autoconhecimento.',
    bg: './assets/img/soulcollage-bg.png',
},
];

const categories = ['Todos', ...Array.from(new Set(services.map(s => s.cat)))];

const filtersEl = document.getElementById('serviceFilters');
const gridEl = document.getElementById('servicesGrid');

function renderFilters(active = 'Todos') {
  if (!filtersEl) return;

  filtersEl.innerHTML = '';
  categories.forEach(cat => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'chip' + (cat === active ? ' active' : '');
    b.textContent = cat;

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
    const hasBg = !!s.bg;

    const bgPos = s.bgPos || 'center';
    const bgSize = s.bgSize || 'cover';

    const style = hasBg
      ? `style="
          background-color: rgba(19,45,38,.62);
          background-image:
            linear-gradient(180deg, rgba(10,18,15,.78), rgba(10,18,15,.55)),
            url('${escapeAttr(s.bg)}');
          background-size: ${escapeAttr(bgSize)};
          background-position: ${escapeAttr(bgPos)};
          background-repeat: no-repeat;
        "`
      : '';

    return `
      <article class="serviceCard" ${style}>
        <div class="meta">
          <span class="badge">${escapeHtml(s.cat)}</span>
          ${s.duration ? `<span class="badge">${escapeHtml(s.duration)}</span>` : ''}
          ${s.price ? `<span class="badge price">${escapeHtml(s.price)}</span>` : ''}
        </div>
        <h4>${escapeHtml(s.title)}</h4>
        <p>${escapeHtml(s.desc)}</p>
      </article>
    `;
  }).join('');
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
  // evita quebrar o atributo style (principalmente aspas simples)
  return String(str).replace(/'/g, "\\'");
}

renderFilters('Todos');
renderServices('Todos');

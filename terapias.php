<?php
// ================================
// Página de Terapias do Espaço Pindorama.
// Reaproveita partials de header/footer/bootstrap.
// Renderização do catálogo é feita por assets/js/terapias.js.
// ================================

$activePage      = 'terapias';
$pageTitle       = 'Terapias do Espaço Pindorama • Saúde Integrativa';
$pageDescription = 'Terapias integrativas, atendimentos individuais e práticas corporais no Espaço Pindorama, em Recife/PE.';
$pageScripts     = ['assets/js/terapias.js'];

require __DIR__ . '/partials/bootstrap.php';
require __DIR__ . '/partials/header.php';
?>

<main id="topo">
  <!-- HERO -->
  <div class="container hero">
    <div class="heroGrid heroGrid--single">
      <div class="heroCard">
        <div class="heroInner">
          <div class="kicker">
            <span class="pill highlight">Terapias integrativas</span>
            <span class="pill">Espaço Pindorama • Recife/PE</span>
            <span class="pill">Atendimentos individuais</span>
          </div>

          <h2 class="heroTitle">Terapias e práticas integrativas para um cuidado de verdade.</h2>
          <p class="heroText">
            Massagens corporais, terapias orientais, cuidado energético e práticas expressivas — em um só lugar,
            com escuta atenta e profissionais que dialogam entre si.
          </p>

          <div class="heroActions">
            <a class="btn primary" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">Agendar no WhatsApp</a>
            <a class="btn" href="#catalogo">Ver catálogo</a>
            <a class="btn ghost" href="<?= htmlspecialchars($homeUrl) ?>">Voltar para o Coletivo</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CATÁLOGO -->
  <section id="catalogo">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Terapias e práticas</h2>
          <p>Use os filtros para explorar por categoria. Todos os atendimentos são agendados pelo WhatsApp.</p>
        </div>
        <div class="tools" id="serviceFilters" aria-label="Filtros de terapias"></div>
      </div>

      <div class="servicesWrap">
        <div class="servicesGrid" id="servicesGrid"></div>
        <p class="note">
          Preços exibidos são “a partir de”, baseados em pacotes. Algumas práticas têm valor sob consulta —
          combine pelo <a class="link" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">WhatsApp</a>.
        </p>
      </div>
    </div>
  </section>

  <!-- COMO ESCOLHER -->
  <section id="como-escolher">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Como escolher sua terapia</h2>
          <p>Algumas pistas para encontrar o cuidado que faz sentido para o seu momento.</p>
        </div>
      </div>

      <div class="grid">
        <div class="card span4">
          <span class="tag">1 · Escute o corpo</span>
          <h3>Onde mora a tensão?</h3>
          <p>Dor física, cansaço, ansiedade, sono ruim — cada sintoma aponta caminhos diferentes.</p>
        </div>

        <div class="card span4">
          <span class="tag">2 · Considere o tempo</span>
          <h3>O que cabe na sua rotina</h3>
          <p>Sessões rápidas (Quick, Auriculoterapia) ou imersões longas (Ayurvédica, Pedras Quentes).</p>
        </div>

        <div class="card span4">
          <span class="tag">3 · Combine saberes</span>
          <h3>Ocidente + Oriente + Arte</h3>
          <p>Massagens, MTC, práticas corporais e expressivas se complementam. Pode misturar.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CUIDADO INTEGRATIVO -->
  <section id="cuidado">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Cuidado integrativo no Espaço Pindorama</h2>
          <p>Atendemos cada pessoa em sua totalidade — corpo, mente e comunidade. Saberes tradicionais e contemporâneos, com escuta ativa e compromisso ético.</p>
        </div>
      </div>

      <div class="grid">
        <div class="card span6">
          <span class="tag">Abordagem</span>
          <h3>Integral, não fragmentada</h3>
          <p>Olhamos para o todo: estilo de vida, vínculos, sono, alimentação e história — não apenas para o sintoma.</p>
        </div>

        <div class="card span6">
          <span class="tag">Profissionais</span>
          <h3>Equipe multidisciplinar</h3>
          <p>Terapeutas formados em diferentes tradições compartilham o espaço e dialogam entre si para construir um plano de cuidado.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA FINAL -->
  <section id="agendar">
    <div class="container">
      <div class="card ctaCard">
        <div>
          <span class="tag">Pronto para começar?</span>
          <h3>Agende sua sessão pelo WhatsApp</h3>
          <p>Combinamos horário, valor exato e qualquer dúvida que você tiver.</p>
        </div>
        <div class="heroActions">
          <a class="btn primary" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">Agendar no WhatsApp</a>
          <a class="btn" href="<?= htmlspecialchars($homeUrl) ?>#contato">Falar por formulário</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

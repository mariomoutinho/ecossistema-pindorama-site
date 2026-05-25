<?php
// ================================
// Página principal do Coletivo Pindorama.
// Bootstrap (DB, sessão, helpers, variáveis globais) está em partials/bootstrap.php.
// Header e footer também são partials, compartilhados com terapias.php.
// ================================

$activePage      = 'home';
$pageTitle       = 'Coletivo Pindorama • Saúde Integrativa & Bem-Estar';
$pageDescription = 'Saúde Integrativa & Bem-Estar em Recife/PE. Terapias, atividades coletivas, formações e a metodologia Cuidar+.';
$pageScripts     = ['assets/js/home.js'];

require __DIR__ . '/partials/bootstrap.php';

// --------------------------------
// Tratamento do formulário de contato (POST). Mantém comportamento original.
// Roda antes de qualquer output para permitir header() de redirect.
// --------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome     = trim($_POST['nome']     ?? '');
  $email    = trim($_POST['email']    ?? '');
  $telefone = trim($_POST['telefone'] ?? '');
  $mensagem = trim($_POST['mensagem'] ?? '');
  $origem   = 'site';

  if ($nome === '' || $mensagem === '') {
    flash_set('error', 'Por favor, preencha seu nome e a mensagem.');
    header('Location: ' . $_SERVER['PHP_SELF'] . '#contato');
    exit;
  }

  // Honeypot
  $hp = trim($_POST['website'] ?? '');
  if ($hp !== '') {
    flash_set('success', 'Mensagem recebida. Obrigado!');
    header('Location: ' . $_SERVER['PHP_SELF'] . '#contato');
    exit;
  }

  if (!$db_ok || !($pdo instanceof PDO)) {
    flash_set('error', 'Banco de dados indisponível. Tente novamente em instantes.');
    header('Location: ' . $_SERVER['PHP_SELF'] . '#contato');
    exit;
  }

  try {
    // Garante a existência da tabela apenas no fluxo que precisa dela.
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL,
        email VARCHAR(160) NULL,
        telefone VARCHAR(40) NULL,
        mensagem TEXT NOT NULL,
        origem VARCHAR(60) DEFAULT 'site',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $stmt = $pdo->prepare("INSERT INTO leads (nome,email,telefone,mensagem,origem) VALUES (?,?,?,?,?)");
    $stmt->execute([$nome, $email ?: null, $telefone ?: null, $mensagem, $origem]);
    flash_set('success', 'Recebemos sua mensagem 💛 Em breve a gente te responde.');
  } catch (Throwable $e) {
    flash_set('error', 'Não foi possível enviar agora. Tente novamente.');
  }

  header('Location: ' . $_SERVER['PHP_SELF'] . '#contato');
  exit;
}

$flash = flash_get();

require __DIR__ . '/partials/header.php';
?>

<main id="topo">
  <!-- HERO -->
  <div class="container hero">
    <div class="heroGrid">
      <div class="heroCard">
        <div class="heroInner">
          <div class="kicker">
            <span class="pill highlight">Acolhimento • Corpo • Comunidade</span>
            <span class="pill">Recife/PE • Espaço Pindorama</span>
            <span class="pill">Metodologia Cuidar+</span>
          </div>

          <h2 class="heroTitle">Cuidado integral, com saberes ancestrais e práticas contemporâneas.</h2>
          <p class="heroText">
            Terapias integrativas, atendimentos individuais, atividades coletivas e formações para promover equilíbrio físico,
            emocional e social — com escuta ativa, acolhimento e compromisso ético.
          </p>

          <div class="heroActions">
            <a class="btn primary" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">Agendar no WhatsApp</a>
            <a class="btn" href="<?= htmlspecialchars($terapiasUrl) ?>">Ver terapias</a>
            <a class="btn ghost" href="<?= htmlspecialchars($cuidarUrl) ?>">Conhecer Cuidar+</a>
          </div>
        </div>
      </div>

      <aside class="sideCard">
        <h3>Como a gente trabalha</h3>
        <ul class="bullets">
          <li>Escuta ativa e diálogo horizontal</li>
          <li>Valorização das experiências do grupo</li>
          <li>Construção coletiva de soluções</li>
          <li>Vivências corporais e ludicidade</li>
        </ul>

        <div class="mini">
          <div class="miniRow">
            <span class="dot dotLeaf" aria-hidden="true"></span>
            <div>
              <strong>Instagram</strong>
              <span>
                <a class="link" href="<?= htmlspecialchars($insta) ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars($instaHandle) ?>
                </a>
              </span>
            </div>
          </div>

          <div class="miniRow">
            <span class="dot dotLeaf" aria-hidden="true"></span>
            <div>
              <strong>Endereço</strong>
              <span><?= htmlspecialchars($endereco) ?></span>
            </div>
          </div>

          <div class="mapWrap" aria-label="Mapa do Espaço Pindorama">
            <iframe
              title="Mapa - Rua Dom Carlos Coelho, 86"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              src="https://www.google.com/maps?q=Rua%20Dom%20Carlos%20Coelho,%2086,%20Boa%20Vista,%20Recife%20PE&output=embed">
            </iframe>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <!-- ECOSSISTEMA PINDORAMA -->
  <section id="ecossistema">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Ecossistema Pindorama</h2>
          <p>Frentes que se conversam: cuidado terapêutico, formação e cultura.</p>
        </div>
      </div>

      <div class="ecosystem-grid">
        <a class="ecosystem-card ecosystem-card--terapias" href="<?= htmlspecialchars($terapiasUrl) ?>">
          <span class="ecosystem-card__bg" aria-hidden="true"></span>
          <span class="ecosystem-card__tag">Terapias</span>
          <div class="ecosystem-card__content">
            <h3>Espaço Pindorama</h3>
            <p>Atendimentos individuais e práticas integrativas para corpo, escuta, energia e expressão.</p>
            <span class="ecosystem-card__cta">Ver terapias →</span>
          </div>
        </a>

        <a class="ecosystem-card ecosystem-card--cuidar" href="<?= htmlspecialchars($cuidarUrl) ?>">
          <span class="ecosystem-card__bg" aria-hidden="true"></span>
          <span class="ecosystem-card__tag">Formação</span>
          <div class="ecosystem-card__content">
            <h3>Cuidar+</h3>
            <p>Educação popular em saúde no trabalho, oficinas, vivências e cuidado para organizações.</p>
            <span class="ecosystem-card__cta">Conhecer Cuidar+ →</span>
          </div>
        </a>

        <a class="ecosystem-card ecosystem-card--rpg" href="<?= htmlspecialchars($rpgUrl) ?>" target="_blank" rel="noopener">
          <span class="ecosystem-card__bg" aria-hidden="true"></span>
          <span class="ecosystem-card__tag">Cultura</span>
          <div class="ecosystem-card__content">
            <h3>Pindorama RPG</h3>
            <p>Mesas, narrativas e experiências inspiradas em saberes brasileiros, ancestralidade e imaginação.</p>
            <span class="ecosystem-card__cta">Visitar site →</span>
          </div>
        </a>

        <a class="ecosystem-card ecosystem-card--sementeira" href="<?= htmlspecialchars($gessUrl) ?>">
          <span class="ecosystem-card__bg" aria-hidden="true"></span>
          <span class="ecosystem-card__tag">Projetos</span>
          <div class="ecosystem-card__content">
            <h3>Sementeira</h3>
            <p>Espaço de germinação de ideias e projetos do ecossistema Pindorama.</p>
            <span class="ecosystem-card__cta">Conhecer Sementeira →</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <!-- SERVIÇOS EM DESTAQUE -->
  <section id="servicos">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Serviços em destaque</h2>
          <p>Uma seleção das nossas terapias. Use os filtros para explorar ou veja o catálogo completo.</p>
        </div>
        <div class="tools" id="serviceFilters" aria-label="Filtros de serviços"></div>
      </div>

      <div class="therapyCarousel" id="therapyCarousel" aria-label="Terapias em destaque"></div>

      <div class="servicesWrap">
        <div class="servicesGrid" id="servicesGrid"></div>
        <p class="note">
          Valores e formatos completos podem ser atualizados no catálogo.
          <a class="link" href="<?= htmlspecialchars($terapiasUrl) ?>">Ver todas as terapias →</a>
        </p>
      </div>
    </div>
  </section>

  <!-- CUIDAR+ -->
  <section id="cuidar">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Metodologia Cuidar+</h2>
          <p>Educação popular em saúde e trabalho, com vivências e construção coletiva — inspirada em Paulo Freire e adaptada a contextos organizacionais.</p>
        </div>
      </div>

      <div class="grid">
        <div class="card span6">
          <span class="tag">Princípios</span>
          <h3>Diálogo, presença e co-criação</h3>
          <p>Encontros com escuta ativa, valorização de saberes e elaboração coletiva de soluções e compromissos éticos.</p>
        </div>

        <div class="card span6">
          <span class="tag">Vivências</span>
          <h3>Corpo como parte do processo</h3>
          <p>Práticas corporais e momentos de cuidado que apoiam reflexão, presença e aprendizagem significativa.</p>
        </div>

        <div class="card span12">
          <span class="tag">Exemplo de atividade</span>
          <h3>Oficinas em instituições e organizações</h3>
          <p>Vivências formativas sobre convivência ética, prevenção de violências e fortalecimento de cultura de cuidado — com metodologias ativas, ludicidade e sistematização coletiva.</p>
          <p style="margin-top:10px;">
            <a class="link" href="<?= htmlspecialchars($cuidarUrl) ?>">Saiba mais sobre o Cuidar+ →</a>
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- SOBRE -->
  <section id="sobre">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Sobre o Coletivo</h2>
          <p>Uma iniciativa pernambucana dedicada à promoção de saúde integral — corpo, mente e comunidade — com atendimentos, atividades coletivas e formações.</p>
        </div>
      </div>

      <div class="grid">
        <div class="card span6">
          <span class="tag">Missão</span>
          <h3>Promover saúde e qualidade de vida</h3>
          <p>Acolhendo cada pessoa em sua totalidade — corpo, mente e comunidade.</p>
        </div>

        <div class="card span6">
          <span class="tag">Valores</span>
          <h3>Cuidado, ética e integração</h3>
          <p>Empatia no atendimento, integração de saberes tradicionais e científicos, escuta ativa e compromisso social.</p>
        </div>

        <div class="card span12">
          <span class="tag">Onde estamos</span>
          <h3>Espaço Pindorama</h3>
          <p><?= htmlspecialchars($endereco) ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTATO -->
  <section id="contato">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Contato</h2>
          <p>Quer agendar, tirar dúvidas ou pedir uma proposta? Envie mensagem ou fale direto no WhatsApp.</p>
        </div>
      </div>

      <div class="contactGrid">
        <div class="card">
          <?php if ($flash): ?>
            <div class="alert <?= htmlspecialchars($flash['type']) ?>">
              <?= htmlspecialchars($flash['msg']) ?>
            </div>
          <?php endif; ?>

          <?php if (!$db_ok): ?>
            <div class="alert error">
              <strong>⚠ Estamos com instabilidade no envio agora.</strong><br>
              Por favor, fale com a gente diretamente no
              <a class="link" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">WhatsApp</a>.
              <?php if ($is_dev): ?>
                <div class="note" style="margin-top:8px;">
                  [dev] Banco: <code><?= htmlspecialchars(DB_NAME) ?></code> · Detalhe: <?= htmlspecialchars($db_error ?? '') ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>#contato" autocomplete="on">
            <input type="text" name="website" class="honeypot" tabindex="-1" aria-hidden="true">

            <div class="field">
              <label for="nome">Seu nome *</label>
              <input id="nome" name="nome" required placeholder="Como podemos te chamar?" />
            </div>

            <div class="field">
              <label for="email">E-mail</label>
              <input id="email" name="email" type="email" placeholder="voce@exemplo.com" />
            </div>

            <div class="field">
              <label for="telefone">Telefone/WhatsApp</label>
              <input id="telefone" name="telefone" inputmode="tel" placeholder="(81) 9xxxx-xxxx" />
            </div>

            <div class="field">
              <label for="mensagem">Mensagem *</label>
              <textarea id="mensagem" name="mensagem" required placeholder="Conte um pouco do que você precisa (serviço, data, objetivo, etc.)"></textarea>
            </div>

            <button class="btn primary" type="submit">Enviar mensagem</button>
            <p class="note">Ou, se preferir:
              <a href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener" class="link">abrir WhatsApp</a>.
            </p>
          </form>
        </div>

        <div class="card">
          <span class="tag">Informações rápidas</span>
          <h3 class="mt0">Coletivo Pindorama</h3>
          <p class="mb12">Saúde Integrativa &amp; Bem-Estar</p>

          <div class="mini miniNoTop">
            <div class="miniRow">
              <span class="dot" aria-hidden="true"></span>
              <div>
                <strong>WhatsApp</strong>
                <span><a href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener" class="link">(81) 99521-6450</a></span>
              </div>
            </div>

            <div class="miniRow">
              <span class="dot dotLeaf" aria-hidden="true"></span>
              <div>
                <strong>Instagram</strong>
                <span>
                  <a href="<?= htmlspecialchars($insta) ?>" target="_blank" rel="noopener" class="link">
                    <?= htmlspecialchars($instaHandle) ?>
                  </a>
                </span>
              </div>
            </div>

            <div class="miniRow">
              <span class="dot dotSand" aria-hidden="true"></span>
              <div>
                <strong>E-mail</strong>
                <span>
                  <a href="mailto:<?= htmlspecialchars($emailContato) ?>" class="link">
                    <?= htmlspecialchars($emailContato) ?>
                  </a>
                </span>
              </div>
            </div>

            <div class="miniRow">
              <span class="dot" aria-hidden="true"></span>
              <div>
                <strong>Endereço</strong>
                <span><?= htmlspecialchars($endereco) ?></span>
              </div>
            </div>
          </div>

          <div class="mapWrap" aria-label="Mapa do Espaço Pindorama">
            <iframe
              title="Mapa - Rua Dom Carlos Coelho, 86"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              src="https://www.google.com/maps?q=Rua%20Dom%20Carlos%20Coelho,%2086,%20Boa%20Vista,%20Recife%20PE&output=embed">
            </iframe>
          </div>

          <div class="actionsTop">
            <a class="btn" href="<?= htmlspecialchars($terapiasUrl) ?>">Explorar terapias</a>
            <a class="btn ghost" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">Agendar</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

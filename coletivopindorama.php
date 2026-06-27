<?php
// ================================
// Página principal do Coletivo Pindorama.
// Bootstrap (DB, sessão, helpers, variáveis globais) está em partials/bootstrap.php.
// Header e footer também são partials, compartilhados com terapias.php.
// ================================

$activePage      = 'home';
$pageTitle       = 'Coletivo Pindorama • Saúde Integrativa & Bem-Estar';
$pageDescription = 'Saúde Integrativa & Bem-Estar em Recife/PE. Terapias, atividades coletivas, formações e a metodologia Cuidar+.';
$pageScripts     = ['assets/js/home-hero-carousel.js', 'assets/js/home.js'];
$extraStyles     = [];
$asyncStyles     = ['assets/css/global.css', 'assets/css/home.css', 'assets/css/home-hero-carousel.css'];
$preloadImages   = [[
  'href' => 'assets/img/home/banner/01-acolhimento-pindorama-1280.webp',
  'type' => 'image/webp',
  'imagesrcset' => 'assets/img/home/banner/01-acolhimento-pindorama-640.webp 640w, assets/img/home/banner/01-acolhimento-pindorama-960.webp 960w, assets/img/home/banner/01-acolhimento-pindorama-1280.webp 1280w, assets/img/home/banner/01-acolhimento-pindorama.webp 1600w',
  'imagesizes' => '(max-width: 767px) calc(100vw - 40px), min(1120px, calc(100vw - 40px))',
  'fetchpriority' => 'high',
]];
$criticalCss = <<<'CSS'
:root{--bg:#0E1C17;--surface:#112620;--card:#132D26;--text:#EAF3EF;--muted:rgba(234,243,239,.86);--line:rgba(234,243,239,.12);--sand:#F4E7D3;--sand2:#EAD7B8;--leaf:#3E8E6A;--leaf2:#66B48F;--focus:rgba(102,180,143,.28);--radius2:26px;--max:1120px}*,*::before,*::after{box-sizing:border-box}html{width:100%;max-width:100%;scroll-behavior:smooth}body{width:100%;max-width:100%;margin:0;overflow-x:clip;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:radial-gradient(1200px 600px at 20% 5%,rgba(102,180,143,.16),transparent 60%),radial-gradient(900px 500px at 80% 10%,rgba(196,106,74,.14),transparent 55%),linear-gradient(180deg,var(--bg),#07110E 60%,#050C0A);color:var(--text)}a{color:inherit;text-decoration:none}.container{width:min(var(--max),calc(100% - 40px));margin:0 auto}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:11px 14px;border-radius:999px;border:1px solid var(--line);background:rgba(234,243,239,.06);color:var(--text);font-size:13px;cursor:pointer;transition:transform .15s ease,background .15s ease,border-color .15s ease;user-select:none}.btn.primary{background:linear-gradient(135deg,var(--leaf),var(--leaf2));border-color:rgba(102,180,143,.35);color:#052015;font-weight:650;box-shadow:0 18px 45px rgba(62,142,106,.22)}.btn.ghost{background:transparent;border-color:rgba(244,231,211,.22);color:var(--sand)}.btn.btn--terapeutas{gap:8px;border-color:rgba(102,180,143,.45);color:var(--leaf2);background:rgba(102,180,143,.10);font-weight:600}.siteHeader{position:sticky;top:0;z-index:50;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);background:rgba(14,28,23,.65);border-bottom:1px solid var(--line)}.nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0}.brand{display:flex;gap:12px;align-items:center}.brand h1{font-size:15px;margin:0;letter-spacing:.2px}.brand p{margin:2px 0 0;font-size:12px;color:var(--muted)}.logo{width:64px;height:64px;object-fit:contain;background:transparent;border:0;box-shadow:none;padding:0;border-radius:0;display:block}.menu{display:flex;gap:16px;align-items:center}.menu a{font-size:13px;color:var(--muted);padding:10px;border-radius:999px}.cta{display:flex;gap:10px;align-items:center}.hamb{display:none}.drawer{display:none;border-top:1px solid var(--line);padding:10px 0 14px}.drawer.open{display:block}section{padding:56px 0}main>section:not(:first-child){content-visibility:auto;contain-intrinsic-size:auto 480px}.hhc{padding:22px 0 8px}.hhc__frame{position:relative;width:100%;aspect-ratio:5/4;height:auto;min-height:0;max-height:520px;border-radius:20px;overflow:hidden;isolation:isolate;background:#10231d;border:1px solid rgba(244,231,211,.16);box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 26px 70px rgba(0,0,0,.34),0 0 44px rgba(102,180,143,.10);touch-action:pan-y}.hhc__track{display:flex;height:100%;will-change:transform;transition:none}.hhc__slide{position:relative;flex:0 0 100%;height:100%;background:radial-gradient(80% 70% at 18% 22%,rgba(244,231,211,.10),transparent 60%),radial-gradient(70% 60% at 82% 16%,rgba(196,106,74,.12),transparent 58%),linear-gradient(160deg,#051e18 0%,#173a2c 100%)}.hhc__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:var(--pos-m,center center);user-select:none;-webkit-user-drag:none}.hhc__overlay{position:absolute;inset:0;z-index:2;pointer-events:none;background:radial-gradient(70% 80% at 16% 26%,rgba(244,231,211,.10),transparent 60%),linear-gradient(90deg,rgba(5,30,24,.90) 0%,rgba(5,30,24,.62) 38%,rgba(5,30,24,.18) 72%,rgba(18,52,60,.10) 100%)}.hhc__content{position:absolute;z-index:3;top:50%;left:0;transform:translateY(-50%);width:min(560px,calc(100% - 96px));padding:0 0 0 56px;display:flex;flex-direction:column;gap:12px}.hhc__label{align-self:flex-start;margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.9px;color:var(--sand);padding:6px 12px;border-radius:999px;background:rgba(244,231,211,.12);border:1px solid rgba(244,231,211,.22)}.hhc__title{margin:0;font-size:clamp(30px,4.6vw,50px);line-height:1.05;letter-spacing:-.6px;font-weight:700;text-shadow:0 16px 40px rgba(0,0,0,.5)}.hhc__tagline{margin:0;font-size:clamp(16px,2vw,20px);color:var(--sand);letter-spacing:.1px;text-shadow:0 8px 26px rgba(0,0,0,.5)}.hhc__text{margin:2px 0 6px;max-width:46ch;font-size:clamp(14px,1.5vw,15.5px);line-height:1.6;color:rgba(234,243,239,.96);text-shadow:0 6px 22px rgba(0,0,0,.55)}.hhc__actions{display:flex;flex-wrap:wrap;gap:10px}.hhc__arrow{position:absolute;top:50%;z-index:4;width:46px;height:46px;transform:translateY(-50%);border-radius:999px;border:1px solid rgba(244,231,211,.24);background:rgba(7,17,14,.55);color:var(--sand);font-size:32px;line-height:1;cursor:pointer}.hhc__arrow--prev{left:18px}.hhc__arrow--next{right:18px}.hhc__dots{position:absolute;z-index:4;left:0;right:0;bottom:20px;display:flex;justify-content:center;gap:4px;padding:0}.hhc__dot{position:relative;width:44px;height:44px;padding:0;margin:-10px;border:0;background:transparent;cursor:pointer}.hhc__dot::before{content:"";position:absolute;top:50%;left:50%;width:9px;height:9px;transform:translate(-50%,-50%);border-radius:999px;border:1px solid rgba(244,231,211,.42);background:rgba(244,231,211,.18)}.hhc__dot.active::before{transform:translate(-50%,-50%) scaleX(3.1);background:var(--sand);border-color:var(--sand)}.hhc__sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap}@media (min-width:768px){.hhc__frame{aspect-ratio:16/9;max-height:620px;border-radius:var(--radius2)}.hhc__img{object-position:var(--pos-d,center center)}}@media (min-width:1200px){.hhc__frame{aspect-ratio:16/7;max-height:620px}}@media (max-width:980px){.hhc__content{width:min(560px,calc(100% - 64px));padding-left:40px}}@media (max-width:767px){.nav{padding:10px 0;gap:10px}.logo{width:44px;height:44px}.brand h1{font-size:13px;line-height:1.1}.brand p,.menu,.cta .btn--terapeutas{display:none}.hamb{display:inline-flex}.cta{gap:8px}.cta .btn.primary,.hamb{padding:10px 12px;font-size:12px;white-space:nowrap}.hhc{padding:14px 0 4px}.hhc__overlay{background:linear-gradient(180deg,rgba(5,30,24,.08) 0%,rgba(5,30,24,.18) 42%,rgba(5,30,24,.90) 100%)}.hhc__content{top:auto;bottom:38px;transform:none;width:100%;padding:0 18px;gap:8px}.hhc__title{font-size:clamp(26px,7vw,34px)}.hhc__tagline{font-size:15px}.hhc__text{max-width:100%;font-size:13.5px;margin:0}.hhc__actions{width:100%;gap:8px}.hhc__actions .btn{flex:1 1 auto;justify-content:center;text-align:center}.hhc__label,.hhc__arrow{display:none}.hhc__dots{bottom:14px}}@media (max-width:389px){.hhc__text{display:none}.hhc__content{bottom:42px;gap:8px}}@media (max-width:380px){.hhc__actions{flex-direction:column}.hhc__actions .btn{width:100%}}
CSS;

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
  <!-- BANNER DINÂMICO (carrossel) — somente na home -->
  <?php require __DIR__ . '/partials/home-hero-carousel.php'; ?>

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

        <a class="ecosystem-card ecosystem-card--suinda" href="<?= htmlspecialchars($suindaUrl) ?>">
          <span class="ecosystem-card__bg" aria-hidden="true"></span>
          <span class="ecosystem-card__tag">Educação</span>
          <div class="ecosystem-card__content">
            <h3>Suindá</h3>
            <p>Espaço de cursos, trilhas formativas e ferramentas de aprendizagem — com revisão por repetição espaçada.</p>
            <span class="ecosystem-card__cta">Conhecer o Suindá →</span>
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
          <p>Uma seleção das nossas terapias. Veja o catálogo completo para conhecer todas as práticas.</p>
        </div>
      </div>

      <div class="therapyCarousel" id="therapyCarousel" role="region" aria-roledescription="carrossel" aria-label="Terapias em destaque"></div>

      <p class="note" style="margin-top:18px;">
        Valores e formatos completos podem ser atualizados no catálogo.
        <a class="link" href="<?= htmlspecialchars($terapiasUrl) ?>">Ver todas as terapias →</a>
      </p>
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
          <span class="tag">Como a gente trabalha</span>
          <h3>Escuta ativa e construção coletiva</h3>
          <ul class="bullets">
            <li>Escuta ativa e diálogo horizontal</li>
            <li>Valorização das experiências do grupo</li>
            <li>Construção coletiva de soluções</li>
            <li>Vivências corporais e ludicidade</li>
          </ul>
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

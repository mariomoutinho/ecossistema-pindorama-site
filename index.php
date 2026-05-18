<?php
// ================================
// CONFIG BANCO — credenciais carregadas de config-db.php (gitignored).
// Use config-db.example.php como template ao configurar um ambiente novo.
// ================================
require_once __DIR__ . '/config-db.php';

session_start();
function flash_set($type, $msg) { $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
function flash_get() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}

$pdo = null;
$db_ok = false;
$db_error = null;

try {
  $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  $db_ok = true;

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

} catch (Throwable $e) {
  $db_ok = false;
  $db_error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $telefone = trim($_POST['telefone'] ?? '');
  $mensagem = trim($_POST['mensagem'] ?? '');
  $origem = 'site';

  if ($nome === '' || $mensagem === '') {
    flash_set('error', 'Por favor, preencha seu nome e a mensagem.');
    header('Location: '.$_SERVER['PHP_SELF'].'#contato');
    exit;
  }

  $hp = trim($_POST['website'] ?? '');
  if ($hp !== '') {
    flash_set('success', 'Mensagem recebida. Obrigado!');
    header('Location: '.$_SERVER['PHP_SELF'].'#contato');
    exit;
  }

  if (!$db_ok) {
    flash_set('error', 'Banco de dados indisponível. Confira a configuração do MySQL no XAMPP.');
    header('Location: '.$_SERVER['PHP_SELF'].'#contato');
    exit;
  }

  try {
    $stmt = $pdo->prepare("INSERT INTO leads (nome,email,telefone,mensagem,origem) VALUES (?,?,?,?,?)");
    $stmt->execute([$nome, $email ?: null, $telefone ?: null, $mensagem, $origem]);
    flash_set('success', 'Recebemos sua mensagem 💛 Em breve a gente te responde.');
  } catch (Throwable $e) {
    flash_set('error', 'Não foi possível enviar agora. Tente novamente.');
  }

  header('Location: '.$_SERVER['PHP_SELF'].'#contato');
  exit;
}

$flash = flash_get();

$whatsNumber = '5581995216450';
$whatsLink = "https://wa.me/{$whatsNumber}?text=".rawurlencode("Olá! Vim pelo site do Coletivo Pindorama e gostaria de agendar um horário.");
$insta = "https://www.instagram.com/coletivo_pindorama";
$emailContato = 'contato@coletivopindorama.com';
$endereco = 'Espaço Pindorama — Rua Dom Carlos Coelho, 86 — Boa Vista, Recife/PE';

?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Coletivo Pindorama • Saúde Integrativa & Bem-Estar</title>
  <meta name="description" content="Saúde Integrativa & Bem-Estar em Recife/PE. Terapias, atividades coletivas, formações e a metodologia Cuidar+." />

  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/home.css">
</head>

<body>
<header class="siteHeader">
  <div class="container">
    <div class="nav">
      <a class="brand" href="#topo">
        <img class="logo" src="./assets/img/logo-pindorama.svg" alt="Coletivo Pindorama">
        <div>
          <h1>Coletivo Pindorama</h1>
          <p>Saúde Integrativa & Bem-Estar</p>
        </div>
      </a>

      <nav class="menu" aria-label="Navegação principal">
        <a href="#servicos">Serviços</a>
        <a href="/cuidar-mais/">Ir para Cuidar+</a>
        <a href="#sobre">Sobre</a>
        <a href="#contato">Contato</a>
      </nav>

      <div class="cta">
        <!-- CTA primário fica no topo -->
        <a class="btn primary" href="https://wa.me/5581995216450?text=Ol%C3%A1%21+Quero+agendar+um+atendimento."
 target="_blank" rel="noopener">
          Agendar no WhatsApp
        </a>

        <!-- Botão do menu (hamburger) -->
        <button class="btn hamb" id="btnMenu" type="button" aria-expanded="false" aria-controls="drawer">
          Menu
        </button>
      </div>
    </div>

    <!-- Drawer (mobile) -->
    <div class="drawer" id="drawer">
      <!-- Ações (inclui o "Falar com a gente" que você queria dentro do menu) -->
      <div class="drawerActions">
        <a class="btn primary" href="SEU_LINK_WHATSAPP" target="_blank" rel="noopener">Agendar no WhatsApp</a>
        <a class="btn" href="#contato">Falar com a gente</a>
      </div>

      <div class="drawerLinks">
        <a href="#servicos">Serviços</a>
        <a href="#cuidar">Cuidar+</a>
        <a href="#sobre">Sobre</a>
        <a href="#contato">Contato</a>
      </div>
    </div>
  </div>
</header>


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
            <a class="btn primary" href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener">Agendar agora</a>
            <a class="btn" href="#servicos">Ver serviços</a>
            <a class="btn" href="#cuidar">Conhecer Cuidar+</a>
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
      <a class="link"
         href="<?php echo htmlspecialchars($insta); ?>"
         target="_blank"
         rel="noopener">
        <?php echo htmlspecialchars($insta); ?>
      </a>
    </span>
  </div>
</div>

          <div class="miniRow">
            <span class="dot dotLeaf" aria-hidden="true"></span>
            <div>
              <strong>Endereço</strong>
              <span><?php echo htmlspecialchars($endereco); ?></span>
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

  <!-- SERVIÇOS -->
  <section id="servicos">
    <div class="container">
      <div class="sectionHead">
        <div>
          <h2>Serviços</h2>
          <p>Uma seleção dos atendimentos e atividades do Coletivo Pindorama. Use os filtros para explorar.</p>
        </div>
        <div class="tools" id="serviceFilters" aria-label="Filtros de serviços"></div>
      </div>

      <div class="servicesWrap">
        <div class="servicesGrid" id="servicesGrid"></div>
        <p class="note">Valores e formatos completos podem ser atualizados no catálogo. Agende pelo WhatsApp para combinar horários.</p>
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
          <p><?php echo htmlspecialchars($endereco); ?></p>
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
            <div class="alert <?php echo htmlspecialchars($flash['type']); ?>">
              <?php echo htmlspecialchars($flash['msg']); ?>
            </div>
          <?php endif; ?>

          <?php if (!$db_ok): ?>
            <div class="alert error">
              <strong>⚠ Banco não conectado.</strong><br>
              Confirme se o MySQL do XAMPP está ligado e se o banco <code><?php echo htmlspecialchars(DB_NAME); ?></code> existe.
              <div class="note" style="margin-top:8px;">
                Detalhe: <?php echo htmlspecialchars($db_error ?? ''); ?>
              </div>
            </div>
          <?php endif; ?>

          <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>#contato" autocomplete="on">
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
            <p class="note">Ou, se preferir: <a href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener" class="link">abrir WhatsApp</a>.</p>
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
                <span><a href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener" class="link">(81) 99521-6450</a></span>
              </div>
            </div>

            <div class="miniRow">
              <span class="dot dotLeaf" aria-hidden="true"></span>
              <div>
                <strong>Instagram</strong>
                <span><?php echo htmlspecialchars($insta); ?></span>
              </div>
            </div>

            <div class="miniRow">
              <span class="dot dotSand" aria-hidden="true"></span>
              <div>
                <strong>E-mail</strong>
                <span><?php echo htmlspecialchars($emailContato); ?></span>
              </div>
            </div>

            <div class="miniRow">
              <span class="dot" aria-hidden="true"></span>
              <div>
                <strong>Endereço</strong>
                <span><?php echo htmlspecialchars($endereco); ?></span>
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
            <a class="btn" href="#servicos">Explorar serviços</a>
            <a class="btn ghost" href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener">Agendar</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="siteFooter">
  <div class="container foot">
    <div>© <?php echo date('Y'); ?> Coletivo Pindorama • Recife/PE</div>
    <div>
      <a href="#contato" class="link">Contato</a>
      <span class="sep">•</span>
      <a href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener" class="link">WhatsApp</a>
    </div>
  </div>
</footer>

<script src="assets/js/home.js"></script>
</body>
</html>

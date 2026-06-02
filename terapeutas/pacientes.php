<?php
// ================================
// Lista de PACIENTES do terapeuta logado.
// - Busca por nome, nome social, telefone ou e-mail.
// - Filtro por ativos / inativos / todos.
// - Paginação.
// Cada terapeuta vê apenas os próprios pacientes (escopo no backend).
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$flash = flash_get();

$busca   = trim((string)($_GET['q'] ?? ''));
$status  = $_GET['status'] ?? 'ativos';
if (!in_array($status, ['ativos', 'inativos', 'todos'], true)) $status = 'ativos';
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));

$res = pac_listar((int)$terapeutaLogado['id'], $busca, $status, $pagina);

// Contagens por status (do terapeuta logado)
$meus = store_where('pacientes', fn($r) => (int)($r['terapeuta_id'] ?? 0) === (int)$terapeutaLogado['id']);
$cont = ['ativos' => 0, 'inativos' => 0, 'todos' => count($meus)];
foreach ($meus as $p) {
  if (($p['status'] ?? 'ativo') === 'inativo') $cont['inativos']++;
  else $cont['ativos']++;
}

function pac_qs(array $extra = []): string {
  $base = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? 'ativos'];
  $q = array_merge($base, $extra);
  $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
  return http_build_query($q);
}

$pageTitle = 'Pacientes • Espaço Pindorama';
$activeApp = 'pacientes';
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1>Pacientes</h1>
    <p>Cadastro e acompanhamento dos seus pacientes. Visível apenas para você.</p>
  </div>
  <a class="terap-btn terap-btn--primary" href="paciente-form.php?novo=1">+ Novo paciente</a>
</div>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<!-- BUSCA + FILTROS -->
<form method="get" action="pacientes.php" class="pac-toolbar" role="search">
  <div class="pac-search">
    <span class="pac-search__icon" aria-hidden="true">⌕</span>
    <input type="search" name="q" value="<?= htmlspecialchars($busca) ?>"
           placeholder="Buscar por nome, nome social, telefone ou e-mail" aria-label="Buscar pacientes">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <button type="submit" class="terap-btn terap-btn--sm">Buscar</button>
    <?php if ($busca !== ''): ?>
      <a class="terap-btn terap-btn--sm terap-btn--ghost" href="pacientes.php?status=<?= htmlspecialchars($status) ?>">Limpar</a>
    <?php endif; ?>
  </div>
</form>

<div class="terap-filters" role="group" aria-label="Filtrar por status">
  <a class="terap-filter <?= $status === 'ativos' ? 'is-active' : '' ?>" href="pacientes.php?<?= htmlspecialchars(pac_qs(['status' => 'ativos', 'pagina' => null])) ?>">Ativos <span><?= $cont['ativos'] ?></span></a>
  <a class="terap-filter <?= $status === 'inativos' ? 'is-active' : '' ?>" href="pacientes.php?<?= htmlspecialchars(pac_qs(['status' => 'inativos', 'pagina' => null])) ?>">Inativos <span><?= $cont['inativos'] ?></span></a>
  <a class="terap-filter <?= $status === 'todos' ? 'is-active' : '' ?>" href="pacientes.php?<?= htmlspecialchars(pac_qs(['status' => 'todos', 'pagina' => null])) ?>">Todos <span><?= $cont['todos'] ?></span></a>
</div>

<?php if (!$res['itens']): ?>
  <div class="terap-alert terap-alert--info">
    <?php if ($busca !== ''): ?>
      Nenhum paciente encontrado para “<?= htmlspecialchars($busca) ?>”.
    <?php else: ?>
      Você ainda não cadastrou pacientes. <a class="terap-link" href="paciente-form.php?novo=1">Cadastrar o primeiro</a>.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="pac-list">
    <?php foreach ($res['itens'] as $p):
      $nome   = pac_nome_exibicao($p);
      $idade  = pac_idade($p['data_nascimento'] ?? null);
      $inativo= ($p['status'] ?? 'ativo') === 'inativo';
      $tel    = $p['whatsapp'] ?: ($p['telefone'] ?? '');
    ?>
      <a class="pac-card" href="paciente.php?id=<?= (int)$p['id'] ?>">
        <span class="pac-card__avatar" aria-hidden="true"><?= htmlspecialchars(mb_substr($nome, 0, 1, 'UTF-8')) ?></span>
        <span class="pac-card__body">
          <span class="pac-card__name">
            <?= htmlspecialchars($nome) ?>
            <?php if ($inativo): ?><em class="pac-badge pac-badge--off">Inativo</em><?php endif; ?>
          </span>
          <span class="pac-card__meta">
            <?php if ($idade !== null): ?><?= $idade ?> anos<?php endif; ?>
            <?php if ($tel): ?><?= ($idade !== null ? ' · ' : '') ?><?= htmlspecialchars($tel) ?><?php endif; ?>
          </span>
          <?php if (!empty($p['queixa_principal'])): ?>
            <span class="pac-card__hint"><?= htmlspecialchars(mb_strimwidth($p['queixa_principal'], 0, 80, '…', 'UTF-8')) ?></span>
          <?php endif; ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($res['paginas'] > 1): ?>
    <nav class="pac-pagination" aria-label="Paginação">
      <?php if ($res['pagina'] > 1): ?>
        <a class="terap-btn terap-btn--sm" href="pacientes.php?<?= htmlspecialchars(pac_qs(['pagina' => $res['pagina'] - 1])) ?>">← Anterior</a>
      <?php endif; ?>
      <span class="pac-pagination__info">Página <?= $res['pagina'] ?> de <?= $res['paginas'] ?> · <?= $res['total'] ?> paciente(s)</span>
      <?php if ($res['pagina'] < $res['paginas']): ?>
        <a class="terap-btn terap-btn--sm" href="pacientes.php?<?= htmlspecialchars(pac_qs(['pagina' => $res['pagina'] + 1])) ?>">Próxima →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>

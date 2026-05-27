<?php
// ================================
// Acupuntura — módulo experimental do Ecossistema Pindorama.
//
// Página única, autocontida, isolada do resto do site:
//  - Não toca em terapias.php, na home ou na área restrita.
//  - Reaproveita apenas assets/css/global.css (tokens visuais).
//  - Dados em arquivos JSON versionados (seed/*.json).
//  - Sem persistência: nada do que o usuário digita é gravado.
//
// Para REMOVER por completo, basta apagar a pasta /terapias/acupuntura/.
//
// >>> Para crescer a base de pontos: adicionar entradas em seed/pontos.json
//     no mesmo formato. Os catálogos do formulário (sintomas/síndromes/ações)
//     são derivados automaticamente.
// ================================

require_once __DIR__ . '/lib/pontos.php';
require_once __DIR__ . '/lib/recomendacao.php';

// Filtros vindos do POST
$filtros = [
  'paciente'   => trim((string)($_POST['paciente'] ?? '')),
  'idade'      => trim((string)($_POST['idade']    ?? '')),
  'queixa'     => trim((string)($_POST['queixa']   ?? '')),
  'sintomas'   => isset($_POST['sintomas'])   && is_array($_POST['sintomas'])   ? $_POST['sintomas']   : [],
  'sindromes'  => isset($_POST['sindromes'])  && is_array($_POST['sindromes'])  ? $_POST['sindromes']  : [],
  'acoes'      => isset($_POST['acoes'])      && is_array($_POST['acoes'])      ? $_POST['acoes']      : [],
  'regioes'    => isset($_POST['regioes'])    && is_array($_POST['regioes'])    ? $_POST['regioes']    : [],
  'restricoes' => isset($_POST['restricoes']) && is_array($_POST['restricoes']) ? $_POST['restricoes'] : [],
];
$temFiltro = (bool)($filtros['sintomas'] || $filtros['sindromes'] || $filtros['acoes'] || $filtros['regioes']);

$resultados = $temFiltro ? rec_calcular($filtros) : [];
// Mapa codigo->resultado para anotar os pontos no SVG
$mapaScore = [];
foreach ($resultados as $r) {
  $mapaScore[strtolower($r['ponto']['codigo'])] = $r;
}

$catSintomas  = acup_catalogo('sintomas_relacionados');
$catSindromes = acup_catalogo('sindromes_relacionadas');
$catAcoes     = acup_catalogo('acoes_energeticas');
$regioes      = acup_regioes();
$catRegioes   = array_column($regioes, 'nome', 'id');

$selSint = array_map('mb_strtolower', $filtros['sintomas']);
$selSind = array_map('mb_strtolower', $filtros['sindromes']);
$selAcoe = array_map('mb_strtolower', $filtros['acoes']);
$selRegi = $filtros['regioes'];
$selRest = $filtros['restricoes'];

// Quais regiões estão "ativas" no mapa: top 5 pontos não-descartados acendem
// suas regiões. Casa nomes com acento ("cabeça") aos IDs sem acento ("cabeca").
$regioesAtivas = [];
$topAtivos = 0;
foreach ($resultados as $r) {
  if ($topAtivos >= 5) break;
  if ($r['descartado']) continue;
  foreach (($r['ponto']['regiao_afetada'] ?? []) as $reg) {
    $regioesAtivas[rec_normaliza((string)$reg)] = true;
  }
  $topAtivos++;
}

function svg_region_path(array $regiao, string $vista): ?string {
  $vistas = $regiao['vistas'] ?? [];
  return $vistas[$vista]['d'] ?? null;
}

function svg_pontos_da_vista(string $vista, array $mapaScore): string {
  $html = '';
  foreach (acup_pontos() as $p) {
    $cm = $p['coordenadas_mapa'] ?? [];
    if (($cm['vista'] ?? '') !== $vista) continue;

    $cx = (float)($cm['x'] ?? 0);
    $cy = (float)($cm['y'] ?? 0);
    $lado = $cm['lado'] ?? 'central';

    $r   = $mapaScore[strtolower($p['codigo'])] ?? null;
    $rel = $r ? rec_relevancia($r['score']) : 0;
    $descartado = $r && $r['descartado'];

    $cls = 'acup-point';
    if ($descartado)       $cls .= ' acup-point--descartado';
    elseif ($rel > 0)      $cls .= ' acup-point--r' . $rel;
    else                   $cls .= ' acup-point--r1';

    $motivos = $r ? htmlspecialchars(implode(' • ', $r['motivos']), ENT_QUOTES) : '';
    $dataAttrs = sprintf(
      'data-codigo="%s" data-nome="%s" data-meridiano="%s" data-score="%d" data-descartado="%s" data-motivos="%s"',
      htmlspecialchars($p['codigo'], ENT_QUOTES),
      htmlspecialchars($p['nome'], ENT_QUOTES),
      htmlspecialchars($p['meridiano'], ENT_QUOTES),
      $r ? $r['score'] : 0,
      $descartado ? '1' : '0',
      $motivos
    );

    // bilateral = espelha no eixo x=100
    $coords = [];
    if ($lado === 'bilateral') {
      $coords[] = [$cx, $cy];
      $coords[] = [200 - $cx, $cy];
    } else {
      $coords[] = [$cx, $cy];
    }
    foreach ($coords as $c) {
      $html .= sprintf(
        '<circle class="%s" cx="%.2f" cy="%.2f" %s tabindex="0" focusable="true"></circle>',
        $cls, $c[0], $c[1], $dataAttrs
      );
    }
  }
  return $html;
}

function svg_silhueta(string $vista): string {
  // Silhueta minimalista — vertical, viewBox 0 0 200 500.
  $vista = $vista; // unused for now (mesmo desenho nas 3 vistas), placeholder.
  return <<<SVG
<!-- cabeça -->
<ellipse class="acup-silhouette" cx="100" cy="50" rx="28" ry="40" />
<!-- pescoço -->
<rect class="acup-silhouette" x="90" y="86" width="20" height="22" rx="4" />
<!-- tronco -->
<path class="acup-silhouette" d="M 62 110 Q 64 105 70 105 L 130 105 Q 136 105 138 110 L 138 200 L 62 200 Z" />
<!-- abdomen -->
<path class="acup-silhouette" d="M 70 200 L 130 200 L 128 290 L 72 290 Z" />
<!-- braços -->
<path class="acup-silhouette" d="M 40 130 L 64 110 L 70 270 L 46 280 Z" />
<path class="acup-silhouette" d="M 160 130 L 136 110 L 130 270 L 154 280 Z" />
<!-- mãos -->
<ellipse class="acup-silhouette" cx="50" cy="290" rx="14" ry="14" />
<ellipse class="acup-silhouette" cx="150" cy="290" rx="14" ry="14" />
<!-- quadril -->
<path class="acup-silhouette" d="M 72 286 L 128 286 L 132 310 L 68 310 Z" />
<!-- pernas -->
<path class="acup-silhouette" d="M 72 310 L 100 310 L 96 460 L 76 460 Z" />
<path class="acup-silhouette" d="M 100 310 L 128 310 L 124 460 L 104 460 Z" />
<!-- pés -->
<ellipse class="acup-silhouette" cx="86" cy="470" rx="16" ry="10" />
<ellipse class="acup-silhouette" cx="114" cy="470" rx="16" ry="10" />
SVG;
}

function svg_regioes(string $vista, array $regioes, array $ativas): string {
  $html = '';
  foreach ($regioes as $reg) {
    $d = svg_region_path($reg, $vista);
    if (!$d) continue;
    $cls = 'acup-region';
    // Comparação por id normalizado e por nome normalizado — o ponto
    // pode referir-se à região tanto pelo id ("cabeca") quanto pelo nome ("cabeça").
    $idNorm   = rec_normaliza((string)$reg['id']);
    $nomeNorm = rec_normaliza((string)($reg['nome'] ?? ''));
    if (!empty($ativas[$idNorm]) || !empty($ativas[$nomeNorm])) $cls .= ' is-active';
    $html .= sprintf('<path class="%s" data-regiao="%s" d="%s" />', $cls, htmlspecialchars($reg['id']), $d);
  }
  return $html;
}

$pageTitle = 'Acupuntura — Mapa interativo (experimental) • Pindorama';
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#0E1C17" />
  <meta name="robots" content="noindex" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="Ferramenta experimental de apoio clínico em acupuntura — mapa corporal interativo e recomendações por sintoma/síndrome/ação. Educacional, não substitui consulta.">
  <link rel="icon" type="image/svg+xml" href="../../assets/img/logo-pindorama.svg" />
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="assets/acupuntura.css">
</head>
<body>

<header class="acup-topbar">
  <div class="acup-topbar__inner">
    <a class="acup-topbar__brand" href="../../index.php">
      <img src="../../assets/img/logo-pindorama.svg" alt="Pindorama">
      <div>
        <strong>Coletivo Pindorama</strong>
        <span>Acupuntura · ferramenta experimental</span>
      </div>
    </a>
    <nav class="acup-topbar__nav" aria-label="Navegação">
      <a href="../../terapias.php">Terapias</a>
      <a href="../../index.php#contato">Contato</a>
    </nav>
  </div>
</header>

<main class="acup-shell">

  <section class="acup-head">
    <h1>Mapa interativo de Acupuntura <span style="font-size:.55em;color:var(--muted);font-weight:400;">· experimental</span></h1>
    <p>Marque os sintomas, síndromes e ações desejadas. O sistema sugere pontos por relevância e ilumina as regiões corporais correspondentes. Use o zoom e passe o mouse (ou toque) sobre os pontos para ver detalhes.</p>
  </section>

  <div class="acup-disclaimer">
    <div>
      <strong>Apoio educacional.</strong> Esta ferramenta é experimental e não substitui consulta com profissional habilitado. Nenhum dado preenchido é salvo — tudo é processado apenas durante esta visita.
    </div>
  </div>

  <div class="acup-grid">

    <!-- ============ FICHA ============ -->
    <aside class="acup-card" aria-labelledby="ficha-h2">
      <h2 id="ficha-h2">Ficha</h2>
      <p class="acup-card__sub">Os campos não são obrigatórios — só os que ajudarem na avaliação.</p>

      <form method="post" action="">
        <div class="acup-field">
          <label for="paciente">Paciente (identificação)</label>
          <input id="paciente" name="paciente" maxlength="80" value="<?= htmlspecialchars($filtros['paciente']) ?>" placeholder="Ex.: Maria S. (não use nome completo)">
        </div>
        <div class="acup-field--row">
          <div class="acup-field" style="flex:1;">
            <label for="idade">Idade</label>
            <input id="idade" name="idade" inputmode="numeric" maxlength="3" value="<?= htmlspecialchars($filtros['idade']) ?>" placeholder="Anos">
          </div>
        </div>
        <div class="acup-field">
          <label for="queixa">Queixa principal</label>
          <textarea id="queixa" name="queixa" rows="2" placeholder="Em uma frase: o que trouxe a pessoa hoje."><?= htmlspecialchars($filtros['queixa']) ?></textarea>
        </div>

        <fieldset class="acup-fieldset">
          <legend>Sintomas (<?= count($catSintomas) ?>)</legend>
          <div class="acup-chips">
            <?php foreach ($catSintomas as $s):
              $checked = in_array(mb_strtolower($s), $selSint, true);
            ?>
              <label class="acup-chip">
                <input type="checkbox" name="sintomas[]" value="<?= htmlspecialchars($s) ?>" <?= $checked ? 'checked' : '' ?>>
                <?= htmlspecialchars($s) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="acup-fieldset">
          <legend>Síndromes suspeitas (<?= count($catSindromes) ?>)</legend>
          <div class="acup-chips">
            <?php foreach ($catSindromes as $s):
              $checked = in_array(mb_strtolower($s), $selSind, true);
            ?>
              <label class="acup-chip">
                <input type="checkbox" name="sindromes[]" value="<?= htmlspecialchars($s) ?>" <?= $checked ? 'checked' : '' ?>>
                <?= htmlspecialchars($s) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="acup-fieldset">
          <legend>Ações energéticas desejadas (<?= count($catAcoes) ?>)</legend>
          <div class="acup-chips">
            <?php foreach ($catAcoes as $s):
              $checked = in_array(mb_strtolower($s), $selAcoe, true);
            ?>
              <label class="acup-chip">
                <input type="checkbox" name="acoes[]" value="<?= htmlspecialchars($s) ?>" <?= $checked ? 'checked' : '' ?>>
                <?= htmlspecialchars($s) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="acup-fieldset">
          <legend>Foco anatômico (opcional)</legend>
          <div class="acup-chips">
            <?php foreach ($catRegioes as $id => $nome):
              $checked = in_array($id, $selRegi, true);
            ?>
              <label class="acup-chip">
                <input type="checkbox" name="regioes[]" value="<?= htmlspecialchars($id) ?>" <?= $checked ? 'checked' : '' ?>>
                <?= htmlspecialchars($nome) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="acup-fieldset">
          <legend>Restrições</legend>
          <div class="acup-chips">
            <?php foreach (['gestante' => 'Gestante', 'hipertensao' => 'Hipertensão grave', 'marcapasso' => 'Marca-passo'] as $k => $lbl):
              $checked = in_array($k, $selRest, true);
            ?>
              <label class="acup-chip acup-chip--clay">
                <input type="checkbox" name="restricoes[]" value="<?= $k ?>" <?= $checked ? 'checked' : '' ?>>
                <?= htmlspecialchars($lbl) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="acup-actions">
          <button type="submit" class="acup-btn acup-btn--primary">Sugerir pontos</button>
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="acup-btn acup-btn--ghost">Limpar</a>
        </div>
      </form>
    </aside>

    <!-- ============ MAPA + RECOMENDAÇÕES ============ -->
    <section class="acup-map-panel">

      <div class="acup-toolbar" role="toolbar" aria-label="Ferramentas do mapa">
        <div class="acup-toolbar__group" aria-label="Vista">
          <button type="button" class="acup-toolbar__btn is-active" data-acup-view-toggle="todas">Todas</button>
          <button type="button" class="acup-toolbar__btn" data-acup-view-toggle="frente">Frente</button>
          <button type="button" class="acup-toolbar__btn" data-acup-view-toggle="costas">Costas</button>
          <button type="button" class="acup-toolbar__btn" data-acup-view-toggle="perfil">Perfil</button>
        </div>

        <div class="acup-zoom" aria-label="Zoom">
          <button type="button" class="acup-zoom__btn" data-acup-zoom="out" aria-label="Diminuir zoom">−</button>
          <span class="acup-zoom__val" data-acup-zoom-val>100%</span>
          <button type="button" class="acup-zoom__btn" data-acup-zoom="in"  aria-label="Aumentar zoom">+</button>
          <button type="button" class="acup-zoom__btn" data-acup-zoom="reset" aria-label="Resetar zoom">⟳</button>
        </div>
      </div>

      <div class="acup-map" id="acup-map">
        <div class="acup-map__scroller">
          <?php foreach (['frente' => 'Frente', 'costas' => 'Costas', 'perfil' => 'Perfil'] as $vista => $label): ?>
            <figure class="acup-figure" data-acup-vista="<?= $vista ?>">
              <figcaption class="acup-figure__label"><?= $label ?></figcaption>
              <svg viewBox="0 0 200 500" xmlns="http://www.w3.org/2000/svg" aria-label="Silhueta vista <?= $vista ?>">
                <?= svg_silhueta($vista) ?>
                <?= svg_regioes($vista, $regioes, $regioesAtivas) ?>
                <?= svg_pontos_da_vista($vista, $mapaScore) ?>
              </svg>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="acup-legend" aria-label="Legenda de relevância">
        <span class="acup-legend__item"><span class="acup-legend__dot" style="background: var(--clay2);"></span> Alta relevância (pulsa)</span>
        <span class="acup-legend__item"><span class="acup-legend__dot" style="background: rgba(224, 138, 107, .95);"></span> Boa</span>
        <span class="acup-legend__item"><span class="acup-legend__dot" style="background: var(--leaf2);"></span> Sugerido</span>
        <span class="acup-legend__item"><span class="acup-legend__dot" style="background: rgba(244, 231, 211, .55);"></span> Base</span>
        <span class="acup-legend__item"><span class="acup-legend__dot" style="background: rgba(120,120,120,.45); border-style: dashed;"></span> Descartado por restrição</span>
      </div>

      <!-- ============ RECOMENDAÇÕES ============ -->
      <div class="acup-card" aria-labelledby="rec-h2">
        <h2 id="rec-h2">Pontos sugeridos
          <?php if ($temFiltro): ?>
            <span style="font-size:.7em;color:var(--muted);font-weight:400;">· <?= count(array_filter($resultados, fn($r) => !$r['descartado'])) ?> ativos</span>
          <?php endif; ?>
        </h2>

        <?php if (!$temFiltro): ?>
          <p class="acup-card__sub">Marque pelo menos um sintoma, síndrome, ação ou região na ficha para ver sugestões. Todos os pontos da base já aparecem no mapa como pontos-base.</p>
        <?php else:
          // Separa: top ativos + todos os descartados (que merecem visibilidade
          // mesmo se cortados pelo slice — são alertas clínicos).
          $ativos     = array_filter($resultados, fn($r) => !$r['descartado']);
          $descartados= array_filter($resultados, fn($r) =>  $r['descartado']);
          $exibir = array_merge(array_slice($ativos, 0, 12), $descartados);
        ?>
          <?php if ($descartados): ?>
            <div class="acup-disclaimer" style="margin-bottom:12px;">
              <div>
                <strong><?= count($descartados) ?> ponto(s) descartado(s)</strong> por restrições marcadas na ficha.
                Aparecem ao final da lista, com o motivo do descarte.
              </div>
            </div>
          <?php endif; ?>

          <div class="acup-recs">
            <?php foreach ($exibir as $r):
              $p = $r['ponto'];
              $rel = $r['descartado'] ? 0 : rec_relevancia($r['score']);
              $relCls = $r['descartado'] ? 'acup-rec--descartado' : 'acup-rec--r' . $rel;
            ?>
              <details class="acup-rec <?= $relCls ?>" data-rec-codigo="<?= htmlspecialchars($p['codigo']) ?>">
                <summary style="list-style:none; cursor:pointer; display:contents;">
                  <span class="acup-rec__code"><?= htmlspecialchars($p['codigo']) ?></span>
                  <div class="acup-rec__body">
                    <strong><?= htmlspecialchars($p['nome']) ?></strong>
                    <div class="acup-rec__meridiano"><?= htmlspecialchars($p['meridiano']) ?> · <?= htmlspecialchars(implode(' / ', $p['categoria'] ?? [])) ?></div>
                    <?php if ($r['descartado']): ?>
                      <div class="acup-rec__descartado">⚠ Descartado: <?= htmlspecialchars((string)$r['descartado_por']) ?></div>
                    <?php elseif ($r['motivos']): ?>
                      <div class="acup-rec__motivos"><?= htmlspecialchars(implode(' • ', $r['motivos'])) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="acup-rec__score">
                    <strong><?= max(0, $r['score']) ?></strong>
                    relevância <?= $rel ?: '—' ?>/5
                  </div>
                </summary>
                <dl class="acup-rec__details">
                  <div><dt>Localização</dt><dd><?= htmlspecialchars($p['localizacao'] ?? '—') ?></dd></div>
                  <?php if (!empty($p['acoes_energeticas'])): ?>
                    <div><dt>Ações energéticas</dt><dd><?= htmlspecialchars(implode(', ', $p['acoes_energeticas'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['indicacoes_terapeuticas'])): ?>
                    <div><dt>Indicações</dt><dd><?= htmlspecialchars(implode(', ', $p['indicacoes_terapeuticas'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['combinacoes'])): ?>
                    <div><dt>Combinações</dt><dd>
                      <?php foreach ($p['combinacoes'] as $i => $c): ?>
                        <?= $i > 0 ? '; ' : '' ?><strong><?= htmlspecialchars($c['com']) ?></strong> — <?= htmlspecialchars($c['objetivo']) ?>
                      <?php endforeach; ?>
                    </dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['contraindicacoes'])): ?>
                    <div><dt>Contraindicações</dt><dd style="color:var(--clay2);"><?= htmlspecialchars(implode('; ', $p['contraindicacoes'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['observacoes_clinicas'])): ?>
                    <div><dt>Observação clínica</dt><dd><?= htmlspecialchars($p['observacoes_clinicas']) ?></dd></div>
                  <?php endif; ?>
                </dl>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </section>
  </div>

  <footer style="text-align:center; color:var(--muted); font-size:11px; padding-top:18px;">
    Base experimental com <?= count(acup_pontos()) ?> pontos clássicos · estrutura preparada para 100+ pontos
    · <a href="../../index.php" style="color:var(--sand);">Voltar ao Coletivo Pindorama</a>
  </footer>

</main>

<script src="assets/acupuntura.js" defer></script>
</body>
</html>

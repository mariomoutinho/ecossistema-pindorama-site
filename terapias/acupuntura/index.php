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
function _post_array(string $k): array {
  return (isset($_POST[$k]) && is_array($_POST[$k])) ? array_values(array_filter(array_map('strval', $_POST[$k]), fn($v) => $v !== '')) : [];
}
$filtros = [
  'paciente'         => trim((string)($_POST['paciente'] ?? '')),
  'idade'            => trim((string)($_POST['idade']    ?? '')),
  'queixa'           => trim((string)($_POST['queixa']   ?? '')),
  'sintomas'         => _post_array('sintomas'),
  'sindromes'        => _post_array('sindromes'),
  'acoes'            => _post_array('acoes'),
  'regioes'          => _post_array('regioes'),
  'meridianos'       => _post_array('meridianos'),
  'categorias'       => _post_array('categorias'),
  'pontos_utilizados'=> _post_array('pontos_utilizados'),
  'praticas'         => _post_array('praticas'),
  'restricoes'       => _post_array('restricoes'),
];
$temFiltro = (bool)(
  $filtros['sintomas'] || $filtros['sindromes'] || $filtros['acoes'] ||
  $filtros['regioes']  || $filtros['meridianos'] || $filtros['categorias'] ||
  $filtros['pontos_utilizados']
);

$resultados = $temFiltro ? rec_calcular($filtros) : [];
// Mapa codigo->resultado para anotar os pontos no SVG
$mapaScore = [];
foreach ($resultados as $r) {
  $mapaScore[strtolower($r['ponto']['codigo'])] = $r;
}

$catSintomas    = acup_catalogo('sintomas_relacionados');
$catSindromes   = acup_catalogo('sindromes_relacionadas');
$catAcoes       = acup_catalogo('acoes_energeticas');
$catMeridianos  = acup_meridianos();
$catCategorias  = acup_catalogo('categoria');
$catPraticas    = acup_praticas();
$catPontos      = acup_pontos_para_autocomplete();
$regioes        = acup_regioes();
$catRegioes     = []; // {value: id, label: nome}
foreach ($regioes as $r) $catRegioes[] = ['value' => $r['id'], 'label' => $r['nome']];
$catRestricoes  = [
  ['value' => 'gestante',    'label' => 'Gestante'],
  ['value' => 'hipertensao', 'label' => 'Hipertensão grave'],
  ['value' => 'marcapasso',  'label' => 'Marca-passo'],
];

// Filtro de exibição por meridiano (querystring ?meridiano=...). Permite ao
// terapeuta focar a lista em um meridiano específico mantendo os filtros do POST.
$meridianoVisualizado = isset($_POST['meridiano_view']) ? trim((string)$_POST['meridiano_view']) : '';

// Distribuição de pontos não-descartados por meridiano (para os chips de filtro)
$contagemMeridiano = [];
foreach ($resultados as $r) {
  if ($r['descartado']) continue;
  $m = $r['ponto']['meridiano'] ?? '?';
  $contagemMeridiano[$m] = ($contagemMeridiano[$m] ?? 0) + 1;
}
uksort($contagemMeridiano, fn($a, $b) => strcmp($a, $b));

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

      <form method="post" action="" id="ficha-form">
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

        <?php
          // Helper para imprimir um container .acup-ac padronizado.
          // $options: array de strings OU array de {value,label}
          $renderAc = function (string $name, string $label, string $placeholder, array $options, array $selected, string $modifier = '') {
            // Contagem total (excluindo já selecionados — info útil pro usuário)
            $total = count($options);
            $optsJson = htmlspecialchars(json_encode(array_values($options), JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            $selJson  = htmlspecialchars(json_encode(array_values($selected), JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            $cls = 'acup-ac' . ($modifier ? ' ' . $modifier : '');
            echo '<div class="acup-field">'
               . '<label>' . htmlspecialchars($label) . ' <span style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;">· ' . $total . ' opções</span></label>'
               . '<div class="' . $cls . '"'
               . ' data-name="' . htmlspecialchars($name) . '"'
               . ' data-placeholder="' . htmlspecialchars($placeholder) . '"'
               . ' data-options="' . $optsJson . '"'
               . ' data-selected="' . $selJson . '"></div>'
               . '</div>';
          };
        ?>

        <?php $renderAc('sintomas',          'Sintomas',                'Buscar ou selecionar sintomas...',     $catSintomas,   $filtros['sintomas']); ?>
        <?php $renderAc('sindromes',         'Síndromes suspeitas',      'Buscar síndromes suspeitas...',        $catSindromes,  $filtros['sindromes']); ?>
        <?php $renderAc('acoes',             'Ações energéticas',        'Buscar ações energéticas...',          $catAcoes,      $filtros['acoes']); ?>
        <?php $renderAc('meridianos',        'Meridianos',               'Buscar meridianos...',                 $catMeridianos, $filtros['meridianos']); ?>
        <?php $renderAc('categorias',        'Categorias de pontos',     'Buscar categorias...',                 $catCategorias, $filtros['categorias']); ?>
        <?php $renderAc('regioes',           'Foco anatômico',           'Selecionar regiões corporais...',      $catRegioes,    $filtros['regioes']); ?>
        <?php $renderAc('pontos_utilizados', 'Pontos já utilizados',     'Buscar pontos já utilizados...',       $catPontos,     $filtros['pontos_utilizados']); ?>
        <?php $renderAc('praticas',          'Práticas associadas',      'Buscar práticas associadas...',        $catPraticas,   $filtros['praticas']); ?>
        <?php $renderAc('restricoes',        'Restrições clínicas',      'Selecionar restrições...',             $catRestricoes, $filtros['restricoes'], 'acup-ac--clay'); ?>

        <div class="acup-actions">
          <button type="submit" class="acup-btn acup-btn--primary">Sugerir pontos</button>
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="acup-btn acup-btn--ghost">Limpar</a>
        </div>
      </form>
    </aside>

    <!-- ============ RESULTADOS ============ -->
    <section class="acup-results-panel" aria-labelledby="rec-h2">

      <div class="acup-card">
        <h2 id="rec-h2" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          Pontos sugeridos
          <?php if ($temFiltro):
            $totalAtivos = count(array_filter($resultados, fn($r) => !$r['descartado']));
            $totalDesc   = count(array_filter($resultados, fn($r) =>  $r['descartado']));
          ?>
            <span style="font-size:.65em;color:var(--muted);font-weight:400;">
              · <?= $totalAtivos ?> ativos<?= $totalDesc ? " · {$totalDesc} descartados" : '' ?>
            </span>
          <?php else: ?>
            <span style="font-size:.55em;color:var(--muted);font-weight:400;">
              · base com <?= count(acup_pontos()) ?> pontos · <?= count(array_filter(acup_pontos(), fn($p) => !empty($p['dados_completos']))) ?> com dados clínicos completos
            </span>
          <?php endif; ?>
        </h2>

        <?php if (!$temFiltro): ?>
          <p class="acup-card__sub">
            Marque pelo menos um sintoma, síndrome, ação energética ou meridiano na ficha
            ao lado para ver sugestões ranqueadas. A base contém os 361 pontos canônicos
            dos 14 meridianos principais e ~20 pontos extraordinários.
          </p>
          <div class="acup-disclaimer">
            <div>
              <strong>Ferramenta experimental de apoio à decisão clínica.</strong>
              Não substitui avaliação direta nem julgamento profissional. As recomendações
              partem de uma base estruturada e podem conter pontos com dados clínicos
              parciais — marcados como esqueleto na lista.
            </div>
          </div>
        <?php else:
          // Separa ativos e descartados; aplica filtro por meridiano se houver.
          $ativos      = array_values(array_filter($resultados, fn($r) => !$r['descartado']));
          $descartados = array_values(array_filter($resultados, fn($r) =>  $r['descartado']));
          if ($meridianoVisualizado !== '') {
            $ativos = array_values(array_filter($ativos, fn($r) => ($r['ponto']['meridiano'] ?? '') === $meridianoVisualizado));
          }
          // Limita exibição a 30 ativos (suficiente para inspecionar; resto fica disponível na base).
          $exibir = array_merge(array_slice($ativos, 0, 30), $descartados);
        ?>

          <?php if (count($contagemMeridiano) > 1): ?>
            <div class="acup-filter-bar" role="group" aria-label="Filtrar por meridiano">
              <button type="submit" form="ficha-form" name="meridiano_view" value=""
                      class="acup-filter <?= $meridianoVisualizado === '' ? 'is-active' : '' ?>">
                Todos <span><?= array_sum($contagemMeridiano) ?></span>
              </button>
              <?php foreach ($contagemMeridiano as $m => $n): ?>
                <button type="submit" form="ficha-form" name="meridiano_view" value="<?= htmlspecialchars($m) ?>"
                        class="acup-filter <?= $meridianoVisualizado === $m ? 'is-active' : '' ?>">
                  <?= htmlspecialchars($m) ?> <span><?= $n ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($descartados): ?>
            <div class="acup-disclaimer" style="margin-bottom:12px;">
              <div>
                <strong><?= count($descartados) ?> ponto(s) descartado(s)</strong> por restrições marcadas na ficha.
                Aparecem ao final da lista, com o motivo do descarte.
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$exibir): ?>
            <p class="acup-card__sub">Nenhum ponto corresponde aos critérios selecionados neste meridiano.</p>
          <?php endif; ?>

          <div class="acup-recs">
            <?php foreach ($exibir as $r):
              $p = $r['ponto'];
              $rel = $r['descartado'] ? 0 : rec_relevancia($r['score']);
              $relCls = $r['descartado'] ? 'acup-rec--descartado' : 'acup-rec--r' . $rel;
              $esqueleto = empty($p['dados_completos']);
            ?>
              <details class="acup-rec <?= $relCls ?> <?= $esqueleto ? 'acup-rec--esqueleto' : '' ?>" data-rec-codigo="<?= htmlspecialchars($p['codigo']) ?>">
                <summary>
                  <span class="acup-rec__code"><?= htmlspecialchars($p['codigo']) ?></span>
                  <div class="acup-rec__body">
                    <strong><?= htmlspecialchars($p['nome']) ?></strong>
                    <div class="acup-rec__meridiano">
                      <?= htmlspecialchars($p['meridiano']) ?>
                      <?php if (!empty($p['categoria'])): ?>
                        <span style="color:var(--muted);">· <?= htmlspecialchars(implode(' / ', $p['categoria'])) ?></span>
                      <?php endif; ?>
                      <?php if ($esqueleto): ?>
                        <span class="acup-rec__esqueleto-tag" title="Sem dados clínicos completos">esqueleto</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($r['descartado']): ?>
                      <div class="acup-rec__descartado">⚠ Descartado: <?= htmlspecialchars((string)$r['descartado_por']) ?></div>
                    <?php elseif ($r['motivos']): ?>
                      <div class="acup-rec__motivos"><?= htmlspecialchars(implode(' • ', $r['motivos'])) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="acup-rec__score">
                    <strong><?= max(0, $r['score']) ?></strong>
                    <span>relevância <?= $rel ?: '—' ?>/5</span>
                  </div>
                </summary>
                <dl class="acup-rec__details">
                  <?php if (!empty($p['localizacao'])): ?>
                    <div><dt>Localização</dt><dd><?= htmlspecialchars($p['localizacao']) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['acoes_energeticas'])): ?>
                    <div><dt>Ações energéticas</dt><dd><?= htmlspecialchars(implode(', ', $p['acoes_energeticas'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['sintomas_relacionados'])): ?>
                    <div><dt>Sintomas relacionados</dt><dd><?= htmlspecialchars(implode(', ', $p['sintomas_relacionados'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['sindromes_relacionadas'])): ?>
                    <div><dt>Síndromes relacionadas</dt><dd><?= htmlspecialchars(implode(', ', $p['sindromes_relacionadas'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['indicacoes_terapeuticas'])): ?>
                    <div><dt>Indicações terapêuticas</dt><dd><?= htmlspecialchars(implode(', ', $p['indicacoes_terapeuticas'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['combinacoes'])): ?>
                    <div><dt>Combinações</dt><dd>
                      <?php foreach ($p['combinacoes'] as $i => $c): ?>
                        <?= $i > 0 ? '; ' : '' ?><strong><?= htmlspecialchars($c['com'] ?? '') ?></strong> — <?= htmlspecialchars($c['objetivo'] ?? '') ?>
                      <?php endforeach; ?>
                    </dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['contraindicacoes'])): ?>
                    <div><dt>⚠ Contraindicações</dt><dd style="color:var(--clay2);"><?= htmlspecialchars(implode('; ', $p['contraindicacoes'])) ?></dd></div>
                  <?php endif; ?>
                  <?php if (!empty($p['observacoes_clinicas'])): ?>
                    <div><dt>Observação clínica</dt><dd><?= htmlspecialchars($p['observacoes_clinicas']) ?></dd></div>
                  <?php endif; ?>
                  <?php if ($esqueleto): ?>
                    <div><dt>Status</dt><dd style="color:var(--muted);">
                      Esqueleto — código, nome, meridiano e localização padrão.
                      Sintomas/síndromes/ações ainda não modelados nesta base.
                    </dd></div>
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

<script src="assets/autocomplete.js" defer></script>
<script src="assets/acupuntura.js" defer></script>
</body>
</html>

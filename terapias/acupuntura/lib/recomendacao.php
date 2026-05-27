<?php
// ================================
// Algoritmo de recomendação de pontos.
//
// Entrada: array de filtros vindos do formulário —
//   - sintomas:   ['dor de cabeça', 'ansiedade', ...]
//   - sindromes:  ['estagnação de Qi', ...]
//   - acoes:      ['acalmar Shen', ...]
//   - regioes:    ['cabeça', 'lombar'] (opcional, foco anatômico)
//   - restricoes: ['gestante']         (filtra contraindicações)
//
// Saída: lista ordenada por score. Cada item:
//   ['ponto' => array, 'score' => int, 'motivos' => array, 'descartado' => bool, 'descartado_por' => string]
//
// Score:
//   base       = peso_base (1..10) * 10
//   + 35 por sintoma   batido
//   + 28 por síndrome  batida
//   + 22 por ação      batida
//   + 18 por região    batida
//   - 100 e marca "descartado" se uma contraindicação corresponder a uma restrição ativa.
//
// >>> Por que pesos fixos: simples de explicar pro terapeuta e fácil de
//     ajustar olhando a planilha. Em iteração futura dá pra trocar por
//     TF-IDF/embeddings, mas o overhead não se paga no MVP.
// ================================

require_once __DIR__ . '/pontos.php';

function rec_normaliza(string $s): string {
  $s = mb_strtolower(trim($s), 'UTF-8');
  // remove acentuação básica para casamento robusto
  $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
  return strtr($s, $map);
}

function rec_intersect(array $listaPonto, array $filtro): array {
  if (!$filtro) return [];
  $normaisFiltro = array_map('rec_normaliza', $filtro);
  $hits = [];
  foreach ($listaPonto as $item) {
    if (in_array(rec_normaliza((string)$item), $normaisFiltro, true)) {
      $hits[] = $item;
    }
  }
  return $hits;
}

/**
 * Contraindicação ativa? Faz match por palavra-chave: se a restrição
 * "gestante" aparece em qualquer string da contraindicação do ponto,
 * considera ativa.
 */
function rec_contraindicado(array $ponto, array $restricoes): ?string {
  $contras = $ponto['contraindicacoes'] ?? [];
  if (!$contras || !$restricoes) return null;
  foreach ($restricoes as $r) {
    $rn = rec_normaliza($r);
    if ($rn === '') continue;
    foreach ($contras as $c) {
      if (strpos(rec_normaliza((string)$c), $rn) !== false) {
        return (string)$c;
      }
    }
  }
  return null;
}

function rec_calcular(array $filtros): array {
  $sintomas  = $filtros['sintomas']   ?? [];
  $sindromes = $filtros['sindromes']  ?? [];
  $acoes     = $filtros['acoes']      ?? [];
  $regioes   = $filtros['regioes']    ?? [];
  $restr     = $filtros['restricoes'] ?? [];

  $resultados = [];
  foreach (acup_pontos() as $p) {
    $score = (int)($p['peso_base'] ?? 1) * 10;
    $motivos = [];

    $hitSint = rec_intersect($p['sintomas_relacionados']   ?? [], $sintomas);
    $hitSind = rec_intersect($p['sindromes_relacionadas']  ?? [], $sindromes);
    $hitAcoe = rec_intersect($p['acoes_energeticas']       ?? [], $acoes);
    $hitRegi = rec_intersect($p['regiao_afetada']          ?? [], $regioes);

    if ($hitSint) { $score += 35 * count($hitSint); $motivos[] = 'sintomas: ' . implode(', ', $hitSint); }
    if ($hitSind) { $score += 28 * count($hitSind); $motivos[] = 'síndromes: ' . implode(', ', $hitSind); }
    if ($hitAcoe) { $score += 22 * count($hitAcoe); $motivos[] = 'ações: ' . implode(', ', $hitAcoe); }
    if ($hitRegi) { $score += 18 * count($hitRegi); $motivos[] = 'região: ' . implode(', ', $hitRegi); }

    $contra = rec_contraindicado($p, $restr);
    $descartado = false;
    if ($contra !== null) {
      $descartado = true;
      $score -= 1000;
    }

    $resultados[] = [
      'ponto'           => $p,
      'score'           => $score,
      'motivos'         => $motivos,
      'descartado'      => $descartado,
      'descartado_por'  => $contra,
    ];
  }

  // Ordena por score desc; descartados vão pro fim.
  usort($resultados, function ($a, $b) {
    if ($a['descartado'] !== $b['descartado']) return $a['descartado'] ? 1 : -1;
    return $b['score'] <=> $a['score'];
  });

  return $resultados;
}

/**
 * Faixa de relevância (1-5) com base no score absoluto.
 * Usada para colorir o ponto no mapa: 5 = brasa pulsante, 1 = sutil.
 */
function rec_relevancia(int $score): int {
  if ($score >= 220) return 5;
  if ($score >= 170) return 4;
  if ($score >= 130) return 3;
  if ($score >= 100) return 2;
  return 1;
}

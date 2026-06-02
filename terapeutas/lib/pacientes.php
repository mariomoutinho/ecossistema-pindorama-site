<?php
// ================================
// Domínio de PACIENTES da área restrita.
//
// Tabela JSON: data/terapeutas/pacientes.json (gitignored — dados clínicos
// nunca vão para o Git). Cada paciente pertence a um terapeuta (terapeuta_id)
// e só é acessível por ele (ver pac_find_do_terapeuta / pac_assert_dono).
//
// As condições de saúde e os medicamentos são guardados como arrays
// embutidos no documento do paciente — coerente com o storage document-store
// já usado na área. Os campos seguem a spec (mapeados para chaves em PT).
// ================================

require_once __DIR__ . '/storage.php';

const PAC_TABELA = 'pacientes';
const PAC_POR_PAGINA = 12;

/** Catálogo de condições de saúde (Seção D). chave => rótulo. */
function pac_catalogo_condicoes(): array {
  return [
    'diabetes'         => 'Diabetes',
    'hipertensao'      => 'Hipertensão arterial',
    'cardiopatias'     => 'Cardiopatias',
    'circulatorios'    => 'Problemas circulatórios',
    'respiratorias'    => 'Doenças respiratórias',
    'renais'           => 'Doenças renais',
    'epilepsia'        => 'Epilepsia / convulsões',
    'infecciosas'      => 'Doenças infecciosas relevantes',
    'dores_cronicas'   => 'Dores crônicas',
    'lesoes'           => 'Lesões musculares ou articulares',
    'cirurgias'        => 'Cirurgias anteriores',
    'protese'          => 'Prótese / dispositivo implantado',
    'gestacao'         => 'Gestação',
    'alergias'         => 'Alergias',
    'contraindicacoes' => 'Contraindicações conhecidas',
    'outras'           => 'Outras condições de saúde',
  ];
}

/** Opções de sexo registrado ao nascer (Seção A). */
function pac_opcoes_sexo(): array {
  return [
    'feminino'     => 'Feminino',
    'masculino'    => 'Masculino',
    'intersexo'    => 'Intersexo',
    'nao_informar' => 'Prefiro não informar',
  ];
}

/** Opções de uso de substâncias (Seção F). */
function pac_opcoes_uso(): array {
  return [
    ''             => '—',
    'nao'          => 'Não usa',
    'ocasional'    => 'Uso ocasional',
    'frequente'    => 'Uso frequente',
    'nao_informar' => 'Prefiro não informar',
  ];
}

/** Campos de texto simples aceitos no formulário (chave => obrigatório?). */
function pac_campos_texto(): array {
  return [
    'nome_completo'           => true,
    'nome_social'             => false,
    'data_nascimento'         => false,
    'sexo_nascimento'         => false,
    'identidade_genero'       => false,
    'pronomes'                => false,
    'cpf'                     => false,
    'ocupacao'                => false,
    'email'                   => false,
    'telefone'                => false,
    'whatsapp'                => false,
    'telefone_alt'            => false,
    'contato_emerg_nome'      => false,
    'contato_emerg_relacao'   => false,
    'contato_emerg_telefone'  => false,
    'endereco'                => false,
    'queixa_principal'        => false,
    'motivo_procura'          => false,
    'historico_queixa'        => false,
    'objetivos'               => false,
    'observacoes_gerais'      => false,
    'outras_informacoes'      => false,
    'tratamentos_andamento'   => false,
    'profissionais_acompanham'=> false,
    'medicamentos_obs'        => false,
    'sm_historico'            => false,
    'sm_medicacao'            => false,
    'sm_observacoes'          => false,
    'uso_alcool'              => false,
    'uso_tabaco'              => false,
    'uso_outras_substancias'  => false,
    'substancias_obs'         => false,
    'ter_terapias_realizadas' => false,
    'ter_experiencias'        => false,
    'ter_resposta'            => false,
    'ter_preferencias'        => false,
    'ter_evitar'              => false,
    'ter_observacoes'         => false,
    'consentimento_obs'       => false,
  ];
}

/** Remove acentos e baixa caixa, para busca tolerante. */
function pac_normaliza(string $s): string {
  $s = mb_strtolower(trim($s), 'UTF-8');
  $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
          'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o',
          'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
  return strtr($s, $map);
}

/** Sanitiza telefone: mantém dígitos e um '+' inicial opcional. */
function pac_sanitiza_telefone(string $s): string {
  $s = trim($s);
  $plus = (strlen($s) > 0 && $s[0] === '+') ? '+' : '';
  return $plus . preg_replace('/\D/', '', $s);
}

/** Idade a partir da data de nascimento (Y-m-d). Null se inválida/futura. */
function pac_idade(?string $dataNascimento): ?int {
  if (!$dataNascimento) return null;
  $dt = DateTime::createFromFormat('Y-m-d', $dataNascimento);
  if (!$dt) return null;
  $hoje = new DateTime('today');
  if ($dt > $hoje) return null;
  return (int)$dt->diff($hoje)->y;
}

/** Nome de exibição: nome social quando houver, senão nome completo. */
function pac_nome_exibicao(array $p): string {
  $social = trim((string)($p['nome_social'] ?? ''));
  return $social !== '' ? $social : trim((string)($p['nome_completo'] ?? 'Paciente'));
}

/**
 * Busca um paciente garantindo que pertence ao terapeuta.
 * Retorna null se não existir OU não pertencer (não distingue os casos —
 * evita vazar existência de pacientes de terceiros).
 */
function pac_find_do_terapeuta($id, int $terapeutaId): ?array {
  $p = store_find(PAC_TABELA, $id);
  if (!$p) return null;
  if ((int)($p['terapeuta_id'] ?? 0) !== $terapeutaId) return null;
  return $p;
}

/**
 * Lista paginada dos pacientes de um terapeuta, com busca e filtro de status.
 * $status: 'ativos' | 'inativos' | 'todos'.
 * Retorna ['itens' => [...], 'total' => int, 'pagina' => int, 'paginas' => int].
 */
function pac_listar(int $terapeutaId, string $busca = '', string $status = 'ativos', int $pagina = 1, int $porPagina = PAC_POR_PAGINA): array {
  $todos = store_where(PAC_TABELA, fn($r) => (int)($r['terapeuta_id'] ?? 0) === $terapeutaId);

  // Filtro de status
  $todos = array_values(array_filter($todos, function ($p) use ($status) {
    $ativo = ($p['status'] ?? 'ativo') !== 'inativo';
    if ($status === 'ativos')   return $ativo;
    if ($status === 'inativos') return !$ativo;
    return true; // todos
  }));

  // Busca por nome, nome social, telefone, whatsapp, e-mail
  $busca = trim($busca);
  if ($busca !== '') {
    $alvo = pac_normaliza($busca);
    $alvoTel = preg_replace('/\D/', '', $busca);
    $todos = array_values(array_filter($todos, function ($p) use ($alvo, $alvoTel) {
      $campos = [
        pac_normaliza((string)($p['nome_completo'] ?? '')),
        pac_normaliza((string)($p['nome_social'] ?? '')),
        pac_normaliza((string)($p['email'] ?? '')),
      ];
      foreach ($campos as $c) {
        if ($c !== '' && strpos($c, $alvo) !== false) return true;
      }
      if ($alvoTel !== '') {
        foreach (['telefone', 'whatsapp', 'telefone_alt'] as $tk) {
          $tel = preg_replace('/\D/', '', (string)($p[$tk] ?? ''));
          if ($tel !== '' && strpos($tel, $alvoTel) !== false) return true;
        }
      }
      return false;
    }));
  }

  // Ordena por nome de exibição
  usort($todos, fn($a, $b) => strcmp(pac_normaliza(pac_nome_exibicao($a)), pac_normaliza(pac_nome_exibicao($b))));

  $total = count($todos);
  $paginas = max(1, (int)ceil($total / $porPagina));
  $pagina = max(1, min($pagina, $paginas));
  $itens = array_slice($todos, ($pagina - 1) * $porPagina, $porPagina);

  return ['itens' => $itens, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
}

/**
 * Constrói o array do paciente a partir do POST, sanitizando.
 * Não valida — use pac_validar() em seguida.
 */
function pac_montar_do_post(array $post): array {
  $dados = [];
  foreach (pac_campos_texto() as $campo => $_obrig) {
    $dados[$campo] = trim((string)($post[$campo] ?? ''));
  }

  // Telefones
  foreach (['telefone', 'whatsapp', 'telefone_alt', 'contato_emerg_telefone'] as $tk) {
    $dados[$tk] = pac_sanitiza_telefone($dados[$tk] ?? '');
  }
  // CPF: mantém apenas dígitos
  $dados['cpf'] = preg_replace('/\D/', '', $dados['cpf'] ?? '');

  // Enums controlados
  $dados['sexo_nascimento'] = array_key_exists($dados['sexo_nascimento'], pac_opcoes_sexo()) ? $dados['sexo_nascimento'] : '';
  foreach (['uso_alcool', 'uso_tabaco', 'uso_outras_substancias'] as $uk) {
    $dados[$uk] = array_key_exists($dados[$uk], pac_opcoes_uso()) ? $dados[$uk] : '';
  }

  $dados['status'] = (($post['status'] ?? 'ativo') === 'inativo') ? 'inativo' : 'ativo';
  $dados['sm_prefere_nao_informar'] = !empty($post['sm_prefere_nao_informar']);

  // Consentimento (Seção H)
  $dados['consentimento'] = !empty($post['consentimento']);
  $cd = trim((string)($post['consentimento_data'] ?? ''));
  $dados['consentimento_data'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd) ? $cd : '';

  // Condições de saúde (Seção D)
  $dados['condicoes'] = pac_montar_condicoes($post);

  // Medicamentos (Seção E)
  $dados['medicamentos'] = pac_montar_medicamentos($post);

  return $dados;
}

function pac_montar_condicoes(array $post): array {
  $catalogo = pac_catalogo_condicoes();
  $marcadas = (array)($post['condicoes'] ?? []);
  $detalhes = (array)($post['condicoes_detalhe'] ?? []);
  $out = [];
  foreach ($marcadas as $chave) {
    $chave = (string)$chave;
    if (!isset($catalogo[$chave])) continue;
    $out[] = [
      'key'     => $chave,
      'label'   => $catalogo[$chave],
      'details' => trim((string)($detalhes[$chave] ?? '')),
    ];
  }
  return $out;
}

function pac_montar_medicamentos(array $post): array {
  $nomes      = (array)($post['med_nome'] ?? []);
  $dosagens   = (array)($post['med_dosagem'] ?? []);
  $frequencias= (array)($post['med_frequencia'] ?? []);
  $notas      = (array)($post['med_notas'] ?? []);
  $out = [];
  foreach ($nomes as $i => $nome) {
    $nome = trim((string)$nome);
    if ($nome === '') continue;
    $out[] = [
      'nome'       => $nome,
      'dosagem'    => trim((string)($dosagens[$i] ?? '')),
      'frequencia' => trim((string)($frequencias[$i] ?? '')),
      'notas'      => trim((string)($notas[$i] ?? '')),
    ];
  }
  return $out;
}

/**
 * Valida os dados do paciente. Retorna lista de mensagens de erro (vazia = ok).
 */
function pac_validar(array $dados): array {
  $erros = [];
  if (($dados['nome_completo'] ?? '') === '') {
    $erros[] = 'O nome completo é obrigatório.';
  }
  $dn = $dados['data_nascimento'] ?? '';
  if ($dn !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dn)) {
      $erros[] = 'Data de nascimento inválida.';
    } else {
      $dt = DateTime::createFromFormat('Y-m-d', $dn);
      if (!$dt || $dt->format('Y-m-d') !== $dn) {
        $erros[] = 'Data de nascimento inválida.';
      } elseif ($dt > new DateTime('today')) {
        $erros[] = 'A data de nascimento não pode ser no futuro.';
      }
    }
  }
  $email = $dados['email'] ?? '';
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'E-mail do paciente inválido.';
  }
  if (($dados['cpf'] ?? '') !== '' && strlen($dados['cpf']) !== 11) {
    $erros[] = 'CPF deve conter 11 dígitos (ou deixe em branco).';
  }
  return $erros;
}

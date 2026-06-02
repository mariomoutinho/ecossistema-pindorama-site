<?php
// ================================
// Segurança da conta do terapeuta: troca de senha por código enviado
// ao e-mail cadastrado.
//
// Tabela JSON: data/terapeutas/codigos_senha.json
//   id, terapeuta_id, code_hash, expires_at, used_at, attempts,
//   criado_em, atualizado_em
//
// Regras (spec §3):
//   - Código de 6 dígitos, validade de 10 min, uso único.
//   - Armazena apenas o HASH do código (password_hash), nunca o código puro.
//   - Limite de tentativas de validação por código (ACCOUNT_MAX_TENTATIVAS).
//   - Intervalo mínimo entre solicitações (ACCOUNT_REENVIO_SEG).
//   - Ao solicitar um novo código, invalida os anteriores.
//   - O código nunca aparece em log de produção.
// ================================

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/mailer.php';

const ACCOUNT_CODIGO_TTL_SEG   = 600;  // 10 minutos
const ACCOUNT_MAX_TENTATIVAS   = 5;    // tentativas de validação por código
const ACCOUNT_REENVIO_SEG      = 60;   // intervalo mínimo entre solicitações
const ACCOUNT_TABELA           = 'codigos_senha';

/**
 * Marca como usados/expirados todos os códigos ativos de um terapeuta.
 */
function account_invalidar_codigos(int $terapeutaId): void {
  $rows = store_all(ACCOUNT_TABELA);
  $mudou = false;
  foreach ($rows as &$r) {
    if ((int)($r['terapeuta_id'] ?? 0) === $terapeutaId && empty($r['used_at'])) {
      $r['used_at'] = date('c');
      $r['atualizado_em'] = date('c');
      $mudou = true;
    }
  }
  unset($r);
  if ($mudou) store_save(ACCOUNT_TABELA, $rows);
}

/**
 * Retorna o código ativo mais recente do terapeuta (não usado e não expirado),
 * ou null.
 */
function account_codigo_ativo(int $terapeutaId): ?array {
  $agora = time();
  $ativos = store_where(ACCOUNT_TABELA, function ($r) use ($terapeutaId, $agora) {
    if ((int)($r['terapeuta_id'] ?? 0) !== $terapeutaId) return false;
    if (!empty($r['used_at'])) return false;
    $exp = strtotime((string)($r['expires_at'] ?? ''));
    return $exp && $exp > $agora;
  });
  if (!$ativos) return null;
  usort($ativos, fn($a, $b) => strcmp((string)($b['criado_em'] ?? ''), (string)($a['criado_em'] ?? '')));
  return $ativos[0];
}

/**
 * Solicita um novo código e o envia por e-mail.
 * Retorna ['ok' => bool, 'motivo' => string].
 * Mensagens são genéricas (não revelam se o e-mail existe).
 *
 * $devCode (saída por referência) recebe o código em claro APENAS quando o
 * transporte for "log" (DEV), para facilitar testes automatizados/manuais.
 */
function account_solicitar_codigo(int $terapeutaId, ?string &$devCode = null): array {
  $devCode = null;
  $terapeuta = store_find('terapeutas', $terapeutaId);
  if (!$terapeuta || empty($terapeuta['ativo'])) {
    return ['ok' => false, 'motivo' => 'indisponivel'];
  }

  // Intervalo mínimo entre solicitações.
  $ultimo = account_codigo_ativo($terapeutaId);
  if ($ultimo) {
    $criadoTs = strtotime((string)($ultimo['criado_em'] ?? ''));
    if ($criadoTs && (time() - $criadoTs) < ACCOUNT_REENVIO_SEG) {
      return ['ok' => false, 'motivo' => 'aguarde'];
    }
  }

  // Invalida anteriores e cria um novo.
  account_invalidar_codigos($terapeutaId);

  $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  store_insert(ACCOUNT_TABELA, [
    'terapeuta_id' => $terapeutaId,
    'code_hash'    => password_hash($codigo, PASSWORD_DEFAULT),
    'expires_at'   => date('c', time() + ACCOUNT_CODIGO_TTL_SEG),
    'used_at'      => null,
    'attempts'     => 0,
  ]);

  $html = mailer_template_codigo(
    explode(' ', trim((string)($terapeuta['nome'] ?? 'Terapeuta')))[0],
    $codigo,
    (int)(ACCOUNT_CODIGO_TTL_SEG / 60)
  );
  $res = mailer_send((string)$terapeuta['email'], 'Seu código de verificação — Espaço Pindorama', $html);

  if (($res['transport'] ?? '') === 'log') {
    // Apenas DEV: expõe o código para teste local (transporte "log").
    $devCode = $codigo;
  }

  // Mesmo se o envio falhar, devolvemos ok genérico para não revelar estado,
  // mas registramos a falha técnica (sem o conteúdo) para diagnóstico.
  if (empty($res['ok'])) {
    error_log('[account] envio de código falhou para terapeuta #' . $terapeutaId);
  }
  return ['ok' => true, 'motivo' => 'enviado'];
}

/**
 * Valida o código e troca a senha.
 * Retorna ['ok' => bool, 'motivo' => string].
 *   motivos de erro: 'sem_codigo', 'expirado', 'bloqueado', 'invalido', 'senha_fraca'
 */
function account_trocar_senha(int $terapeutaId, string $codigo, string $novaSenha, string $confirmaSenha): array {
  $codigo = preg_replace('/\D/', '', $codigo);

  if ($novaSenha !== $confirmaSenha) {
    return ['ok' => false, 'motivo' => 'confirma'];
  }
  $forca = account_validar_forca_senha($novaSenha);
  if ($forca !== true) {
    return ['ok' => false, 'motivo' => 'senha_fraca', 'detalhe' => $forca];
  }

  $registro = account_codigo_ativo($terapeutaId);
  if (!$registro) {
    return ['ok' => false, 'motivo' => 'sem_codigo'];
  }

  // Bloqueio por excesso de tentativas.
  if ((int)($registro['attempts'] ?? 0) >= ACCOUNT_MAX_TENTATIVAS) {
    store_update(ACCOUNT_TABELA, $registro['id'], ['used_at' => date('c')]);
    return ['ok' => false, 'motivo' => 'bloqueado'];
  }

  if (!password_verify($codigo, (string)($registro['code_hash'] ?? ''))) {
    store_update(ACCOUNT_TABELA, $registro['id'], ['attempts' => (int)($registro['attempts'] ?? 0) + 1]);
    $restantes = ACCOUNT_MAX_TENTATIVAS - ((int)($registro['attempts'] ?? 0) + 1);
    return ['ok' => false, 'motivo' => 'invalido', 'restantes' => max(0, $restantes)];
  }

  // Sucesso: troca a senha, marca código como usado e limpa a flag.
  store_update('terapeutas', $terapeutaId, [
    'senha_hash'           => password_hash($novaSenha, PASSWORD_DEFAULT),
    'must_change_password' => false,
    'senha_trocada_em'     => date('c'),
  ]);
  store_update(ACCOUNT_TABELA, $registro['id'], ['used_at' => date('c')]);
  // Garante que nenhum outro código fique ativo.
  account_invalidar_codigos($terapeutaId);

  return ['ok' => true, 'motivo' => 'trocada'];
}

/**
 * Política de senha: mínimo 8 caracteres, com ao menos uma letra e um número.
 * Retorna true se válida, ou string com a mensagem de erro.
 */
function account_validar_forca_senha(string $senha) {
  if (strlen($senha) < 8) {
    return 'A senha deve ter pelo menos 8 caracteres.';
  }
  if (!preg_match('/[A-Za-zÀ-ÿ]/', $senha) || !preg_match('/\d/', $senha)) {
    return 'A senha deve conter ao menos uma letra e um número.';
  }
  return true;
}

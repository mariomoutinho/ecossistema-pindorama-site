<?php
// ================================
// Lógica idempotente de provisionamento de terapeuta, compartilhada pelo
// script CLI (bin/provisionar-terapeuta.php) e pelo provisionamento web
// (provisionar.php). A senha nunca é impressa/logada — só o hash é gravado.
// ================================

require_once __DIR__ . '/storage.php';

/**
 * Cria ou atualiza um terapeuta de forma idempotente.
 * Retorna ['status' => 'created|updated|noop|error', 'message' => string, 'id' => ?int].
 * - Não duplica (identifica por e-mail).
 * - Por padrão NÃO sobrescreve senha existente; use $reset = true para redefinir.
 * - Sempre marca must_change_password ao definir/redefinir a senha.
 */
function provision_terapeuta(string $nome, string $email, string $senha, bool $reset = false): array {
  $nome  = trim($nome);
  $email = strtolower(trim($email));

  if ($nome === '' || $email === '' || $senha === '') {
    return ['status' => 'error', 'message' => 'Nome, e-mail e senha são obrigatórios.', 'id' => null];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['status' => 'error', 'message' => 'E-mail inválido.', 'id' => null];
  }

  store_bootstrap('terapeutas');
  $existentes = store_where('terapeutas', fn($r) => strtolower((string)($r['email'] ?? '')) === $email);

  if ($existentes) {
    $u = $existentes[0];
    $changes = [];
    if (empty($u['ativo']))           $changes['ativo'] = true;
    if (($u['nome'] ?? '') !== $nome) $changes['nome'] = $nome;
    if (empty($u['senha_hash']) || $reset) {
      $changes['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
      $changes['must_change_password'] = true;
    }
    if ($changes) {
      store_update('terapeutas', $u['id'], $changes);
      $msg = isset($changes['senha_hash'])
        ? 'Terapeuta já existia — atualizado (senha redefinida, troca obrigatória).'
        : 'Terapeuta já existia — dados atualizados.';
      return ['status' => 'updated', 'message' => $msg, 'id' => (int)$u['id']];
    }
    return ['status' => 'noop', 'message' => 'Terapeuta já provisionado — nada a fazer.', 'id' => (int)$u['id']];
  }

  $novo = store_insert('terapeutas', [
    'nome'                 => $nome,
    'email'                => $email,
    'senha_hash'           => password_hash($senha, PASSWORD_DEFAULT),
    'telefone'             => '',
    'especialidade'        => 'Terapeuta',
    'ativo'                => true,
    'papel'                => 'terapeuta',
    'must_change_password' => true,
  ]);
  return ['status' => 'created', 'message' => 'Terapeuta provisionado com sucesso. Senha temporária exige troca no 1º acesso.', 'id' => (int)$novo['id']];
}

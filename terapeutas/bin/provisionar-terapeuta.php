<?php
// ================================
// Provisionamento idempotente do terapeuta inicial.
//
// Lê as credenciais de VARIÁVEIS DE AMBIENTE (nunca do código):
//   INITIAL_THERAPIST_NAME
//   INITIAL_THERAPIST_EMAIL
//   INITIAL_THERAPIST_PASSWORD
//
// Uso (na raiz do repositório):
//   INITIAL_THERAPIST_NAME="Luiz Mario Barros Moutinho" \
//   INITIAL_THERAPIST_EMAIL="luizmariomoutinho1@gmail.com" \
//   INITIAL_THERAPIST_PASSWORD="..." \
//   php terapeutas/bin/provisionar-terapeuta.php
//
// Opções:
//   --reset-password   Redefine a senha de um terapeuta já existente para o
//                      valor do ambiente e marca troca obrigatória.
//
// Idempotente: se o terapeuta já existir (mesmo e-mail), não duplica e, por
// padrão, NÃO sobrescreve a senha existente. A senha em claro nunca é
// impressa, logada ou gravada — apenas o hash é persistido.
// ================================

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit("Este script só pode ser executado pela linha de comando.\n");
}

require_once __DIR__ . '/../lib/env.php';

// Permite apontar o storage para um diretório alternativo (usado pelos testes
// e por execuções pontuais de ops). Sem isso, usa o padrão de data/terapeutas.
$dataDirEnv = getenv('TERAP_DATA_DIR');
if ($dataDirEnv !== false && $dataDirEnv !== '' && !defined('TERAP_DATA_DIR')) {
  define('TERAP_DATA_DIR', $dataDirEnv);
}

require_once __DIR__ . '/../lib/storage.php';

$opts = $argv ?? [];
$resetPassword = in_array('--reset-password', $opts, true);

$nome  = trim((string)terap_env('INITIAL_THERAPIST_NAME', ''));
$email = strtolower(trim((string)terap_env('INITIAL_THERAPIST_EMAIL', '')));
$senha = (string)terap_env('INITIAL_THERAPIST_PASSWORD', '');

$faltando = [];
if ($nome === '')  $faltando[] = 'INITIAL_THERAPIST_NAME';
if ($email === '') $faltando[] = 'INITIAL_THERAPIST_EMAIL';
if ($senha === '') $faltando[] = 'INITIAL_THERAPIST_PASSWORD';
if ($faltando) {
  fwrite(STDERR, "Variáveis de ambiente ausentes: " . implode(', ', $faltando) . "\n");
  exit(2);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  fwrite(STDERR, "INITIAL_THERAPIST_EMAIL inválido.\n");
  exit(2);
}

store_bootstrap('terapeutas');

$existentes = store_where('terapeutas', fn($r) => strtolower((string)($r['email'] ?? '')) === $email);

if ($existentes) {
  $u = $existentes[0];
  $changes = [];
  if (empty($u['ativo']))            $changes['ativo'] = true;
  if (($u['nome'] ?? '') !== $nome)  $changes['nome'] = $nome;

  // Define a senha apenas se não houver hash ainda, OU se --reset-password.
  if (empty($u['senha_hash']) || $resetPassword) {
    $changes['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
    $changes['must_change_password'] = true;
  }

  if ($changes) {
    store_update('terapeutas', $u['id'], $changes);
    $acao = isset($changes['senha_hash']) ? 'atualizado (senha redefinida, troca obrigatória)' : 'atualizado';
    echo "Terapeuta já existia (#{$u['id']}, {$email}) — {$acao}.\n";
  } else {
    echo "Terapeuta já provisionado (#{$u['id']}, {$email}) — nada a fazer.\n";
  }
} else {
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
  echo "Terapeuta provisionado com sucesso (#{$novo['id']}, {$email}). Senha temporária exige troca no 1º acesso.\n";
}

// Higiene: limpa a variável de senha do processo após o uso.
putenv('INITIAL_THERAPIST_PASSWORD');
unset($senha, $_ENV['INITIAL_THERAPIST_PASSWORD'], $_SERVER['INITIAL_THERAPIST_PASSWORD']);

echo "Concluído.\n";
exit(0);

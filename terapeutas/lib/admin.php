<?php
// ================================
// Domínio de GESTÃO DA EQUIPE (perfis administrativos) — spec §4, §5, §6, §11.
//
// Toda a autorização e as regras de segurança vivem AQUI (backend), nunca no
// frontend. As telas (equipe.php) apenas chamam estas funções e exibem o
// resultado. Os usuários são os registros da "tabela" JSON `terapeutas`:
//   id, nome, email, senha_hash, telefone, especialidade, ativo (bool),
//   papel ('admin'|'terapeuta'), must_change_password (bool), criado_em.
//
// Regras de segurança implementadas (spec §4.3):
//   - Só administradores criam contas e concedem/removem o perfil admin.
//   - Um admin não pode desativar a própria conta enquanto autenticado.
//   - O último administrador ATIVO não pode ser rebaixado nem desativado.
//   - E-mail normalizado (minúsculas/trim) e único.
//   - Senhas só como hash; senha temporária exige troca no 1º acesso.
// ================================

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/audit.php';

const ADMIN_TABELA = 'terapeutas';

/** Normaliza e-mail para comparação/armazenamento. */
function admin_normalizar_email(string $email): string {
  return strtolower(trim($email));
}

/** Remove campos sensíveis antes de devolver um usuário à camada de view. */
function admin_sanitizar(array $u): array {
  unset($u['senha_hash']);
  return $u;
}

/** Lista todos os usuários (sem hash), ordenados por nome. */
function admin_listar_usuarios(): array {
  $rows = array_map('admin_sanitizar', store_all(ADMIN_TABELA));
  usort($rows, fn($a, $b) => strcasecmp((string)($a['nome'] ?? ''), (string)($b['nome'] ?? '')));
  return $rows;
}

/** Busca um usuário por id (sem hash). */
function admin_obter_usuario(int $id): ?array {
  $u = store_find(ADMIN_TABELA, $id);
  return $u ? admin_sanitizar($u) : null;
}

/** Quantidade de administradores ATIVOS no sistema. */
function admin_contar_admins_ativos(): int {
  return count(store_where(ADMIN_TABELA, fn($r) =>
    ($r['papel'] ?? 'terapeuta') === 'admin' && !empty($r['ativo'])
  ));
}

/**
 * Este usuário é o ÚLTIMO administrador ativo? Usado para bloquear rebaixar,
 * desativar ou excluir o único admin restante (spec §4.3).
 */
function admin_eh_ultimo_admin_ativo(int $id): bool {
  $u = store_find(ADMIN_TABELA, $id);
  if (!$u || ($u['papel'] ?? '') !== 'admin' || empty($u['ativo'])) return false;
  return admin_contar_admins_ativos() <= 1;
}

/** Existe outro usuário (id diferente) com este e-mail normalizado? */
function admin_email_em_uso(string $email, int $ignorarId = 0): bool {
  $email = admin_normalizar_email($email);
  foreach (store_all(ADMIN_TABELA) as $r) {
    if ((int)($r['id'] ?? 0) === $ignorarId) continue;
    if (admin_normalizar_email((string)($r['email'] ?? '')) === $email) return true;
  }
  return false;
}

/**
 * Valida os campos de um cadastro/edição. $novo indica criação (exige senha).
 * Retorna lista de mensagens de erro (vazia = válido).
 */
function admin_validar(array $d, bool $novo, int $ignorarId = 0): array {
  $erros = [];
  $nome  = trim((string)($d['nome'] ?? ''));
  $email = admin_normalizar_email((string)($d['email'] ?? ''));
  $papel = (string)($d['papel'] ?? '');

  if ($nome === '')                                   $erros[] = 'Informe o nome completo.';
  if ($email === '')                                  $erros[] = 'Informe o e-mail.';
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail em formato inválido.';
  elseif (admin_email_em_uso($email, $ignorarId))     $erros[] = 'Já existe um usuário com este e-mail.';

  if (!auth_papel_valido($papel)) $erros[] = 'Perfil inválido.';

  if ($novo) {
    $senha    = (string)($d['senha'] ?? '');
    $confirma = (string)($d['confirma'] ?? '');
    if ($senha === '')                $erros[] = 'Informe a senha temporária.';
    elseif ($senha !== $confirma)     $erros[] = 'A confirmação não confere com a senha temporária.';
    else {
      $forca = account_validar_forca_senha($senha);
      if ($forca !== true) $erros[] = $forca;
    }
  }

  return $erros;
}

/**
 * Cria um novo usuário interno com senha temporária (troca obrigatória no 1º
 * acesso). Apenas administradores podem chamar (verifique antes na rota).
 * Retorna ['ok'=>bool, 'erros'=>string[], 'id'=>?int].
 */
function admin_criar_usuario(array $ator, array $d): array {
  if (!auth_is_admin($ator)) {
    return ['ok' => false, 'erros' => ['Somente administradores podem criar contas.'], 'id' => null];
  }
  $erros = admin_validar($d, true);
  if ($erros) return ['ok' => false, 'erros' => $erros, 'id' => null];

  $papel = (string)$d['papel'];
  $novo = store_insert(ADMIN_TABELA, [
    'nome'                 => trim((string)$d['nome']),
    'email'                => admin_normalizar_email((string)$d['email']),
    'senha_hash'           => password_hash((string)$d['senha'], PASSWORD_DEFAULT),
    'telefone'             => trim((string)($d['telefone'] ?? '')),
    'especialidade'        => trim((string)($d['especialidade'] ?? '')) ?: auth_papel_label($papel),
    'ativo'                => true,
    'papel'                => $papel,
    'must_change_password' => true,
  ]);

  audit_log('usuario_criado', $ator, $novo, 'perfil: ' . $papel);
  return ['ok' => true, 'erros' => [], 'id' => (int)$novo['id']];
}

/**
 * Atualiza dados cadastrais básicos (nome, e-mail, telefone, especialidade).
 * Não troca papel nem status (use as funções dedicadas). Só admin.
 */
function admin_atualizar_dados(array $ator, int $id, array $d): array {
  if (!auth_is_admin($ator)) {
    return ['ok' => false, 'erros' => ['Sem permissão.']];
  }
  $alvo = store_find(ADMIN_TABELA, $id);
  if (!$alvo) return ['ok' => false, 'erros' => ['Usuário não encontrado.']];

  $erros = admin_validar([
    'nome'  => $d['nome']  ?? '',
    'email' => $d['email'] ?? '',
    'papel' => $alvo['papel'] ?? 'terapeuta', // papel não muda aqui
  ], false, $id);
  if ($erros) return ['ok' => false, 'erros' => $erros];

  store_update(ADMIN_TABELA, $id, [
    'nome'          => trim((string)$d['nome']),
    'email'         => admin_normalizar_email((string)$d['email']),
    'telefone'      => trim((string)($d['telefone'] ?? '')),
    'especialidade' => trim((string)($d['especialidade'] ?? '')),
  ]);
  audit_log('dados_atualizados', $ator, $alvo, '');
  return ['ok' => true, 'erros' => []];
}

/**
 * Concede/remove o perfil de administrador (spec §4.3). Bloqueia rebaixar o
 * último admin ativo. Só admin.
 */
function admin_alterar_papel(array $ator, int $id, string $novoPapel): array {
  if (!auth_is_admin($ator)) {
    return ['ok' => false, 'erros' => ['Somente administradores podem alterar perfis.']];
  }
  if (!auth_papel_valido($novoPapel)) {
    return ['ok' => false, 'erros' => ['Perfil inválido.']];
  }
  $alvo = store_find(ADMIN_TABELA, $id);
  if (!$alvo) return ['ok' => false, 'erros' => ['Usuário não encontrado.']];

  $atual = $alvo['papel'] ?? 'terapeuta';
  if ($atual === $novoPapel) return ['ok' => true, 'erros' => []]; // nada a fazer

  // Rebaixar admin → terapeuta: não pode ser o último admin ativo.
  if ($atual === 'admin' && $novoPapel !== 'admin' && admin_eh_ultimo_admin_ativo($id)) {
    return ['ok' => false, 'erros' => ['Não é possível rebaixar o último administrador ativo.']];
  }

  store_update(ADMIN_TABELA, $id, ['papel' => $novoPapel]);
  audit_log('perfil_alterado', $ator, $alvo, 'papel: ' . $atual . ' → ' . $novoPapel);
  return ['ok' => true, 'erros' => []];
}

/**
 * Ativa/desativa uma conta (spec §4.3). Bloqueia:
 *   - desativar a PRÓPRIA conta enquanto autenticado;
 *   - desativar o ÚLTIMO administrador ativo.
 */
function admin_definir_status(array $ator, int $id, bool $ativo): array {
  if (!auth_is_admin($ator)) {
    return ['ok' => false, 'erros' => ['Sem permissão.']];
  }
  $alvo = store_find(ADMIN_TABELA, $id);
  if (!$alvo) return ['ok' => false, 'erros' => ['Usuário não encontrado.']];

  if (!$ativo) {
    if ((int)$id === (int)($ator['id'] ?? 0)) {
      return ['ok' => false, 'erros' => ['Você não pode desativar a sua própria conta.']];
    }
    if (admin_eh_ultimo_admin_ativo($id)) {
      return ['ok' => false, 'erros' => ['Não é possível desativar o último administrador ativo.']];
    }
  }

  if ((bool)($alvo['ativo'] ?? false) === $ativo) return ['ok' => true, 'erros' => []];

  store_update(ADMIN_TABELA, $id, ['ativo' => $ativo]);
  audit_log($ativo ? 'conta_ativada' : 'conta_desativada', $ator, $alvo, '');
  return ['ok' => true, 'erros' => []];
}

/**
 * Redefine a senha temporária de um usuário e marca troca obrigatória no
 * próximo acesso (spec §5.1). A senha em claro nunca é gravada/logada.
 */
function admin_redefinir_senha_temp(array $ator, int $id, string $senha, string $confirma): array {
  if (!auth_is_admin($ator)) {
    return ['ok' => false, 'erros' => ['Sem permissão.']];
  }
  $alvo = store_find(ADMIN_TABELA, $id);
  if (!$alvo) return ['ok' => false, 'erros' => ['Usuário não encontrado.']];

  if ($senha !== $confirma) return ['ok' => false, 'erros' => ['A confirmação não confere.']];
  $forca = account_validar_forca_senha($senha);
  if ($forca !== true) return ['ok' => false, 'erros' => [$forca]];

  store_update(ADMIN_TABELA, $id, [
    'senha_hash'           => password_hash($senha, PASSWORD_DEFAULT),
    'must_change_password' => true,
  ]);
  // Invalida códigos de troca por e-mail pendentes do alvo, por higiene.
  if (function_exists('account_invalidar_codigos')) {
    account_invalidar_codigos($id);
  }
  audit_log('senha_temp_redefinida', $ator, $alvo, '');
  return ['ok' => true, 'erros' => []];
}

/**
 * Garante, de forma idempotente, que o administrador inicial exista com perfil
 * 'admin' e ativo. Procura pelo e-mail normalizado; se o usuário já existe,
 * apenas promove/ativa o que for necessário (preserva os demais dados e a
 * senha). NÃO cria senha aqui — a senha inicial vem do provisionamento
 * (bin/provisionar-terapeuta.php) ou do fluxo de redefinição.
 *
 * Retorna 'created'|'promoted'|'noop'. (Criação só ocorre se uma senha temporária
 * for fornecida via $senhaInicial — caso contrário, não cria sem senha.)
 */
function admin_garantir_admin_inicial(string $nome, string $email, ?string $senhaInicial = null): string {
  $email = admin_normalizar_email($email);
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'noop';

  $existentes = store_where(ADMIN_TABELA, fn($r) =>
    admin_normalizar_email((string)($r['email'] ?? '')) === $email);

  if ($existentes) {
    $u = $existentes[0];
    $changes = [];
    if (($u['papel'] ?? '') !== 'admin') $changes['papel'] = 'admin';
    if (empty($u['ativo']))              $changes['ativo'] = true;
    if (!$changes) return 'noop';
    store_update(ADMIN_TABELA, $u['id'], $changes);
    return 'promoted';
  }

  // Sem registro: só cria se houver senha temporária (não gravamos conta sem hash).
  if ($senhaInicial === null || $senhaInicial === '') return 'noop';
  store_insert(ADMIN_TABELA, [
    'nome'                 => trim($nome) !== '' ? trim($nome) : 'Administrador',
    'email'                => $email,
    'senha_hash'           => password_hash($senhaInicial, PASSWORD_DEFAULT),
    'telefone'             => '',
    'especialidade'        => 'Coordenação',
    'ativo'                => true,
    'papel'                => 'admin',
    'must_change_password' => true,
  ]);
  return 'created';
}

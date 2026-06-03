<?php
// ================================
// Auditoria mínima de ações administrativas (spec §12).
//
// Tabela JSON: data/terapeutas/auditoria.json
//   id, acao, ator_id, ator_nome, alvo_id, alvo_email, detalhe, criado_em
//
// NUNCA registra senhas, hashes ou códigos — apenas o tipo de ação e quem a
// executou sobre quem. Pensada para ser leve: registrar é "best effort" e nunca
// deve interromper o fluxo principal se a escrita falhar.
// ================================

require_once __DIR__ . '/storage.php';

const AUDIT_TABELA = 'auditoria';

// Ações reconhecidas (rótulos para exibição futura).
const AUDIT_ACOES = [
  'usuario_criado'        => 'Usuário criado',
  'perfil_alterado'       => 'Perfil alterado',
  'conta_ativada'         => 'Conta ativada',
  'conta_desativada'      => 'Conta desativada',
  'senha_temp_redefinida' => 'Senha temporária redefinida',
  'dados_atualizados'     => 'Dados cadastrais atualizados',
  'agendamento_admin'     => 'Agendamento alterado por administrador',
];

function audit_acao_label(string $acao): string {
  return AUDIT_ACOES[$acao] ?? $acao;
}

/**
 * Registra uma ação administrativa. $detalhe deve ser texto curto e sem dados
 * sensíveis (ex.: "papel: terapeuta → admin"). Falhas de escrita são engolidas
 * de propósito — auditar não pode quebrar a operação que está auditando.
 */
function audit_log(string $acao, ?array $ator, ?array $alvo = null, string $detalhe = ''): void {
  try {
    store_insert(AUDIT_TABELA, [
      'acao'       => $acao,
      'ator_id'    => $ator['id'] ?? null,
      'ator_nome'  => $ator['nome'] ?? '—',
      'alvo_id'    => $alvo['id'] ?? null,
      'alvo_email' => $alvo['email'] ?? null,
      'detalhe'    => $detalhe,
    ]);
  } catch (\Throwable $e) {
    error_log('[audit] falha ao registrar ação "' . $acao . '"');
  }
}

/** Últimos N registros de auditoria, mais recentes primeiro. */
function audit_recentes(int $limite = 50): array {
  $rows = store_all(AUDIT_TABELA);
  usort($rows, fn($a, $b) => strcmp((string)($b['criado_em'] ?? ''), (string)($a['criado_em'] ?? '')));
  return array_slice($rows, 0, max(0, $limite));
}

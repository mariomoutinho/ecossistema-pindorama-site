<?php
// ================================
// Suíte de testes da área restrita (CLI, sem dependências externas).
// Usa um diretório de dados TEMPORÁRIO — nunca toca data/terapeutas real.
//
//   php terapeutas/tests/run.php
// ================================

if (PHP_SAPI !== 'cli') { exit("Somente CLI.\n"); }

error_reporting(E_ALL & ~E_DEPRECATED);

// ---- Ambiente isolado ----
$TMP = sys_get_temp_dir() . '/pind_terap_tests_' . getmypid();
@mkdir($TMP, 0775, true);
define('TERAP_DATA_DIR', $TMP);

require_once __DIR__ . '/../lib/env.php';
// Dispara o carregamento de config (uma vez) e então força transporte de log,
// garantindo que nenhum e-mail real seja enviado durante os testes.
terap_env('__INIT__');
putenv('TERAP_MAIL_TRANSPORT=log');
putenv('TERAP_MAIL_FROM=teste@local');

require_once __DIR__ . '/../lib/storage.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/pacientes.php';

// ---- Mini framework ----
$TESTS = ['pass' => 0, 'fail' => 0, 'fails' => []];
function ok(bool $cond, string $msg): void {
  global $TESTS;
  if ($cond) { $TESTS['pass']++; }
  else { $TESTS['fail']++; $TESTS['fails'][] = $msg; echo "  ✗ $msg\n"; }
}
function eq($a, $b, string $msg): void { ok($a === $b, $msg . " (esperado=" . var_export($b, true) . ", obtido=" . var_export($a, true) . ")"); }
function section(string $t): void { echo "\n== $t ==\n"; }

function limpar_tabelas(array $tabelas): void {
  foreach ($tabelas as $t) store_save($t, []);
}

// =====================================================================
section('Provisionamento idempotente (script real, subprocesso)');
$scriptPath = realpath(__DIR__ . '/../bin/provisionar-terapeuta.php');
$envBase = [
  'TERAP_DATA_DIR'            => $TMP,
  'INITIAL_THERAPIST_NAME'    => 'Luiz Mario Barros Moutinho',
  'INITIAL_THERAPIST_EMAIL'   => 'luiz.teste@example.com',
  'INITIAL_THERAPIST_PASSWORD'=> '@Senha123Temp',
  'PATH'                      => getenv('PATH'),
];
function rodar_provision(array $env, string $scriptPath): string {
  $cmd = 'php ' . escapeshellarg($scriptPath);
  $descr = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $p = proc_open($cmd, $descr, $pipes, null, $env);
  $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
  return $out;
}
$out1 = rodar_provision($envBase, $scriptPath);
ok(stripos($out1, 'provisionado') !== false, 'primeira execução cria o terapeuta');
$out2 = rodar_provision($envBase, $scriptPath);
ok(stripos($out2, 'nada a fazer') !== false, 'segunda execução é idempotente (não duplica)');

$terapeutas = store_where('terapeutas', fn($r) => strtolower($r['email'] ?? '') === 'luiz.teste@example.com');
eq(count($terapeutas), 1, 'existe exatamente 1 terapeuta provisionado');
$luiz = $terapeutas[0];
ok(!empty($luiz['senha_hash']), 'terapeuta possui hash de senha');
ok(($luiz['senha_hash'] ?? '') !== '@Senha123Temp', 'senha NUNCA é salva em texto puro');
ok(password_verify('@Senha123Temp', $luiz['senha_hash']), 'hash confere com a senha temporária');
ok(!empty($luiz['must_change_password']), 'flag must_change_password está marcada');

// =====================================================================
section('Autenticação (verificação de senha)');
ok(password_verify('@Senha123Temp', $luiz['senha_hash']), 'login com senha válida confere');
ok(!password_verify('senha-errada', $luiz['senha_hash']), 'senha inválida é rejeitada');

// =====================================================================
section('Códigos de troca de senha');
limpar_tabelas(['codigos_senha']);
$tid = (int)$luiz['id'];

// Solicitar (transporte log -> devolve o código em DEV)
$dev = null;
$r = account_solicitar_codigo($tid, $dev);
ok(!empty($r['ok']), 'solicitação de código retorna ok');
ok(is_string($dev) && preg_match('/^\d{6}$/', $dev) === 1, 'código tem 6 dígitos');

// E-mail foi "enviado" (gravado como .eml no modo log)
$emls = glob($TMP . '/_mail/*.eml');
ok(is_array($emls) && count($emls) >= 1, 'e-mail do código foi gerado (modo log)');

// Reenvio imediato é barrado pelo intervalo mínimo
$r2 = account_solicitar_codigo($tid, $dev2);
eq($r2['motivo'] ?? '', 'aguarde', 'reenvio imediato respeita intervalo mínimo');

// Código inválido é rejeitado e conta tentativa
$errado = str_pad((string)((intval($dev) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
$ri = account_trocar_senha($tid, $errado, 'NovaSenha123', 'NovaSenha123');
eq($ri['motivo'] ?? '', 'invalido', 'código incorreto é rejeitado');

// Senha fraca é barrada antes de consumir o código
$rf = account_trocar_senha($tid, $dev, 'abc', 'abc');
eq($rf['motivo'] ?? '', 'senha_fraca', 'senha fraca é rejeitada');

// Confirmação divergente
$rc = account_trocar_senha($tid, $dev, 'NovaSenha123', 'Outra123');
eq($rc['motivo'] ?? '', 'confirma', 'confirmação divergente é rejeitada');

// Troca correta
$rok = account_trocar_senha($tid, $dev, 'NovaSenha123', 'NovaSenha123');
ok(!empty($rok['ok']), 'troca de senha com código válido funciona');

$luizAtual = store_find('terapeutas', $tid);
ok(password_verify('NovaSenha123', $luizAtual['senha_hash']), 'login funciona com a nova senha');
ok(!password_verify('@Senha123Temp', $luizAtual['senha_hash']), 'senha antiga é rejeitada após troca');
ok(empty($luizAtual['must_change_password']), 'flag de troca obrigatória é removida');

// Código não pode ser reutilizado
$rreuse = account_trocar_senha($tid, $dev, 'OutraNova123', 'OutraNova123');
eq($rreuse['motivo'] ?? '', 'sem_codigo', 'código usado não pode ser reutilizado');

// Expiração
limpar_tabelas(['codigos_senha']);
store_insert('codigos_senha', [
  'terapeuta_id' => $tid,
  'code_hash'    => password_hash('111111', PASSWORD_DEFAULT),
  'expires_at'   => date('c', time() - 60),
  'used_at'      => null,
  'attempts'     => 0,
]);
$rexp = account_trocar_senha($tid, '111111', 'NovaSenha123', 'NovaSenha123');
eq($rexp['motivo'] ?? '', 'sem_codigo', 'código expirado não é aceito');

// Limite de tentativas -> bloqueio
limpar_tabelas(['codigos_senha']);
$dev3 = null;
account_solicitar_codigo($tid, $dev3);
$errado3 = str_pad((string)((intval($dev3) + 7) % 1000000), 6, '0', STR_PAD_LEFT);
for ($i = 0; $i < ACCOUNT_MAX_TENTATIVAS; $i++) {
  account_trocar_senha($tid, $errado3, 'NovaSenha123', 'NovaSenha123');
}
$rblock = account_trocar_senha($tid, $dev3, 'NovaSenha123', 'NovaSenha123');
eq($rblock['motivo'] ?? '', 'bloqueado', 'excesso de tentativas bloqueia o código');

// =====================================================================
section('Pacientes — idade');
eq(pac_idade('2000-01-01'), (int)(new DateTime('2000-01-01'))->diff(new DateTime('today'))->y, 'idade calculada pela data de nascimento');
eq(pac_idade(date('Y-m-d', strtotime('+1 day'))), null, 'data futura não gera idade');
eq(pac_idade('data-invalida'), null, 'data inválida não gera idade');

// =====================================================================
section('Pacientes — validação');
$errosV = pac_validar(pac_montar_do_post(['nome_completo' => '']));
ok(!empty($errosV), 'nome completo obrigatório');
$errosV = pac_validar(pac_montar_do_post(['nome_completo' => 'X', 'data_nascimento' => date('Y-m-d', strtotime('+2 day'))]));
ok(!empty($errosV), 'nascimento futuro é inválido');
$errosV = pac_validar(pac_montar_do_post(['nome_completo' => 'X', 'email' => 'naoé-email']));
ok(!empty($errosV), 'e-mail inválido é barrado');
$errosV = pac_validar(pac_montar_do_post(['nome_completo' => 'Maria', 'email' => 'maria@ex.com', 'data_nascimento' => '1990-05-10']));
eq($errosV, [], 'dados válidos passam sem erro');

// =====================================================================
section('Pacientes — CRUD, busca, isolamento, paginação');
limpar_tabelas(['pacientes', 'evolucoes', 'agendamentos']);

$T1 = 101; $T2 = 202;
function novo_pac(int $terap, array $over = []): array {
  $base = pac_montar_do_post(array_merge([
    'nome_completo' => 'Paciente Base',
    'telefone'      => '81 99999-0000',
    'email'         => 'base@ex.com',
  ], $over));
  $base['terapeuta_id'] = $terap;
  return store_insert('pacientes', $base);
}
$p1 = novo_pac($T1, ['nome_completo' => 'Ana Clara Souza', 'telefone' => '81 98888-1111', 'email' => 'ana@ex.com']);
$p2 = novo_pac($T1, ['nome_completo' => 'Bruno Lima', 'nome_social' => 'Bê', 'whatsapp' => '81 97777-2222', 'email' => 'bruno@ex.com']);
$pOutro = novo_pac($T2, ['nome_completo' => 'Carlos do Outro Terapeuta']);

// Isolamento
ok(pac_find_do_terapeuta($p1['id'], $T1) !== null, 'dono acessa o próprio paciente');
ok(pac_find_do_terapeuta($p1['id'], $T2) === null, 'terapeuta não acessa paciente de outro');
ok(pac_find_do_terapeuta($pOutro['id'], $T1) === null, 'isolamento vale nos dois sentidos');

// Busca por nome
$rb = pac_listar($T1, 'ana', 'ativos');
eq($rb['total'], 1, 'busca por nome encontra Ana');
// Busca por nome social
$rb = pac_listar($T1, 'bê', 'ativos');
eq($rb['total'], 1, 'busca por nome social encontra Bruno (Bê)');
// Busca por telefone (só dígitos)
$rb = pac_listar($T1, '988881111', 'ativos');
eq($rb['total'], 1, 'busca por telefone encontra Ana');
// Busca por e-mail
$rb = pac_listar($T1, 'bruno@ex', 'ativos');
eq($rb['total'], 1, 'busca por e-mail encontra Bruno');
// Busca não vaza paciente de outro terapeuta
$rb = pac_listar($T1, 'Carlos', 'ativos');
eq($rb['total'], 0, 'busca não retorna paciente de outro terapeuta');

// Inativação preserva histórico
store_update('pacientes', $p2['id'], ['status' => 'inativo']);
$rA = pac_listar($T1, '', 'ativos');
$rI = pac_listar($T1, '', 'inativos');
eq($rA['total'], 1, 'inativo sai da lista de ativos');
eq($rI['total'], 1, 'inativo aparece na lista de inativos');
ok(store_find('pacientes', $p2['id']) !== null, 'inativação não apaga o registro');

// Paginação
for ($i = 0; $i < 15; $i++) novo_pac($T1, ['nome_completo' => sprintf('Lote %02d', $i)]);
$rp = pac_listar($T1, '', 'todos', 1);
eq($rp['pagina'], 1, 'paginação: página 1');
ok($rp['paginas'] >= 2, 'paginação: mais de uma página');
ok(count($rp['itens']) <= PAC_POR_PAGINA, 'paginação: respeita itens por página');

// =====================================================================
section('Evoluções — vínculo, ordenação, isolamento, soft-delete');
limpar_tabelas(['evolucoes']);
store_insert('evolucoes', ['terapeuta_id' => $T1, 'paciente_id' => (int)$p1['id'], 'data' => '2026-01-10', 'descricao' => 'antiga', 'status' => 'ativo']);
store_insert('evolucoes', ['terapeuta_id' => $T1, 'paciente_id' => (int)$p1['id'], 'data' => '2026-03-20', 'descricao' => 'recente', 'status' => 'ativo']);
store_insert('evolucoes', ['terapeuta_id' => $T2, 'paciente_id' => (int)$pOutro['id'], 'data' => '2026-02-01', 'descricao' => 'de outro', 'status' => 'ativo']);

$evs = store_where('evolucoes', fn($r) => (int)($r['paciente_id'] ?? 0) === (int)$p1['id'] && (int)($r['terapeuta_id'] ?? 0) === $T1);
eq(count($evs), 2, 'evoluções do paciente são listadas');
usort($evs, fn($a, $b) => strcmp($b['data'], $a['data']));
eq($evs[0]['descricao'], 'recente', 'evoluções ordenadas do mais recente para o mais antigo');

$evsOutro = store_where('evolucoes', fn($r) => (int)($r['paciente_id'] ?? 0) === (int)$p1['id'] && (int)($r['terapeuta_id'] ?? 0) === $T2);
eq(count($evsOutro), 0, 'terapeuta não vê evoluções de paciente alheio');

$alvo = $evs[0]['id'];
store_update('evolucoes', $alvo, ['status' => 'inativo']);
ok(store_find('evolucoes', $alvo) !== null, 'inativar evolução preserva o registro');
eq(store_find('evolucoes', $alvo)['status'], 'inativo', 'evolução marcada como inativa');

// =====================================================================
section('Agendamentos — vínculo com paciente e compatibilidade');
limpar_tabelas(['agendamentos']);
// Antigo, sem paciente_id
$antigo = store_insert('agendamentos', ['data' => '2026-01-05', 'hora_inicio' => '09:00', 'hora_fim' => '10:00', 'sala' => 'sala-1', 'terapeuta_id' => $T1, 'paciente' => 'Nome Livre', 'status' => 'agendado']);
ok((int)($antigo['paciente_id'] ?? 0) === 0, 'agendamento antigo sem paciente_id continua válido');
// Novo, com paciente_id
$novoAg = store_insert('agendamentos', ['data' => '2026-04-05', 'hora_inicio' => '09:00', 'hora_fim' => '10:00', 'sala' => 'sala-1', 'terapeuta_id' => $T1, 'paciente' => pac_nome_exibicao($p1), 'paciente_id' => (int)$p1['id'], 'status' => 'agendado']);
eq((int)$novoAg['paciente_id'], (int)$p1['id'], 'agendamento novo registra vínculo com paciente');

// Autocomplete só mostra ativos do terapeuta
$ac = pac_listar($T1, '', 'ativos');
$idsAtivos = array_column($ac['itens'], 'id');
ok(!in_array((int)$pOutro['id'], $idsAtivos, true), 'autocomplete não inclui paciente de outro terapeuta');
ok(!in_array((int)$p2['id'], $idsAtivos, true), 'autocomplete não inclui paciente inativo');

// =====================================================================
// Limpeza
array_map('unlink', glob($TMP . '/*.json') ?: []);
array_map('unlink', glob($TMP . '/_mail/*.eml') ?: []);
@rmdir($TMP . '/_mail');
@rmdir($TMP);

echo "\n----------------------------------------\n";
echo "Resultado: {$TESTS['pass']} passou(aram), {$TESTS['fail']} falhou(aram).\n";
if ($TESTS['fail'] > 0) {
  echo "Falhas:\n - " . implode("\n - ", $TESTS['fails']) . "\n";
  exit(1);
}
echo "Todos os testes passaram.\n";
exit(0);

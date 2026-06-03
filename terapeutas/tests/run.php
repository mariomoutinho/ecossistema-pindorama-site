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
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/account.php';
require_once __DIR__ . '/../lib/pacientes.php';
require_once __DIR__ . '/../lib/agendamentos.php';
require_once __DIR__ . '/../lib/pacotes.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/admin.php';

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
section('Agendamentos — propriedade (Fase 1)');
limpar_tabelas(['agendamentos']);
store_save('terapeutas', [
  ['id' => 1,  'nome' => 'Admin',       'email' => 'a@x',  'papel' => 'admin',     'ativo' => true],
  ['id' => 10, 'nome' => 'Terapeuta A', 'email' => 'ta@x', 'papel' => 'terapeuta', 'ativo' => true],
  ['id' => 20, 'nome' => 'Terapeuta B', 'email' => 'tb@x', 'papel' => 'terapeuta', 'ativo' => true],
]);
$admin = store_find('terapeutas', 1);
$tA    = store_find('terapeutas', 10);
$tB    = store_find('terapeutas', 20);
$agA = store_insert('agendamentos', ['data' => '2026-07-01', 'hora_inicio' => '09:00', 'hora_fim' => '10:00', 'sala' => 'sala-1', 'terapeuta_id' => 10, 'paciente' => 'X', 'status' => 'agendado']);

ok(agenda_is_admin($admin), 'admin é reconhecido como admin');
ok(!agenda_is_admin($tA), 'terapeuta comum não é admin');
ok(agenda_pode_gerir($agA, $tA), 'dono pode gerir o próprio agendamento');
ok(!agenda_pode_gerir($agA, $tB), 'terapeuta não gere agendamento de outro');
ok(agenda_pode_gerir($agA, $admin), 'admin gere agendamento de qualquer um');
ok(!agenda_pode_gerir(null, $tA), 'agendamento inexistente não é gerível');

eq(agenda_resolver_terapeuta_id($tA, 20), 10, 'não-admin: dono forçado ao próprio (ignora terapeuta_id do form)');
eq(agenda_resolver_terapeuta_id($tA, 10, $agA), 10, 'não-admin em edição: preserva o dono original');
eq(agenda_resolver_terapeuta_id($admin, 20), 20, 'admin: atribui ao terapeuta ativo enviado');
eq(agenda_resolver_terapeuta_id($admin, 999), 1, 'admin: id inválido cai no próprio');

// =====================================================================
section('Pacotes — ledger, saldo e idempotência (Fase 2)');
limpar_tabelas(['pacotes', 'pacote_movimentacoes', 'agendamentos']);
$TPpac = 501;
$PACpac = 700;

$rc = pacote_criar(['paciente_id' => $PACpac, 'terapeuta_id' => $TPpac, 'nome' => 'Massoterapia 10', 'terapia' => 'Massoterapia', 'total_sessoes' => 10]);
ok(!empty($rc['ok']), 'cria pacote de 10 sessões');
$pid = (int)$rc['pacote']['id'];
eq(pacote_disponivel($pid), 10, 'compra: disponível = total (10)');

// Reserva + idempotência
$ag1 = 9101;
ok(pacote_reservar($pid, $ag1, $TPpac)['ok'], 'reserva ok');
eq(pacote_disponivel($pid), 9, 'após 1 reserva: 9');
$dup = pacote_reservar($pid, $ag1, $TPpac);
ok(!empty($dup['ja']), 'reserva do mesmo agendamento é idempotente');
eq(pacote_disponivel($pid), 9, 'reserva repetida não muda saldo (9)');

// Reagendar (mesma reserva ativa) não reconsome
$re = pacote_reservar($pid, $ag1, $TPpac);
ok(!empty($re['ja']), 'reagendar não reconsome (idempotente)');
eq(pacote_disponivel($pid), 9, 'reagendar mantém saldo (9)');

// Realizada não altera disponível, idempotente
pacote_marcar_realizada($pid, $ag1, $TPpac);
eq(pacote_disponivel($pid), 9, 'realizada não altera disponível (9)');
pacote_marcar_realizada($pid, $ag1, $TPpac);
eq(pacote_conta_mov($pid, $ag1, 'realizada'), 1, 'realizada é idempotente (1 movimento)');

// Não devolve sessão já realizada
$devR = pacote_devolver($pid, $ag1, $TPpac);
ok(!empty($devR['ja']), 'não devolve sessão já realizada');
eq(pacote_disponivel($pid), 9, 'saldo intacto após tentar devolver realizada (9)');

// Cancelamento devolve (agendamento ainda em aberto)
$ag2 = 9102;
pacote_reservar($pid, $ag2, $TPpac);
eq(pacote_disponivel($pid), 8, 'após 2ª reserva: 8');
$dev = pacote_devolver($pid, $ag2, $TPpac);
ok($dev['ok'] && empty($dev['ja']), 'cancelamento devolve a sessão');
eq(pacote_disponivel($pid), 9, 'devolução: +1 -> 9');
ok(!empty(pacote_devolver($pid, $ag2, $TPpac)['ja']), 'devolução é idempotente');
eq(pacote_disponivel($pid), 9, 'devolução repetida não muda saldo (9)');

// Reativar após cancelar reconsome
$reAtiva = pacote_reservar($pid, $ag2, $TPpac);
ok($reAtiva['ok'] && empty($reAtiva['ja']), 'reativar reconsome a sessão');
eq(pacote_disponivel($pid), 8, 'reativação consome de novo (8)');

// Falta com devolução (+1) e com consumo (0)
$ag3 = 9103; pacote_reservar($pid, $ag3, $TPpac);          // 7
eq(pacote_disponivel($pid), 7, 'reserva ag3 -> 7');
pacote_falta($pid, $ag3, $TPpac, false, 'paciente avisou');// devolve -> 8
eq(pacote_disponivel($pid), 8, 'falta com devolução -> 8');
$ag4 = 9104; pacote_reservar($pid, $ag4, $TPpac);          // 7
pacote_falta($pid, $ag4, $TPpac, true, 'falta sem aviso'); // consumo -> 7
eq(pacote_disponivel($pid), 7, 'falta com consumo mantém saldo (7)');
ok(!pacote_sessao_aberta($pid, $ag4), 'sessão consumida por falta não fica em aberto');

// Ajuste manual: motivo obrigatório, sem negativo
ok(!pacote_ajuste_manual($pid, $TPpac, 0, 'x')['ok'], 'ajuste com delta 0 é barrado');
ok(!pacote_ajuste_manual($pid, $TPpac, 2, '')['ok'], 'ajuste sem motivo é barrado');
ok(pacote_ajuste_manual($pid, $TPpac, 3, 'Bônus de fidelidade')['ok'], 'ajuste +3 com motivo ok');
eq(pacote_disponivel($pid), 10, 'ajuste manual +3 -> 10');
ok(!pacote_ajuste_manual($pid, $TPpac, -50, 'tentativa')['ok'], 'ajuste que deixaria negativo é barrado');
eq(pacote_disponivel($pid), 10, 'saldo intacto após ajuste inválido (10)');

// Esgotar e bloquear reserva sem saldo
limpar_tabelas(['pacotes', 'pacote_movimentacoes']);
$rc2 = pacote_criar(['paciente_id' => $PACpac, 'terapeuta_id' => $TPpac, 'nome' => 'Mini 1', 'terapia' => 'Massoterapia', 'total_sessoes' => 1]);
$pid2 = (int)$rc2['pacote']['id'];
ok(pacote_reservar($pid2, 1, $TPpac)['ok'], 'reserva única ok');
$semSaldo = pacote_reservar($pid2, 2, $TPpac);
ok(empty($semSaldo['ok']), 'reserva sem saldo é bloqueada (não-negativo)');
eq(pacote_disponivel($pid2), 0, 'saldo nunca fica negativo (0)');

// Isolamento por terapeuta
ok(pacote_find_do_terapeuta($pid2, $TPpac) !== null, 'dono acessa o próprio pacote');
ok(pacote_find_do_terapeuta($pid2, 999) === null, 'outro terapeuta não acessa o pacote');

// saldo_apos do ledger é coerente
$movs = pacote_movimentacoes($pid2);
eq((int)$movs[0]['saldo_apos'], 1, 'compra grava saldo_apos = 1');
eq((int)end($movs)['saldo_apos'], 0, 'reserva grava saldo_apos = 0');

// Resumo derivado de agendamentos vinculados
limpar_tabelas(['pacotes', 'pacote_movimentacoes', 'agendamentos']);
$rc3 = pacote_criar(['paciente_id' => $PACpac, 'terapeuta_id' => $TPpac, 'nome' => 'P', 'terapia' => 'Massoterapia', 'total_sessoes' => 10]);
$pid3 = (int)$rc3['pacote']['id'];
$a1 = store_insert('agendamentos', ['paciente_package_id' => $pid3, 'status' => 'realizado', 'terapeuta_id' => $TPpac]);
$a2 = store_insert('agendamentos', ['paciente_package_id' => $pid3, 'status' => 'agendado',  'terapeuta_id' => $TPpac]);
$a3 = store_insert('agendamentos', ['paciente_package_id' => $pid3, 'status' => 'agendado',  'terapeuta_id' => $TPpac]);
pacote_reservar($pid3, (int)$a2['id'], $TPpac);
pacote_reservar($pid3, (int)$a3['id'], $TPpac);
$resumo = pacote_resumo(store_find('pacotes', $pid3));
eq($resumo['realizadas'], 1, 'resumo: 1 realizada');
eq($resumo['agendadas'], 2, 'resumo: 2 agendadas');
eq($resumo['disponivel'], 8, 'resumo: 8 disponíveis (10 - 2 reservas)');

// =====================================================================
section('Pacote do Ronaldo — provisionamento idempotente (script real)');
limpar_tabelas(['pacientes', 'pacotes', 'pacote_movimentacoes', 'agendamentos']);
$scriptR = realpath(__DIR__ . '/../bin/provisionar-pacote-ronaldo.php');
function rodar_ronaldo(string $tmp, string $script, array $args = []): array {
  $cmd = 'php ' . escapeshellarg($script);
  foreach ($args as $a) $cmd .= ' ' . escapeshellarg($a);
  $descr = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $p = proc_open($cmd, $descr, $pipes, null, ['TERAP_DATA_DIR' => $tmp, 'PATH' => getenv('PATH')]);
  $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($p);
  return ['out' => $out . $err, 'code' => $code];
}
$r0 = rodar_ronaldo($TMP, $scriptR);
eq($r0['code'], 1, 'sem Ronaldo cadastrado: script falha (exit 1)');

$ron = store_insert('pacientes', ['nome_completo' => 'Ronaldo Teste', 'terapeuta_id' => 77, 'status' => 'ativo']);
$r1 = rodar_ronaldo($TMP, $scriptR, ['--aplicar']);
eq($r1['code'], 0, 'cria pacote do Ronaldo (exit 0)');
$pkR = store_where('pacotes', fn($p) => (int)$p['paciente_id'] === (int)$ron['id']);
eq(count($pkR), 1, '1 pacote criado para o Ronaldo');
eq((int)$pkR[0]['total_sessoes'], 10, 'pacote com 10 sessões');
eq(pacote_disponivel((int)$pkR[0]['id']), 10, 'saldo 10 disponível');

$r2 = rodar_ronaldo($TMP, $scriptR, ['--aplicar']);
ok(stripos($r2['out'], 'idempotente') !== false || stripos($r2['out'], 'nada a fazer') !== false, 'reexecução é idempotente');
eq(count(store_where('pacotes', fn($p) => (int)$p['paciente_id'] === (int)$ron['id'])), 1, 'não duplica o pacote');

store_insert('pacientes', ['nome_completo' => 'Ronaldo Segundo', 'terapeuta_id' => 77, 'status' => 'ativo']);
$r3 = rodar_ronaldo($TMP, $scriptR, ['--aplicar']);
eq($r3['code'], 2, 'múltiplos Ronaldos: exige desambiguação (exit 2)');

// =====================================================================
section('Agenda — status estendidos e sessão no pacote (Fase 3)');
eq(agenda_status_label('confirmado'), 'Confirmado', 'rótulo de status: confirmado');
eq(agenda_status_label('falta'), 'Falta', 'rótulo de status: falta');
eq(agenda_status_label('reagendado'), 'Reagendado', 'rótulo de status: reagendado');
ok(agenda_status_ativo('confirmado'), 'confirmado ocupa a sessão');
ok(!agenda_status_ativo('cancelado'), 'cancelado não ocupa');
ok(!agenda_status_ativo('falta'), 'falta não é status ativo');

limpar_tabelas(['pacotes', 'pacote_movimentacoes', 'agendamentos']);
$rk = pacote_criar(['paciente_id' => 700, 'terapeuta_id' => 501, 'nome' => 'P', 'terapia' => 'M', 'total_sessoes' => 10]);
$pkid = (int)$rk['pacote']['id'];
$x1 = store_insert('agendamentos', ['paciente_package_id' => $pkid, 'data' => '2026-08-01', 'hora_inicio' => '09:00', 'status' => 'agendado']);
$x2 = store_insert('agendamentos', ['paciente_package_id' => $pkid, 'data' => '2026-08-08', 'hora_inicio' => '09:00', 'status' => 'agendado']);
$x3 = store_insert('agendamentos', ['paciente_package_id' => $pkid, 'data' => '2026-08-15', 'hora_inicio' => '09:00', 'status' => 'agendado']);
$pos2 = agenda_sessao_no_pacote(store_find('agendamentos', (int)$x2['id']));
eq($pos2['n'], 2, 'sessão do meio é a 2ª');
eq($pos2['m'], 10, 'total de sessões = 10');
eq(agenda_sessao_no_pacote(store_find('agendamentos', (int)$x3['id']))['n'], 3, 'última é a 3ª');
store_update('agendamentos', (int)$x1['id'], ['status' => 'cancelado']);
eq(agenda_sessao_no_pacote(store_find('agendamentos', (int)$x3['id']))['n'], 2, 'cancelado sai da contagem (3ª vira 2ª)');
ok(agenda_sessao_no_pacote(['paciente_package_id' => 0, 'id' => 1]) === null, 'sem pacote: retorna null');

// =====================================================================
section('Perfis e autorização administrativa');
eq(auth_papel_label('admin'), 'Administrador', 'rótulo do perfil admin');
eq(auth_papel_label('terapeuta'), 'Terapeuta', 'rótulo do perfil terapeuta');
eq(auth_papel_label('qualquer'), 'Terapeuta', 'perfil desconhecido cai em Terapeuta');
ok(auth_papel_valido('admin') && auth_papel_valido('terapeuta'), 'perfis válidos aceitos');
ok(!auth_papel_valido('root'), 'perfil inválido rejeitado');
ok(auth_is_admin(['papel' => 'admin']), 'auth_is_admin reconhece admin');
ok(!auth_is_admin(['papel' => 'terapeuta']), 'auth_is_admin nega terapeuta');
ok(!auth_is_admin(null), 'auth_is_admin nega nulo');

section('Gestão da equipe — criação, validação e regras de segurança');
limpar_tabelas(['terapeutas', 'auditoria', 'codigos_senha']);
$admin = store_insert('terapeutas', ['nome' => 'Coordenação', 'email' => 'coord@x.com', 'senha_hash' => password_hash('Coord123', PASSWORD_DEFAULT), 'ativo' => true, 'papel' => 'admin']);
$terap = store_insert('terapeutas', ['nome' => 'Terap', 'email' => 'terap@x.com', 'senha_hash' => password_hash('Terap123', PASSWORD_DEFAULT), 'ativo' => true, 'papel' => 'terapeuta']);

// Terapeuta não pode criar contas.
$rNeg = admin_criar_usuario($terap, ['nome' => 'X', 'email' => 'x@x.com', 'papel' => 'terapeuta', 'senha' => 'Abcd1234', 'confirma' => 'Abcd1234']);
ok(!$rNeg['ok'], 'terapeuta não cria usuários');

// Admin cria terapeuta com senha temporária (must_change_password).
$rOk = admin_criar_usuario($admin, ['nome' => 'Nova Terapeuta', 'email' => 'NOVA@x.com ', 'papel' => 'terapeuta', 'senha' => 'Abcd1234', 'confirma' => 'Abcd1234']);
ok($rOk['ok'], 'admin cria usuário com senha temporária');
$nova = store_find('terapeutas', $rOk['id']);
eq($nova['email'], 'nova@x.com', 'e-mail normalizado (minúsculas/trim)');
ok(!empty($nova['must_change_password']), 'novo usuário exige troca de senha');
ok($nova['senha_hash'] !== 'Abcd1234', 'senha guardada como hash, nunca em claro');

// E-mail duplicado e validações.
$rDup = admin_criar_usuario($admin, ['nome' => 'Dup', 'email' => 'nova@x.com', 'papel' => 'terapeuta', 'senha' => 'Abcd1234', 'confirma' => 'Abcd1234']);
ok(!$rDup['ok'], 'e-mail duplicado rejeitado');
$rMail = admin_criar_usuario($admin, ['nome' => 'M', 'email' => 'invalido', 'papel' => 'terapeuta', 'senha' => 'Abcd1234', 'confirma' => 'Abcd1234']);
ok(!$rMail['ok'], 'e-mail inválido rejeitado');
$rConf = admin_criar_usuario($admin, ['nome' => 'C', 'email' => 'c@x.com', 'papel' => 'terapeuta', 'senha' => 'Abcd1234', 'confirma' => 'OUTRA123']);
ok(!$rConf['ok'], 'confirmação divergente rejeitada');
$rPap = admin_criar_usuario($admin, ['nome' => 'P', 'email' => 'p@x.com', 'papel' => 'root', 'senha' => 'Abcd1234', 'confirma' => 'Abcd1234']);
ok(!$rPap['ok'], 'perfil inexistente rejeitado');
$rFraca = admin_criar_usuario($admin, ['nome' => 'F', 'email' => 'f@x.com', 'papel' => 'terapeuta', 'senha' => 'abc', 'confirma' => 'abc']);
ok(!$rFraca['ok'], 'senha fraca rejeitada');

// Admin cria outro admin.
$rAdm = admin_criar_usuario($admin, ['nome' => 'Admin 2', 'email' => 'adm2@x.com', 'papel' => 'admin', 'senha' => 'Abcd1234', 'confirma' => 'Abcd1234']);
ok($rAdm['ok'], 'admin cria outro admin');
eq(admin_contar_admins_ativos(), 2, 'agora há 2 admins ativos');

// Promover terapeuta e rebaixar — regras do último admin.
ok(admin_alterar_papel($admin, (int)$terap['id'], 'admin')['ok'], 'admin promove terapeuta a admin');
eq(admin_contar_admins_ativos(), 3, '3 admins ativos após promoção');
// Rebaixa adm2 e terap de volta — restam só 1 admin (coordenação) e deve travar.
admin_alterar_papel($admin, (int)$rAdm['id'], 'terapeuta');
admin_alterar_papel($admin, (int)$terap['id'], 'terapeuta');
eq(admin_contar_admins_ativos(), 1, 'sobra 1 admin ativo');
ok(admin_eh_ultimo_admin_ativo((int)$admin['id']), 'coordenação é o último admin ativo');
$rUlt = admin_alterar_papel($admin, (int)$admin['id'], 'terapeuta');
ok(!$rUlt['ok'], 'último admin ativo não pode ser rebaixado');

// Não desativar a si mesmo; não desativar o último admin ativo.
$rEu = admin_definir_status($admin, (int)$admin['id'], false);
ok(!$rEu['ok'], 'admin não desativa a própria conta');
// terap agora é terapeuta: pode desativar.
ok(admin_definir_status($admin, (int)$terap['id'], false)['ok'], 'admin desativa um terapeuta');
$tDesat = store_find('terapeutas', (int)$terap['id']);
ok(empty($tDesat['ativo']), 'conta marcada como desativada');
ok(admin_definir_status($admin, (int)$terap['id'], true)['ok'], 'admin reativa a conta');

// Redefinir senha temporária.
$rRed = admin_redefinir_senha_temp($admin, (int)$nova['id'], 'NovaSenha9', 'NovaSenha9');
ok($rRed['ok'], 'admin redefine senha temporária');
$nova2 = store_find('terapeutas', (int)$nova['id']);
ok(!empty($nova2['must_change_password']), 'redefinição re-exige troca no próximo acesso');
ok(password_verify('NovaSenha9', $nova2['senha_hash']), 'nova senha temporária vira hash válido');

section('Troca direta de senha (primeiro acesso, sem e-mail)');
$rAtual = account_trocar_senha_direta((int)$nova['id'], 'errada', 'OutraSenha9', 'OutraSenha9');
eq($rAtual['motivo'], 'atual_incorreta', 'senha atual incorreta é rejeitada');
$rIgual = account_trocar_senha_direta((int)$nova['id'], 'NovaSenha9', 'NovaSenha9', 'NovaSenha9');
eq($rIgual['motivo'], 'senha_igual', 'nova senha igual à atual é rejeitada');
$rTroca = account_trocar_senha_direta((int)$nova['id'], 'NovaSenha9', 'DefinitivaX1', 'DefinitivaX1');
ok($rTroca['ok'], 'troca direta com senha atual correta funciona');
$nova3 = store_find('terapeutas', (int)$nova['id']);
ok(empty($nova3['must_change_password']), 'must_change_password limpo após troca');
ok(password_verify('DefinitivaX1', $nova3['senha_hash']), 'nova senha pessoal salva como hash');

section('Admin inicial idempotente (spec §11)');
limpar_tabelas(['terapeutas']);
store_insert('terapeutas', ['nome' => 'Luiz', 'email' => 'luiz@x.com', 'senha_hash' => password_hash('Temp1234', PASSWORD_DEFAULT), 'ativo' => true, 'papel' => 'terapeuta']);
eq(admin_garantir_admin_inicial('Luiz', 'luiz@x.com'), 'promoted', '1ª execução promove a admin');
eq(admin_garantir_admin_inicial('Luiz', 'luiz@x.com'), 'noop', '2ª execução não faz nada (idempotente)');
eq(count(store_where('terapeutas', fn($r) => strtolower($r['email']) === 'luiz@x.com')), 1, 'não duplica o usuário');
$luiz = store_where('terapeutas', fn($r) => strtolower($r['email']) === 'luiz@x.com')[0];
eq($luiz['papel'], 'admin', 'Luiz ficou admin');

// =====================================================================
section('Agenda — geometria, conflito e validação de intervalo (timeline)');
eq(agenda_minutos('15:30'), 930, '15:30 → 930 min');
eq(agenda_minutos('07:00'), 420, '07:00 → 420 min');
ok(agenda_minutos('99:99') === null, 'hora inválida → null');
eq(agenda_hhmm(930), '15:30', '930 min → 15:30');
eq(agenda_hhmm(615), '10:15', '615 min → 10:15');

// Validação de intervalo (formato, fim>início, duração mín., faixa 07–22).
ok(agenda_validar_intervalo('2026-06-03', '15:30', '16:30')['ok'], '15:30–16:30 é válido');
ok(agenda_validar_intervalo('2026-06-03', '18:30', '22:00')['ok'], '18:30–22:00 cabe na grade (até 22h)');
ok(!agenda_validar_intervalo('2026-06-03', '16:30', '16:30')['ok'], 'fim igual ao início é inválido');
ok(!agenda_validar_intervalo('2026-06-03', '16:30', '15:30')['ok'], 'fim antes do início é inválido');
ok(!agenda_validar_intervalo('2026-06-03', '15:00', '15:10')['ok'], 'duração < 15 min é inválida');
ok(agenda_validar_intervalo('2026-06-03', '15:00', '15:15')['ok'], 'duração de exatamente 15 min é válida');
ok(!agenda_validar_intervalo('2026-06-03', '06:30', '07:30')['ok'], 'antes das 07h é inválido');
ok(!agenda_validar_intervalo('2026-06-03', '21:30', '22:30')['ok'], 'depois das 22h é inválido');
ok(!agenda_validar_intervalo('03/06/2026', '15:00', '16:00')['ok'], 'data fora do formato ISO é inválida');

// Conflito por SALA (regra do projeto): mesma sala+data sobrepondo bloqueia;
// salas diferentes ou cancelados não bloqueiam.
limpar_tabelas(['agendamentos']);
store_insert('agendamentos', ['data' => '2026-06-03', 'hora_inicio' => '15:00', 'hora_fim' => '16:00', 'sala' => 'sala-1', 'status' => 'agendado']);
$todosAg = store_all('agendamentos');
ok(agenda_ha_conflito($todosAg, '2026-06-03', '15:30', '16:30', 'sala-1', null), 'sobreposição na mesma sala bloqueia');
ok(!agenda_ha_conflito($todosAg, '2026-06-03', '15:30', '16:30', 'sala-2', null), 'sala diferente não bloqueia');
ok(!agenda_ha_conflito($todosAg, '2026-06-03', '16:00', '17:00', 'sala-1', null), 'encostar no fim (16:00) não é conflito');
ok(!agenda_ha_conflito($todosAg, '2026-06-04', '15:30', '16:30', 'sala-1', null), 'outro dia não bloqueia');
$idA = (int)$todosAg[0]['id'];
ok(!agenda_ha_conflito($todosAg, '2026-06-03', '15:30', '16:30', 'sala-1', $idA), 'ignorar o próprio id não bloqueia (mover/redimensionar)');
store_update('agendamentos', $idA, ['status' => 'cancelado']);
ok(!agenda_ha_conflito(store_all('agendamentos'), '2026-06-03', '15:30', '16:30', 'sala-1', null), 'cancelado nunca bloqueia');

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

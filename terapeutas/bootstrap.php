<?php
// ================================
// Bootstrap da área restrita de terapeutas.
// - Reaproveita o bootstrap do site (sessão, helpers de flash, $whatsLink etc.).
// - Garante carregamento dos módulos lib/* (storage, auth, whatsapp).
// - Define $terapeutasBase para links internos da área restrita.
// ================================

require_once dirname(__DIR__) . '/partials/bootstrap.php';

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/whatsapp.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/account.php';
require_once __DIR__ . '/lib/pacientes.php';
require_once __DIR__ . '/lib/agendamentos.php';
require_once __DIR__ . '/lib/pacotes.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/admin.php';

// Garante que os arquivos de dados existam (cria a partir do seed se faltar)
foreach (['terapeutas', 'agendamentos', 'evolucoes', 'lembretes', 'notificacoes', 'pacientes', 'codigos_senha', 'pacotes', 'pacote_movimentacoes', 'auditoria'] as $tbl) {
  store_bootstrap($tbl);
}

// Promoção idempotente do administrador inicial (spec §11). Roda UMA vez por
// ambiente (marcador em data/), promovendo o e-mail configurado a 'admin' sem
// duplicar nem mexer na senha. Default aponta para o admin inicial do projeto;
// pode ser sobrescrito por INITIAL_ADMIN_EMAIL/INITIAL_ADMIN_NAME no ambiente.
$adminInicialMarker = TERAP_DATA_DIR . '/.admin-inicial.done';
if (!is_file($adminInicialMarker)) {
  $adminEmail = admin_normalizar_email((string)terap_env('INITIAL_ADMIN_EMAIL', 'luizmariomoutinho1@gmail.com'));
  $adminNome  = (string)terap_env('INITIAL_ADMIN_NAME', 'Luiz');
  if ($adminEmail !== '') {
    admin_garantir_admin_inicial($adminNome, $adminEmail, null);
  }
  @file_put_contents($adminInicialMarker, date('c'));
}

// Sincroniza terapeutas novos do seed sem sobrescrever os existentes.
// Só roda quando o seed muda (compara mtime com marcador em data/).
$seedTerapPath = store_seed_path('terapeutas');
$markerPath    = TERAP_DATA_DIR . '/.terapeutas-seed.mtime';
$seedMtime     = is_file($seedTerapPath) ? (string)filemtime($seedTerapPath) : '';
$markerMtime   = is_file($markerPath) ? trim((string)@file_get_contents($markerPath)) : '';
if ($seedMtime !== '' && $seedMtime !== $markerMtime) {
  store_seed_upsert('terapeutas', 'email');
  @file_put_contents($markerPath, $seedMtime);
}

// Base para links internos da área restrita (relativa)
$terapeutasBase = 'index.php';

// Salas disponíveis no espaço — usadas para evitar choque de horários.
// Manter esta lista alinhada com o que existe fisicamente no Espaço Pindorama.
$salasDisponiveis = [
  'sala-1'    => 'Sala 1 — Acolhimento',
  'sala-2'    => 'Sala 2 — Corpo',
  'sala-3'    => 'Sala 3 — Práticas integrativas',
  'salao'     => 'Salão coletivo',
];

// Resolve usuário logado (pode ser null)
$terapeutaLogado = auth_user();

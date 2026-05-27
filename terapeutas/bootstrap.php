<?php
// ================================
// Bootstrap da área restrita de terapeutas.
// - Reaproveita o bootstrap do site (sessão, helpers de flash, $whatsLink etc.).
// - Garante carregamento dos módulos lib/* (storage, auth, whatsapp).
// - Define $terapeutasBase para links internos da área restrita.
// ================================

require_once dirname(__DIR__) . '/partials/bootstrap.php';

require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/whatsapp.php';

// Garante que os arquivos de dados existam (cria a partir do seed se faltar)
foreach (['terapeutas', 'agendamentos', 'evolucoes', 'lembretes', 'notificacoes'] as $tbl) {
  store_bootstrap($tbl);
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

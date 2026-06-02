<?php
// ================================
// TEMPLATE do provisionamento web (padrão config-db.php).
// Copie para terapeutas/config-provision.php (ignorado pelo Git) APENAS no
// servidor, defina um token longo e aleatório, e use uma única vez.
//
// Depois de provisionar, APAGUE config-provision.php e provisionar.php.
//
// Nenhuma senha vai aqui — só o token de autorização.
// ================================

putenv('TERAP_PROVISION_TOKEN=troque-por-um-token-longo-e-aleatorio');

// Opcional: pré-preencher nome/e-mail do formulário (a senha é sempre digitada).
// putenv('INITIAL_THERAPIST_NAME=Luiz Mario Barros Moutinho');
// putenv('INITIAL_THERAPIST_EMAIL=luizmariomoutinho1@gmail.com');

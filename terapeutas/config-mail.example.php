<?php
// ================================
// TEMPLATE de configuração de e-mail da área restrita (padrão config-db.php).
// Copie para terapeutas/config-mail.php (ignorado pelo Git) e preencha.
// Este arquivo é carregado automaticamente por terapeutas/lib/env.php.
//
// Alternativa: definir estas mesmas variáveis no ambiente do servidor
// (painel da hospedagem) e NÃO usar este arquivo.
//
// NUNCA versione credenciais reais.
// ================================

// Transporte: 'mail' (sendmail/PHP), 'smtp' ou 'log' (apenas DEV).
putenv('TERAP_MAIL_TRANSPORT=mail');
putenv('TERAP_MAIL_FROM=nao-responda@coletivopindorama.com');
putenv('TERAP_MAIL_FROM_NAME=Espaço Pindorama');

// --- Apenas se TERAP_MAIL_TRANSPORT=smtp ---
// putenv('TERAP_SMTP_HOST=smtp.seuprovedor.com');
// putenv('TERAP_SMTP_PORT=587');
// putenv('TERAP_SMTP_USER=usuario@dominio.com');
// putenv('TERAP_SMTP_PASS=senha-do-smtp');
// putenv('TERAP_SMTP_SECURE=tls'); // tls | ssl | none
// putenv('TERAP_SMTP_EHLO=coletivopindorama.com');

<?php
// ================================
// Camada de envio de e-mail da área restrita.
//
// Transporte escolhido por variável de ambiente TERAP_MAIL_TRANSPORT:
//   - "mail" (padrão): usa a função mail() do PHP (sendmail/Hostinger).
//   - "smtp": cliente SMTP mínimo (AUTH LOGIN, STARTTLS ou SSL) lendo
//             TERAP_SMTP_HOST / _PORT / _USER / _PASS / _SECURE.
//   - "log":  NÃO envia — grava o e-mail renderizado em data/terapeutas/_mail/
//             como arquivo .eml. Use SOMENTE em desenvolvimento/testes.
//
// Nenhuma credencial fica no código: tudo vem de variáveis de ambiente
// (ver terapeutas/config-mail.example.php e .env.example).
//
// IMPORTANTE (LGPD/segurança): o corpo do e-mail (que contém o código)
// nunca é gravado em log de produção. O modo "log" é só para DEV e grava
// num diretório dentro de data/ (gitignored).
// ================================

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/storage.php';

function mailer_from(): array {
  $email = terap_env('TERAP_MAIL_FROM', 'nao-responda@coletivopindorama.com');
  $nome  = terap_env('TERAP_MAIL_FROM_NAME', 'Espaço Pindorama');
  return [$email, $nome];
}

function mailer_transport(): string {
  $t = strtolower((string)terap_env('TERAP_MAIL_TRANSPORT', 'mail'));
  return in_array($t, ['mail', 'smtp', 'log'], true) ? $t : 'mail';
}

/**
 * Envia um e-mail HTML (com alternativa em texto puro).
 * Retorna ['ok' => bool, 'transport' => string, 'detail' => string|null].
 * NUNCA lança exceção para o chamador — falha de envio não deve vazar
 * detalhes nem derrubar a página.
 */
function mailer_send(string $paraEmail, string $assunto, string $html, string $texto = ''): array {
  [$fromEmail, $fromNome] = mailer_from();
  $transport = mailer_transport();

  if ($texto === '') {
    $texto = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
  }

  try {
    switch ($transport) {
      case 'log':
        return mailer_send_log($paraEmail, $assunto, $html, $fromEmail, $fromNome);
      case 'smtp':
        return mailer_send_smtp($paraEmail, $assunto, $html, $texto, $fromEmail, $fromNome);
      case 'mail':
      default:
        return mailer_send_mail($paraEmail, $assunto, $html, $texto, $fromEmail, $fromNome);
    }
  } catch (Throwable $e) {
    // Não vaza conteúdo do e-mail; registra só o tipo de erro.
    error_log('[mailer] falha de envio (' . $transport . '): ' . $e->getMessage());
    return ['ok' => false, 'transport' => $transport, 'detail' => null];
  }
}

function mailer_send_mail(string $para, string $assunto, string $html, string $texto, string $fromEmail, string $fromNome): array {
  $boundary = 'pind_' . bin2hex(random_bytes(8));
  $headers  = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'From: ' . mailer_encode_nome($fromNome) . ' <' . $fromEmail . '>';
  $headers[] = 'Reply-To: ' . $fromEmail;
  $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

  $corpo  = "--$boundary\r\n";
  $corpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $corpo .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
  $corpo .= $texto . "\r\n\r\n";
  $corpo .= "--$boundary\r\n";
  $corpo .= "Content-Type: text/html; charset=UTF-8\r\n";
  $corpo .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
  $corpo .= $html . "\r\n\r\n";
  $corpo .= "--$boundary--\r\n";

  $assuntoEnc = mailer_encode_assunto($assunto);
  $ok = @mail($para, $assuntoEnc, $corpo, implode("\r\n", $headers), '-f' . $fromEmail);
  return ['ok' => (bool)$ok, 'transport' => 'mail', 'detail' => null];
}

function mailer_send_log(string $para, string $assunto, string $html, string $fromEmail, string $fromNome): array {
  $dir = TERAP_DATA_DIR . '/_mail';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $nome = date('Ymd-His') . '_' . preg_replace('/[^a-z0-9]+/i', '-', $para) . '.eml';
  $eml  = "From: $fromNome <$fromEmail>\r\nTo: $para\r\nSubject: $assunto\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html;
  $path = $dir . '/' . $nome;
  @file_put_contents($path, $eml, LOCK_EX);
  return ['ok' => true, 'transport' => 'log', 'detail' => $path];
}

/**
 * Cliente SMTP mínimo. Suporta TERAP_SMTP_SECURE = "ssl" | "tls" | "none".
 * Pensado para servidores comuns (porta 587 STARTTLS ou 465 SSL).
 */
function mailer_send_smtp(string $para, string $assunto, string $html, string $texto, string $fromEmail, string $fromNome): array {
  $host   = terap_env('TERAP_SMTP_HOST', '');
  $port   = (int)terap_env('TERAP_SMTP_PORT', '587');
  $user   = terap_env('TERAP_SMTP_USER', '');
  $pass   = terap_env('TERAP_SMTP_PASS', '');
  $secure = strtolower((string)terap_env('TERAP_SMTP_SECURE', 'tls'));
  if ($host === '') throw new RuntimeException('TERAP_SMTP_HOST ausente');

  $transportPrefix = ($secure === 'ssl') ? 'ssl://' : '';
  $fp = @stream_socket_client($transportPrefix . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
  if (!$fp) throw new RuntimeException("conexão SMTP falhou: $errstr ($errno)");
  stream_set_timeout($fp, 15);

  $read = function () use ($fp): string {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
      $data .= $line;
      if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
  };
  $cmd = function (string $c) use ($fp, $read): string {
    fwrite($fp, $c . "\r\n");
    return $read();
  };

  $read(); // saudação
  $ehloHost = terap_env('TERAP_SMTP_EHLO', 'localhost');
  $cmd('EHLO ' . $ehloHost);

  if ($secure === 'tls') {
    $cmd('STARTTLS');
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
      fclose($fp);
      throw new RuntimeException('STARTTLS falhou');
    }
    $cmd('EHLO ' . $ehloHost);
  }

  if ($user !== '') {
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($user));
    $resp = $cmd(base64_encode((string)$pass));
    if (strpos($resp, '235') !== 0) {
      fclose($fp);
      throw new RuntimeException('autenticação SMTP rejeitada');
    }
  }

  $cmd('MAIL FROM:<' . $fromEmail . '>');
  $rcpt = $cmd('RCPT TO:<' . $para . '>');
  if ($rcpt === '' || (!str_starts_with($rcpt, '25'))) {
    fclose($fp);
    throw new RuntimeException('destinatário rejeitado pelo SMTP');
  }
  $cmd('DATA');

  $boundary = 'pind_' . bin2hex(random_bytes(8));
  $headers  = 'From: ' . mailer_encode_nome($fromNome) . ' <' . $fromEmail . '>' . "\r\n";
  $headers .= 'To: <' . $para . '>' . "\r\n";
  $headers .= 'Subject: ' . mailer_encode_assunto($assunto) . "\r\n";
  $headers .= 'MIME-Version: 1.0' . "\r\n";
  $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
  $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $texto . "\r\n";
  $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n";
  $body .= "--$boundary--\r\n";

  // Dot-stuffing para linhas iniciadas por ponto.
  $data = $headers . "\r\n" . $body;
  $data = preg_replace('/^\./m', '..', $data);
  $resp = $cmd($data . "\r\n.");
  $cmd('QUIT');
  fclose($fp);

  $ok = str_starts_with($resp, '250');
  return ['ok' => $ok, 'transport' => 'smtp', 'detail' => null];
}

function mailer_encode_assunto(string $assunto): string {
  return '=?UTF-8?B?' . base64_encode($assunto) . '?=';
}
function mailer_encode_nome(string $nome): string {
  // Codifica nome se tiver caracteres não-ASCII.
  if (preg_match('/[^\x20-\x7E]/', $nome)) {
    return '=?UTF-8?B?' . base64_encode($nome) . '?=';
  }
  return $nome;
}

/**
 * Template HTML simples e responsivo para o e-mail de código de verificação.
 */
function mailer_template_codigo(string $nome, string $codigo, int $minutos = 10): string {
  $nomeEsc   = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
  $codigoEsc = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
  return <<<HTML
<!doctype html>
<html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0E1C17;font-family:Arial,Helvetica,sans-serif;color:#EAF3EF;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0E1C17;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:440px;background:#132D26;border:1px solid #1f3b33;border-radius:18px;overflow:hidden;">
        <tr><td style="padding:28px 28px 8px;">
          <p style="margin:0 0 4px;font-size:13px;color:#9fb4ab;letter-spacing:.5px;text-transform:uppercase;">Espaço Pindorama</p>
          <h1 style="margin:0;font-size:20px;color:#EAF3EF;">Código de verificação</h1>
        </td></tr>
        <tr><td style="padding:8px 28px 0;">
          <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#cdddd5;">Olá, {$nomeEsc}. Use o código abaixo para confirmar a alteração da sua senha na área dos terapeutas.</p>
        </td></tr>
        <tr><td style="padding:0 28px;">
          <div style="text-align:center;background:#07110E;border:1px solid #2f5a4b;border-radius:14px;padding:18px;margin:6px 0 18px;">
            <span style="font-size:34px;letter-spacing:10px;font-weight:700;color:#8fd3b0;">{$codigoEsc}</span>
          </div>
        </td></tr>
        <tr><td style="padding:0 28px 26px;">
          <p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#9fb4ab;">Este código expira em <strong style="color:#cdddd5;">{$minutos} minutos</strong> e só pode ser usado uma vez.</p>
          <p style="margin:0;font-size:13px;line-height:1.6;color:#9fb4ab;">Se você não solicitou esta alteração, ignore este e-mail — sua senha continua a mesma.</p>
        </td></tr>
      </table>
      <p style="max-width:440px;margin:14px auto 0;font-size:11px;color:#6f857c;text-align:center;">Mensagem automática — por favor não responda.</p>
    </td></tr>
  </table>
</body></html>
HTML;
}

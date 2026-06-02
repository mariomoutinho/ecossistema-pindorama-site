<?php
// ================================
// Leitura de variáveis de ambiente para a área restrita.
//
// Ordem de resolução de cada chave:
//   1. getenv() / $_ENV / $_SERVER  (ambiente do processo — produção)
//   2. arquivo .env na raiz do repositório (apenas conveniência de DEV;
//      o .env é ignorado pelo Git).
//
// Não há segredos no código: tudo vem do ambiente ou de arquivos
// gitignored. Mantém o mesmo espírito do config-db.php do projeto.
// ================================

if (!function_exists('terap_env_load_file')) {
  function terap_env_load_file(): void {
    static $carregado = false;
    if ($carregado) return;
    $carregado = true;

    // Config de e-mail opcional no padrão do projeto (gitignored), carregada
    // independentemente do .env. O arquivo deve usar putenv() para definir
    // TERAP_MAIL_*/TERAP_SMTP_*.
    $mailCfg = dirname(__DIR__) . '/config-mail.php';
    if (is_file($mailCfg) && is_readable($mailCfg)) {
      require_once $mailCfg;
    }

    // .env na raiz do repositório (dirname 2 acima de /terapeutas/lib) — só DEV.
    $envPath = dirname(__DIR__, 2) . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) return;

    $linhas = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($linhas)) return;

    foreach ($linhas as $linha) {
      $linha = trim($linha);
      if ($linha === '' || $linha[0] === '#') continue;
      $pos = strpos($linha, '=');
      if ($pos === false) continue;
      $chave = trim(substr($linha, 0, $pos));
      $valor = trim(substr($linha, $pos + 1));
      // Remove aspas externas, se houver.
      if (strlen($valor) >= 2) {
        $a = $valor[0];
        $b = $valor[strlen($valor) - 1];
        if (($a === '"' && $b === '"') || ($a === "'" && $b === "'")) {
          $valor = substr($valor, 1, -1);
        }
      }
      if ($chave === '') continue;
      // Não sobrescreve o que já existe no ambiente real.
      if (getenv($chave) === false && !isset($_ENV[$chave])) {
        putenv("$chave=$valor");
        $_ENV[$chave] = $valor;
      }
    }
  }
}

if (!function_exists('terap_env')) {
  function terap_env(string $chave, ?string $default = null): ?string {
    terap_env_load_file();
    $v = getenv($chave);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$chave]) && $_ENV[$chave] !== '') return (string)$_ENV[$chave];
    if (isset($_SERVER[$chave]) && $_SERVER[$chave] !== '') return (string)$_SERVER[$chave];
    return $default;
  }
}

if (!function_exists('terap_env_bool')) {
  function terap_env_bool(string $chave, bool $default = false): bool {
    $v = terap_env($chave);
    if ($v === null) return $default;
    return in_array(strtolower($v), ['1', 'true', 'yes', 'on', 'sim'], true);
  }
}

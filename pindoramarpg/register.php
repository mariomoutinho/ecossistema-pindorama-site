<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/foto-usuario.php';

iniciarSessao();

if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = null;
$nomePreenchido = '';
$emailPreenchido = '';
$rolePreenchido = 'participante';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomePreenchido  = trim($_POST['nome']  ?? '');
    $emailPreenchido = trim($_POST['email'] ?? '');
    $rolePreenchido  = (string) ($_POST['role'] ?? 'participante');
    $senha           = (string) ($_POST['senha']     ?? '');
    $senhaConfirmar  = (string) ($_POST['senha_confirmar'] ?? '');
    $tokenEnviado    = (string) ($_POST['csrf'] ?? '');

    if (!validarCsrf($tokenEnviado)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif ($nomePreenchido === '' || $emailPreenchido === '') {
        $erro = 'Preencha nome e email.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($senha !== $senhaConfirmar) {
        $erro = 'As senhas não coincidem.';
    } elseif (!in_array($rolePreenchido, ['facilitador', 'participante'], true)) {
        $erro = 'Perfil inválido.';
    } else {
        // Foto é opcional. Se enviada e inválida, abortamos antes do INSERT.
        $erroFoto = null;
        $fotoPath = processarUploadFotoUsuario($_FILES['foto'] ?? null, $erroFoto);
        if ($erroFoto) {
            $erro = $erroFoto;
        } else {
            $novoId = registrarUsuario($nomePreenchido, $emailPreenchido, $senha, $rolePreenchido);
            if ($novoId === null) {
                $erro = 'Não foi possível criar a conta. Verifique se o email já está em uso.';
                // Se o INSERT falhou e já tínhamos salvo o arquivo, removemos.
                if ($fotoPath !== null) excluirArquivoFotoUsuario($fotoPath);
            } else {
                if ($fotoPath !== null) {
                    $stmt = $pdo->prepare("UPDATE usuarios SET foto_path = :p WHERE id = :id");
                    $stmt->execute(['p' => $fotoPath, 'id' => $novoId]);
                }
                autenticar($emailPreenchido, $senha);
                header('Location: index.php');
                exit;
            }
        }
    }
}

$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Criar conta — Pindorama RPG</title>

    <link rel="stylesheet" href="assets/css/ficha.css" />
    <link rel="stylesheet" href="assets/css/home.css?v=20260513i" />
    <link rel="stylesheet" href="assets/css/auth.css?v=20260513i" />
    <link rel="stylesheet" href="assets/css/transitions.css?v=20260508u" />
</head>
<body class="home-body register-page">
    <script src="assets/js/transitions.js?v=20260508u"></script>

    <main class="home-shell auth-shell">
        <header class="home-hero home-hero-compact">
            <a href="login.php" class="home-back" aria-label="Voltar ao login">&larr;</a>
            <h1 class="home-title">Criar conta</h1>
            <p class="home-subtitle">Cadastre-se como Facilitador ou Participante.</p>
        </header>

        <?php if ($erro): ?>
            <div class="auth-alert" role="alert"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="register.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />

            <label class="auth-field">
                <span>Nome</span>
                <input type="text" name="nome" required maxlength="150"
                       value="<?= htmlspecialchars($nomePreenchido) ?>" />
            </label>

            <div class="auth-field auth-foto-field">
                <span>Foto de perfil <small>(opcional)</small></span>
                <div class="auth-foto-row">
                    <div class="auth-foto-preview is-empty" id="authFotoPreview" aria-hidden="true">?</div>
                    <label class="home-btn home-btn-ghost auth-foto-btn">
                        <input type="file" name="foto" id="authFotoInput"
                               accept="image/jpeg,image/png,image/webp" hidden />
                        <span>Escolher imagem</span>
                    </label>
                </div>
                <small class="auth-foto-hint">JPG, PNG ou WebP — até 5&nbsp;MB.</small>
            </div>

            <label class="auth-field">
                <span>Email</span>
                <input type="email" name="email" required autocomplete="email"
                       value="<?= htmlspecialchars($emailPreenchido) ?>" />
            </label>

            <label class="auth-field">
                <span>Senha</span>
                <input type="password" name="senha" required autocomplete="new-password" minlength="6" />
            </label>

            <label class="auth-field">
                <span>Confirmar senha</span>
                <input type="password" name="senha_confirmar" required autocomplete="new-password" minlength="6" />
            </label>

            <fieldset class="auth-role">
                <legend>Perfil padrão</legend>
                <label class="auth-role-option">
                    <input type="radio" name="role" value="participante"
                           <?= $rolePreenchido !== 'facilitador' ? 'checked' : '' ?> />
                    <span><strong>Participante</strong> — joga em mesas, foca na própria ficha.</span>
                </label>
                <label class="auth-role-option">
                    <input type="radio" name="role" value="facilitador"
                           <?= $rolePreenchido === 'facilitador' ? 'checked' : '' ?> />
                    <span><strong>Facilitador</strong> — cria e conduz mesas, NPCs, mapas e cenas.</span>
                </label>
            </fieldset>

            <button type="submit" class="home-btn auth-submit">Criar conta</button>
        </form>

        <div class="home-list-footer auth-footer">
            <a class="home-btn home-btn-ghost" href="index.php">Voltar</a>
            <a class="home-btn" href="login.php">Já tenho conta</a>
        </div>
    </main>

    <script>
        // Preview imediato da foto. Validação real no backend; aqui é só UX.
        (function () {
            var input   = document.getElementById('authFotoInput');
            var preview = document.getElementById('authFotoPreview');
            var hint    = document.querySelector('.auth-foto-hint');
            if (!input || !preview) return;
            var hintBase = hint ? hint.textContent : '';
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) {
                    preview.classList.add('is-empty');
                    preview.style.backgroundImage = '';
                    preview.textContent = '?';
                    if (hint) hint.textContent = hintBase;
                    return;
                }
                var okMime = ['image/jpeg','image/png','image/webp'].includes(file.type);
                var okSize = file.size <= 5 * 1024 * 1024;
                if (!okMime || !okSize) {
                    input.value = '';
                    preview.classList.add('is-empty');
                    preview.style.backgroundImage = '';
                    preview.textContent = '?';
                    if (hint) hint.textContent = !okMime
                        ? 'Use JPG, PNG ou WebP.'
                        : 'A foto excede 5 MB.';
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    preview.classList.remove('is-empty');
                    preview.textContent = '';
                    preview.style.backgroundImage = "url('" + e.target.result + "')";
                    if (hint) hint.textContent = hintBase;
                };
                reader.readAsDataURL(file);
            });
        })();
    </script>
</body>
</html>

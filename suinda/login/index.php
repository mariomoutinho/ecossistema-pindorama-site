<?php
// ============================================================================
// Suindá — Login (/suinda/login/).
// Ponto único de acesso à área autenticada. Autentica contra a API educacional
// (/suinda/api/auth/login), guarda o token e o usuário no localStorage (mesmas
// chaves usadas pelo app) e redireciona para o painel (/suinda/estudar/).
// ============================================================================
$suindaPageTitle = 'Entrar — Suindá';
$suindaPageDesc  = 'Acesse sua área de estudos do Suindá (Coletivo Pindorama).';
$suindaActiveNav = 'login';
$suindaBodyClass = 'page-auth';
require __DIR__ . '/../inc/header.php';
?>

<main id="conteudo" class="auth-wrap">
  <section class="auth-card" aria-labelledby="loginTitle">
    <div class="auth-card__head">
      <div class="auth-card__owl" aria-hidden="true">🦉</div>
      <h1 id="loginTitle">Entrar no Suindá</h1>
      <p>Acesse sua plataforma de estudos</p>
    </div>

    <div id="loginAlert" class="alert alert--error" role="alert" hidden></div>

    <form id="loginForm" novalidate>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" autocomplete="username"
               placeholder="voce@exemplo.com" required />
      </div>

      <div class="field">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" autocomplete="current-password"
               placeholder="Sua senha" required />
      </div>

      <button type="submit" class="btn btn--primary btn--block btn--lg" id="loginSubmit">Entrar</button>
      <p id="loginMessage" class="form-msg" aria-live="polite"></p>
    </form>

    <p class="auth-meta">
      <button type="button" class="link-button" id="forgotLink">Esqueci minha senha</button>
    </p>
    <p class="auth-meta">
      <a href="/suinda/">← Voltar à página do Suindá</a>
    </p>

    <div class="auth-demo">
      Demonstração — aluno: <strong>aluno@suinda.com</strong> / <strong>123456</strong>
    </div>
  </section>
</main>

<script>
(function () {
  var API_BASE = localStorage.getItem("suinda_api_base_url") || "/suinda/api";
  var ESTUDAR = "/suinda/estudar/";

  // Já autenticado? Vai direto ao painel.
  if (localStorage.getItem("suinda_api_token")) {
    window.location.replace(ESTUDAR);
    return;
  }

  var form = document.getElementById("loginForm");
  var alertBox = document.getElementById("loginAlert");
  var message = document.getElementById("loginMessage");
  var submit = document.getElementById("loginSubmit");

  function showError(text) {
    alertBox.textContent = text;
    alertBox.hidden = false;
    message.textContent = "";
  }

  document.getElementById("forgotLink").addEventListener("click", function () {
    alertBox.className = "alert alert--info";
    alertBox.textContent = "A recuperação de senha estará disponível em breve. Por enquanto, fale com o Coletivo Pindorama para redefinir seu acesso.";
    alertBox.hidden = false;
  });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    alertBox.hidden = true;
    alertBox.className = "alert alert--error";

    var email = document.getElementById("email").value.trim();
    var password = document.getElementById("password").value;

    if (!email || !password) {
      showError("Preencha e-mail e senha para continuar.");
      return;
    }

    submit.disabled = true;
    message.className = "form-msg";
    message.textContent = "Entrando…";

    try {
      var response = await fetch(API_BASE + "/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: email, password: password })
      });

      var data = {};
      try { data = await response.json(); } catch (e) {}

      if (response.ok && data.token) {
        // Mesmas chaves usadas pelo app de repetição espaçada.
        localStorage.setItem("suinda_api_token", data.token);
        localStorage.setItem("suinda_current_user", JSON.stringify(data.user || {}));
        message.className = "form-msg form-msg--ok";
        message.textContent = "Acesso autorizado. Redirecionando…";
        window.location.replace(ESTUDAR);
        return;
      }

      if (response.status === 401) {
        showError("E-mail ou senha inválidos. Confira seus dados e tente novamente.");
      } else {
        showError((data && data.error) ? data.error : "Não foi possível entrar agora. Tente novamente em instantes.");
      }
    } catch (err) {
      showError("Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.");
    } finally {
      submit.disabled = false;
      if (message.classList.contains("form-msg--ok") === false) {
        message.textContent = "";
      }
    }
  });
})();
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>

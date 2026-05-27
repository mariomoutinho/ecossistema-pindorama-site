<?php
// ================================
// Footer da área restrita.
// Fecha o <main> aberto pelo header e adiciona um script mínimo
// para abrir/fechar o menu lateral no mobile.
// ================================
$showSidebar = isset($terapeutaLogado) && $terapeutaLogado !== null;
?>
  </main>
<?php if ($showSidebar): ?>
</div><!-- /.terap-shell -->
<script>
(function() {
  var btn = document.getElementById('terapBurger');
  var aside = document.getElementById('terapSidebar');
  if (!btn || !aside) return;
  btn.addEventListener('click', function() {
    var open = aside.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
<?php endif; ?>
</body>
</html>

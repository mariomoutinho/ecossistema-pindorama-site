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

  function set(open) {
    aside.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    set(!aside.classList.contains('is-open'));
  });
  // Toca/clica fora do drawer (e fora do botão) → fecha.
  document.addEventListener('click', function(e) {
    if (!aside.classList.contains('is-open')) return;
    if (aside.contains(e.target) || btn.contains(e.target)) return;
    set(false);
  });
  // Clicar num item do drawer → fecha (navegação fluida).
  aside.addEventListener('click', function(e) {
    if (e.target.closest('a')) set(false);
  });
  // Tecla Esc → fecha.
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && aside.classList.contains('is-open')) set(false);
  });
})();
</script>
<?php endif; ?>
</body>
</html>

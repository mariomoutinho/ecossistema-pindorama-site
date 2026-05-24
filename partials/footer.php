<?php
// ================================
// Footer compartilhado. Espera $pageScripts (array opcional) para incluir
// scripts específicos da página (ex.: home.js, terapias.js).
// Depende de variáveis definidas em partials/bootstrap.php.
// ================================
$pageScripts = $pageScripts ?? [];
?>
<footer class="siteFooter">
  <div class="container foot">
    <div>© <?= date('Y') ?> Coletivo Pindorama • Recife/PE</div>
    <nav class="footLinks" aria-label="Atalhos do rodapé">
      <a href="<?= htmlspecialchars($homeUrl) ?>" class="link">Coletivo</a>
      <span class="sep">•</span>
      <a href="<?= htmlspecialchars($terapiasUrl) ?>" class="link">Terapias</a>
      <span class="sep">•</span>
      <a href="<?= htmlspecialchars($cuidarUrl) ?>" class="link">Cuidar+</a>
      <span class="sep">•</span>
      <a href="<?= htmlspecialchars($rpgUrl) ?>" target="_blank" rel="noopener" class="link">Pindorama RPG</a>
      <span class="sep">•</span>
      <a href="<?= htmlspecialchars($insta) ?>" target="_blank" rel="noopener" class="link">Instagram</a>
      <span class="sep">•</span>
      <a href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener" class="link">WhatsApp</a>
    </nav>
  </div>
</footer>

<?php foreach ($pageScripts as $src): ?>
<script src="<?= htmlspecialchars($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>

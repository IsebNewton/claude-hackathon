<?php
// Pagination-Include
// Erwartet: $total (Gesamtanzahl), $seite (aktuelle Seite, 1-basiert),
//           $basis_url (z.B. "index.php?page=kunden&suche=test")
// Errechnet Offset fuer SQL: ($seite - 1) * ITEMS_PER_PAGE

$gesamt_seiten = ceil($total / ITEMS_PER_PAGE);

if ($gesamt_seiten <= 1) {
    // Keine Pagination noetig
    return;
}
?>
<nav>
  <ul class="pagination">
    <?php if ($seite > 1): ?>
    <li><a href="<?php echo $basis_url; ?>&seite=<?php echo ($seite - 1); ?>">&laquo;</a></li>
    <?php else: ?>
    <li class="disabled"><span>&laquo;</span></li>
    <?php endif; ?>

    <?php
    // Seitenzahlen — maximal 7 anzeigen
    $von = max(1, $seite - 3);
    $bis = min($gesamt_seiten, $seite + 3);
    for ($s = $von; $s <= $bis; $s++):
    ?>
    <li <?php if ($s == $seite) echo 'class="active"'; ?>>
      <a href="<?php echo $basis_url; ?>&seite=<?php echo $s; ?>"><?php echo $s; ?></a>
    </li>
    <?php endfor; ?>

    <?php if ($seite < $gesamt_seiten): ?>
    <li><a href="<?php echo $basis_url; ?>&seite=<?php echo ($seite + 1); ?>">&raquo;</a></li>
    <?php else: ?>
    <li class="disabled"><span>&raquo;</span></li>
    <?php endif; ?>
  </ul>
</nav>
<p class="text-muted" style="font-size:12px;">
  Zeige <?php echo (($seite-1)*ITEMS_PER_PAGE)+1; ?>–<?php echo min($seite*ITEMS_PER_PAGE, $total); ?>
  von <?php echo $total; ?> Einträgen
</p>

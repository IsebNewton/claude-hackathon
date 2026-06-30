<?php
require_once '../init.php';
require_once '../classes/Northwind.php';

// Loeschen-Aktion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    // Soft-delete — Artikel nie wirklich loeschen wegen historischer Auftragsdaten
    mysql_query("UPDATE artikel SET aktiv = 0 WHERE id = $del_id", $db);
    $_SESSION['success'] = 'Artikel deaktiviert.';
    header('Location: index.php?page=artikel');
    exit;
}

$suche  = isset($_GET['suche']) ? trim($_GET['suche']) : '';
$seite  = isset($_GET['seite']) ? max(1, (int)$_GET['seite']) : 1;
$offset = ($seite - 1) * ITEMS_PER_PAGE;

$where = "aktiv = 1";
if (!empty($suche)) {
    $s = sanitize($suche);
    $where .= " AND (name LIKE '%$s%' OR sku LIKE '%$s%' OR beschreibung LIKE '%$s%')";
}

$count_res = mysql_query("SELECT COUNT(*) as cnt FROM artikel WHERE $where", $db);
$count_row = mysql_fetch_assoc($count_res);
$total = $count_row['cnt'];

$result = mysql_query(
    "SELECT * FROM artikel WHERE $where ORDER BY name ASC LIMIT $offset, " . ITEMS_PER_PAGE, $db
);

$basis_url = 'index.php?page=artikel' . (!empty($suche) ? '&suche=' . urlencode($suche) : '');

include '../includes/header.php';
?>

<h2>Artikel <a href="index.php?page=artikel_edit" class="btn btn-success btn-sm">+ Neu</a></h2>

<form method="get" action="index.php" class="form-inline" style="margin-bottom:15px;">
  <input type="hidden" name="page" value="artikel">
  <div class="input-group">
    <input type="text" name="suche" class="form-control" placeholder="Name, SKU..."
           value="<?php echo htmlspecialchars($suche); ?>">
    <span class="input-group-btn">
      <button type="submit" class="btn btn-default">Suchen</button>
      <?php if (!empty($suche)): ?>
      <a href="index.php?page=artikel" class="btn btn-default">Zurücksetzen</a>
      <?php endif; ?>
    </span>
  </div>
</form>

<table class="table table-striped table-hover table-condensed">
  <thead>
    <tr>
      <th>SKU</th>
      <th>Name</th>
      <th>Preis (netto)</th>
      <th>MwSt.</th>
      <th>Preis (brutto)</th>
      <th>Lagerbestand</th>
      <th>Aktionen</th>
    </tr>
  </thead>
  <tbody>
  <?php while ($a = mysql_fetch_assoc($result)): ?>
    <?php
    $preis_brutto = $a['preis_netto'] * (1 + $a['mwst_satz'] / 100);
    $lager_class = $a['lagerbestand'] < 5 ? 'lagerbestand-niedrig' : '';
    ?>
    <tr>
      <td><code><?php echo htmlspecialchars($a['sku']); ?></code></td>
      <td>
        <?php echo htmlspecialchars($a['name']); ?>
        <?php if (!empty($a['beschreibung'])): ?>
          <br><small class="text-muted"><?php echo htmlspecialchars(truncate($a['beschreibung'], 60)); ?></small>
        <?php endif; ?>
      </td>
      <td><?php echo formatEuro($a['preis_netto']); ?></td>
      <td><?php echo number_format($a['mwst_satz'], 0); ?> %</td>
      <td><?php echo formatEuro($preis_brutto); ?></td>
      <td class="<?php echo $lager_class; ?>">
        <?php echo $a['lagerbestand']; ?>
        <?php if ($a['lagerbestand'] < 5): ?>
          <span class="label label-danger">Niedrig</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="index.php?page=artikel_edit&id=<?php echo $a['id']; ?>" class="btn btn-xs btn-default">Bearbeiten</a>
        <a href="index.php?page=artikel&action=delete&id=<?php echo $a['id']; ?>"
           class="btn btn-xs btn-danger"
           onclick="return confirm('Artikel wirklich deaktivieren?')">Deaktivieren</a>
      </td>
    </tr>
  <?php endwhile; ?>
  <?php if ($total == 0): ?>
    <tr><td colspan="7" class="text-center text-muted">Keine Artikel gefunden.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php include '../includes/pagination.php'; ?>
<?php include '../includes/footer.php'; ?>

<?php
require_once '../init.php';
require_once '../classes/Northwind.php';

// Loeschen-Aktion vor der Ausgabe verarbeiten
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];

    // Pruefe ob Kunde Auftraege hat — wenn ja nur soft-delete
    $check = mysql_query("SELECT COUNT(*) as cnt FROM auftraege WHERE kunden_id = $del_id", $db);
    $check_row = mysql_fetch_assoc($check);

    if ($check_row['cnt'] > 0) {
        // Hat Auftraege: nur deaktivieren
        mysql_query("UPDATE kunden SET aktiv = 0 WHERE id = $del_id", $db);
        $_SESSION['success'] = 'Kunde deaktiviert (hat noch ' . $check_row['cnt'] . ' Auftrag/Aufträge).';
    } else {
        // Kein Auftrag: wirklich loeschen
        mysql_query("DELETE FROM kunden WHERE id = $del_id", $db);
        $_SESSION['success'] = 'Kunde gelöscht.';
    }
    header('Location: index.php?page=kunden');
    exit;
}

// Suchfilter
$suche   = isset($_GET['suche']) ? trim($_GET['suche']) : '';
$seite   = isset($_GET['seite']) ? max(1, (int)$_GET['seite']) : 1;
$offset  = ($seite - 1) * ITEMS_PER_PAGE;

$where = "k.aktiv = 1";
if (!empty($suche)) {
    $s = sanitize($suche);
    // Suche ueber mehrere Felder — direkte Konkatenation, war 2012 "schnell genug"
    $where .= " AND (k.name LIKE '%$s%' OR k.nummer LIKE '%$s%' OR k.ort LIKE '%$s%' OR k.email LIKE '%$s%')";
}

// Gesamtanzahl fuer Pagination
$count_sql = "SELECT COUNT(*) as cnt FROM kunden k WHERE $where";
$count_res = mysql_query($count_sql, $db);
$count_row = mysql_fetch_assoc($count_res);
$total = $count_row['cnt'];

// Kundenliste — bewusst NICHT ueber Northwind-Klasse, weil Peter das damals direkt gemacht hat
$sql = "SELECT k.*,
               (SELECT COUNT(*) FROM auftraege WHERE kunden_id = k.id) as auftrag_count
        FROM kunden k
        WHERE $where
        ORDER BY k.name ASC
        LIMIT $offset, " . ITEMS_PER_PAGE;

$result = mysql_query($sql, $db);

$basis_url = 'index.php?page=kunden' . (!empty($suche) ? '&suche=' . urlencode($suche) : '');

include '../includes/header.php';
?>

<h2>Kunden <a href="index.php?page=kunden_edit" class="btn btn-success btn-sm">+ Neu</a></h2>

<form method="get" action="index.php" class="form-inline" style="margin-bottom:15px;">
  <input type="hidden" name="page" value="kunden">
  <div class="input-group">
    <input type="text" name="suche" class="form-control" placeholder="Name, Nummer, Ort, E-Mail..."
           value="<?php echo htmlspecialchars($suche); ?>">
    <span class="input-group-btn">
      <button type="submit" class="btn btn-default">Suchen</button>
      <?php if (!empty($suche)): ?>
      <a href="index.php?page=kunden" class="btn btn-default">Zurücksetzen</a>
      <?php endif; ?>
    </span>
  </div>
</form>

<table class="table table-striped table-hover table-condensed">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Name / Firma</th>
      <th>Ort</th>
      <th>E-Mail</th>
      <th>Typ</th>
      <th>Aufträge</th>
      <th>Aktionen</th>
    </tr>
  </thead>
  <tbody>
  <?php while ($k = mysql_fetch_assoc($result)): ?>
    <tr>
      <td><?php echo htmlspecialchars($k['nummer']); ?></td>
      <td>
        <?php echo htmlspecialchars($k['name']); ?>
        <?php if (!empty($k['firma'])): ?>
          <br><small class="text-muted"><?php echo htmlspecialchars($k['firma']); ?></small>
        <?php endif; ?>
      </td>
      <td><?php echo htmlspecialchars($k['plz'] . ' ' . $k['ort']); ?></td>
      <td><?php echo htmlspecialchars($k['email']); ?></td>
      <td>
        <?php echo $k['typ'] == 'geschaeft'
            ? '<span class="label label-info">Geschäftskunde</span>'
            : '<span class="label label-default">Privatkunde</span>'; ?>
      </td>
      <td><?php echo $k['auftrag_count']; ?></td>
      <td>
        <a href="index.php?page=kunden_edit&id=<?php echo $k['id']; ?>" class="btn btn-xs btn-default">Bearbeiten</a>
        <a href="index.php?page=kunden&action=delete&id=<?php echo $k['id']; ?>"
           class="btn btn-xs btn-danger"
           onclick="return confirm('Kunden wirklich löschen/deaktivieren?')">Löschen</a>
      </td>
    </tr>
  <?php endwhile; ?>
  <?php if ($total == 0): ?>
    <tr><td colspan="7" class="text-center text-muted">Keine Kunden gefunden.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php include '../includes/pagination.php'; ?>
<?php include '../includes/footer.php'; ?>

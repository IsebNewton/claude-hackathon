<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$heute         = date('Y-m-d');

$where = array('1=1');
if ($status_filter != '') {
    $where[] = "r.status = '" . $status_filter . "'";
}
$where_sql = implode(' AND ', $where);

$seite  = isset($_GET['seite']) ? max(1, (int)$_GET['seite']) : 1;
$offset = ($seite - 1) * ITEMS_PER_PAGE;

$count_sql = "SELECT COUNT(*) as anz FROM rechnungen r WHERE " . $where_sql;
$count_res = mysql_query($count_sql, $db);
$count_row = mysql_fetch_assoc($count_res);
$gesamt    = $count_row['anz'];

// Join zu kunden und auftraege — direkt inline
$sql = "SELECT r.*, a.auftrag_nr, k.name as kunde_name, k.id as kunden_id
        FROM rechnungen r
        JOIN auftraege a ON r.auftrag_id = a.id
        JOIN kunden k    ON r.kunden_id  = k.id
        WHERE " . $where_sql . "
        ORDER BY r.faellig_am ASC, r.angelegt_am DESC
        LIMIT " . $offset . ", " . ITEMS_PER_PAGE;

$result = mysql_query($sql, $db);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <h2>Rechnungen</h2>

    <!-- Filter -->
    <form method="get" action="index.php" class="form-inline" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="rechnungen">
        <div class="form-group">
            <label>Status:</label>
            <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                <option value="">Alle</option>
                <option value="offen"      <?php echo $status_filter=='offen'      ? 'selected' : ''; ?>>Offen</option>
                <option value="teilbezahlt"<?php echo $status_filter=='teilbezahlt'? 'selected' : ''; ?>>Teilbezahlt</option>
                <option value="bezahlt"    <?php echo $status_filter=='bezahlt'    ? 'selected' : ''; ?>>Bezahlt</option>
                <option value="gemahnt"    <?php echo $status_filter=='gemahnt'    ? 'selected' : ''; ?>>Gemahnt</option>
                <option value="storniert"  <?php echo $status_filter=='storniert'  ? 'selected' : ''; ?>>Storniert</option>
            </select>
        </div>
        &nbsp;
        <a href="index.php?page=rechnungen" class="btn btn-link btn-sm">Alle anzeigen</a>
    </form>

    <table class="table table-striped table-hover table-condensed">
        <thead>
            <tr>
                <th>Rechnungs-Nr</th>
                <th>Auftrag</th>
                <th>Kunde</th>
                <th>Betrag brutto</th>
                <th>Fällig am</th>
                <th>Status</th>
                <th>Mahnstufe</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysql_num_rows($result) == 0): ?>
            <tr><td colspan="8" class="text-center text-muted">Keine Rechnungen gefunden.</td></tr>
        <?php endif; ?>
        <?php while ($row = mysql_fetch_assoc($result)):
            // Überfällig = offen AND faellig_am in der Vergangenheit
            $ueberfaellig = ($row['status'] == 'offen' && $row['faellig_am'] < $heute);
        ?>
            <tr class="<?php echo $ueberfaellig ? 'danger' : ''; ?>">
                <td>
                    <a href="index.php?page=rechnung_detail&id=<?php echo $row['id']; ?>">
                        <?php echo htmlspecialchars($row['rechnungs_nr']); ?>
                    </a>
                    <?php if ($ueberfaellig): ?>
                        <span class="label label-danger">Überfällig</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="index.php?page=auftrag_detail&id=<?php echo $row['auftrag_id']; ?>">
                        <?php echo htmlspecialchars($row['auftrag_nr']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars(truncate($row['kunde_name'], 30)); ?></td>
                <td><?php echo formatEuro($row['betrag_brutto']); ?></td>
                <td><?php echo nl_date($row['faellig_am']); ?></td>
                <td>
                    <?php
                    $label_map = array(
                        'offen'       => 'warning',
                        'teilbezahlt' => 'info',
                        'bezahlt'     => 'success',
                        'gemahnt'     => 'danger',
                        'storniert'   => 'default',
                    );
                    $badge = isset($label_map[$row['status']]) ? $label_map[$row['status']] : 'default';
                    ?>
                    <span class="label label-<?php echo $badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                </td>
                <td>
                    <?php if ($row['mahnstufe'] > 0): ?>
                        <span class="badge"><?php echo $row['mahnstufe']; ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="index.php?page=rechnung_detail&id=<?php echo $row['id']; ?>" class="btn btn-xs btn-default">Detail</a>
                    <?php if ($row['status'] == 'offen' || $row['status'] == 'teilbezahlt'): ?>
                        <a href="index.php?page=rechnung_zahlung&id=<?php echo $row['id']; ?>" class="btn btn-xs btn-primary">Zahlung erfassen</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php echo getPaginierung($gesamt, $seite, ITEMS_PER_PAGE, 'index.php?page=rechnungen&status=' . urlencode($status_filter)); ?>

    <p class="text-muted small"><?php echo $gesamt; ?> Rechnung(en) gesamt.</p>
</div>

<?php require_once '../includes/footer.php'; ?>

<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

$carrier_filter = isset($_GET['carrier']) ? sanitize($_GET['carrier']) : '';
$status_filter  = isset($_GET['status'])  ? sanitize($_GET['status'])  : '';

$where = array('1=1');
if ($carrier_filter != '') {
    $where[] = "l.carrier_code = '" . $carrier_filter . "'";
}
if ($status_filter != '') {
    $where[] = "l.status = '" . $status_filter . "'";
}
$where_sql = implode(' AND ', $where);

$seite   = isset($_GET['seite']) ? max(1, (int)$_GET['seite']) : 1;
$offset  = ($seite - 1) * ITEMS_PER_PAGE;

$count_sql = "SELECT COUNT(*) as anz FROM lieferungen l WHERE " . $where_sql;
$count_res = mysql_query($count_sql, $db);
$count_row = mysql_fetch_assoc($count_res);
$gesamt    = $count_row['anz'];

// Inline SQL mit JOIN — Northwind-Klasse hat keine Liste-Methode für Lieferungen
$sql = "SELECT l.*, a.auftrag_nr, k.name as kunde_name
        FROM lieferungen l
        JOIN auftraege a ON l.auftrag_id = a.id
        JOIN kunden k ON a.kunden_id = k.id
        WHERE " . $where_sql . "
        ORDER BY l.angelegt_am DESC
        LIMIT " . $offset . ", " . ITEMS_PER_PAGE;

$result = mysql_query($sql, $db);

// Tracking-URL-Muster — hardcoded, wird auch in tracking_url Spalte gespeichert
// aber manchmal stimmt die Spalte nicht (stale), deswegen hier nochmal berechnen
function getTrackingUrl($carrier, $tracking_nr) {
    if (!$tracking_nr) return null;
    switch ($carrier) {
        case 'DHL':
            return 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=' . urlencode($tracking_nr);
        case 'DPD':
            return 'https://tracking.dpd.de/status/de_DE/parcel/' . urlencode($tracking_nr);
        case 'UPS':
            return 'https://www.ups.com/track?loc=de_DE&tracknum=' . urlencode($tracking_nr);
        case 'GLS':
            return 'https://gls-group.eu/DE/de/paketverfolgung?match=' . urlencode($tracking_nr);
        default:
            return null;
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <h2>Lieferungen</h2>

    <!-- Filter -->
    <form method="get" action="index.php" class="form-inline" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="lieferungen">
        <div class="form-group">
            <label>Carrier:</label>
            <select name="carrier" class="form-control input-sm" onchange="this.form.submit()">
                <option value="">Alle</option>
                <option value="DHL" <?php echo $carrier_filter=='DHL' ? 'selected' : ''; ?>>DHL</option>
                <option value="DPD" <?php echo $carrier_filter=='DPD' ? 'selected' : ''; ?>>DPD</option>
                <option value="UPS" <?php echo $carrier_filter=='UPS' ? 'selected' : ''; ?>>UPS</option>
                <option value="GLS" <?php echo $carrier_filter=='GLS' ? 'selected' : ''; ?>>GLS</option>
            </select>
        </div>
        &nbsp;
        <div class="form-group">
            <label>Status:</label>
            <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                <option value="">Alle</option>
                <option value="erstellt"   <?php echo $status_filter=='erstellt'   ? 'selected' : ''; ?>>Erstellt</option>
                <option value="abgeholt"   <?php echo $status_filter=='abgeholt'   ? 'selected' : ''; ?>>Abgeholt</option>
                <option value="unterwegs"  <?php echo $status_filter=='unterwegs'  ? 'selected' : ''; ?>>Unterwegs</option>
                <option value="zugestellt" <?php echo $status_filter=='zugestellt' ? 'selected' : ''; ?>>Zugestellt</option>
                <option value="zurueck"    <?php echo $status_filter=='zurueck'    ? 'selected' : ''; ?>>Zurück</option>
            </select>
        </div>
        &nbsp;
        <a href="index.php?page=lieferungen" class="btn btn-link btn-sm">Zurücksetzen</a>
    </form>

    <table class="table table-striped table-hover table-condensed">
        <thead>
            <tr>
                <th>ID</th>
                <th>Auftrag</th>
                <th>Kunde</th>
                <th>Carrier</th>
                <th>Tracking-Nr</th>
                <th>Status</th>
                <th>Versanddatum</th>
                <th>Zustellung soll</th>
                <th>Zustellung ist</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysql_num_rows($result) == 0): ?>
            <tr><td colspan="10" class="text-center text-muted">Keine Lieferungen gefunden.</td></tr>
        <?php endif; ?>
        <?php while ($row = mysql_fetch_assoc($result)): ?>
            <?php
            $tracking_url = $row['tracking_url'] ?: getTrackingUrl($row['carrier_code'], $row['tracking_nummer']);
            ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><a href="index.php?page=auftrag_detail&id=<?php echo $row['auftrag_id']; ?>"><?php echo htmlspecialchars($row['auftrag_nr']); ?></a></td>
                <td><?php echo htmlspecialchars(truncate($row['kunde_name'], 25)); ?></td>
                <td><?php echo htmlspecialchars(getCarrierName($row['carrier_code'])); ?></td>
                <td>
                    <?php if ($tracking_url): ?>
                        <a href="<?php echo htmlspecialchars($tracking_url); ?>" target="_blank">
                            <?php echo htmlspecialchars($row['tracking_nummer']); ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted"><?php echo htmlspecialchars($row['tracking_nummer'] ?: '—'); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo $row['versanddatum']       ? nl_date($row['versanddatum'])       : '—'; ?></td>
                <td><?php echo $row['zustelldatum_soll']  ? nl_date($row['zustelldatum_soll'])  : '—'; ?></td>
                <td><?php echo $row['zustelldatum_ist']   ? nl_date($row['zustelldatum_ist'])   : '—'; ?></td>
                <td><a href="index.php?page=lieferung_detail&id=<?php echo $row['id']; ?>" class="btn btn-xs btn-default">Detail</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php echo getPaginierung($gesamt, $seite, ITEMS_PER_PAGE, 'index.php?page=lieferungen&carrier=' . urlencode($carrier_filter) . '&status=' . urlencode($status_filter)); ?>

    <p class="text-muted small"><?php echo $gesamt; ?> Lieferung(en) gesamt.</p>
</div>

<?php require_once '../includes/footer.php'; ?>

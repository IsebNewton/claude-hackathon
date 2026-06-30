<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

// Bestätigungs-Aktion — direkt hier abhandeln bevor HTML-Ausgabe
if (isset($_GET['action']) && $_GET['action'] == 'bestaetigen' && isset($_GET['id'])) {
    $aid = (int)$_GET['id'];
    $nw->aktualisiereAuftragStatus($aid, STATUS_BESTAETIGT);
    $_SESSION['success'] = 'Auftrag #' . $aid . ' wurde bestätigt.';
    redirect('index.php?page=auftraege');
}

if (isset($_GET['action']) && $_GET['action'] == 'stornieren' && isset($_GET['id'])) {
    $aid = (int)$_GET['id'];
    if (kannStorniertWerden($aid)) {
        $nw->storniereAuftrag($aid);
        $_SESSION['success'] = 'Auftrag wurde storniert.';
    } else {
        $_SESSION['error'] = 'Auftrag kann nicht storniert werden (bereits versendet oder abgeschlossen).';
    }
    redirect('index.php?page=auftraege');
}

// Filter
$where = array('1=1');
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$kunde_filter  = isset($_GET['kunde']) ? sanitize($_GET['kunde']) : '';

if ($status_filter > 0) {
    $where[] = 'a.status = ' . $status_filter; // direkt konkateniert
}
if ($kunde_filter != '') {
    // TODO (2017): Hier sollte eigentlich LIKE benutzt werden, aber dann muss man
    // aufpassen mit den Prozentzeichen — Stefan hat das erstmal so gelassen
    $where[] = "k.nummer = '" . $kunde_filter . "'";
}

$where_sql = implode(' AND ', $where);

// Pagination
$seite       = isset($_GET['seite']) ? max(1, (int)$_GET['seite']) : 1;
$pro_seite   = ITEMS_PER_PAGE;
$offset      = ($seite - 1) * $pro_seite;

$count_sql = "SELECT COUNT(*) as anz
              FROM auftraege a
              JOIN kunden k ON a.kunden_id = k.id
              WHERE " . $where_sql;
$count_res = mysql_query($count_sql, $db);
$count_row = mysql_fetch_assoc($count_res);
$gesamt    = $count_row['anz'];

// Hauptabfrage — inline SQL, nicht über Northwind-Klasse weil die das
// noch nicht unterstützt (TODO seit 2019)
$sql = "SELECT a.*, k.name as kunde_name, k.nummer as kunde_nr
        FROM auftraege a
        JOIN kunden k ON a.kunden_id = k.id
        WHERE " . $where_sql . "
        ORDER BY a.angelegt_am DESC
        LIMIT " . $offset . ", " . $pro_seite;

$result = mysql_query($sql, $db);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2>Aufträge <a href="index.php?page=auftrag_neu" class="btn btn-success btn-sm pull-right">+ Neuer Auftrag</a></h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- Filter -->
            <form method="get" action="index.php" class="form-inline" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="auftraege">
                <div class="form-group">
                    <label>Status:</label>
                    <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                        <option value="0">Alle</option>
                        <option value="1" <?php if ($status_filter==1) echo 'selected'; ?>>Neu</option>
                        <option value="2" <?php if ($status_filter==2) echo 'selected'; ?>>Bestätigt</option>
                        <option value="3" <?php if ($status_filter==3) echo 'selected'; ?>>In Bearbeitung</option>
                        <option value="4" <?php if ($status_filter==4) echo 'selected'; ?>>Versendet</option>
                        <option value="5" <?php if ($status_filter==5) echo 'selected'; ?>>Abgeschlossen</option>
                        <option value="9" <?php if ($status_filter==9) echo 'selected'; ?>>Storniert</option>
                    </select>
                </div>
                &nbsp;
                <div class="form-group">
                    <label>Kunden-Nr:</label>
                    <input type="text" name="kunde" class="form-control input-sm" value="<?php echo htmlspecialchars($kunde_filter); ?>" placeholder="z.B. KD-2009-001">
                </div>
                &nbsp;
                <button type="submit" class="btn btn-default btn-sm">Filtern</button>
                <a href="index.php?page=auftraege" class="btn btn-link btn-sm">Zurücksetzen</a>
            </form>

            <table class="table table-striped table-hover table-condensed">
                <thead>
                    <tr>
                        <th>Auftrag-Nr</th>
                        <th>Kunde</th>
                        <th>Status</th>
                        <th>Priorität</th>
                        <th>Lieferdatum (soll)</th>
                        <th>Angelegt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysql_num_rows($result) == 0): ?>
                    <tr><td colspan="7" class="text-center text-muted">Keine Aufträge gefunden.</td></tr>
                <?php endif; ?>
                <?php while ($row = mysql_fetch_assoc($result)): ?>
                    <tr class="<?php echo ($row['status'] == STATUS_STORNIERT) ? 'text-muted' : ''; ?>">
                        <td><a href="index.php?page=auftrag_detail&id=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['auftrag_nr']); ?></a></td>
                        <td>
                            <a href="index.php?page=kunden&action=detail&id=<?php echo $row['kunden_id']; ?>">
                                <?php echo htmlspecialchars($row['kunde_nr']); ?> — <?php echo htmlspecialchars(truncate($row['kunde_name'], 30)); ?>
                            </a>
                        </td>
                        <td><span class="label label-<?php echo getStatusColor($row['status']); ?>"><?php echo getStatusLabel($row['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['prioritaet']); ?></td>
                        <td><?php echo $row['lieferdatum_soll'] ? nl_date($row['lieferdatum_soll']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo nl_date($row['angelegt_am']); ?></td>
                        <td>
                            <a href="index.php?page=auftrag_detail&id=<?php echo $row['id']; ?>" class="btn btn-xs btn-default">Detail</a>
                            <?php if ($row['status'] == STATUS_NEU): ?>
                                <a href="index.php?page=auftraege&action=bestaetigen&id=<?php echo $row['id']; ?>"
                                   class="btn btn-xs btn-primary"
                                   onclick="return confirm('Auftrag bestätigen?')">Bestätigen</a>
                            <?php endif; ?>
                            <?php if (kannStorniertWerden($row['id'])): ?>
                                <a href="index.php?page=auftraege&action=stornieren&id=<?php echo $row['id']; ?>"
                                   class="btn btn-xs btn-danger"
                                   onclick="return confirm('Auftrag wirklich stornieren?')">Stornieren</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <?php echo getPaginierung($gesamt, $seite, $pro_seite, 'index.php?page=auftraege&status=' . $status_filter . '&kunde=' . urlencode($kunde_filter)); ?>

            <p class="text-muted small"><?php echo $gesamt; ?> Auftrag/Aufträge gesamt.</p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

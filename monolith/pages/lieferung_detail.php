<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Keine Lieferung angegeben.'); }

$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $neuer_status = sanitize($_POST['neuer_status']);
    $erlaubte     = array('erstellt','abgeholt','unterwegs','zugestellt','zurueck');

    if (in_array($neuer_status, $erlaubte)) {
        // Northwind-Methode aufrufen
        $nw->aktualisiereLieferstatus($id, $neuer_status);

        if ($neuer_status == 'zugestellt') {
            // Hinweis: der Trigger tr_lieferung_zugestellt_after_update setzt
            // auftraege.status = 4 in der DB. Wir versuchen das hier nochmal
            // über PHP, aber der Trigger macht es schon — doppelter Update ist
            // harmlos, aber verwirrend wenn man den Code debuggt.
            // TODO: klären ob wir das über Trigger oder PHP machen wollen — 2019
            $liefrow = mysql_query("SELECT auftrag_id FROM lieferungen WHERE id = " . $id, $db);
            $liefdata = mysql_fetch_assoc($liefrow);
            if ($liefdata) {
                // Wenn Status noch nicht 4 oder 5 ist, setzen
                $aufrow = mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$liefdata['auftrag_id'], $db);
                $aufdata = mysql_fetch_assoc($aufrow);
                if ($aufdata && $aufdata['status'] < STATUS_VERSENDET) {
                    $nw->aktualisiereAuftragStatus($liefdata['auftrag_id'], STATUS_VERSENDET);
                }
            }
            $hinweis = 'Lieferung als zugestellt markiert. Auftragsstatus wurde automatisch aktualisiert.';
        }

        // Tracking-Nummer und -URL bei "abgeholt" setzen
        if ($neuer_status == 'abgeholt' && isset($_POST['tracking_nummer']) && $_POST['tracking_nummer'] != '') {
            $tn  = sanitize($_POST['tracking_nummer']);
            $lrow = mysql_query("SELECT carrier_code FROM lieferungen WHERE id = " . $id, $db);
            $ldata = mysql_fetch_assoc($lrow);
            $turl = '';
            if ($ldata) {
                switch ($ldata['carrier_code']) {
                    case 'DHL': $turl = 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=' . urlencode($tn); break;
                    case 'DPD': $turl = 'https://tracking.dpd.de/status/de_DE/parcel/' . urlencode($tn); break;
                    case 'UPS': $turl = 'https://www.ups.com/track?loc=de_DE&tracknum=' . urlencode($tn); break;
                    case 'GLS': $turl = 'https://gls-group.eu/DE/de/paketverfolgung?match=' . urlencode($tn); break;
                }
            }
            // Direkt SQL — kein Update-Methode in Northwind dafür
            mysql_query("UPDATE lieferungen SET tracking_nummer = '" . $tn . "', tracking_url = '" . sanitize($turl) . "', versanddatum = CURDATE() WHERE id = " . $id, $db);
        }

        if (!$hinweis) {
            $hinweis = 'Status aktualisiert.';
        }
        $_SESSION['success'] = $hinweis;
        redirect('index.php?page=lieferung_detail&id=' . $id);
    }
}

// Lieferung laden — inline SQL weil getLieferung() nicht alle JOINs macht
$sql = "SELECT l.*, a.auftrag_nr, a.id as auftrag_id, a.status as auftrag_status,
               k.name as kunde_name, k.id as kunden_id
        FROM lieferungen l
        JOIN auftraege a ON l.auftrag_id = a.id
        JOIN kunden k ON a.kunden_id = k.id
        WHERE l.id = " . $id;
$result = mysql_query($sql, $db);
$lieferung = mysql_fetch_assoc($result);

if (!$lieferung) { die('Lieferung nicht gefunden.'); }

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <ol class="breadcrumb">
        <li><a href="index.php?page=lieferungen">Lieferungen</a></li>
        <li><a href="index.php?page=auftrag_detail&id=<?php echo $lieferung['auftrag_id']; ?>"><?php echo htmlspecialchars($lieferung['auftrag_nr']); ?></a></li>
        <li class="active">Lieferung #<?php echo $id; ?></li>
    </ol>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Lieferdetails -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">Lieferung #<?php echo $id; ?></div>
                <div class="panel-body">
                    <table class="table table-condensed" style="margin:0;">
                        <tr><td><strong>Auftrag:</strong></td><td><a href="index.php?page=auftrag_detail&id=<?php echo $lieferung['auftrag_id']; ?>"><?php echo htmlspecialchars($lieferung['auftrag_nr']); ?></a></td></tr>
                        <tr><td><strong>Kunde:</strong></td><td><?php echo htmlspecialchars($lieferung['kunde_name']); ?></td></tr>
                        <tr><td><strong>Carrier:</strong></td><td><?php echo htmlspecialchars(getCarrierName($lieferung['carrier_code'])); ?></td></tr>
                        <tr><td><strong>Status:</strong></td><td><?php echo htmlspecialchars($lieferung['status']); ?></td></tr>
                        <tr>
                            <td><strong>Tracking:</strong></td>
                            <td>
                                <?php if ($lieferung['tracking_url']): ?>
                                    <a href="<?php echo htmlspecialchars($lieferung['tracking_url']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($lieferung['tracking_nummer']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($lieferung['tracking_nummer'] ?: '—'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><td><strong>Gewicht:</strong></td><td><?php echo $lieferung['gewicht_kg'] ? number_format($lieferung['gewicht_kg'], 2, ',', '.') . ' kg' : '—'; ?></td></tr>
                        <tr><td><strong>Versanddatum:</strong></td><td><?php echo $lieferung['versanddatum']      ? nl_date($lieferung['versanddatum'])      : '—'; ?></td></tr>
                        <tr><td><strong>Zustellung soll:</strong></td><td><?php echo $lieferung['zustelldatum_soll'] ? nl_date($lieferung['zustelldatum_soll']) : '—'; ?></td></tr>
                        <tr><td><strong>Zustellung ist:</strong></td><td><?php echo $lieferung['zustelldatum_ist']  ? nl_date($lieferung['zustelldatum_ist'])  : '—'; ?></td></tr>
                        <tr><td><strong>Angelegt:</strong></td><td><?php echo nl_datetime($lieferung['angelegt_am']); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Empfängeradresse (falls abweichend) -->
            <?php if ($lieferung['empfaenger_name']): ?>
            <div class="panel panel-default">
                <div class="panel-heading">Empfängeradresse</div>
                <div class="panel-body">
                    <?php echo htmlspecialchars($lieferung['empfaenger_name']); ?><br>
                    <?php echo htmlspecialchars($lieferung['empfaenger_strasse']); ?><br>
                    <?php echo htmlspecialchars($lieferung['empfaenger_plz'] . ' ' . $lieferung['empfaenger_ort']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status-Update -->
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">Status aktualisieren</div>
                <div class="panel-body">
                    <form method="post" action="index.php?page=lieferung_detail&id=<?php echo $id; ?>">
                        <input type="hidden" name="action" value="update_status">
                        <div class="form-group">
                            <label>Neuer Status:</label>
                            <select name="neuer_status" class="form-control">
                                <option value="erstellt"   <?php echo $lieferung['status']=='erstellt'   ? 'selected' : ''; ?>>Erstellt</option>
                                <option value="abgeholt"   <?php echo $lieferung['status']=='abgeholt'   ? 'selected' : ''; ?>>Abgeholt</option>
                                <option value="unterwegs"  <?php echo $lieferung['status']=='unterwegs'  ? 'selected' : ''; ?>>Unterwegs</option>
                                <option value="zugestellt" <?php echo $lieferung['status']=='zugestellt' ? 'selected' : ''; ?>>Zugestellt</option>
                                <option value="zurueck"    <?php echo $lieferung['status']=='zurueck'    ? 'selected' : ''; ?>>Zurück</option>
                            </select>
                        </div>
                        <?php if ($lieferung['status'] == 'erstellt'): ?>
                        <div class="form-group">
                            <label>Tracking-Nr (bei Abholung):</label>
                            <input type="text" name="tracking_nummer" class="form-control"
                                   value="<?php echo htmlspecialchars($lieferung['tracking_nummer'] ?: ''); ?>">
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Status speichern</button>
                    </form>

                    <?php if ($lieferung['status'] == 'zugestellt'): ?>
                    <div class="alert alert-info" style="margin-top:15px;">
                        <small>
                            <strong>Hinweis:</strong> Bei "Zugestellt" wird der Auftragsstatus automatisch aktualisiert
                            (sowohl durch PHP als auch durch den Datenbank-Trigger — das ist redundant, aber harmlos).
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

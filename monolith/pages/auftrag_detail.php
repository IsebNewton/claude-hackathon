<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Kein Auftrag angegeben.'); }

// Aktionen abhandeln
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'bestaetigen') {
        $nw->aktualisiereAuftragStatus($id, STATUS_BESTAETIGT);
        $_SESSION['success'] = 'Auftrag bestätigt.';
        redirect('index.php?page=auftrag_detail&id=' . $id);
    }

    if ($action == 'stornieren') {
        if (kannStorniertWerden($id)) {
            $nw->storniereAuftrag($id);
            $_SESSION['success'] = 'Auftrag storniert.';
        } else {
            $_SESSION['error'] = 'Kann nicht storniert werden.';
        }
        redirect('index.php?page=auftrag_detail&id=' . $id);
    }

    if ($action == 'erstelle_rechnung') {
        $rid = $nw->erstelleRechnung($id);
        if ($rid) {
            $_SESSION['success'] = 'Rechnung wurde erstellt.';
            redirect('index.php?page=rechnung_detail&id=' . $rid);
        } else {
            $_SESSION['error'] = 'Fehler beim Erstellen der Rechnung (ggf. bereits vorhanden).';
            redirect('index.php?page=auftrag_detail&id=' . $id);
        }
    }

    if ($action == 'erstelle_lieferung') {
        $carrier = sanitize($_POST['carrier_code']);
        $lid     = $nw->erstelleLieferung($id, $carrier);
        if ($lid) {
            $_SESSION['success'] = 'Lieferung erstellt.';
        } else {
            $_SESSION['error'] = 'Fehler beim Erstellen der Lieferung.';
        }
        redirect('index.php?page=auftrag_detail&id=' . $id);
    }
}

// Auftragsdaten laden — manche Sachen über Northwind-Klasse, manche inline
// weil die Klasse nicht alles abdeckt (historisch gewachsen)
$auftrag = $nw->getAuftrag($id);
if (!$auftrag) { die('Auftrag nicht gefunden.'); }

$positionen = $nw->getAuftragPositionen($id);

// Kundeinfo inline — Northwind::getAuftrag() gibt nur kunden_id zurück, keinen Join
$sql_kunde = "SELECT k.*, k.nummer as kunde_nr FROM kunden k WHERE k.id = " . (int)$auftrag['kunden_id'];
$res_kunde = mysql_query($sql_kunde, $db);
$kunde     = mysql_fetch_assoc($res_kunde);

$lieferungen = $nw->getLieferungenFuerAuftrag($id);

// Rechnung — direkt abfragen weil Northwind keine "getRechnungFuerAuftrag"-Methode hat
// TODO: in Northwind-Klasse auslagern
$res_rechnung = mysql_query("SELECT id, rechnungs_nr, betrag_brutto, status FROM rechnungen WHERE auftrag_id = " . $id . " LIMIT 1", $db);
$rechnung     = $res_rechnung ? mysql_fetch_assoc($res_rechnung) : null;

// Summe
$summe = $nw->berechneAuftragSumme($id);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <ol class="breadcrumb">
                <li><a href="index.php?page=auftraege">Aufträge</a></li>
                <li class="active"><?php echo htmlspecialchars($auftrag['auftrag_nr']); ?></li>
            </ol>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($auftrag['status'] == STATUS_STORNIERT): ?>
        <div class="alert alert-warning"><strong>Dieser Auftrag wurde storniert.</strong> Es können keine weiteren Aktionen durchgeführt werden.</div>
    <?php endif; ?>

    <div class="row">
        <!-- Auftragskopf -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>Auftrag <?php echo htmlspecialchars($auftrag['auftrag_nr']); ?></strong>
                    <span class="label label-<?php echo getStatusColor($auftrag['status']); ?> pull-right">
                        <?php echo getStatusLabel($auftrag['status']); ?>
                    </span>
                </div>
                <div class="panel-body">
                    <table class="table table-condensed" style="margin:0;">
                        <tr><td><strong>Angelegt:</strong></td><td><?php echo nl_datetime($auftrag['angelegt_am']); ?></td></tr>
                        <tr><td><strong>Priorität:</strong></td><td><?php echo htmlspecialchars($auftrag['prioritaet']); ?></td></tr>
                        <tr><td><strong>Lieferdatum soll:</strong></td><td><?php echo $auftrag['lieferdatum_soll'] ? nl_date($auftrag['lieferdatum_soll']) : '—'; ?></td></tr>
                        <tr><td><strong>Lieferdatum ist:</strong></td><td><?php echo $auftrag['lieferdatum_ist'] ? nl_date($auftrag['lieferdatum_ist']) : '—'; ?></td></tr>
                        <?php if ($auftrag['bestaetigt_am']): ?>
                        <tr><td><strong>Bestätigt:</strong></td><td><?php echo nl_datetime($auftrag['bestaetigt_am']); ?></td></tr>
                        <?php endif; ?>
                        <?php if ($auftrag['bemerkungen']): ?>
                        <tr><td><strong>Bemerkungen:</strong></td><td><?php echo nl2br(htmlspecialchars($auftrag['bemerkungen'])); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kundendaten -->
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading">Kunde</div>
                <div class="panel-body">
                    <a href="index.php?page=kunden&action=detail&id=<?php echo $kunde['id']; ?>">
                        <strong><?php echo htmlspecialchars($kunde['nummer']); ?></strong>
                    </a><br>
                    <?php echo htmlspecialchars($kunde['name']); ?><br>
                    <?php echo htmlspecialchars($kunde['strasse'] . ' ' . $kunde['hausnummer']); ?><br>
                    <?php echo htmlspecialchars($kunde['plz'] . ' ' . $kunde['ort']); ?><br>
                    <?php if ($kunde['email']): ?>
                        <a href="mailto:<?php echo htmlspecialchars($kunde['email']); ?>"><?php echo htmlspecialchars($kunde['email']); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading">Aktionen</div>
                <div class="panel-body">
                    <?php if ($auftrag['status'] == STATUS_NEU): ?>
                    <form method="post" action="index.php?page=auftrag_detail&id=<?php echo $id; ?>">
                        <input type="hidden" name="action" value="bestaetigen">
                        <button type="submit" class="btn btn-primary btn-block">Bestätigen</button>
                    </form>
                    <br>
                    <?php endif; ?>

                    <?php if ($auftrag['status'] >= STATUS_BESTAETIGT && $auftrag['status'] != STATUS_STORNIERT): ?>
                    <form method="post" action="index.php?page=auftrag_detail&id=<?php echo $id; ?>">
                        <input type="hidden" name="action" value="erstelle_lieferung">
                        <div class="form-group">
                            <select name="carrier_code" class="form-control input-sm">
                                <option value="DHL">DHL</option>
                                <option value="DPD">DPD</option>
                                <option value="UPS">UPS</option>
                                <option value="GLS">GLS</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-default btn-block btn-sm">+ Lieferung erstellen</button>
                    </form>
                    <br>
                    <?php endif; ?>

                    <?php if (kannStorniertWerden($id) && $auftrag['status'] != STATUS_STORNIERT): ?>
                    <form method="post" action="index.php?page=auftrag_detail&id=<?php echo $id; ?>"
                          onsubmit="return confirm('Auftrag wirklich stornieren?')">
                        <input type="hidden" name="action" value="stornieren">
                        <button type="submit" class="btn btn-danger btn-block btn-sm">Stornieren</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Positionen -->
    <div class="row">
        <div class="col-md-12">
            <h4>Positionen</h4>
            <table class="table table-condensed table-bordered">
                <thead>
                    <tr><th>#</th><th>Artikel</th><th>Menge</th><th>Einzelpreis netto</th><th>Gesamt netto</th><th>MwSt %</th><th>Gesamt brutto</th></tr>
                </thead>
                <tbody>
                <?php
                $sum_netto = 0;
                $sum_brutto = 0;
                $pos_nr = 1;
                foreach ($positionen as $pos):
                    $netto  = $pos['menge'] * $pos['einzelpreis_netto'];
                    $mwst   = berechneMwst($netto, $pos['mwst_satz']);
                    $brutto = $netto + $mwst;
                    $sum_netto  += $netto;
                    $sum_brutto += $brutto;
                ?>
                    <tr>
                        <td><?php echo $pos_nr++; ?></td>
                        <td><?php echo htmlspecialchars($pos['bezeichnung']); ?></td>
                        <td><?php echo number_format($pos['menge'], 2, ',', '.'); ?></td>
                        <td><?php echo formatEuro($pos['einzelpreis_netto']); ?></td>
                        <td><?php echo formatEuro($netto); ?></td>
                        <td><?php echo number_format($pos['mwst_satz'], 0); ?> %</td>
                        <td><?php echo formatEuro($brutto); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Summe:</strong></td>
                        <td><strong><?php echo formatEuro($sum_netto); ?></strong></td>
                        <td></td>
                        <td><strong><?php echo formatEuro($sum_brutto); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Lieferungen -->
    <div class="row">
        <div class="col-md-12">
            <h4>Lieferungen</h4>
            <?php if (empty($lieferungen)): ?>
                <p class="text-muted">Keine Lieferungen vorhanden.</p>
            <?php else: ?>
            <table class="table table-condensed table-bordered">
                <thead><tr><th>ID</th><th>Carrier</th><th>Tracking</th><th>Status</th><th>Versand</th><th>Zustellung soll</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lieferungen as $lief): ?>
                    <tr>
                        <td><?php echo $lief['id']; ?></td>
                        <td><?php echo htmlspecialchars(getCarrierName($lief['carrier_code'])); ?></td>
                        <td>
                            <?php if ($lief['tracking_nummer'] && $lief['tracking_url']): ?>
                                <a href="<?php echo htmlspecialchars($lief['tracking_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($lief['tracking_nummer']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted"><?php echo htmlspecialchars($lief['tracking_nummer'] ?: '—'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lief['status']); ?></td>
                        <td><?php echo $lief['versanddatum'] ? nl_date($lief['versanddatum']) : '—'; ?></td>
                        <td><?php echo $lief['zustelldatum_soll'] ? nl_date($lief['zustelldatum_soll']) : '—'; ?></td>
                        <td><a href="index.php?page=lieferung_detail&id=<?php echo $lief['id']; ?>" class="btn btn-xs btn-default">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rechnung -->
    <div class="row">
        <div class="col-md-12">
            <h4>Rechnung</h4>
            <?php if ($rechnung): ?>
                <p>
                    <a href="index.php?page=rechnung_detail&id=<?php echo $rechnung['id']; ?>">
                        <?php echo htmlspecialchars($rechnung['rechnungs_nr']); ?>
                    </a>
                    — <?php echo formatEuro($rechnung['betrag_brutto']); ?>
                    — <span class="label label-default"><?php echo htmlspecialchars($rechnung['status']); ?></span>
                </p>
            <?php elseif ($auftrag['status'] != STATUS_STORNIERT): ?>
                <form method="post" action="index.php?page=auftrag_detail&id=<?php echo $id; ?>">
                    <input type="hidden" name="action" value="erstelle_rechnung">
                    <button type="submit" class="btn btn-default">Rechnung erstellen</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Auftrag storniert — keine Rechnung möglich.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>

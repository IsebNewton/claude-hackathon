<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Keine Rechnung angegeben.'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'sende_rechnung') {
        $ok = $nw->sendeRechnung($id);
        $_SESSION[($ok ? 'success' : 'error')] = $ok ? 'Rechnung wurde per E-Mail versendet.' : 'Fehler beim Senden der Rechnung.';
        redirect('index.php?page=rechnung_detail&id=' . $id);
    }
}

// Rechnung laden — inline SQL mit allen JOINs weil Northwind::getRechnung() zu wenig zurückgibt
$sql = "SELECT r.*,
               a.auftrag_nr, a.id as auftrag_id, a.status as auftrag_status,
               k.name as kunde_name, k.nummer as kunde_nr, k.email as kunde_email,
               k.strasse, k.hausnummer, k.plz, k.ort
        FROM rechnungen r
        JOIN auftraege a ON r.auftrag_id = a.id
        JOIN kunden k    ON r.kunden_id  = k.id
        WHERE r.id = " . $id;
$res      = mysql_query($sql, $db);
$rechnung = mysql_fetch_assoc($res);
if (!$rechnung) { die('Rechnung nicht gefunden.'); }

// Zahlungen laden
$sql_z    = "SELECT * FROM zahlungen WHERE rechnung_id = " . $id . " ORDER BY datum ASC";
$res_z    = mysql_query($sql_z, $db);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <ol class="breadcrumb">
        <li><a href="index.php?page=rechnungen">Rechnungen</a></li>
        <li class="active"><?php echo htmlspecialchars($rechnung['rechnungs_nr']); ?></li>
    </ol>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Rechnungsdaten -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong><?php echo htmlspecialchars($rechnung['rechnungs_nr']); ?></strong>
                    &nbsp;
                    <?php
                    $sbadge = array('offen'=>'warning','teilbezahlt'=>'info','bezahlt'=>'success','storniert'=>'default','gemahnt'=>'danger');
                    $sc = isset($sbadge[$rechnung['status']]) ? $sbadge[$rechnung['status']] : 'default';
                    ?>
                    <span class="label label-<?php echo $sc; ?>"><?php echo htmlspecialchars($rechnung['status']); ?></span>
                </div>
                <div class="panel-body">
                    <table class="table table-condensed" style="margin:0;">
                        <tr><td width="140"><strong>Auftrag:</strong></td>
                            <td><a href="index.php?page=auftrag_detail&id=<?php echo $rechnung['auftrag_id']; ?>"><?php echo htmlspecialchars($rechnung['auftrag_nr']); ?></a></td></tr>
                        <tr><td><strong>Angelegt:</strong></td><td><?php echo nl_date($rechnung['angelegt_am']); ?></td></tr>
                        <tr><td><strong>Fällig am:</strong></td><td>
                            <?php
                            $heute = date('Y-m-d');
                            $ueberfaellig = ($rechnung['status'] == 'offen' && $rechnung['faellig_am'] < $heute);
                            echo nl_date($rechnung['faellig_am']);
                            if ($ueberfaellig) echo ' <span class="label label-danger">Überfällig</span>';
                            ?>
                        </td></tr>
                        <tr><td><strong>Betrag netto:</strong></td><td><?php echo formatEuro($rechnung['betrag_netto']); ?></td></tr>
                        <tr><td><strong>MwSt:</strong></td><td><?php echo formatEuro($rechnung['mwst_betrag']); ?></td></tr>
                        <tr><td><strong>Betrag brutto:</strong></td><td><strong><?php echo formatEuro($rechnung['betrag_brutto']); ?></strong></td></tr>
                        <tr><td><strong>Bezahlt:</strong></td><td><?php echo formatEuro($rechnung['bezahlter_betrag']); ?></td></tr>
                        <tr><td><strong>Offen:</strong></td><td><?php echo formatEuro($rechnung['betrag_brutto'] - $rechnung['bezahlter_betrag']); ?></td></tr>
                        <?php if ($rechnung['bezahlt_am']): ?>
                        <tr><td><strong>Bezahlt am:</strong></td><td><?php echo nl_datetime($rechnung['bezahlt_am']); ?></td></tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($rechnung['mahnstufe'] > 0): ?>
                    <div class="alert alert-warning" style="margin-top:10px;">
                        <strong>Mahnstufe <?php echo $rechnung['mahnstufe']; ?>/3</strong>
                        <?php if ($rechnung['letzte_mahnung']): ?>
                            — Letzte Mahnung: <?php echo nl_date($rechnung['letzte_mahnung']); ?>
                        <?php endif; ?>
                        <?php if ($rechnung['mahngebuehr'] > 0): ?>
                            — Mahngebühr: <?php echo formatEuro($rechnung['mahngebuehr']); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kunde + Aktionen -->
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading">Rechnungsempfänger</div>
                <div class="panel-body">
                    <strong><?php echo htmlspecialchars($rechnung['kunde_nr']); ?></strong><br>
                    <?php echo htmlspecialchars($rechnung['kunde_name']); ?><br>
                    <?php echo htmlspecialchars($rechnung['strasse'] . ' ' . $rechnung['hausnummer']); ?><br>
                    <?php echo htmlspecialchars($rechnung['plz'] . ' ' . $rechnung['ort']); ?><br>
                    <?php if ($rechnung['kunde_email']): ?>
                        <a href="mailto:<?php echo htmlspecialchars($rechnung['kunde_email']); ?>"><?php echo htmlspecialchars($rechnung['kunde_email']); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading">Aktionen</div>
                <div class="panel-body">
                    <?php if (in_array($rechnung['status'], array('offen','teilbezahlt'))): ?>
                        <a href="index.php?page=rechnung_zahlung&id=<?php echo $id; ?>" class="btn btn-primary btn-block">
                            Zahlung erfassen
                        </a>
                        <br>
                    <?php endif; ?>

                    <form method="post" action="index.php?page=rechnung_detail&id=<?php echo $id; ?>">
                        <input type="hidden" name="action" value="sende_rechnung">
                        <button type="submit" class="btn btn-default btn-block">
                            Rechnung senden (PDF per E-Mail)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Zahlungen -->
    <div class="row">
        <div class="col-md-12">
            <h4>Zahlungseingänge</h4>
            <?php if (mysql_num_rows($res_z) == 0): ?>
                <p class="text-muted">Noch keine Zahlungen erfasst.</p>
            <?php else: ?>
            <table class="table table-condensed table-bordered">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Betrag</th>
                        <th>Zahlungsart</th>
                        <th>BLZ</th>
                        <th>Kontonummer</th>
                        <th>BAV-Prüfung</th>
                        <th>Bemerkung</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($z = mysql_fetch_assoc($res_z)): ?>
                    <tr>
                        <td><?php echo nl_datetime($z['datum']); ?></td>
                        <td><?php echo formatEuro($z['betrag']); ?></td>
                        <td><?php echo htmlspecialchars($z['zahlungsart']); ?></td>
                        <td><?php echo htmlspecialchars($z['blz'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($z['kontonummer'] ?: '—'); ?></td>
                        <td>
                            <?php if ($z['bav_validiert'] === null): ?>
                                <span class="text-muted">—</span>
                            <?php elseif ($z['bav_validiert']): ?>
                                <span class="text-success">✓ Gültig</span>
                            <?php else: ?>
                                <span class="text-danger">✗ Ungültig</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(truncate($z['bemerkung'] ?: '', 50)); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

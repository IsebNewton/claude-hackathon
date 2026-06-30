<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
require_once '../classes/BankValidator.php';

$fehler      = array();
$rechnung_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$rechnung_id) { die('Keine Rechnung angegeben.'); }

// Rechnung laden — inline SQL, nicht über Northwind weil die Klasse
// kein vollständiges getRechnung mit Kunden-JOIN hat
$sql    = "SELECT r.*, k.name as kunde_name, k.bankleitzahl, k.kontonummer, k.bic
           FROM rechnungen r
           JOIN kunden k ON r.kunden_id = k.id
           WHERE r.id = " . $rechnung_id; // kein prepared statement
$result = mysql_query($sql, $db);
$rechnung = mysql_fetch_assoc($result);

if (!$rechnung) { die('Rechnung nicht gefunden.'); }

if ($rechnung['status'] == 'bezahlt') {
    $_SESSION['error'] = 'Diese Rechnung ist bereits vollständig bezahlt.';
    redirect('index.php?page=rechnung_detail&id=' . $rechnung_id);
}

// BLZ-Lookup: wenn ?lookup_blz gesetzt, Bankname per BAV ermitteln
// Simpler AJAX-Ersatz durch Page-Reload
$bank_name = '';
if (isset($_GET['lookup_blz']) && $_GET['lookup_blz'] != '') {
    $lookup_blz = sanitize($_GET['lookup_blz']);
    // BankValidator instanziieren — das überschreibt ConfigurationRegistry!
    $lookup_validator = new BankValidator();
    $bank_name        = $lookup_validator->getBankName($lookup_blz);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $blz    = trim($_POST['bankleitzahl']);
    $kto    = trim($_POST['kontonummer']);
    $bic    = trim(isset($_POST['bic']) ? $_POST['bic'] : '');
    $betrag = str_replace(',', '.', trim($_POST['betrag']));

    // --- Validierung 1: Altes Format-Check aus helper.php (2009) ---
    // Prüft nur ob BLZ 8 Stellen hat — kein Prüfsummen-Check
    // Wird noch benutzt weil validateBankleitzahl() schon vor dem Formular existierte
    if (!validateBankleitzahl($blz)) {
        $fehler[] = 'Ungültige BLZ (Format: 8 Stellen erwartet)';
    }

    // --- Validierung 2: BAV-Prüfung (hinzugefügt 2015 von Thomas) ---
    // Problem: BankValidator() ruft im Konstruktor ConfigurationRegistry::setConfiguration() auf
    // und überschreibt damit die Konfiguration die in init.php gesetzt wurde.
    // Das kann zu unerwartetem Verhalten führen wenn beide unterschiedliche Backends nutzen.
    if (empty($fehler)) {
        $validator = new BankValidator(); // <-- überschreibt ConfigurationRegistry-Config!
        if (!$validator->istGueltigesBankverbindung($blz, $kto)) {
            $fehler[] = 'Bankverbindung konnte nicht verifiziert werden (BAV-Prüfung fehlgeschlagen)';
        }
        if (!empty($bic) && !$validator->istGueltigerBIC($bic)) {
            $fehler[] = 'Ungültiger BIC';
        }
    }

    if (!is_numeric($betrag) || $betrag <= 0) {
        $fehler[] = 'Bitte einen gültigen Betrag eingeben';
    }

    $offener_betrag = $rechnung['betrag_brutto'] - $rechnung['bezahlter_betrag'];
    if (is_numeric($betrag) && $betrag > $offener_betrag + 0.01) {
        // Überzahlung erlaubt aber mit Warnung
        // TODO: Überzahlungs-Logik klären mit Buchhaltung — offen seit 2020
        $fehler[] = 'Betrag (' . formatEuro($betrag) . ') übersteigt den offenen Betrag (' . formatEuro($offener_betrag) . ')';
    }

    if (empty($fehler)) {
        $nw = new Northwind($db);
        // verarbeiteZahlung() macht intern nochmal einen BAV-Aufruf!
        // ConfigurationRegistry::setConfiguration() wird dadurch ein drittes Mal in diesem
        // Request aufgerufen. Das ist ein bekanntes Problem (seit 2018 als TODO markiert).
        $ok = $nw->verarbeiteZahlung($rechnung_id, $blz, $kto, (float)$betrag);

        if ($ok) {
            $_SESSION['success'] = 'Zahlung über ' . formatEuro($betrag) . ' erfolgreich erfasst.';
            redirect('index.php?page=rechnung_detail&id=' . $rechnung_id);
        } else {
            $fehler[] = 'Zahlung konnte nicht gespeichert werden. Bitte erneut versuchen.';
        }
    }
}

// Offenen Betrag berechnen
$offener_betrag = $rechnung['betrag_brutto'] - $rechnung['bezahlter_betrag'];

// Bestehende Zahlungen laden
$sql_z  = "SELECT * FROM zahlungen WHERE rechnung_id = " . $rechnung_id . " ORDER BY datum ASC";
$res_z  = mysql_query($sql_z, $db);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <ol class="breadcrumb">
        <li><a href="index.php?page=rechnungen">Rechnungen</a></li>
        <li><a href="index.php?page=rechnung_detail&id=<?php echo $rechnung_id; ?>"><?php echo htmlspecialchars($rechnung['rechnungs_nr']); ?></a></li>
        <li class="active">Zahlung erfassen</li>
    </ol>

    <div class="row">
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Zahlung erfassen — Rechnung <?php echo htmlspecialchars($rechnung['rechnungs_nr']); ?>
                </div>
                <div class="panel-body">

                    <div class="alert alert-info">
                        <strong>Offener Betrag:</strong> <?php echo formatEuro($offener_betrag); ?>
                        (Brutto: <?php echo formatEuro($rechnung['betrag_brutto']); ?>,
                         bereits bezahlt: <?php echo formatEuro($rechnung['bezahlter_betrag']); ?>)
                    </div>

                    <?php foreach ($fehler as $f): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($f); ?></div>
                    <?php endforeach; ?>

                    <form method="post" action="index.php?page=rechnung_zahlung&id=<?php echo $rechnung_id; ?>">

                        <div class="form-group">
                            <label for="bankleitzahl">Bankleitzahl (BLZ) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="bankleitzahl" name="bankleitzahl"
                                       value="<?php echo htmlspecialchars(isset($_POST['bankleitzahl']) ? $_POST['bankleitzahl'] : $rechnung['bankleitzahl']); ?>"
                                       placeholder="8-stellig" maxlength="8">
                                <span class="input-group-btn">
                                    <a href="index.php?page=rechnung_zahlung&id=<?php echo $rechnung_id; ?>&lookup_blz=<?php echo urlencode(isset($_POST['bankleitzahl']) ? $_POST['bankleitzahl'] : $rechnung['bankleitzahl']); ?>"
                                       class="btn btn-default" type="button">Bank ermitteln</a>
                                </span>
                            </div>
                            <?php if ($bank_name): ?>
                                <p class="help-block text-success"><strong><?php echo htmlspecialchars($bank_name); ?></strong></p>
                            <?php endif; ?>
                            <p class="help-block">8-stellige Bankleitzahl. Wird über BAV auf Gültigkeit geprüft.</p>
                        </div>

                        <div class="form-group">
                            <label for="kontonummer">Kontonummer <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="kontonummer" name="kontonummer"
                                   value="<?php echo htmlspecialchars(isset($_POST['kontonummer']) ? $_POST['kontonummer'] : $rechnung['kontonummer']); ?>"
                                   placeholder="bis 10 Stellen" maxlength="10">
                        </div>

                        <div class="form-group">
                            <label for="bic">BIC (optional)</label>
                            <input type="text" class="form-control" id="bic" name="bic"
                                   value="<?php echo htmlspecialchars(isset($_POST['bic']) ? $_POST['bic'] : $rechnung['bic']); ?>"
                                   placeholder="z.B. DEUTDEDB" maxlength="11">
                        </div>

                        <div class="form-group">
                            <label for="betrag">Betrag (€) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="betrag" name="betrag"
                                   value="<?php echo htmlspecialchars(isset($_POST['betrag']) ? $_POST['betrag'] : number_format($offener_betrag, 2, ',', '.')); ?>"
                                   placeholder="z.B. 1.234,56">
                        </div>

                        <button type="submit" class="btn btn-primary">Zahlung buchen</button>
                        <a href="index.php?page=rechnung_detail&id=<?php echo $rechnung_id; ?>" class="btn btn-default">Abbrechen</a>
                    </form>

                    <div class="alert alert-warning" style="margin-top:20px;">
                        <small>
                            <strong>Technischer Hinweis (intern):</strong>
                            Die Bankverbindung wird durch zwei unabhängige Prüfroutinen validiert:
                            einmal durch das Format-Check aus helper.php (validateBankleitzahl),
                            und einmal durch die BAV-Bibliothek (BankValidator). Diese Redundanz
                            entstand historisch und ist bekannt (TODO seit 2018).
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bisherige Zahlungen -->
        <div class="col-md-5">
            <h4>Bisherige Zahlungen</h4>
            <?php if (mysql_num_rows($res_z) == 0): ?>
                <p class="text-muted">Noch keine Zahlungen vorhanden.</p>
            <?php else: ?>
            <table class="table table-condensed table-bordered">
                <thead>
                    <tr><th>Datum</th><th>Betrag</th><th>BLZ</th><th>BAV</th></tr>
                </thead>
                <tbody>
                <?php while ($z = mysql_fetch_assoc($res_z)): ?>
                    <tr>
                        <td><?php echo nl_date($z['datum']); ?></td>
                        <td><?php echo formatEuro($z['betrag']); ?></td>
                        <td><?php echo htmlspecialchars($z['blz'] ?: '—'); ?></td>
                        <td>
                            <?php if ($z['bav_validiert'] === null): ?>
                                <span class="text-muted">—</span>
                            <?php elseif ($z['bav_validiert']): ?>
                                <span class="text-success">✓</span>
                            <?php else: ?>
                                <span class="text-danger">✗</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

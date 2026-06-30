<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
$nw = new Northwind($db);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Session initialisieren
if (!isset($_SESSION['neuer_auftrag'])) {
    $_SESSION['neuer_auftrag'] = array(
        'kunden_id'  => null,
        'positionen' => array(),
    );
}

$fehler = array();

// === SCHRITT 1: Kunden auswählen ===
if ($step == 1 && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kunden_id'])) {
    $kid = (int)$_POST['kunden_id'];
    if ($kid > 0) {
        // Kurz prüfen ob Kunde existiert
        $res = mysql_query("SELECT id, name FROM kunden WHERE id = " . $kid . " AND aktiv = 1", $db);
        if (mysql_num_rows($res) > 0) {
            $_SESSION['neuer_auftrag']['kunden_id'] = $kid;
            redirect('index.php?page=auftrag_neu&step=2');
        } else {
            $fehler[] = 'Kunde nicht gefunden.';
        }
    } else {
        $fehler[] = 'Bitte einen Kunden auswählen.';
    }
}

// === SCHRITT 2: Positionen ===
if ($step == 2) {
    // Position hinzufügen
    if (isset($_POST['action']) && $_POST['action'] == 'add_position') {
        $artikel_id    = (int)$_POST['artikel_id'];
        $menge         = str_replace(',', '.', $_POST['menge']);
        $einzelpreis   = str_replace(',', '.', $_POST['einzelpreis']);

        if ($artikel_id > 0 && is_numeric($menge) && $menge > 0) {
            $art_res = mysql_query("SELECT name, preis_netto, mwst_satz FROM artikel WHERE id = " . $artikel_id . " AND aktiv = 1", $db);
            if ($art_res && mysql_num_rows($art_res) > 0) {
                $art = mysql_fetch_assoc($art_res);
                // Einzelpreis übernehmen wenn nicht manuell eingegeben
                if (!is_numeric($einzelpreis) || $einzelpreis <= 0) {
                    $einzelpreis = $art['preis_netto'];
                }
                $_SESSION['neuer_auftrag']['positionen'][] = array(
                    'artikel_id'      => $artikel_id,
                    'bezeichnung'     => $art['name'],
                    'menge'           => (float)$menge,
                    'einzelpreis_netto' => (float)$einzelpreis,
                    'mwst_satz'       => (float)$art['mwst_satz'],
                );
            }
        }
        redirect('index.php?page=auftrag_neu&step=2');
    }

    // Position entfernen
    if (isset($_POST['action']) && $_POST['action'] == 'remove_position') {
        $idx = (int)$_POST['idx'];
        if (isset($_SESSION['neuer_auftrag']['positionen'][$idx])) {
            array_splice($_SESSION['neuer_auftrag']['positionen'], $idx, 1);
        }
        redirect('index.php?page=auftrag_neu&step=2');
    }
}

// === SCHRITT 3: Bestätigen & Absenden ===
if ($step == 3 && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bestaetigen'])) {
    $kunden_id  = $_SESSION['neuer_auftrag']['kunden_id'];
    $positionen = $_SESSION['neuer_auftrag']['positionen'];

    if (empty($positionen)) {
        $fehler[] = 'Bitte mindestens eine Position hinzufügen.';
        $step = 2;
    } elseif (!$kunden_id) {
        $fehler[] = 'Kein Kunde ausgewählt. Bitte von vorne beginnen.';
        $step = 1;
    } else {
        // Priorität und Lieferdatum aus POST
        $prioritaet      = in_array($_POST['prioritaet'], array('normal','hoch','express')) ? $_POST['prioritaet'] : 'normal';
        $lieferdatum_soll = isset($_POST['lieferdatum_soll']) && $_POST['lieferdatum_soll'] != '' ? $_POST['lieferdatum_soll'] : null;
        $bemerkungen      = sanitize($_POST['bemerkungen']);

        // Bankdaten-Check — wurde 2016 von Max hinzugefügt weil er sicher gehen wollte
        // dass der Kunde gültige Bankdaten hat bevor ein Auftrag angelegt wird.
        // Das macht eigentlich keinen Sinn hier, aber es steht nun mal drin.
        // TODO (2016): raus nehmen wenn Zeit ist — Max
        $kunden_res = mysql_query("SELECT bankleitzahl, kontonummer FROM kunden WHERE id = " . (int)$kunden_id, $db);
        $kunden_row = mysql_fetch_assoc($kunden_res);
        if ($kunden_row['bankleitzahl'] != '' && !validateBankleitzahl($kunden_row['bankleitzahl'])) {
            // Nur warnen, nicht blockieren
            logError('Auftrag fuer Kunden mit ungueltiger BLZ angelegt: kunden_id=' . $kunden_id);
        }

        $auftrag_id = $nw->erstelleAuftrag($kunden_id, $positionen, $prioritaet, $lieferdatum_soll, $bemerkungen);

        if ($auftrag_id) {
            unset($_SESSION['neuer_auftrag']);
            $_SESSION['success'] = 'Auftrag erfolgreich angelegt.';
            redirect('index.php?page=auftrag_detail&id=' . $auftrag_id);
        } else {
            $fehler[] = 'Fehler beim Anlegen des Auftrags. Bitte erneut versuchen.';
        }
    }
}

require_once '../includes/header.php';

// Kundenliste für Step 1 (Suchfunktion)
$kunden_suche   = isset($_GET['kunden_suche']) ? sanitize($_GET['kunden_suche']) : '';
$kunden_result  = null;
if ($step == 1 && $kunden_suche != '') {
    $kunden_result = mysql_query(
        "SELECT id, nummer, name, ort FROM kunden
         WHERE aktiv = 1
           AND (name LIKE '%" . $kunden_suche . "%' OR nummer LIKE '%" . $kunden_suche . "%')
         ORDER BY name ASC LIMIT 20", $db
    );
}

// Artikelliste für Step 2
$artikel_result = null;
if ($step == 2) {
    $artikel_result = mysql_query("SELECT id, sku, name, preis_netto FROM artikel WHERE aktiv = 1 ORDER BY name ASC", $db);
}
?>

<div class="container-fluid">
    <h2>Neuer Auftrag</h2>

    <!-- Wizard-Schritte -->
    <div class="row" style="margin-bottom:20px;">
        <div class="col-md-12">
            <ol class="breadcrumb">
                <li class="<?php echo $step==1 ? 'active' : ''; ?>">Schritt 1: Kunde</li>
                <li class="<?php echo $step==2 ? 'active' : ''; ?>">Schritt 2: Positionen</li>
                <li class="<?php echo $step==3 ? 'active' : ''; ?>">Schritt 3: Bestätigen</li>
            </ol>
        </div>
    </div>

    <?php foreach ($fehler as $f): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($f); ?></div>
    <?php endforeach; ?>

    <?php if ($step == 1): ?>
    <!-- SCHRITT 1 -->
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">Kunden suchen</div>
                <div class="panel-body">
                    <form method="get" action="index.php" class="form-inline">
                        <input type="hidden" name="page" value="auftrag_neu">
                        <input type="hidden" name="step" value="1">
                        <div class="form-group">
                            <input type="text" name="kunden_suche" class="form-control"
                                   placeholder="Name oder Kunden-Nr" value="<?php echo htmlspecialchars($kunden_suche); ?>">
                        </div>
                        <button type="submit" class="btn btn-default">Suchen</button>
                    </form>

                    <?php if ($kunden_result !== null): ?>
                    <hr>
                    <form method="post" action="index.php?page=auftrag_neu&step=1">
                        <table class="table table-condensed table-hover">
                            <thead><tr><th></th><th>Nr</th><th>Name</th><th>Ort</th></tr></thead>
                            <tbody>
                            <?php if (mysql_num_rows($kunden_result) == 0): ?>
                                <tr><td colspan="4" class="text-muted">Kein Treffer.</td></tr>
                            <?php endif; ?>
                            <?php while ($k = mysql_fetch_assoc($kunden_result)): ?>
                                <tr>
                                    <td><input type="radio" name="kunden_id" value="<?php echo $k['id']; ?>"></td>
                                    <td><?php echo htmlspecialchars($k['nummer']); ?></td>
                                    <td><?php echo htmlspecialchars($k['name']); ?></td>
                                    <td><?php echo htmlspecialchars($k['ort']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-primary">Weiter mit ausgewähltem Kunden →</button>
                    </form>
                    <?php elseif ($kunden_suche == ''): ?>
                        <p class="text-muted" style="margin-top:10px;">Bitte Suchbegriff eingeben.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($step == 2): ?>
    <!-- SCHRITT 2 -->
    <?php
    $kunden_id = $_SESSION['neuer_auftrag']['kunden_id'];
    $kunde_res = mysql_query("SELECT name, nummer FROM kunden WHERE id = " . (int)$kunden_id, $db);
    $kunde     = mysql_fetch_assoc($kunde_res);
    ?>
    <p>Kunde: <strong><?php echo htmlspecialchars($kunde['nummer'] . ' — ' . $kunde['name']); ?></strong>
       <a href="index.php?page=auftrag_neu&step=1" class="text-muted small">(ändern)</a>
    </p>

    <!-- Position hinzufügen -->
    <div class="panel panel-default">
        <div class="panel-heading">Position hinzufügen</div>
        <div class="panel-body">
            <form method="post" action="index.php?page=auftrag_neu&step=2" class="form-inline">
                <input type="hidden" name="action" value="add_position">
                <div class="form-group">
                    <label>Artikel:</label>
                    <select name="artikel_id" class="form-control input-sm" id="artikel_select">
                        <option value="">— auswählen —</option>
                        <?php while ($art = mysql_fetch_assoc($artikel_result)): ?>
                            <option value="<?php echo $art['id']; ?>"
                                    data-preis="<?php echo $art['preis_netto']; ?>">
                                <?php echo htmlspecialchars($art['sku'] . ' — ' . $art['name']); ?>
                                (<?php echo formatEuro($art['preis_netto']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Menge:</label>
                    <input type="text" name="menge" class="form-control input-sm" style="width:60px;" value="1">
                </div>
                <div class="form-group">
                    <label>Einzelpreis (€):</label>
                    <input type="text" name="einzelpreis" id="einzelpreis" class="form-control input-sm" style="width:80px;" placeholder="aus Artikel">
                </div>
                <button type="submit" class="btn btn-success btn-sm">+ Hinzufügen</button>
            </form>
            <script>
            document.getElementById('artikel_select').addEventListener('change', function() {
                var opt = this.options[this.selectedIndex];
                document.getElementById('einzelpreis').value = opt.getAttribute('data-preis') || '';
            });
            </script>
        </div>
    </div>

    <!-- Positionsliste -->
    <h4>Aktuelle Positionen</h4>
    <?php if (empty($_SESSION['neuer_auftrag']['positionen'])): ?>
        <p class="text-muted">Noch keine Positionen.</p>
    <?php else: ?>
    <table class="table table-condensed table-bordered">
        <thead>
            <tr><th>#</th><th>Artikel</th><th>Menge</th><th>Einzelpreis</th><th>Gesamt netto</th><th>MwSt</th><th></th></tr>
        </thead>
        <tbody>
        <?php
        $gesamt_netto = 0;
        foreach ($_SESSION['neuer_auftrag']['positionen'] as $i => $pos):
            $zeilensumme = $pos['menge'] * $pos['einzelpreis_netto'];
            $gesamt_netto += $zeilensumme;
        ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($pos['bezeichnung']); ?></td>
                <td><?php echo number_format($pos['menge'], 2, ',', '.'); ?></td>
                <td><?php echo formatEuro($pos['einzelpreis_netto']); ?></td>
                <td><?php echo formatEuro($zeilensumme); ?></td>
                <td><?php echo number_format($pos['mwst_satz'], 0); ?> %</td>
                <td>
                    <form method="post" action="index.php?page=auftrag_neu&step=2" style="display:inline;">
                        <input type="hidden" name="action" value="remove_position">
                        <input type="hidden" name="idx" value="<?php echo $i; ?>">
                        <button type="submit" class="btn btn-xs btn-danger">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="4"><strong>Summe netto:</strong></td><td><strong><?php echo formatEuro($gesamt_netto); ?></strong></td><td colspan="2"></td></tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <a href="index.php?page=auftrag_neu&step=3" class="btn btn-primary">Weiter zur Bestätigung →</a>
    <a href="index.php?page=auftraege" class="btn btn-default">Abbrechen</a>

    <?php elseif ($step == 3): ?>
    <!-- SCHRITT 3 -->
    <?php
    $kunden_id = $_SESSION['neuer_auftrag']['kunden_id'];
    $kunde_res = mysql_query("SELECT * FROM kunden WHERE id = " . (int)$kunden_id, $db);
    $kunde     = mysql_fetch_assoc($kunde_res);
    $positionen = $_SESSION['neuer_auftrag']['positionen'];
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">Auftragsdetails bestätigen</div>
        <div class="panel-body">
            <form method="post" action="index.php?page=auftrag_neu&step=3">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Kunde</h4>
                        <p><strong><?php echo htmlspecialchars($kunde['nummer'] . ' — ' . $kunde['name']); ?></strong><br>
                        <?php echo htmlspecialchars($kunde['strasse'] . ' ' . $kunde['hausnummer']); ?><br>
                        <?php echo htmlspecialchars($kunde['plz'] . ' ' . $kunde['ort']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h4>Auftragsoptionen</h4>
                        <div class="form-group">
                            <label>Priorität:</label>
                            <select name="prioritaet" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="hoch">Hoch</option>
                                <option value="express">Express</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Lieferdatum (soll):</label>
                            <input type="date" name="lieferdatum_soll" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Bemerkungen:</label>
                            <textarea name="bemerkungen" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <h4>Positionen</h4>
                <?php
                $gesamt_netto = 0;
                $gesamt_mwst  = 0;
                ?>
                <table class="table table-condensed table-bordered">
                    <thead><tr><th>#</th><th>Artikel</th><th>Menge</th><th>Einzelpreis</th><th>Gesamt netto</th></tr></thead>
                    <tbody>
                    <?php foreach ($positionen as $i => $pos):
                        $zeilensumme  = $pos['menge'] * $pos['einzelpreis_netto'];
                        $mwst_betrag  = berechneMwst($zeilensumme, $pos['mwst_satz']);
                        $gesamt_netto += $zeilensumme;
                        $gesamt_mwst  += $mwst_betrag;
                    ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($pos['bezeichnung']); ?></td>
                            <td><?php echo number_format($pos['menge'], 2, ',', '.'); ?></td>
                            <td><?php echo formatEuro($pos['einzelpreis_netto']); ?></td>
                            <td><?php echo formatEuro($zeilensumme); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4">Netto:</td><td><?php echo formatEuro($gesamt_netto); ?></td></tr>
                        <tr><td colspan="4">MwSt:</td><td><?php echo formatEuro($gesamt_mwst); ?></td></tr>
                        <tr><td colspan="4"><strong>Brutto:</strong></td><td><strong><?php echo formatEuro($gesamt_netto + $gesamt_mwst); ?></strong></td></tr>
                    </tfoot>
                </table>

                <button type="submit" name="bestaetigen" class="btn btn-primary">Auftrag anlegen</button>
                <a href="index.php?page=auftrag_neu&step=2" class="btn btn-default">← Zurück</a>
                <a href="index.php?page=auftraege" class="btn btn-link">Abbrechen</a>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>

<?php
require_once '../init.php';
require_once '../classes/Northwind.php';
require_once '../classes/BankValidator.php';

$nw     = new Northwind($db);
$fehler = array();
$kunde  = array(
    'id' => 0, 'nummer' => '', 'name' => '', 'firma' => '',
    'strasse' => '', 'hausnummer' => '', 'plz' => '', 'ort' => '',
    'land' => 'DEU', 'email' => '', 'telefon' => '',
    'bankleitzahl' => '', 'kontonummer' => '', 'bic' => '', 'iban' => '',
    'typ' => 'privat',
);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// IBAN berechnen — Sonderaktion
if (isset($_GET['action']) && $_GET['action'] == 'iban_berechnen' && $id > 0) {
    $k = $nw->getKunde($id);
    if ($k && !empty($k['bankleitzahl']) && !empty($k['kontonummer'])) {
        $validator = new BankValidator();
        $iban = $validator->berechneIBAN($k['bankleitzahl'], $k['kontonummer']);
        // IBAN speichern und zurueck
        mysql_query("UPDATE kunden SET iban = '" . sanitize($iban) . "' WHERE id = $id", $db);
        $_SESSION['success'] = 'IBAN berechnet und gespeichert: ' . $iban;
    }
    header('Location: index.php?page=kunden_edit&id=' . $id);
    exit;
}

// Bestehenden Kunden laden
if ($id > 0) {
    $k = $nw->getKunde($id);
    if ($k) {
        $kunde = $k;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Daten aus POST uebernehmen
    $kunde['name']        = trim($_POST['name']);
    $kunde['firma']       = trim($_POST['firma']);
    $kunde['strasse']     = trim($_POST['strasse']);
    $kunde['hausnummer']  = trim($_POST['hausnummer']);
    $kunde['plz']         = trim($_POST['plz']);
    $kunde['ort']         = trim($_POST['ort']);
    $kunde['land']        = trim($_POST['land']);
    $kunde['email']       = trim($_POST['email']);
    $kunde['telefon']     = trim($_POST['telefon']);
    $kunde['bankleitzahl'] = trim($_POST['bankleitzahl']);
    $kunde['kontonummer'] = trim($_POST['kontonummer']);
    $kunde['bic']         = trim($_POST['bic']);
    $kunde['iban']        = trim($_POST['iban']);
    $kunde['typ']         = $_POST['typ'];

    // Pflichtfelder
    if (empty($kunde['name']))     $fehler[] = 'Name ist Pflichtfeld.';
    if (empty($kunde['strasse']))  $fehler[] = 'Straße ist Pflichtfeld.';
    if (empty($kunde['plz']))      $fehler[] = 'PLZ ist Pflichtfeld.';
    if (empty($kunde['ort']))      $fehler[] = 'Ort ist Pflichtfeld.';

    if (!empty($kunde['email']) && !validateEmail($kunde['email'])) {
        $fehler[] = 'E-Mail-Adresse ungültig.';
    }
    if (!empty($kunde['plz']) && !validatePlz($kunde['plz'])) {
        $fehler[] = 'PLZ muss 5-stellig und numerisch sein.';
    }

    // Bankverbindung pruefen — hier das doppelte Validierungs-Anti-Pattern:
    // erst die alte helper.php-Funktion (nur Format), dann BAV (Pruefziffer)
    if (!empty($kunde['bankleitzahl'])) {
        // Alte Validierung aus 2009 — prueft nur Format, keine Pruefziffer
        if (!validateBankleitzahl($kunde['bankleitzahl'])) {
            $fehler[] = 'BLZ-Format ungültig (muss 8-stellig numerisch sein).';
        } else {
            // Neue Validierung via BAV — prueft Existenz und Pruefziffer
            $validator = new BankValidator();
            if (!empty($kunde['kontonummer'])) {
                if (!$validator->istGueltigesBankverbindung($kunde['bankleitzahl'], $kunde['kontonummer'])) {
                    $fehler[] = 'Bankverbindung konnte nicht verifiziert werden (ungültige BLZ/Kontonummer-Kombination).';
                }
            } else {
                // Nur BLZ pruefen
                if (!$validator->istGueltigeBLZ($kunde['bankleitzahl'])) {
                    $fehler[] = 'BLZ existiert nicht in der Bundesbank-Datenbank.';
                }
            }
        }
    }

    if (!empty($kunde['bic'])) {
        $validator = isset($validator) ? $validator : new BankValidator();
        if (!$validator->istGueltigerBIC($kunde['bic'])) {
            $fehler[] = 'BIC ungültig.';
        }
    }

    if (empty($fehler)) {
        // Kundennummer generieren fuer Neukunde
        if ($id == 0) {
            $jahr = date('Y');
            $cnt_res = mysql_query("SELECT COUNT(*) as cnt FROM kunden WHERE YEAR(angelegt_am) = $jahr", $db);
            $cnt_row = mysql_fetch_assoc($cnt_res);
            $kunde['nummer'] = 'KD-' . $jahr . '-' . str_pad($cnt_row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
            $kunde['angelegt_am'] = date('Y-m-d H:i:s');
            $kunde['angelegt_von'] = $_SESSION['user_id'];
        }

        $result = $nw->speichereKunde($kunde);
        if ($result) {
            $_SESSION['success'] = $id > 0 ? 'Kunde aktualisiert.' : 'Kunde angelegt.';
            header('Location: index.php?page=kunden');
            exit;
        } else {
            $fehler[] = 'Fehler beim Speichern. Bitte erneut versuchen.';
        }
    }
}

include '../includes/header.php';
?>

<h2><?php echo $id > 0 ? 'Kunde bearbeiten' : 'Neuer Kunde'; ?></h2>

<?php if (!empty($fehler)): ?>
<div class="alert alert-danger">
  <ul style="margin:0">
  <?php foreach ($fehler as $f): ?>
    <li><?php echo htmlspecialchars($f); ?></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="post" action="index.php?page=kunden_edit<?php echo $id > 0 ? '&id='.$id : ''; ?>">
<div class="row">
  <div class="col-md-6">
    <h4>Stammdaten</h4>
    <div class="form-group">
      <label>Typ</label>
      <select name="typ" class="form-control">
        <option value="privat" <?php if ($kunde['typ']=='privat') echo 'selected'; ?>>Privatkunde</option>
        <option value="geschaeft" <?php if ($kunde['typ']=='geschaeft') echo 'selected'; ?>>Geschäftskunde</option>
      </select>
    </div>
    <div class="form-group">
      <label>Name <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($kunde['name']); ?>">
    </div>
    <div class="form-group">
      <label>Firma</label>
      <input type="text" name="firma" class="form-control" value="<?php echo htmlspecialchars($kunde['firma']); ?>">
    </div>
    <div class="form-group">
      <label>E-Mail</label>
      <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($kunde['email']); ?>">
    </div>
    <div class="form-group">
      <label>Telefon</label>
      <input type="text" name="telefon" class="form-control" value="<?php echo htmlspecialchars($kunde['telefon']); ?>">
    </div>
  </div>
  <div class="col-md-6">
    <h4>Adresse</h4>
    <div class="row">
      <div class="col-md-9">
        <div class="form-group">
          <label>Straße <span class="text-danger">*</span></label>
          <input type="text" name="strasse" class="form-control" value="<?php echo htmlspecialchars($kunde['strasse']); ?>">
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          <label>Nr.</label>
          <input type="text" name="hausnummer" class="form-control" value="<?php echo htmlspecialchars($kunde['hausnummer']); ?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-4">
        <div class="form-group">
          <label>PLZ <span class="text-danger">*</span></label>
          <input type="text" name="plz" class="form-control" maxlength="5" value="<?php echo htmlspecialchars($kunde['plz']); ?>">
        </div>
      </div>
      <div class="col-md-8">
        <div class="form-group">
          <label>Ort <span class="text-danger">*</span></label>
          <input type="text" name="ort" class="form-control" value="<?php echo htmlspecialchars($kunde['ort']); ?>">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Land</label>
      <input type="text" name="land" class="form-control" maxlength="3" value="<?php echo htmlspecialchars($kunde['land']); ?>">
    </div>
  </div>
</div>

<h4>Bankverbindung (SEPA-Lastschrift)</h4>
<div class="row">
  <div class="col-md-3">
    <div class="form-group">
      <label>BLZ (8-stellig)</label>
      <input type="text" name="bankleitzahl" class="form-control" maxlength="8"
             value="<?php echo htmlspecialchars($kunde['bankleitzahl']); ?>">
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label>Kontonummer</label>
      <input type="text" name="kontonummer" class="form-control" maxlength="10"
             value="<?php echo htmlspecialchars($kunde['kontonummer']); ?>">
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label>BIC</label>
      <input type="text" name="bic" class="form-control" maxlength="11"
             value="<?php echo htmlspecialchars($kunde['bic']); ?>">
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label>IBAN
        <?php if ($id > 0 && !empty($kunde['bankleitzahl'])): ?>
          <a href="index.php?page=kunden_edit&id=<?php echo $id; ?>&action=iban_berechnen"
             class="btn btn-xs btn-default">Berechnen</a>
        <?php endif; ?>
      </label>
      <input type="text" name="iban" class="form-control" maxlength="34"
             value="<?php echo htmlspecialchars($kunde['iban']); ?>">
    </div>
  </div>
</div>

<button type="submit" class="btn btn-primary">Speichern</button>
<a href="index.php?page=kunden" class="btn btn-default">Abbrechen</a>
</form>

<?php include '../includes/footer.php'; ?>

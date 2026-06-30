<?php
require_once '../init.php';
require_once '../classes/Northwind.php';

$fehler  = array();
$artikel = array(
    'id' => 0, 'sku' => '', 'name' => '', 'beschreibung' => '',
    'preis_netto' => '', 'mwst_satz' => 19.00,
    'gewicht_kg' => '', 'laenge_cm' => '', 'breite_cm' => '', 'hoehe_cm' => '',
    'lagerbestand' => 0,
);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $res = mysql_query("SELECT * FROM artikel WHERE id = $id AND aktiv = 1 LIMIT 1", $db);
    if ($res && mysql_num_rows($res) > 0) {
        $artikel = mysql_fetch_assoc($res);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $artikel['sku']         = trim($_POST['sku']);
    $artikel['name']        = trim($_POST['name']);
    $artikel['beschreibung'] = trim($_POST['beschreibung']);
    $artikel['preis_netto'] = str_replace(',', '.', trim($_POST['preis_netto']));
    $artikel['mwst_satz']   = (float)$_POST['mwst_satz'];
    $artikel['gewicht_kg']  = str_replace(',', '.', trim($_POST['gewicht_kg']));
    $artikel['laenge_cm']   = str_replace(',', '.', trim($_POST['laenge_cm']));
    $artikel['breite_cm']   = str_replace(',', '.', trim($_POST['breite_cm']));
    $artikel['hoehe_cm']    = str_replace(',', '.', trim($_POST['hoehe_cm']));
    $artikel['lagerbestand'] = (int)$_POST['lagerbestand'];

    if (empty($artikel['sku']))  $fehler[] = 'SKU ist Pflichtfeld.';
    if (empty($artikel['name'])) $fehler[] = 'Name ist Pflichtfeld.';

    if (!is_numeric($artikel['preis_netto']) || $artikel['preis_netto'] <= 0) {
        $fehler[] = 'Preis muss eine positive Zahl sein.';
    }

    // SKU-Eindeutigkeit pruefen — direkt inline statt ueber Northwind-Klasse
    if (!empty($artikel['sku'])) {
        $sku_safe = sanitize($artikel['sku']);
        $dup_sql  = "SELECT id FROM artikel WHERE sku = '$sku_safe' AND aktiv = 1";
        if ($id > 0) {
            $dup_sql .= " AND id != $id";
        }
        $dup_res = mysql_query($dup_sql, $db);
        if ($dup_res && mysql_num_rows($dup_res) > 0) {
            $fehler[] = 'SKU bereits vergeben. Bitte andere SKU wählen.';
        }
    }

    if (empty($fehler)) {
        $now = date('Y-m-d H:i:s');
        if ($id > 0) {
            $sql = "UPDATE artikel SET
                        sku          = '" . sanitize($artikel['sku'])         . "',
                        name         = '" . sanitize($artikel['name'])        . "',
                        beschreibung = '" . sanitize($artikel['beschreibung']) . "',
                        preis_netto  = " . (float)$artikel['preis_netto']    . ",
                        mwst_satz    = " . (float)$artikel['mwst_satz']      . ",
                        gewicht_kg   = " . (empty($artikel['gewicht_kg']) ? 'NULL' : (float)$artikel['gewicht_kg']) . ",
                        laenge_cm    = " . (empty($artikel['laenge_cm'])  ? 'NULL' : (float)$artikel['laenge_cm'])  . ",
                        breite_cm    = " . (empty($artikel['breite_cm'])  ? 'NULL' : (float)$artikel['breite_cm'])  . ",
                        hoehe_cm     = " . (empty($artikel['hoehe_cm'])   ? 'NULL' : (float)$artikel['hoehe_cm'])   . ",
                        lagerbestand = " . $artikel['lagerbestand'] . "
                    WHERE id = $id";
        } else {
            $sql = "INSERT INTO artikel
                        (sku, name, beschreibung, preis_netto, mwst_satz,
                         gewicht_kg, laenge_cm, breite_cm, hoehe_cm, lagerbestand, aktiv, angelegt_am)
                    VALUES
                        ('" . sanitize($artikel['sku'])          . "',
                         '" . sanitize($artikel['name'])         . "',
                         '" . sanitize($artikel['beschreibung']) . "',
                         "  . (float)$artikel['preis_netto']     . ",
                         "  . (float)$artikel['mwst_satz']       . ",
                         "  . (empty($artikel['gewicht_kg']) ? 'NULL' : (float)$artikel['gewicht_kg']) . ",
                         "  . (empty($artikel['laenge_cm'])  ? 'NULL' : (float)$artikel['laenge_cm'])  . ",
                         "  . (empty($artikel['breite_cm'])  ? 'NULL' : (float)$artikel['breite_cm'])  . ",
                         "  . (empty($artikel['hoehe_cm'])   ? 'NULL' : (float)$artikel['hoehe_cm'])   . ",
                         "  . $artikel['lagerbestand']           . ",
                         1, '$now')";
        }

        if (mysql_query($sql, $db)) {
            $_SESSION['success'] = $id > 0 ? 'Artikel aktualisiert.' : 'Artikel angelegt.';
            header('Location: index.php?page=artikel');
            exit;
        } else {
            $fehler[] = 'Datenbankfehler: ' . mysql_error($db);
        }
    }
}

include '../includes/header.php';
?>

<h2><?php echo $id > 0 ? 'Artikel bearbeiten' : 'Neuer Artikel'; ?></h2>

<?php if (!empty($fehler)): ?>
<div class="alert alert-danger">
  <ul style="margin:0">
  <?php foreach ($fehler as $f): ?>
    <li><?php echo htmlspecialchars($f); ?></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="post" action="index.php?page=artikel_edit<?php echo $id > 0 ? '&id='.$id : ''; ?>">
<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label>SKU <span class="text-danger">*</span></label>
      <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($artikel['sku']); ?>">
    </div>
    <div class="form-group">
      <label>Name <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($artikel['name']); ?>">
    </div>
    <div class="form-group">
      <label>Beschreibung</label>
      <textarea name="beschreibung" class="form-control" rows="4"><?php echo htmlspecialchars($artikel['beschreibung']); ?></textarea>
    </div>
  </div>
  <div class="col-md-6">
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label>Preis netto (€) <span class="text-danger">*</span></label>
          <input type="text" name="preis_netto" class="form-control"
                 value="<?php echo htmlspecialchars($artikel['preis_netto']); ?>">
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label>MwSt.-Satz (%)</label>
          <select name="mwst_satz" class="form-control">
            <option value="19" <?php if ($artikel['mwst_satz'] == 19) echo 'selected'; ?>>19 %</option>
            <option value="7"  <?php if ($artikel['mwst_satz'] == 7)  echo 'selected'; ?>>7 %</option>
            <option value="0"  <?php if ($artikel['mwst_satz'] == 0)  echo 'selected'; ?>>0 % (steuerfrei)</option>
          </select>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Lagerbestand</label>
      <input type="number" name="lagerbestand" class="form-control" min="0"
             value="<?php echo (int)$artikel['lagerbestand']; ?>">
    </div>
    <h5>Abmessungen</h5>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label>Gewicht (kg)</label>
          <input type="text" name="gewicht_kg" class="form-control"
                 value="<?php echo htmlspecialchars($artikel['gewicht_kg']); ?>">
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label>Länge (cm)</label>
          <input type="text" name="laenge_cm" class="form-control"
                 value="<?php echo htmlspecialchars($artikel['laenge_cm']); ?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label>Breite (cm)</label>
          <input type="text" name="breite_cm" class="form-control"
                 value="<?php echo htmlspecialchars($artikel['breite_cm']); ?>">
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label>Höhe (cm)</label>
          <input type="text" name="hoehe_cm" class="form-control"
                 value="<?php echo htmlspecialchars($artikel['hoehe_cm']); ?>">
        </div>
      </div>
    </div>
  </div>
</div>

<button type="submit" class="btn btn-primary">Speichern</button>
<a href="index.php?page=artikel" class="btn btn-default">Abbrechen</a>
</form>

<?php include '../includes/footer.php'; ?>

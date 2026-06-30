<?php
// Login-Seite — keine Session-Pruefung hier, wird in index.php uebersprungen
require_once dirname(__FILE__) . '/../init.php';

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $benutzername = isset($_POST['benutzername']) ? trim($_POST['benutzername']) : '';
    $passwort     = isset($_POST['passwort'])     ? $_POST['passwort']           : '';

    if (empty($benutzername) || empty($passwort)) {
        $fehler = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        $user_escaped = mysql_real_escape_string($benutzername, $db);
        $pass_escaped = mysql_real_escape_string($passwort, $db);

        // MD5 — war 2009 "gut genug", nie geaendert
        $sql = "SELECT id, name, rolle FROM benutzer
                WHERE benutzername = '$user_escaped'
                  AND passwort = MD5('$pass_escaped')
                  AND aktiv = 1
                LIMIT 1";

        $result = mysql_query($sql, $db);
        if ($result && mysql_num_rows($result) == 1) {
            $user = mysql_fetch_assoc($result);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['rolle']   = $user['rolle'];

            // Letzten Login aktualisieren
            mysql_query("UPDATE benutzer SET letzter_login = NOW() WHERE id = " . (int)$user['id'], $db);

            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $fehler = 'Benutzername oder Passwort falsch.';
            logError('Fehlgeschlagener Login-Versuch fuer: ' . $benutzername);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Northwind Logistics — Anmeldung</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <style>
    body { background: #f5f5f5; }
    .login-box { max-width: 360px; margin: 80px auto; background: #fff; padding: 30px; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    .login-box h2 { margin-top: 0; font-size: 22px; }
  </style>
</head>
<body>
<div class="login-box">
  <h2>Northwind Logistics</h2>
  <p class="text-muted">Bitte melden Sie sich an.</p>

  <?php if (!empty($fehler)): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($fehler); ?></div>
  <?php endif; ?>

  <form method="post" action="index.php?page=login">
    <div class="form-group">
      <label>Benutzername</label>
      <input type="text" name="benutzername" class="form-control" autofocus
             value="<?php echo htmlspecialchars(isset($_POST['benutzername']) ? $_POST['benutzername'] : ''); ?>">
    </div>
    <div class="form-group">
      <label>Passwort</label>
      <input type="password" name="passwort" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
  </form>
</div>
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>

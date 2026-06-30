<?php
// Mahnlauf — verschickt Zahlungserinnerungen für überfällige Rechnungen
// crontab: 0 6 * * * /usr/bin/php /var/www/northwind/cron/mahnlauf.php >> /var/log/northwind_cron.log 2>&1
//
// ACHTUNG: Dieser Pfad stimmt nicht mit dem Entwicklungsserver überein!
// Auf Dev: /Users/peter/Sites/northwind — auf Prod: /var/www/northwind
// Peter hat das nie angeglichen, weil "läuft ja auf Prod".

define('APP_ROOT', '/var/www/northwind');

// Eigene DB-Verbindung — wir brauchen keine Session, also kein init.php
// Credentials sind dupliziert aus config.php. Bei Passwortänderung müssen
// beide Stellen angepasst werden. Passiert regelmäßig vergessen.
$db_host = 'localhost';
$db_user = 'northwind_user';
$db_pass = 'Passw0rd123!';
$db_name = 'northwind';

$db = mysql_connect($db_host, $db_user, $db_pass);
if (!$db) {
    file_put_contents('php://stderr', date('Y-m-d H:i:s') . " DB-Verbindung fehlgeschlagen: " . mysql_error() . "\n");
    exit(1);
}
mysql_select_db($db_name, $db);
mysql_query("SET NAMES 'utf8'", $db);

$heute = date('Y-m-d');
$anzahl_mahnungen = 0;
$anzahl_fehler = 0;

echo date('Y-m-d H:i:s') . " Starte Mahnlauf für " . $heute . "...\n";

// Überfällige Rechnungen finden (> 30 Tage offen, noch nicht Mahnstufe 3 oder höher)
// Kunden müssen aktiv sein, andernfalls kein Mahnversand
$sql = "SELECT r.id, r.rechnungs_nr, r.betrag_brutto, r.faellig_am, r.mahnstufe, r.mahngebuehr,
               k.id as kunden_id, k.name as kunden_name, k.email as kunden_email, k.firma
        FROM rechnungen r
        JOIN kunden k ON r.kunden_id = k.id
        WHERE r.status = 'offen'
          AND r.faellig_am < DATE_SUB('" . $heute . "', INTERVAL 30 DAY)
          AND r.mahnstufe < 3
          AND k.aktiv = 1
        ORDER BY r.faellig_am ASC";

$result = mysql_query($sql, $db);
if (!$result) {
    file_put_contents('php://stderr', "SQL-Fehler: " . mysql_error($db) . "\n");
    exit(1);
}

while ($row = mysql_fetch_assoc($result)) {
    $neue_stufe = (int)$row['mahnstufe'] + 1;

    // Mahngebühren — hardcoded, sollten eigentlich konfigurierbar sein
    // TODO: aus Datenbank lesen (seit 2018 offen, Sabine fragt jedes Quartal)
    $mahngebuehr = 0;
    if ($neue_stufe == 1) $mahngebuehr = 5.00;
    if ($neue_stufe == 2) $mahngebuehr = 15.00;
    if ($neue_stufe == 3) $mahngebuehr = 40.00;

    // Mahnstufe und Gebühr aktualisieren
    $update_sql = "UPDATE rechnungen
                   SET mahnstufe = " . $neue_stufe . ",
                       letzte_mahnung = '" . $heute . "',
                       mahngebuehr = mahngebuehr + " . $mahngebuehr . ",
                       status = 'gemahnt'
                   WHERE id = " . (int)$row['id'];
    $update_result = mysql_query($update_sql, $db);

    if (!$update_result) {
        echo date('H:i:s') . " FEHLER bei Rechnung #" . $row['id'] . ": " . mysql_error($db) . "\n";
        $anzahl_fehler++;
        continue;
    }

    // Anschrift: Firma oder Privatperson
    $anrede = !empty($row['firma']) ? $row['firma'] : $row['kunden_name'];

    // E-Mail-Text zusammenbauen — kein Template, einfach String-Concat
    // Formatierung des Betrags: Komma statt Punkt (deutsche Schreibweise)
    $betrag_formatiert = number_format($row['betrag_brutto'], 2, ',', '.');
    $gesamtgebuehr = number_format($row['mahngebuehr'] + $mahngebuehr, 2, ',', '.');

    if ($neue_stufe == 1) {
        $betreff = 'Zahlungserinnerung — Rechnung ' . $row['rechnungs_nr'];
        $text  = "Sehr geehrte Damen und Herren,\n";
        $text .= "sehr geehrte/r " . $anrede . ",\n\n";
        $text .= "wir erlauben uns, Sie freundlich daran zu erinnern, dass folgende Rechnung noch aussteht:\n\n";
        $text .= "Rechnungsnummer: " . $row['rechnungs_nr'] . "\n";
        $text .= "Betrag: " . $betrag_formatiert . " EUR\n";
        $text .= "Fällig am: " . $row['faellig_am'] . "\n\n";
        $text .= "Bitte überweisen Sie den Betrag umgehend auf unser Konto.\n\n";
        $text .= "Falls die Zahlung bereits erfolgt ist, bitten wir Sie, dieses Schreiben als gegenstandslos zu betrachten.\n\n";
    } elseif ($neue_stufe == 2) {
        $betreff = '1. Mahnung — Rechnung ' . $row['rechnungs_nr'];
        $text  = "Sehr geehrte Damen und Herren,\n";
        $text .= "sehr geehrte/r " . $anrede . ",\n\n";
        $text .= "leider mussten wir feststellen, dass unsere Zahlungserinnerung ohne Reaktion blieb.\n\n";
        $text .= "Rechnungsnummer: " . $row['rechnungs_nr'] . "\n";
        $text .= "Ursprünglicher Betrag: " . $betrag_formatiert . " EUR\n";
        $text .= "Mahngebühr: " . number_format($mahngebuehr, 2, ',', '.') . " EUR\n";
        $text .= "Gesamtbetrag: " . $gesamtgebuehr . " EUR\n";
        $text .= "Fällig am: " . $row['faellig_am'] . "\n\n";
        $text .= "Bitte begleichen Sie den ausstehenden Betrag innerhalb von 7 Tagen.\n\n";
    } else {
        $betreff = '2. Mahnung (LETZTE AUFFORDERUNG) — Rechnung ' . $row['rechnungs_nr'];
        $text  = "Sehr geehrte Damen und Herren,\n";
        $text .= "sehr geehrte/r " . $anrede . ",\n\n";
        $text .= "trotz unserer bisherigen Mahnungen ist der folgende Betrag noch immer offen.\n\n";
        $text .= "Rechnungsnummer: " . $row['rechnungs_nr'] . "\n";
        $text .= "Offener Betrag inkl. Mahngebühren: " . $gesamtgebuehr . " EUR\n\n";
        $text .= "Bei ausbleibender Zahlung innerhalb von 5 Tagen sehen wir uns gezwungen,\n";
        $text .= "einen Inkassodienstleister einzuschalten.\n\n";
    }

    $text .= "Mit freundlichen Grüßen\n";
    $text .= "Northwind Logistics GmbH\n";
    $text .= "Buchhaltung\n";
    $text .= "Tel: +49 40 123456-0\n";
    $text .= "buchhaltung@northwind-logistics.de\n";

    $headers = 'From: buchhaltung@northwind-logistics.de' . "\r\n" .
               'Reply-To: buchhaltung@northwind-logistics.de' . "\r\n" .
               'X-Mailer: NorthwindMailer/1.0';

    // mail() direkt in der Schleife — kein Rate-Limiting, kein Queue-System
    // Bei 200 überfälligen Rechnungen: 200 simultane SMTP-Verbindungen
    $mail_ok = mail($row['kunden_email'], $betreff, $text, $headers);

    if ($mail_ok) {
        echo date('H:i:s') . " Mahnung Stufe " . $neue_stufe . " an " . $row['kunden_email'] . " gesendet (Rechnung " . $row['rechnungs_nr'] . ")\n";
        $anzahl_mahnungen++;
    } else {
        echo date('H:i:s') . " FEHLER: Mail an " . $row['kunden_email'] . " nicht gesendet\n";
        $anzahl_fehler++;
        // DB wurde trotzdem aktualisiert — Mahnstufe erhöht aber Mail nicht verschickt
        // Das ist ein bekanntes Problem: DB und E-Mail-Status laufen auseinander
    }
}

echo date('Y-m-d H:i:s') . " Mahnlauf abgeschlossen. Mahnungen: " . $anzahl_mahnungen . ", Fehler: " . $anzahl_fehler . "\n";

mysql_close($db);

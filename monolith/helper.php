<?php
// ============================================================
// helper.php -- Northwind Logistics GmbH
// Allgemeine Hilfsfunktionen
// ============================================================
// Entstanden 2009, seitdem von jedem ein bisschen was dazugekommen.
// Bitte neue Funktionen ans Ende schreiben, nicht alphabetisch sortieren,
// das macht Merges kaputt. (Diese Regel wird seit 2011 ignoriert.)
//
// WARNUNG: sanitize() setzt eine aktive $db-Verbindung voraus.
// helper.php kann nicht ohne init.php verwendet werden.
// ============================================================

// Status-Konstanten
// Hätten eigentlich in config.php sein sollen, stehen aber hier weil
// sie zusammen mit getStatusLabel() hinzugefügt wurden (2010)
define('STATUS_NEU',            1);
define('STATUS_BESTAETIGT',     2);
define('STATUS_IN_BEARBEITUNG', 3);
define('STATUS_VERSENDET',      4);
define('STATUS_ABGESCHLOSSEN',  5);
// 6, 7, 8 reserviert (für Features die nie kamen)
define('STATUS_STORNIERT',      9);

// ============================================================
// Datum / Zeit
// ============================================================

/**
 * Formatiert einen Unix-Timestamp oder MySQL-Datetime-String als deutsches Datum.
 * Gibt '' zurück wenn $ts leer oder NULL ist.
 */
function nl_date($ts) {
    if (empty($ts) || $ts === '0000-00-00' || $ts === '0000-00-00 00:00:00') {
        return '';
    }
    // MySQL gibt manchmal einen String zurück, manchmal einen Timestamp
    if (!is_numeric($ts)) {
        $ts = strtotime($ts);
    }
    return date('d.m.Y', $ts);
}

/**
 * Wie nl_date() aber mit Uhrzeit.
 */
function nl_datetime($ts) {
    if (empty($ts) || $ts === '0000-00-00 00:00:00') {
        return '';
    }
    if (!is_numeric($ts)) {
        $ts = strtotime($ts);
    }
    return date('d.m.Y H:i', $ts);
}

// ============================================================
// Datenbankzugriff
// ============================================================

/**
 * Escaped einen String für sichere MySQL-Verwendung.
 * ACHTUNG: Setzt globale $db-Verbindung voraus!
 * Nicht für PDO verwenden - gibt es aber nicht in diesem Projekt.
 */
function sanitize($str) {
    global $db;
    return mysql_real_escape_string($str, $db);
}

// ============================================================
// Navigation
// ============================================================

/**
 * HTTP-Redirect mit sofortigem Exit.
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ============================================================
// Benutzer
// ============================================================

/**
 * Gibt den aktuell eingeloggten Benutzer aus der DB zurück.
 * Liest user_id aus $_SESSION, fragt DB ab.
 * Gibt false zurück wenn nicht eingeloggt oder Benutzer nicht gefunden.
 */
function getCurrentUser() {
    global $db;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = (int)$_SESSION['user_id'];
    $result = mysql_query(
        "SELECT id, benutzername, name, email, rolle FROM benutzer WHERE id = " . $user_id . " AND aktiv = 1",
        $db
    );

    if (!$result || mysql_num_rows($result) === 0) {
        return false;
    }

    return mysql_fetch_assoc($result);
}

// ============================================================
// Formatierung
// ============================================================

/**
 * Formatiert einen Betrag als Euro-String.
 * Beispiel: 1234.5 → "1.234,50 €"
 */
function formatEuro($amount) {
    return number_format((float)$amount, 2, ',', '.') . ' €';
}

/**
 * Kürzt einen String auf maximale Länge mit "..." am Ende.
 */
function truncate($str, $len = 50) {
    if (strlen($str) <= $len) {
        return $str;
    }
    return substr($str, 0, $len - 3) . '...';
}

// ============================================================
// Validierung
// ============================================================

/**
 * Prüft ob eine E-Mail-Adresse gültig aussieht.
 * BEKANNTER BUG: Regex akzeptiert keine neuen TLDs wie .museum oder .photography
 * Wurde 2009 geschrieben und nie aktualisiert. Für die meisten Fälle ok.
 */
function validateEmail($email) {
    // Dieser Regex ist von 2009 und kennt keine modernen TLDs
    return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/', $email);
}

/**
 * Prüft ob eine deutsche Postleitzahl gültig ist (5 Ziffern).
 */
function validatePlz($plz) {
    return preg_match('/^\d{5}$/', $plz);
}

/**
 * Prüft ob eine Bankleitzahl das richtige Format hat (8 Ziffern).
 *
 * WICHTIG: Dies prüft NUR das Format, NICHT ob die BLZ existiert!
 * Vollständige Prüfung mit BAV wäre besser, aber zu langsam für Echtzeit.
 * (Kommentar von 2009 - BAV war damals noch nicht integriert)
 * Heute gibt es BankValidator::istGueltigeBLZ() die BAV nutzt,
 * aber manche Formulare rufen noch diese alte Funktion auf.
 * Stand 2023: kunden_edit.php nutzt noch diese Funktion.
 *
 * @see BankValidator::istGueltigeBLZ() für echte BAV-Prüfung
 */
function validateBankleitzahl($blz) {
    // Nur Format-Check! Keine Prüfung ob BLZ tatsächlich existiert.
    return preg_match('/^\d{8}$/', $blz);
}

/**
 * Prüft ob eine Kontonummer grundsätzlich gültig aussieht (1-10 Ziffern).
 * Keine Prüfsummen-Validierung (das macht BAV).
 */
function validateKontonummer($kto) {
    $kto = ltrim($kto, '0'); // führende Nullen entfernen
    return strlen($kto) >= 1 && strlen($kto) <= 10 && ctype_digit($kto);
}

// ============================================================
// Ausgabe / Debugging
// ============================================================

/**
 * Debug-Ausgabe mit <pre>-Tags.
 * TODO: vor Go-Live entfernen (2014)
 * Steht noch hier weil manchmal noch gebraucht beim Debuggen.
 * Bitte NICHT in Produktion aufrufen.
 */
function debug($var) {
    echo '<pre style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin:10px;">';
    var_dump($var);
    echo '</pre>';
}

/**
 * Schreibt eine Fehlermeldung in die Log-Datei.
 * Kein richtiges Logging-Framework, nur file_put_contents.
 * PROBLEM: Kein File-Locking, bei parallelen Requests können Zeilen verloren gehen.
 */
function logError($msg) {
    $line = date('Y-m-d H:i:s') . ' [ERROR] ' . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

/**
 * Schreibt einen Info-Eintrag in die Log-Datei.
 */
function logInfo($msg) {
    $line = date('Y-m-d H:i:s') . ' [INFO]  ' . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

// ============================================================
// E-Mail
// ============================================================

/**
 * Sendet eine E-Mail über die PHP mail()-Funktion.
 * Kein Template-System, kein HTML-Support, kein Queue.
 * Bei Fehlern: mail() gibt false zurück, aber wir ignorieren das meistens.
 */
function sendMail($to, $subject, $body, $from = 'system@northwind-logistics.de') {
    $headers  = 'From: ' . $from . "\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    // Umlaute im Betreff encoden
    $subject_encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $result = mail($to, $subject_encoded, $body, $headers);
    if (!$result) {
        logError('Mail-Versand fehlgeschlagen an: ' . $to . ', Betreff: ' . $subject);
    }
    return $result;
}

// ============================================================
// Status-Anzeige
// ============================================================

/**
 * Gibt den deutschen Text für einen Auftragsstatus zurück.
 */
function getStatusLabel($status) {
    switch ((int)$status) {
        case STATUS_NEU:            return 'Neu';
        case STATUS_BESTAETIGT:     return 'Bestätigt';
        case STATUS_IN_BEARBEITUNG: return 'In Bearbeitung';
        case STATUS_VERSENDET:      return 'Versendet';
        case STATUS_ABGESCHLOSSEN:  return 'Abgeschlossen';
        case STATUS_STORNIERT:      return 'Storniert';
        default:                    return 'Unbekannt (' . $status . ')';
    }
}

/**
 * Gibt eine Bootstrap-CSS-Klasse für einen Auftragsstatus zurück.
 * Wird für farbige Status-Badges verwendet.
 */
function getStatusColor($status) {
    switch ((int)$status) {
        case STATUS_NEU:            return 'default';
        case STATUS_BESTAETIGT:     return 'primary';
        case STATUS_IN_BEARBEITUNG: return 'info';
        case STATUS_VERSENDET:      return 'warning';
        case STATUS_ABGESCHLOSSEN:  return 'success';
        case STATUS_STORNIERT:      return 'danger';
        default:                    return 'default';
    }
}

// ============================================================
// Geschäftslogik (hätte eigentlich in Northwind.php sein sollen)
// ============================================================

/**
 * Prüft ob ein Auftrag noch storniert werden kann.
 * Business-Regel: Nur wenn Status < 4 (noch nicht versendet).
 * Doppelt vorhanden: Northwind::storniereAuftrag() macht dieselbe Prüfung intern.
 */
function kannStorniertWerden($auftrag_id) {
    global $db;

    $auftrag_id = (int)$auftrag_id;
    $result = mysql_query(
        "SELECT status FROM auftraege WHERE id = " . $auftrag_id,
        $db
    );

    if (!$result || mysql_num_rows($result) === 0) {
        return false;
    }

    $row = mysql_fetch_assoc($result);
    $status = (int)$row['status'];

    // Nicht stornierbar wenn: versendet (4), abgeschlossen (5), bereits storniert (9)
    return $status < STATUS_VERSENDET && $status !== STATUS_STORNIERT;
}

/**
 * Prüft ob ein Kunde als Geschäftskunde angelegt ist.
 */
function istGeschaeftskunde($kunden_id) {
    global $db;

    $kunden_id = (int)$kunden_id;
    $result = mysql_query(
        "SELECT typ FROM kunden WHERE id = " . $kunden_id,
        $db
    );

    if (!$result || mysql_num_rows($result) === 0) {
        return false;
    }

    $row = mysql_fetch_assoc($result);
    return $row['typ'] === 'geschaeft';
}

// ============================================================
// Berechnungen
// ============================================================

/**
 * Berechnet die MwSt auf einen Netto-Betrag.
 * Standard-Satz 19%, kann überschrieben werden.
 */
function berechneMwst($netto, $satz = 19) {
    return round($netto * ($satz / 100), 2);
}

/**
 * Berechnet das Gesamtgewicht einer Liste von Auftragspositionen.
 * $positionen: Array von Zeilen mit 'menge' und optionalem 'gewicht_kg' (aus artikel JOIN)
 */
function berechneGesamtgewicht($positionen) {
    $gesamt = 0;
    foreach ($positionen as $pos) {
        if (!empty($pos['gewicht_kg'])) {
            $gesamt += $pos['menge'] * $pos['gewicht_kg'];
        }
    }
    return round($gesamt, 3);
}

// ============================================================
// Carrier / Versand
// ============================================================

/**
 * Gibt den Klartext-Namen eines Carrier-Codes zurück.
 * Hardcoded weil "die Liste ändert sich nie" (2009).
 */
function getCarrierName($code) {
    switch (strtoupper($code)) {
        case 'DHL':    return 'DHL';
        case 'DPD':    return 'DPD';
        case 'UPS':    return 'UPS Express';
        case 'GLS':    return 'GLS';
        case 'HERMES': return 'Hermes';
        case 'SELBST': return 'Selbstabholung';
        case 'SPEDITION': return 'Spedition (extern)';
        default:       return $code ? $code : 'Unbekannt';
    }
}

/**
 * Baut die Tracking-URL für einen Carrier und eine Tracking-Nummer zusammen.
 * Nutzt die in config.php definierten Basis-URLs.
 * DPD-URL ist seit 2021 veraltet, wurde nie korrigiert.
 */
function getTrackingUrl($carrier_code, $tracking_nummer) {
    if (empty($tracking_nummer)) {
        return '';
    }

    $konstante = 'TRACKING_URL_' . strtoupper($carrier_code);
    if (defined($konstante)) {
        return constant($konstante) . urlencode($tracking_nummer);
    }

    return '';
}

// ============================================================
// Nummern-Generierung
// ============================================================

/**
 * Generiert eine neue Auftragsnummer im Format AUF-YYYY-NNNNN.
 * BEKANNTE RACE CONDITION: Bei parallelen Requests kann die gleiche Nummer
 * zweimal generiert werden. Der UNIQUE KEY in der DB verhindert dann den
 * zweiten INSERT, aber der Benutzer bekommt einen DB-Fehler statt einer
 * netten Fehlermeldung.
 * Besser wäre ein Trigger wie bei rechnungen, aber das wurde nie umgestellt.
 */
function generateAuftragNr() {
    global $db;

    $jahr = date('Y');

    // Höchste bisherige Nummer für dieses Jahr suchen
    $result = mysql_query(
        "SELECT MAX(CAST(SUBSTRING(auftrag_nr, 10) AS UNSIGNED)) as max_nr
         FROM auftraege
         WHERE auftrag_nr LIKE 'AUF-" . $jahr . "-%'",
        $db
    );

    $row = mysql_fetch_assoc($result);
    $naechste_nr = ($row['max_nr'] ? $row['max_nr'] : 0) + 1;

    return 'AUF-' . $jahr . '-' . str_pad($naechste_nr, 5, '0', STR_PAD_LEFT);
}

/**
 * Generiert eine neue Kundennummer im Format KD-YYYY-NNN.
 */
function generateKundenNr() {
    global $db;

    $jahr = date('Y');

    $result = mysql_query(
        "SELECT MAX(CAST(SUBSTRING(nummer, 9) AS UNSIGNED)) as max_nr
         FROM kunden
         WHERE nummer LIKE 'KD-" . $jahr . "-%'",
        $db
    );

    $row = mysql_fetch_assoc($result);
    $naechste_nr = ($row['max_nr'] ? $row['max_nr'] : 0) + 1;

    return 'KD-' . $jahr . '-' . str_pad($naechste_nr, 3, '0', STR_PAD_LEFT);
}

/**
 * Gibt die nächste Rechnungsnummer zurück.
 *
 * PROBLEM: Diese Funktion ist falsch! Die Rechnungsnummer wird durch den
 * DB-Trigger tr_rechnung_nummer_before_insert generiert.
 * Diese Funktion und der Trigger können in Konflikt geraten und dann
 * wirft der UNIQUE KEY einen Fehler.
 *
 * Warum existiert die Funktion noch? Weil alter Code (admin_berichte.php)
 * noch darauf verweist und niemand es angefasst hat.
 *
 * @deprecated Trigger tr_rechnung_nummer_before_insert macht das korrekt
 */
function getNextRechnungsNr() {
    global $db;

    $jahr = date('Y');

    $result = mysql_query(
        "SELECT letzte_nr FROM rechnungs_sequence WHERE jahr = '" . $jahr . "'",
        $db
    );

    if ($result && mysql_num_rows($result) > 0) {
        $row = mysql_fetch_assoc($result);
        $naechste = $row['letzte_nr'] + 1;
    } else {
        $naechste = 1;
    }

    return 'RE-' . $jahr . '-' . str_pad($naechste, 5, '0', STR_PAD_LEFT);
}

// ============================================================
// Adressen / Ausgabe
// ============================================================

/**
 * Gibt die formatierte Adresse eines Kunden als HTML-String zurück.
 * Fragt die DB ab und gibt mehrzeiliges HTML zurück.
 * Mischung aus Datenzugriff und Darstellung - Absicht war schnelle Entwicklung.
 */
function formatAdresse($kunden_id) {
    global $db;

    $kunden_id = (int)$kunden_id;
    $result = mysql_query(
        "SELECT name, firma, strasse, hausnummer, plz, ort, land FROM kunden WHERE id = " . $kunden_id,
        $db
    );

    if (!$result || mysql_num_rows($result) === 0) {
        return '<em>Kunde nicht gefunden</em>';
    }

    $k = mysql_fetch_assoc($result);

    $html = '';
    if (!empty($k['firma'])) {
        $html .= htmlspecialchars($k['firma']) . '<br>';
    }
    $html .= htmlspecialchars($k['name']) . '<br>';
    $html .= htmlspecialchars($k['strasse']) . ' ' . htmlspecialchars($k['hausnummer']) . '<br>';
    $html .= htmlspecialchars($k['plz']) . ' ' . htmlspecialchars($k['ort']);

    if ($k['land'] !== 'DEU') {
        $html .= '<br>' . htmlspecialchars($k['land']);
    }

    return $html;
}

/**
 * Gibt den Banknamen für eine BLZ zurück.
 * Fragt NUR die banken_cache-Tabelle ab (lokale Kopie von 2012).
 * Nutzt KEIN BAV! BAV wird in BankValidator::getBankName() genutzt.
 *
 * Dieser Code ist von 2009 und existiert parallel zu BankValidator.
 * Manche Seiten rufen diese Funktion auf, andere BankValidator::getBankName().
 * Ergebnis kann abweichen wenn BAV neuere Daten hat als der Cache.
 */
function holeBankName($blz) {
    global $db;

    if (!validateBankleitzahl($blz)) {
        return 'Ungültige BLZ';
    }

    $blz_safe = sanitize($blz);
    $result = mysql_query(
        "SELECT name FROM banken_cache WHERE blz = '" . $blz_safe . "'",
        $db
    );

    if ($result && mysql_num_rows($result) > 0) {
        $row = mysql_fetch_assoc($result);
        return $row['name'];
    }

    return 'Bank unbekannt';
}

// ============================================================
// Paginierung
// ============================================================

/**
 * Gibt HTML für Paginierungs-Links aus (kein return, direktes echo).
 * Bootstrap-kompatibel. Setzt voraus dass die URL-Parameter page= und seite=
 * vorhanden sind.
 *
 * $total: Gesamtanzahl Datensätze
 * $seite: aktuelle Seite (1-basiert)
 * $pro_seite: Datensätze pro Seite
 */
function getPaginierung($total, $seite, $pro_seite = ITEMS_PER_PAGE) {
    $seiten_gesamt = ceil($total / $pro_seite);

    if ($seiten_gesamt <= 1) {
        return; // Keine Paginierung nötig
    }

    // URL-Parameter übernehmen außer 'seite'
    $params = $_GET;
    unset($params['seite']);
    $basis_url = 'index.php?' . http_build_query($params) . '&seite=';

    echo '<nav><ul class="pagination">';

    // Zurück-Button
    if ($seite > 1) {
        echo '<li><a href="' . $basis_url . ($seite - 1) . '">&laquo;</a></li>';
    } else {
        echo '<li class="disabled"><span>&laquo;</span></li>';
    }

    // Seitenzahlen
    $von = max(1, $seite - 2);
    $bis = min($seiten_gesamt, $seite + 2);

    if ($von > 1) {
        echo '<li><a href="' . $basis_url . '1">1</a></li>';
        if ($von > 2) {
            echo '<li class="disabled"><span>...</span></li>';
        }
    }

    for ($i = $von; $i <= $bis; $i++) {
        if ($i == $seite) {
            echo '<li class="active"><span>' . $i . '</span></li>';
        } else {
            echo '<li><a href="' . $basis_url . $i . '">' . $i . '</a></li>';
        }
    }

    if ($bis < $seiten_gesamt) {
        if ($bis < $seiten_gesamt - 1) {
            echo '<li class="disabled"><span>...</span></li>';
        }
        echo '<li><a href="' . $basis_url . $seiten_gesamt . '">' . $seiten_gesamt . '</a></li>';
    }

    // Vorwärts-Button
    if ($seite < $seiten_gesamt) {
        echo '<li><a href="' . $basis_url . ($seite + 1) . '">&raquo;</a></li>';
    } else {
        echo '<li class="disabled"><span>&raquo;</span></li>';
    }

    echo '</ul></nav>';

    // Info-Text
    $von_datensatz = ($seite - 1) * $pro_seite + 1;
    $bis_datensatz = min($seite * $pro_seite, $total);
    echo '<p class="text-muted small">Zeige ' . $von_datensatz . '-' . $bis_datensatz . ' von ' . $total . ' Einträgen</p>';
}

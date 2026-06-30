<?php
// ============================================================
// config.php -- Northwind Logistics GmbH
// Systemkonfiguration
// ============================================================
// WARNUNG: Diese Datei enthält Zugangsdaten im Klartext.
// TODO: In Umgebungsvariablen auslagern -- seit 2019 offen, Priorität "niedrig"
// ============================================================

// Datenbankverbindung
$db_host = 'localhost';
$db_user = 'northwind_user';
$db_pass = 'Passw0rd123!';  // TODO: in env variable auslagern (seit 2019 offen)
$db_name = 'northwind';

// Anwendungspfade
define('APP_ROOT', dirname(__FILE__));
define('UPLOAD_DIR', APP_ROOT . '/uploads/');
define('LOG_FILE',   APP_ROOT . '/logs/errors.log');

// Anzeigeeinstellungen
define('ITEMS_PER_PAGE', 25);
define('APP_NAME', 'Northwind Logistics');
define('APP_VERSION', '3.2.1'); // letzte Version die jemand hochgezählt hat

// Fehler-Reporting
// war mal an, jetzt aus weil Kunde das sah und dachte das System ist kaputt
error_reporting(0);
ini_set('display_errors', 0);

// BAV-Konfiguration
// ACHTUNG: BAV_DATA_DIR wird hier definiert aber in init.php wird
// FileDataBackendContainer ohne diesen Pfad instanziiert (vergessen).
// Bleibt stehen weil alte Skripte drauf referenzieren.
define('BAV_DATA_DIR', APP_ROOT . '/vendor/malkusch/bav/data/');

// Carrier-Tracking-URLs (hardcoded, müssten eigentlich in DB)
// DPD hat 2021 die URL geändert, ist noch nicht aktualisiert
define('TRACKING_URL_DHL', 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=');
define('TRACKING_URL_DPD', 'https://tracking.dpd.de/status/de_DE/parcel/'); // veraltet seit 2021
define('TRACKING_URL_UPS', 'https://www.ups.com/track?loc=de_DE&tracknum=');
define('TRACKING_URL_GLS', 'https://gls-group.eu/DE/de/paketverfolgung?match=');
define('TRACKING_URL_HERMES', 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#');

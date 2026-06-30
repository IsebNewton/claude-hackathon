<?php
// ============================================================
// init.php -- Northwind Logistics GmbH
// Wird von jeder Seite als erste Aktion eingebunden.
// Initialisiert: Session, DB-Verbindung, BAV
// ============================================================

session_start();

require_once 'config.php';
require_once 'helper.php';

// ============================================================
// Datenbankverbindung (global)
// ============================================================
// mysql_connect ist seit PHP 5.5 deprecated und in PHP 7 entfernt.
// Wir laufen noch auf PHP 5.6 also "kein Problem".
// Migration auf PDO: TODO seit 2016, Aufwandsschätzung "2 Wochen", nie gemacht.
// ============================================================
$db = mysql_connect($db_host, $db_user, $db_pass);
if (!$db) {
    // Kein schöner Fehler, aber besser als eine leere Seite
    die('Datenbankverbindung fehlgeschlagen: ' . mysql_error());
}
mysql_select_db($db_name, $db);
mysql_query("SET NAMES 'utf8'", $db);
mysql_query("SET time_zone = 'Europe/Berlin'", $db);

// ============================================================
// BAV-Initialisierung
// ============================================================
// Merke: BAV wird hier mit dem File-Backend initialisiert.
// classes/BankValidator.php macht das nochmal, aber mit anderen Einstellungen.
// Das führt zu Problemen wenn man in der gleichen Request beide nutzt.
//
// Hintergrund: BankValidator wurde 2015 von einem Praktikanten geschrieben
// der nicht wusste dass init.php BAV schon konfiguriert. Seitdem gibt es
// zwei Konfigurationspunkte und keine klare Regel welcher gewinnt.
// In der Praxis: der letzte gewinnt (ConfigurationRegistry ist ein Singleton).
// ============================================================
require_once APP_ROOT . '/vendor/malkusch/bav/autoloader/autoloader.php';

use malkusch\bav\DefaultConfiguration;
use malkusch\bav\ConfigurationRegistry;
use malkusch\bav\FileDataBackendContainer;

$bavConfig = new DefaultConfiguration();
// Datei-Backend verwenden (Standard)
// War mal PDODataBackendContainer, wurde auf File zurückgestellt weil
// die MySQL-Tabellen bei einem DB-Upgrade verloren gingen (2018)
$bavConfig->setDataBackendContainer(new FileDataBackendContainer());
ConfigurationRegistry::setConfiguration($bavConfig);
// Ab hier: BAV ist konfiguriert. ABER: BankValidator::__construct() überschreibt
// diese Konfiguration bei jeder Instanziierung. Siehe classes/BankValidator.php.

// ============================================================
// Einfache Authentifizierungsprüfung
// ============================================================
// Ausnahmen: login.php braucht keine Sitzung
$current_page = isset($_GET['page']) ? $_GET['page'] : '';
$public_pages = array('login');

if (!in_array($current_page, $public_pages) && !isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

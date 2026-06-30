<?php
// Aktualisiert die BAV-Bundesbank-Daten (BLZ-Datei)
// Bundesbank veröffentlicht neue Daten: März, Juni, September, Dezember
// crontab: 0 3 1 3,6,9,12 * /usr/bin/php /var/www/northwind/cron/bav_update.php >> /var/log/northwind_bav_update.log 2>&1

define('APP_ROOT', '/var/www/northwind');

require_once APP_ROOT . '/vendor/malkusch/bav/autoloader/autoloader.php';

use malkusch\bav\BAV;
use malkusch\bav\DefaultConfiguration;
use malkusch\bav\ConfigurationRegistry;
use malkusch\bav\FileDataBackendContainer;

// Konfiguration für den Update-Prozess
// Hinweis: Dies überschreibt jede bestehende Konfiguration im ConfigurationRegistry.
// In Produktionsumgebungen wo mehrere Prozesse laufen könnte das ein Problem sein.
$config = new DefaultConfiguration();
$config->setDataBackendContainer(new FileDataBackendContainer());
ConfigurationRegistry::setConfiguration($config);

$bav = new BAV();

echo date('Y-m-d H:i:s') . " Starte BAV-Datenaktualisierung...\n";

try {
    $bav->update();
    echo date('Y-m-d H:i:s') . " BAV-Daten erfolgreich aktualisiert.\n";
} catch (Exception $e) {
    // Kein Alarm, kein Monitoring, kein Retry-Mechanismus
    // TODO: irgendwann mal Monitoring einbauen (seit 2017 offen)
    file_put_contents('php://stderr', date('Y-m-d H:i:s') . " BAV-Update fehlgeschlagen: " . $e->getMessage() . "\n");
    exit(1);
}

echo date('Y-m-d H:i:s') . " Fertig.\n";

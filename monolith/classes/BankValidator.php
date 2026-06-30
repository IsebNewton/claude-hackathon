<?php
/**
 * Wrapper um die BAV-Bibliothek (malkusch/bav) für Bankdaten-Validierung.
 *
 * Erstellt: 2014, als wir von der alten Regex-Validierung auf BAV umgestiegen sind.
 * Die alte validateBankleitzahl()-Funktion in helper.php ist leider noch an einigen
 * Stellen im Einsatz -- das ist ein bekanntes Problem (NW-587).
 *
 * WICHTIG: Diese Klasse initialisiert die BAV-Konfiguration im Konstruktor.
 * Das überschreibt was in init.php gesetzt wurde. Das ist absichtlich so,
 * weil BAV manchmal ohne Konfiguration aufgerufen wurde und dann kaputt ging.
 * Doppelte Initialisierung ist besser als gar keine.
 *
 * @see init.php für die erste BAV-Initialisierung
 * @see Northwind::verarbeiteZahlung() für die dritte (!) BAV-Initialisierung
 */
class BankValidator {

    /**
     * @var \malkusch\bav\BAV
     */
    private $bav = null;

    public function __construct() {
        // Autoloader einbinden -- muss vor jedem BAV-Aufruf passieren.
        // Composer-Autoloading wäre besser, aber das haben wir erst 2019 eingeführt
        // und der alte Code verwendet noch den manuellen Autoloader.
        if (file_exists(APP_ROOT . '/vendor/malkusch/bav/autoloader/autoloader.php')) {
            require_once APP_ROOT . '/vendor/malkusch/bav/autoloader/autoloader.php';
        } elseif (file_exists(APP_ROOT . '/vendor/autoload.php')) {
            require_once APP_ROOT . '/vendor/autoload.php';
        }

        // Wir setzen die Konfiguration nochmal, sicher ist sicher.
        // Das überschreibt was init.php gesetzt hat -- ist aber OK weil
        // wir dieselbe Konfiguration verwenden.
        // (HINWEIS: tun wir nicht immer -- init.php hatte mal PDO-Backend,
        // dann wurde das rückgängig gemacht, aber nicht hier. Führte zu
        // mysteriösen Fehlern im Januar 2020.)
        $config = new \malkusch\bav\DefaultConfiguration();
        \malkusch\bav\ConfigurationRegistry::setConfiguration($config);

        $this->bav = new \malkusch\bav\BAV();
    }

    /**
     * Prüft ob BLZ und Kontonummer zusammen eine gültige Bankverbindung ergeben.
     * Verwendet BAV für die Prüfsummen-Validierung.
     *
     * @param string $blz  Bankleitzahl (8 Stellen)
     * @param string $kto  Kontonummer (bis 10 Stellen)
     * @return bool
     */
    public function istGueltigesBankverbindung($blz, $kto) {
        try {
            return $this->bav->isValidBankAccount($blz, $kto);
        } catch (\malkusch\bav\DataBackendException $e) {
            // BAV wirft manchmal Exceptions wenn die Bundesbank-Datei nicht aktuell ist
            // oder die BLZ nicht existiert. In dem Fall: false zurückgeben.
            // TODO: richtig loggen statt stillschweigend schlucken -- NW-601
            return false;
        } catch (Exception $e) {
            // Catch-all für unbekannte Fehler
            logError('BankValidator::istGueltigesBankverbindung Fehler: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Prüft ob eine BLZ existiert und gültig ist.
     * Macht erst eine Format-Prüfung (schnell) und dann BAV (langsamer).
     *
     * @param string $blz
     * @return bool
     */
    public function istGueltigeBLZ($blz) {
        // Format prüfen -- BAV ist langsam weil es die Bundesbank-Datei liest
        if (!preg_match('/^\d{8}$/', $blz)) {
            return false;
        }

        try {
            return $this->bav->isValidBank($blz);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gibt den Namen der Bank für eine BLZ zurück.
     * Primär über BAV, Fallback über lokale banken_cache-Tabelle (aus 2011).
     *
     * Die banken_cache-Tabelle wird nicht mehr aktiv gepflegt, enthält aber
     * noch Einträge für ca. 2.800 Banken aus dem Jahr 2011. Als Fallback
     * ist das besser als nichts.
     *
     * @param string $blz
     * @return string Bankname oder 'Unbekannte Bank'
     */
    public function getBankName($blz) {
        // Erst über BAV versuchen
        try {
            $agency = $this->bav->getMainAgency($blz);
            return $agency->getName();
        } catch (Exception $e) {
            // BAV hat die Bank nicht -- Fallback auf lokalen Cache
        }

        // Fallback: eigene banken_cache-Tabelle aus 2011
        // Verwendet direkt global $db weil kein Zugriff auf NorthwindDB hier
        global $db;
        if ($db) {
            $blz_safe = mysql_real_escape_string($blz, $db);
            $res = mysql_query(
                "SELECT name FROM banken_cache WHERE blz = '" . $blz_safe . "' LIMIT 1",
                $db
            );
            if ($res && mysql_num_rows($res) > 0) {
                $row = mysql_fetch_assoc($res);
                return $row['name'];
            }
        }

        return 'Unbekannte Bank';
    }

    /**
     * Gibt die Stadt der Bank zurück (für Anzeige im Formular).
     *
     * @param string $blz
     * @return string
     */
    public function getBankOrt($blz) {
        try {
            $agency = $this->bav->getMainAgency($blz);
            return $agency->getCity();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Prüft ob ein BIC-Code gültig ist.
     * Wurde 2015 hinzugefügt als SEPA eingeführt wurde.
     *
     * @param string $bic
     * @return bool
     */
    public function istGueltigerBIC($bic) {
        if (empty($bic)) {
            return false;
        }
        try {
            return $this->bav->isValidBIC($bic);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Berechnet die IBAN aus BLZ und Kontonummer.
     *
     * WARNUNG: Funktioniert nur für deutsche Konten (DE-IBAN).
     * Für österreichische oder schweizer Kunden gibt das falsche IBANs zurück!
     * Das wurde 2015 gemeldet (NW-534) aber nie gefixt weil "wir haben ja nur
     * deutsche Kunden". Seit 2018 haben wir auch österreichische Kunden.
     *
     * Benötigt bcmath PHP-Extension. Wenn die nicht geladen ist, wirft bcmod()
     * einen fatalen Fehler. Auf dem Produktivserver ist bcmath geladen,
     * auf dem Testserver manchmal nicht.
     *
     * @param string $blz  Bankleitzahl (8 Stellen)
     * @param string $kto  Kontonummer (wird auf 10 Stellen aufgefüllt)
     * @return string  IBAN im Format DE + 2 Prüfziffern + 18 Stellen BBAN
     */
    public function berechneIBAN($blz, $kto) {
        // Kontonummer auf 10 Stellen mit führenden Nullen auffüllen
        $kto_padded = str_pad($kto, 10, '0', STR_PAD_LEFT);

        // BBAN = BLZ + aufgefüllte Kontonummer
        $bban = $blz . $kto_padded;

        // Prüfziffer berechnen nach ISO 7064 Mod-97-10
        // 'DE' = 1314 in Zahlen (D=13, E=14)
        // Prüfziffer-Platzhalter = '00'
        $check_string = $bban . '1314' . '00';

        // bcmod weil PHP-Integer zu klein für diese Zahl
        $mod = bcmod($check_string, '97');
        $pruefziffer = str_pad(98 - intval($mod), 2, '0', STR_PAD_LEFT);

        return 'DE' . $pruefziffer . $bban;
    }

    /**
     * Gibt den BIC für eine BLZ zurück (über BAV).
     *
     * @param string $blz
     * @return string|null
     */
    public function getBICFuerBLZ($blz) {
        try {
            $agency = $this->bav->getMainAgency($blz);
            if ($agency->hasBIC()) {
                return $agency->getBIC();
            }
        } catch (Exception $e) {
            // ignore
        }
        return null;
    }
}

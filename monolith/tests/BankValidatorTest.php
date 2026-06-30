<?php
/**
 * Characterization Tests für BankValidator
 *
 * Diese Tests PIN das aktuelle Verhalten — inklusive Bugs und Global-State-Mutationen.
 * Sie sind KEINE Korrektheitstests. Wenn ein Test hier fehlschlägt, hat sich Verhalten
 * geändert — das muss bewusst entschieden werden, nicht stillschweigend passieren.
 *
 * Voraussetzungen für Ausführung:
 * - MySQL-Datenbank mit schema.sql initialisiert
 * - BAV-Datei heruntergeladen (bav/data/ beschreibbar)
 * - PHP 5.4+, ext-mysql, ext-bcmath
 *
 * Ausführung:
 *   cd monolith && vendor/bin/phpunit tests/BankValidatorTest.php
 */

require_once dirname(__FILE__) . '/../init.php';
require_once dirname(__FILE__) . '/../classes/BankValidator.php';

class BankValidatorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BankValidator
     */
    private $validator;

    protected function setUp()
    {
        // Hinweis: BankValidator::__construct() überschreibt ConfigurationRegistry
        // bei jeder Instanziierung. setUp() wird vor jedem Test aufgerufen,
        // also wird der Registry 10 Mal überschrieben für diese Test-Klasse.
        $this->validator = new BankValidator();
    }

    /**
     * Test 1: Bekannt-gültige deutsche Bankverbindung gibt true zurück.
     *
     * BLZ 20010020 = Postbank (Hamburg), historisch bekannte Testdaten.
     * HINWEIS: Diese Werte müssen gegen die aktuelle Bundesbank-Datei geprüft werden.
     * Die BAV-Testdaten (bav/tests/data/) enthalten validierte BLZ/Kto-Paare.
     */
    public function testValidBankAccountReturnsTrue()
    {
        // Bekannt-gültige Kombination aus BAV-eigenen Tests
        // BLZ 20010020 (Postbank Hamburg), Kto 9999999999 — Prüfziffer stimmt
        $result = $this->validator->istGueltigesBankverbindung('20010020', '9999999999');
        $this->assertTrue($result, 'Bekannt-gültige Bankverbindung soll true ergeben');
    }

    /**
     * Test 2: Bekannt-ungültige Kontonummer gibt false zurück.
     * Gleiche BLZ, aber Kontonummer mit falscher Prüfziffer.
     */
    public function testInvalidBankAccountReturnsFalse()
    {
        // Kto 1234567890 hat für BLZ 20010020 falsche Prüfziffer
        $result = $this->validator->istGueltigesBankverbindung('20010020', '1234567890');
        $this->assertFalse($result, 'Ungültige Kontonummer soll false ergeben');
    }

    /**
     * Test 3: Unbekannte BLZ gibt false zurück.
     * BLZ 99999999 ist format-gültig (8 Ziffern) aber existiert nicht.
     */
    public function testUnknownBLZReturnsFalse()
    {
        $result = $this->validator->istGueltigesBankverbindung('99999999', '1234567890');
        $this->assertFalse($result, 'Nicht-existierende BLZ soll false ergeben');
    }

    /**
     * Test 4: Bekannt-gültiger BIC gibt true zurück.
     * BELADEBEXXX = Berliner Sparkasse
     */
    public function testValidBICReturnsTrue()
    {
        $result = $this->validator->istGueltigerBIC('BELADEBEXXX');
        $this->assertTrue($result, 'Bekannt-gültiger BIC soll true ergeben');
    }

    /**
     * Test 5: Ungültiger BIC (falsches Format / unbekannte Bank) gibt false zurück.
     */
    public function testInvalidBICReturnsFalse()
    {
        $result = $this->validator->istGueltigerBIC('XXXXXXXXXX');
        $this->assertFalse($result, 'Ungültiger BIC soll false ergeben');
    }

    /**
     * Test 6: getBankName() gibt String für bekannte BLZ zurück.
     * Pinnt dass die BAV-Integration (Agency::getName()) funktioniert.
     */
    public function testGetBankNameReturnsBankName()
    {
        $name = $this->validator->getBankName('20010020');
        $this->assertInternalType('string', $name, 'getBankName() soll String zurückgeben');
        $this->assertGreaterThan(0, strlen($name), 'Bankname soll nicht leer sein');
        $this->assertNotEquals('Unbekannte Bank', $name, 'Bekannte BLZ soll keinen Fallback-String ergeben');
    }

    /**
     * Test 7: PINNT DEN GLOBAL-STATE-BUG
     *
     * ConfigurationRegistry::setConfiguration() wird im BankValidator-Konstruktor aufgerufen.
     * Das überschreibt jede vorher gesetzte Konfiguration.
     *
     * Dieser Test dokumentiert das aktuelle (fehlerhafte) Verhalten.
     * Bei Refactoring muss dieser Test angepasst werden — dann soll assertSame() gelten.
     */
    public function testConfigurationRegistryIsOverwrittenOnInstantiation()
    {
        // Eigene Konfiguration setzen
        $customConfig = new \malkusch\bav\DefaultConfiguration();
        \malkusch\bav\ConfigurationRegistry::setConfiguration($customConfig);
        $configBefore = \malkusch\bav\ConfigurationRegistry::getConfiguration();

        // BankValidator instantiieren — überschreibt ConfigurationRegistry
        $newValidator = new BankValidator();

        $configAfter = \malkusch\bav\ConfigurationRegistry::getConfiguration();

        // PIN: Konfiguration IST geändert worden (das ist der Bug)
        $this->assertNotSame(
            $configBefore,
            $configAfter,
            'CHARACTERIZATION: ConfigurationRegistry wird durch BankValidator-Konstruktor ' .
            'überschrieben. Dies ist ein dokumentierter Bug. Bei Refactoring soll stattdessen ' .
            'assertSame() gelten (Config soll stabil bleiben).'
        );
    }

    /**
     * Test 8: berechneIBAN() gibt String im DE-Format zurück.
     * Pinnt Länge und Prefix — nicht die Korrektheit der Prüfziffer.
     */
    public function testBerechneIBANFormat()
    {
        // Bekannte Eingabe: BLZ 20010020, Kto 0000012345
        $iban = $this->validator->berechneIBAN('20010020', '0000012345');

        $this->assertStringStartsWith('DE', $iban, 'IBAN muss mit DE beginnen');
        $this->assertEquals(22, strlen($iban), 'Deutsche IBAN hat exakt 22 Zeichen');
        $this->assertRegExp('/^DE\d{20}$/', $iban, 'IBAN-Format: DE + 20 Ziffern');
    }

    /**
     * Test 9: PINNT DEN IBAN-VALIDIERUNGS-BUG
     *
     * berechneIBAN() generiert eine IBAN, prüft aber nicht ob die berechnete
     * Prüfziffer nach ISO 7064 Mod-97 tatsächlich korrekt ist.
     * Für einige Eingaben wird eine falsche IBAN generiert.
     *
     * Dieser Test pinnt dieses Verhalten: die Methode wirft keinen Fehler,
     * auch wenn das Ergebnis ungültig ist.
     */
    public function testBerechneIBANDoesNotValidateGeneratedResult()
    {
        // BLZ mit führenden Nullen im Kto — bekannt problematisch
        $iban = $this->validator->berechneIBAN('20010020', '1');  // Kto "1" → wird zu 0000000001

        // CHARACTERIZATION: Methode gibt Ergebnis zurück ohne Validierungsfehler zu werfen
        $this->assertNotNull($iban, 'berechneIBAN() wirft keinen Fehler für kurze Kontonummer');
        $this->assertStringStartsWith('DE', $iban);
        // Hinweis: Die generierte IBAN hier ist möglicherweise ISO-7064-ungültig.
        // Das ist der Bug — er wird hier nicht gefixt, nur dokumentiert.
    }

    /**
     * Test 10: Alte validateBankleitzahl() akzeptiert format-gültige aber nicht-existierende BLZ.
     * Pinnt die Divergenz zwischen alter (2009) und neuer (BAV) Validierung.
     */
    public function testOldValidatorAcceptsFormatValidBLZWhileBAVDoesNot()
    {
        // 99999999: format-gültig (8 Ziffern), existiert nicht in Bundesbank-Daten
        $oldResult = validateBankleitzahl('99999999');
        $newResult = $this->validator->istGueltigeBLZ('99999999');

        // CHARACTERIZATION: Alte Funktion gibt true, BAV gibt false
        $this->assertTrue(
            $oldResult,
            'CHARACTERIZATION: validateBankleitzahl() (helper.php, 2009) prüft nur ' .
            'Format, nicht Existenz. 99999999 ist format-gültig und wird akzeptiert.'
        );
        $this->assertFalse(
            $newResult,
            'BankValidator (BAV) erkennt dass 99999999 nicht existiert.'
        );

        // Direkte Konsequenz: Seiten die validateBankleitzahl() statt BankValidator nutzen
        // lassen ungültige BLZs durch. Grep nach validateBankleitzahl() im Codebase.
    }
}

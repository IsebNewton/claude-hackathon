<?php
/**
 * Characterization Tests für Kundenverwaltung
 *
 * Pinnt insbesondere:
 * - Inkonsistentes Lösch-Verhalten (Soft vs. Hard Delete)
 * - Doppelte BLZ-Validierung (alte helper.php + neue BankValidator)
 * - BAV-Ergebnis-Caching im kunden-Datensatz
 */

require_once dirname(__FILE__) . '/../init.php';
require_once dirname(__FILE__) . '/../classes/Northwind.php';
require_once dirname(__FILE__) . '/../classes/BankValidator.php';

class KundenTest extends PHPUnit_Framework_TestCase
{
    private $nw;
    private $db;

    protected function setUp()
    {
        global $db;
        $this->db = $db;
        $this->nw = new Northwind($db);
    }

    protected function tearDown()
    {
        mysql_query("DELETE FROM auftrag_positionen WHERE auftrag_id IN (SELECT id FROM auftraege WHERE kunden_id IN (SELECT id FROM kunden WHERE nummer LIKE 'KD-CHARTEST-%'))", $this->db);
        mysql_query("DELETE FROM lieferungen WHERE auftrag_id IN (SELECT id FROM auftraege WHERE kunden_id IN (SELECT id FROM kunden WHERE nummer LIKE 'KD-CHARTEST-%'))", $this->db);
        mysql_query("DELETE FROM rechnungen WHERE kunden_id IN (SELECT id FROM kunden WHERE nummer LIKE 'KD-CHARTEST-%')", $this->db);
        mysql_query("DELETE FROM auftraege WHERE kunden_id IN (SELECT id FROM kunden WHERE nummer LIKE 'KD-CHARTEST-%')", $this->db);
        mysql_query("DELETE FROM kunden WHERE nummer LIKE 'KD-CHARTEST-%'", $this->db);
    }

    /**
     * Test 1: Kunde speichern mit gültiger Bankverbindung setzt bav_ergebnis=1.
     *
     * speichereKunde() ruft BAV intern auf und cached das Ergebnis in bav_ergebnis.
     */
    public function testSpeichereKundeWithValidBankdatenSetsBavErgebnis()
    {
        $daten = array(
            'nummer'        => 'KD-CHARTEST-001',
            'name'          => 'BAV Test GmbH',
            'strasse'       => 'Hauptstr.',
            'hausnummer'    => '1',
            'plz'           => '20000',
            'ort'           => 'Hamburg',
            'bankleitzahl'  => '20010020',
            'kontonummer'   => '9999999999',
        );

        $kunden_id = $this->nw->speichereKunde($daten);
        $this->assertNotFalse($kunden_id, 'Kunde mit gültiger Bankverbindung muss gespeichert werden');

        $row = mysql_fetch_assoc(mysql_query("SELECT bav_ergebnis FROM kunden WHERE id = " . (int)$kunden_id, $this->db));
        $this->assertEquals(
            1,
            (int)$row['bav_ergebnis'],
            'bav_ergebnis muss 1 sein für valide Bankverbindung'
        );
    }

    /**
     * Test 2: PINNT INKONSISTENTES LÖSCH-VERHALTEN (Soft Delete)
     *
     * loescheKunde() mit Kunden der OFFENE AUFTRÄGE hat: setzt aktiv=0 (Soft Delete).
     * Die Methode prüft ob Aufträge existieren und entscheidet dann.
     */
    public function testLoescheKundeWithOrdersDoesNotHardDelete()
    {
        // Kunde mit Auftrag anlegen
        $kunden_id = $this->_erstelleTestkunde('KD-CHARTEST-002', 'Kunde Mit Auftrag AG');
        mysql_query("INSERT INTO auftraege (kunden_id, auftrag_nr, status, angelegt_am)
                     VALUES (" . (int)$kunden_id . ", 'AUF-TEST-001', 1, NOW())", $this->db);

        $this->nw->loescheKunde($kunden_id);

        // Kunde muss noch in DB sein (Soft Delete)
        $row = mysql_fetch_assoc(mysql_query("SELECT id, aktiv FROM kunden WHERE id = " . (int)$kunden_id, $this->db));
        $this->assertNotNull($row, 'CHARACTERIZATION: Kunde mit Aufträgen wird NICHT aus DB gelöscht (Soft Delete)');
        $this->assertEquals(0, (int)$row['aktiv'], 'Kunde wird auf aktiv=0 gesetzt');

        // Cleanup Auftrag
        mysql_query("DELETE FROM auftraege WHERE kunden_id = " . (int)$kunden_id, $this->db);
    }

    /**
     * Test 3: PINNT INKONSISTENTES LÖSCH-VERHALTEN (Hard Delete)
     *
     * loescheKunde() mit Kunden OHNE Aufträge: Hard Delete aus DB.
     * Gleiche Methode, anderes Verhalten — je nach Datenlage.
     */
    public function testLoescheKundeWithoutOrdersHardDeletes()
    {
        $kunden_id = $this->_erstelleTestkunde('KD-CHARTEST-003', 'Kunde Ohne Auftraege GmbH');

        $this->nw->loescheKunde($kunden_id);

        // Kunde darf NICHT mehr in DB sein (Hard Delete)
        $row = mysql_fetch_assoc(mysql_query("SELECT id FROM kunden WHERE id = " . (int)$kunden_id, $this->db));
        $this->assertFalse(
            $row,
            'CHARACTERIZATION: Kunde ohne Aufträge wird HART aus DB gelöscht. ' .
            'Gleiche Methode loescheKunde(), anderes Verhalten als Test 2. ' .
            'Das ist inkonsistent und macht es schwer, Lösch-Verhalten vorherzusagen.'
        );
    }

    /**
     * Test 4: PINNT DOPPELTE VALIDIERUNG
     *
     * validiereKundendaten() ruft sowohl die alte validateBankleitzahl() (helper.php)
     * als auch BankValidator auf. Beide werden tatsächlich aufgerufen.
     *
     * Konsequenz: Eine BLZ die nur Format-valide (nicht BAV-valide) ist, wird
     * von validateBankleitzahl() akzeptiert aber von BankValidator abgelehnt —
     * je nach Code-Pfad gewinnt einer.
     */
    public function testValidiereKundendatenCallsBothOldAndNewValidator()
    {
        // BLZ 99999999 ist format-gültig (8 Ziffern) aber existiert nicht
        $daten = array(
            'nummer'        => 'KD-CHARTEST-004',
            'name'          => 'Validierungs Test GmbH',
            'strasse'       => 'Testgasse',
            'hausnummer'    => '7',
            'plz'           => '10000',
            'ort'           => 'Berlin',
            'bankleitzahl'  => '99999999',
            'kontonummer'   => '1234567890',
        );

        $fehler = $this->nw->validiereKundendaten($daten);

        // CHARACTERIZATION: BAV-Fehler wird zurückgemeldet (BankValidator verliert nicht gegen alte Funktion)
        $this->assertNotEmpty(
            $fehler,
            'CHARACTERIZATION: validiereKundendaten() meldet Fehler für nicht-existierende BLZ, ' .
            'weil BankValidator::istGueltigeBLZ() false zurückgibt. ' .
            'validateBankleitzahl() (helper.php) würde true zurückgeben — die neuere Validierung gewinnt.'
        );

        // Prüfe dass der Fehler tatsächlich die BLZ-Validierung betrifft
        $blz_fehler = array_filter($fehler, function($f) {
            return strpos(strtolower($f), 'bankleitzahl') !== false || strpos(strtolower($f), 'bank') !== false;
        });
        $this->assertNotEmpty($blz_fehler, 'Mindestens ein Fehler muss sich auf die Bankleitzahl beziehen');
    }

    // -------------------------------------------------------------------------

    private function _erstelleTestkunde($nummer, $name)
    {
        mysql_query("INSERT INTO kunden (nummer, name, strasse, hausnummer, plz, ort, angelegt_am)
                     VALUES ('" . mysql_real_escape_string($nummer, $this->db) . "',
                             '" . mysql_real_escape_string($name, $this->db) . "',
                             'Teststr.', '1', '00000', 'Teststadt', NOW())", $this->db);
        return mysql_insert_id($this->db);
    }
}

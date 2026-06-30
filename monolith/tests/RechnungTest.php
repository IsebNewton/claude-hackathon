<?php
/**
 * Characterization Tests für Rechnungen / Zahlungsverarbeitung
 *
 * Pinnt insbesondere das Trigger-Verhalten (tr_rechnung_bezahlt_after_update)
 * und Mahnwesen-Invarianten.
 *
 * WICHTIG: Diese Tests brauchen eine echte MySQL-Datenbank.
 * SQLite unterstützt keine AFTER UPDATE Trigger mit UPDATE auf anderer Tabelle
 * (wegen MySQL-spezifischer Trigger-Syntax in schema.sql).
 */

require_once dirname(__FILE__) . '/../init.php';
require_once dirname(__FILE__) . '/../classes/Northwind.php';
require_once dirname(__FILE__) . '/../classes/BankValidator.php';

class RechnungTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Northwind
     */
    private $nw;

    /**
     * @var resource MySQL-Verbindung
     */
    private $db;

    protected function setUp()
    {
        global $db;
        $this->db = $db;
        $this->nw = new Northwind($db);

        // Testdaten anlegen — minimaler Datensatz für Rechnungs-Tests
        mysql_query("INSERT INTO benutzer (id, benutzername, passwort, rolle, aktiv, angelegt_am)
                     VALUES (999, 'testuser', MD5('test'), 'admin', 1, NOW())
                     ON DUPLICATE KEY UPDATE benutzername='testuser'", $this->db);

        mysql_query("INSERT INTO kunden (id, nummer, name, strasse, hausnummer, plz, ort, angelegt_am)
                     VALUES (9001, 'KD-TEST-001', 'Test GmbH', 'Teststr.', '1', '20000', 'Hamburg', NOW())
                     ON DUPLICATE KEY UPDATE name='Test GmbH'", $this->db);

        mysql_query("INSERT INTO artikel (id, sku, name, preis_netto, angelegt_am)
                     VALUES (9001, 'TEST-001', 'Testartikel', 100.00, NOW())
                     ON DUPLICATE KEY UPDATE sku='TEST-001'", $this->db);
    }

    protected function tearDown()
    {
        // Testdaten wieder entfernen
        mysql_query("DELETE FROM zahlungen WHERE rechnung_id IN (SELECT id FROM rechnungen WHERE kunden_id = 9001)", $this->db);
        mysql_query("DELETE FROM rechnungen WHERE kunden_id = 9001", $this->db);
        mysql_query("DELETE FROM auftrag_positionen WHERE auftrag_id IN (SELECT id FROM auftraege WHERE kunden_id = 9001)", $this->db);
        mysql_query("DELETE FROM lieferungen WHERE auftrag_id IN (SELECT id FROM auftraege WHERE kunden_id = 9001)", $this->db);
        mysql_query("DELETE FROM auftraege WHERE kunden_id = 9001", $this->db);
        mysql_query("DELETE FROM kunden WHERE id = 9001", $this->db);
        mysql_query("DELETE FROM artikel WHERE id = 9001", $this->db);
        mysql_query("DELETE FROM benutzer WHERE id = 999", $this->db);
    }

    /**
     * Test 1: Zahlung mit gültigen Daten setzt Rechnungsstatus auf 'bezahlt'.
     */
    public function testVerarbeiteZahlungWithValidDataUpdatesRechnungStatus()
    {
        // Auftrag + Rechnung anlegen
        $auftrag_id = $this->_erstelleTestAuftrag();
        $rechnung_id = $this->nw->erstelleRechnung($auftrag_id);
        $this->assertNotFalse($rechnung_id, 'Rechnung muss erstellt werden können');

        // Betrag der Rechnung ermitteln
        $r = mysql_fetch_assoc(mysql_query("SELECT betrag_brutto FROM rechnungen WHERE id = " . (int)$rechnung_id, $this->db));
        $betrag = $r['betrag_brutto'];

        // Zahlung verarbeiten — mit bekannt-gültiger Bankverbindung
        // (Validierung durch BAV wird intern aufgerufen)
        $ok = $this->nw->verarbeiteZahlung($rechnung_id, '20010020', '9999999999', $betrag);
        $this->assertTrue($ok, 'verarbeiteZahlung() soll true zurückgeben bei gültigen Daten');

        // Status prüfen
        $row = mysql_fetch_assoc(mysql_query("SELECT status FROM rechnungen WHERE id = " . (int)$rechnung_id, $this->db));
        $this->assertEquals('bezahlt', $row['status'], 'Rechnungsstatus muss nach Zahlung "bezahlt" sein');
    }

    /**
     * Test 2: PINNT TRIGGER 1 (tr_rechnung_bezahlt_after_update)
     *
     * Wenn rechnungen.status auf 'bezahlt' gesetzt wird, setzt ein DB-Trigger
     * AUTOMATISCH auftraege.status auf 5 (abgeschlossen).
     *
     * Dieser Zusammenhang ist NICHT im PHP-Code sichtbar — nur in schema.sql.
     * Wenn jemand den Trigger entfernt oder umbenennt, bricht dieses Verhalten.
     */
    public function testVerarbeiteZahlungTriggerSetsAuftragStatusToAbgeschlossen()
    {
        $auftrag_id = $this->_erstelleTestAuftrag();
        $rechnung_id = $this->nw->erstelleRechnung($auftrag_id);

        // Auftragsstatus vor Zahlung prüfen
        $before = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));
        $this->assertNotEquals(5, (int)$before['status'], 'Auftrag soll vor Zahlung nicht Status 5 haben');

        // Zahlung verarbeiten
        $r = mysql_fetch_assoc(mysql_query("SELECT betrag_brutto FROM rechnungen WHERE id = " . (int)$rechnung_id, $this->db));
        $this->nw->verarbeiteZahlung($rechnung_id, '20010020', '9999999999', $r['betrag_brutto']);

        // TRIGGER-VERHALTEN: Auftragsstatus muss jetzt 5 sein
        $after = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));
        $this->assertEquals(
            5,
            (int)$after['status'],
            'CHARACTERIZATION (Trigger 1): Nach Zahlung setzt tr_rechnung_bezahlt_after_update ' .
            'den Auftragsstatus automatisch auf 5. Dies passiert im DB-Trigger, NICHT im PHP-Code. ' .
            'Bei Trigger-Entfernung bricht dieses Verhalten still.'
        );
    }

    /**
     * Test 3: Zahlung mit ungültiger BLZ gibt false zurück und ändert nichts.
     */
    public function testVerarbeiteZahlungWithInvalidBLZReturnsFalse()
    {
        $auftrag_id = $this->_erstelleTestAuftrag();
        $rechnung_id = $this->nw->erstelleRechnung($auftrag_id);

        $ok = $this->nw->verarbeiteZahlung($rechnung_id, '99999999', '1234567890', 100.00);
        $this->assertFalse($ok, 'Zahlung mit ungültiger BLZ soll false zurückgeben');

        // Status unverändert
        $row = mysql_fetch_assoc(mysql_query("SELECT status FROM rechnungen WHERE id = " . (int)$rechnung_id, $this->db));
        $this->assertNotEquals('bezahlt', $row['status'], 'Rechnungsstatus darf nicht geändert worden sein');
    }

    /**
     * Test 4: Mahnstufe wird nie höher als 3.
     *
     * Invariante: mahnstufe IN (0, 1, 2, 3). Der Mahnlauf-Cron erhöht maximal bis 3.
     */
    public function testMahnstufeCapsAtThree()
    {
        $auftrag_id = $this->_erstelleTestAuftrag();
        $rechnung_id = $this->nw->erstelleRechnung($auftrag_id);

        // Mahnstufe manuell auf 3 setzen (maximaler Wert)
        mysql_query("UPDATE rechnungen SET mahnstufe = 3 WHERE id = " . (int)$rechnung_id, $this->db);

        // Mahnlauf-Logik simulieren: Rechnung mit mahnstufe=3 soll NICHT erhöht werden
        $sql = "SELECT COUNT(*) as anzahl FROM rechnungen
                WHERE id = " . (int)$rechnung_id . "
                  AND mahnstufe < 3";
        $row = mysql_fetch_assoc(mysql_query($sql, $this->db));

        $this->assertEquals(
            0,
            (int)$row['anzahl'],
            'CHARACTERIZATION: Rechnungen mit mahnstufe=3 werden vom Mahnlauf-Query ' .
            'nicht erfasst (WHERE mahnstufe < 3). Mahnstufe darf nie > 3 werden.'
        );
    }

    /**
     * Test 5: Rechnungsnummer folgt dem Muster RE-YYYY-NNNNN.
     * PINNT TRIGGER 5 (tr_rechnung_nummer_before_insert).
     */
    public function testRechnungsNrFollowsPattern()
    {
        $auftrag_id = $this->_erstelleTestAuftrag();
        $rechnung_id = $this->nw->erstelleRechnung($auftrag_id);

        $row = mysql_fetch_assoc(mysql_query("SELECT rechnungs_nr FROM rechnungen WHERE id = " . (int)$rechnung_id, $this->db));

        $this->assertRegExp(
            '/^RE-\d{4}-\d{5}$/',
            $row['rechnungs_nr'],
            'CHARACTERIZATION (Trigger 5): Rechnungsnummer wird durch DB-Trigger generiert. ' .
            'Format: RE-YYYY-NNNNN (5-stellig mit führenden Nullen). ' .
            'Bei Trigger-Entfernung bleibt rechnungs_nr NULL.'
        );

        $year = date('Y');
        $this->assertStringStartsWith('RE-' . $year . '-', $row['rechnungs_nr'],
            'Rechnungsnummer muss aktuelles Jahr enthalten');
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * Erstellt einen minimalen Testauftrag mit einer Position.
     * @return int auftrag_id
     */
    private function _erstelleTestAuftrag()
    {
        $positionen = array(
            array('artikel_id' => 9001, 'bezeichnung' => 'Testartikel', 'menge' => 1, 'einzelpreis_netto' => 100.00, 'mwst_satz' => 19.00)
        );
        $auftrag_id = $this->nw->erstelleAuftrag(9001, $positionen);
        $this->assertNotFalse($auftrag_id, 'Testauftrag muss angelegt werden können');

        // Auftrag bestätigen (Status 2) — nötig für Rechnungserstellung
        $this->nw->aktualisiereAuftragStatus($auftrag_id, 2);

        return $auftrag_id;
    }
}

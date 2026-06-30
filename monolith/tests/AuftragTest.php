<?php
/**
 * Characterization Tests für Aufträge / Trigger-Verhalten
 *
 * Pinnt insbesondere:
 * - Trigger 2: lieferung INSERT → auftrag status=3
 * - Trigger 3 (BUG): lieferung.status='zugestellt' → auftrag status=4 (nicht 5!)
 * - Trigger 4: auftrag status=2 → lagerbestand--
 * - BUG: Storno restauriert Lagerbestand NICHT
 */

require_once dirname(__FILE__) . '/../init.php';
require_once dirname(__FILE__) . '/../classes/Northwind.php';

class AuftragTest extends PHPUnit_Framework_TestCase
{
    private $nw;
    private $db;

    protected function setUp()
    {
        global $db;
        $this->db = $db;
        $this->nw = new Northwind($db);

        mysql_query("INSERT INTO kunden (id, nummer, name, strasse, hausnummer, plz, ort, angelegt_am)
                     VALUES (9002, 'KD-TEST-002', 'Auftrag TestGmbH', 'Industrieweg', '42', '22000', 'Hamburg', NOW())
                     ON DUPLICATE KEY UPDATE name='Auftrag TestGmbH'", $this->db);

        mysql_query("INSERT INTO artikel (id, sku, name, preis_netto, lagerbestand, angelegt_am)
                     VALUES (9002, 'LAGER-001', 'Lagerartikel', 50.00, 100, NOW())
                     ON DUPLICATE KEY UPDATE lagerbestand=100", $this->db);
    }

    protected function tearDown()
    {
        mysql_query("DELETE FROM auftrag_positionen WHERE auftrag_id IN (SELECT id FROM auftraege WHERE kunden_id = 9002)", $this->db);
        mysql_query("DELETE FROM lieferungen WHERE auftrag_id IN (SELECT id FROM auftraege WHERE kunden_id = 9002)", $this->db);
        mysql_query("DELETE FROM rechnungen WHERE kunden_id = 9002", $this->db);
        mysql_query("DELETE FROM auftraege WHERE kunden_id = 9002", $this->db);
        mysql_query("DELETE FROM kunden WHERE id = 9002", $this->db);
        mysql_query("DELETE FROM artikel WHERE id = 9002", $this->db);
    }

    /**
     * Test 1: erstelleAuftrag() legt Positionen in auftrag_positionen an.
     */
    public function testErstelleAuftragCreatesPositionen()
    {
        $positionen = array(
            array('artikel_id' => 9002, 'bezeichnung' => 'Lagerartikel', 'menge' => 3, 'einzelpreis_netto' => 50.00, 'mwst_satz' => 19.00),
            array('artikel_id' => null,  'bezeichnung' => 'Manuelle Position', 'menge' => 1, 'einzelpreis_netto' => 25.00, 'mwst_satz' => 19.00),
        );

        $auftrag_id = $this->nw->erstelleAuftrag(9002, $positionen);
        $this->assertNotFalse($auftrag_id);

        $row = mysql_fetch_assoc(mysql_query(
            "SELECT COUNT(*) as anzahl FROM auftrag_positionen WHERE auftrag_id = " . (int)$auftrag_id,
            $this->db
        ));
        $this->assertEquals(2, (int)$row['anzahl'], 'Beide Positionen müssen in auftrag_positionen stehen');
    }

    /**
     * Test 2: PINNT TRIGGER 2 (tr_lieferung_erstellt_after_insert)
     *
     * INSERT in lieferungen für einen Auftrag mit Status 2 (bestätigt)
     * setzt den Auftragsstatus automatisch auf 3 (in_bearbeitung).
     */
    public function testAuftragStatusBecomesInBearbeitungAfterLieferungInsert()
    {
        $auftrag_id = $this->_erstelleUndBestaetigeAuftrag();

        // Status vor Lieferung: 2 (bestätigt)
        $before = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));
        $this->assertEquals(2, (int)$before['status']);

        // Lieferung anlegen — triggert tr_lieferung_erstellt_after_insert
        mysql_query("INSERT INTO lieferungen (auftrag_id, status, carrier_code, angelegt_am)
                     VALUES (" . (int)$auftrag_id . ", 'erstellt', 'DHL', NOW())", $this->db);

        // TRIGGER-VERHALTEN
        $after = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));
        $this->assertEquals(
            3,
            (int)$after['status'],
            'CHARACTERIZATION (Trigger 2): INSERT in lieferungen setzt auftrag.status=3. ' .
            'Passiert im DB-Trigger, nicht im PHP-Code. ' .
            'erstelleLieferung() in Northwind.php ruft diesen Trigger implizit aus.'
        );
    }

    /**
     * Test 3: PINNT TRIGGER 3 + DEN BUG
     *
     * Wenn lieferungen.status auf 'zugestellt' geändert wird, setzt Trigger 3
     * den Auftragsstatus auf 4 (versendet) — NICHT auf 5 (abgeschlossen).
     *
     * Das ist ein Bug: logisch sollte 'zugestellt' → Status 5 führen.
     * Status 5 kommt erst über Trigger 1 (Rechnung bezahlt).
     * Niemand hat das korrigiert weil unklar war welcher Trigger welchen Status setzt.
     */
    public function testAuftragStatusBecomes4NotFiveAfterLieferungZugestellt()
    {
        $auftrag_id = $this->_erstelleUndBestaetigeAuftrag();

        // Lieferung anlegen und abschicken
        mysql_query("INSERT INTO lieferungen (id, auftrag_id, status, carrier_code, tracking_nummer, versanddatum, angelegt_am)
                     VALUES (9001, " . (int)$auftrag_id . ", 'unterwegs', 'DHL', 'TEST123456', NOW(), NOW())", $this->db);

        // Status auf 'zugestellt' setzen — triggert tr_lieferung_zugestellt_after_update
        mysql_query("UPDATE lieferungen SET status = 'zugestellt' WHERE id = 9001", $this->db);

        $after = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));

        $this->assertEquals(
            4,
            (int)$after['status'],
            'CHARACTERIZATION (Trigger 3 Bug): lieferung.status="zugestellt" setzt auftrag.status=4, ' .
            'NICHT 5. Status 5 (abgeschlossen) kommt nur über Trigger 1 (Rechnung bezahlt). ' .
            'Dies ist ein dokumentierter Design-Bug: "zugestellt" und "abgeschlossen" sind ' .
            'konzeptionell entkoppelt über verschiedene Trigger.'
        );

        // Zusätzlich: Status 5 sollte NICHT gesetzt sein
        $this->assertNotEquals(5, (int)$after['status'],
            'Status 5 wird NICHT durch Lieferstatus-Änderung ausgelöst — nur durch Zahlung.');

        // Cleanup
        mysql_query("DELETE FROM lieferungen WHERE id = 9001", $this->db);
    }

    /**
     * Test 4: PINNT TRIGGER 4 (tr_auftrag_bestaetigt_after_update)
     *
     * Wenn Auftrag von Status 1 → 2 wechselt, reduziert Trigger 4
     * den Lagerbestand aller Artikel-Positionen.
     */
    public function testLagerbestandDecreasesAfterAuftragBestaetigt()
    {
        // Lagerbestand vor Auftrag: 100
        $before = mysql_fetch_assoc(mysql_query("SELECT lagerbestand FROM artikel WHERE id = 9002", $this->db));
        $this->assertEquals(100, (int)$before['lagerbestand']);

        $positionen = array(
            array('artikel_id' => 9002, 'bezeichnung' => 'Lagerartikel', 'menge' => 5, 'einzelpreis_netto' => 50.00, 'mwst_satz' => 19.00),
        );
        $auftrag_id = $this->nw->erstelleAuftrag(9002, $positionen);

        // Status 1 → 2: triggert Trigger 4
        $this->nw->aktualisiereAuftragStatus($auftrag_id, 2);

        $after = mysql_fetch_assoc(mysql_query("SELECT lagerbestand FROM artikel WHERE id = 9002", $this->db));
        $this->assertEquals(
            95,
            (int)$after['lagerbestand'],
            'CHARACTERIZATION (Trigger 4): Auftrag-Bestätigung (Status 1→2) dekrementiert ' .
            'Lagerbestand. 100 - 5 = 95. Passiert im DB-Trigger.'
        );
    }

    /**
     * Test 5: PINNT DEN STORNO-BUG
     *
     * storniereAuftrag() setzt Status auf 9, restauriert aber NICHT den Lagerbestand.
     * Trigger 4 (Lagerbestand-Dekrement bei Bestätigung) hat kein Gegenstück.
     * Nach Stornierung ist der Artikel dauerhaft "weg" aus dem Lagerbestand.
     */
    public function testLagerbestandDoesNotRestoreAfterStorno()
    {
        $positionen = array(
            array('artikel_id' => 9002, 'bezeichnung' => 'Lagerartikel', 'menge' => 10, 'einzelpreis_netto' => 50.00, 'mwst_satz' => 19.00),
        );
        $auftrag_id = $this->nw->erstelleAuftrag(9002, $positionen);
        $this->nw->aktualisiereAuftragStatus($auftrag_id, 2);  // Bestätigen → Lager -10

        $nach_bestaetigng = mysql_fetch_assoc(mysql_query("SELECT lagerbestand FROM artikel WHERE id = 9002", $this->db));
        $lager_nach_bestaetigung = (int)$nach_bestaetigng['lagerbestand'];

        // Stornieren
        $this->nw->storniereAuftrag($auftrag_id);

        $nach_storno = mysql_fetch_assoc(mysql_query("SELECT lagerbestand FROM artikel WHERE id = 9002", $this->db));

        // CHARACTERIZATION (BUG): Lagerbestand bleibt nach Storno unverändert
        $this->assertEquals(
            $lager_nach_bestaetigung,
            (int)$nach_storno['lagerbestand'],
            'CHARACTERIZATION (Bug): storniereAuftrag() restauriert den Lagerbestand NICHT. ' .
            'Trigger 4 dekrementiert bei Bestätigung, aber es gibt keinen Gegentrigger für Storno. ' .
            'Bei Refactoring muss die Restaurierung explizit hinzugefügt werden.'
        );

        // Zusätzlich: Auftragsstatus ist 9 (storniert)
        $auftrag = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));
        $this->assertEquals(9, (int)$auftrag['status']);
    }

    /**
     * Test 6: storniereAuftrag() setzt Auftragsstatus auf 9.
     */
    public function testStorniereAuftragSetsStatusNine()
    {
        $auftrag_id = $this->_erstelleUndBestaetigeAuftrag();
        $this->nw->storniereAuftrag($auftrag_id);

        $row = mysql_fetch_assoc(mysql_query("SELECT status FROM auftraege WHERE id = " . (int)$auftrag_id, $this->db));
        $this->assertEquals(9, (int)$row['status'], 'Stornierter Auftrag muss Status 9 haben');
    }

    // -------------------------------------------------------------------------

    private function _erstelleUndBestaetigeAuftrag()
    {
        $positionen = array(
            array('artikel_id' => 9002, 'bezeichnung' => 'Lagerartikel', 'menge' => 2, 'einzelpreis_netto' => 50.00, 'mwst_satz' => 19.00),
        );
        $auftrag_id = $this->nw->erstelleAuftrag(9002, $positionen);
        $this->assertNotFalse($auftrag_id);
        $this->nw->aktualisiereAuftragStatus($auftrag_id, 2);
        return $auftrag_id;
    }
}

# ADR-003: DB-Trigger → Application Code

**Status:** Accepted  
**Datum:** 2026-06-30  
**Entscheider:** Architect

---

## Context

Der Monolith enthält 5 DB-Trigger, die wesentliche Business-Logik implementieren. Diese Trigger sind unsichtbar für den PHP-Code — sie feuern implizit bei INSERT/UPDATE und verändern Zustand in anderen Tabellen. Das führt zu:

- **Race Conditions:** Cron (`update_lieferstatus.php`) und Trigger 3 (`tr_lieferung_zugestellt`) überschreiben sich gegenseitig
- **Versteckte Bugs:** Trigger 4 (`tr_auftrag_bestaetigt`) dekrementiert Lagerbestand bei Bestätigung, aber kein Reverse-Trigger bei Stornierung (Bug NW-445)
- **Deadlock-Risiko:** Trigger 5 (`tr_rechnung_nummer`) setzt TABLE LOCK auf `rechnungs_sequence` (1 Vorfall 2023)
- **Semantik-Bug:** Trigger 3 setzt Auftragsstatus 4 (versendet) statt 5 (abgeschlossen) bei Zustellung

Neue Services dürfen diese Trigger nicht erben — sie müssen die Business-Logik explizit und atomar in Application Code abbilden.

---

## Decision

**Alle 5 Trigger werden bei Extraktion des zugehörigen Services in expliziten Application Code überführt und aus der DB entfernt.**

Die Trigger werden **nicht** parallel zur neuen App-Logik betrieben (kein doppeltes Feuern). Entfernung erfolgt erst wenn der zugehörige Service live und charakterisierungsgetestet ist.

### Trigger-Migrationsplan

| Trigger | Event | Aktuelles Verhalten | Ziel-Service | App-Code | Bug behoben? |
|---|---|---|---|---|---|
| `tr_auftrag_bestaetigt_after_update` | AFTER UPDATE auftraege (status 1→2) | Dekrementiert `lagerbestand` | Order Management Service | `confirmOrder()`: Lagerabzug in DB-Transaktion + Negativbestand-Check | **Ja:** NW-301 + NW-445 |
| `tr_lieferung_erstellt_after_insert` | AFTER INSERT lieferungen | Setzt Auftragsstatus 2→3 | Order Management Service | `createShipment()`: Status-Update explizit | Nein — Verhalten beibehalten |
| `tr_lieferung_zugestellt_after_update` | AFTER UPDATE lieferungen (status='zugestellt') | Setzt Auftragsstatus **4** (falsch) | Shipment Tracking Service | `markDelivered()`: Status-Update auf **5** (korrekt) | **Ja:** Semantik-Bug |
| `tr_rechnung_bezahlt_after_update` | AFTER UPDATE rechnungen (status='bezahlt') | Setzt Auftragsstatus 5 + Timestamps | Invoice/Payment Service | `processPayment()`: Status-Updates explizit in Transaktion | Ja: explizit statt versteckt |
| `tr_rechnung_nummer_before_insert` | BEFORE INSERT rechnungen | TABLE LOCK auf `rechnungs_sequence` → Deadlock-Risiko | Invoice/Payment Service | App-seitige Sequenz (PostgreSQL SEQUENCE, kein TABLE LOCK) | **Ja:** Deadlock-Risiko eliminiert |

---

### Atomizität

**Order Management Service — `confirmOrder()`:**
```
BEGIN TRANSACTION
  UPDATE orders SET status = CONFIRMED WHERE id = ?
  SELECT stock FROM articles WHERE id = ? FOR UPDATE
  IF stock < quantity THEN ROLLBACK, throw InsufficientStockException
  UPDATE articles SET stock = stock - quantity WHERE id = ?
COMMIT
```

Monolith-Verhalten (Trigger 4): kein Negativbestand-Check, kein BEGIN/COMMIT in `erstelleAuftrag()` (Bug NW-178). Neuer Service behebt beides.

**Invoice/Payment Service — `generateInvoiceNumber()`:**
```sql
-- PostgreSQL SEQUENCE statt TABLE LOCK
CREATE SEQUENCE invoice_number_seq;
SELECT NEXTVAL('invoice_number_seq');
-- Format: RE-YYYY-NNNNN im Application Code
```

---

### Reihenfolge der Trigger-Entfernung

Trigger werden in Extraktionsreihenfolge der zugehörigen Services entfernt:

1. Customer API → keine Trigger (kein Schritt nötig)
2. Order Management Service live → `tr_auftrag_bestaetigt` + `tr_lieferung_erstellt` entfernen
3. Invoice/Payment Service live → `tr_rechnung_bezahlt` + `tr_rechnung_nummer` entfernen
4. Shipment Tracking Service live → `tr_lieferung_zugestellt` entfernen

**Vor jeder Trigger-Entfernung:** `tests/TriggerBehaviorTest.php` muss grün sein und nach Entfernung weiterhin grün bleiben (Verhalten jetzt durch App-Code gedeckt).

---

### Charakterisierungstests als Sicherheitsnetz

`tests/TriggerBehaviorTest.php` pinnt das aktuelle Trigger-Verhalten — Bugs eingeschlossen:

```php
// Pinnt aktuellen Bug: Status nach Zustellung ist 4, nicht 5
public function testTriggerLieferungZugestelltSetsStatus4()
{
    // ... setup
    $this->db->query("UPDATE lieferungen SET status='zugestellt' WHERE id=1");
    $auftrag = $this->db->query("SELECT status FROM auftraege WHERE id=1")->fetch();
    $this->assertEquals(4, $auftrag['status']); // Bug documented, not correct
}
```

Wenn dieser Test nach Shipment-Service-Extraktion fehlschlägt (weil jetzt Status 5 gesetzt wird), ist das **erwünscht** — der Bug ist behoben. Der Test wird dann auf 5 aktualisiert.

---

## Consequences

### Positiv
- Business-Logik ist sichtbar, testbar, und debuggbar
- Race Conditions zwischen Cron und Trigger werden eliminiert
- Bekannte Bugs (NW-445, NW-301, Semantik-Bug in Trigger 3, Deadlock in Trigger 5) werden bei Extraktion behoben
- Atomizität über DB-Transaktionen statt impliziter Trigger-Kaskaden

### Negativ / Risiken
- Mehr App-Code pro Service (explizit = mehr zu schreiben, aber auch mehr zu verstehen)
- Während Dual-Write-Phase: Trigger feuert UND App-Code läuft → doppelte Ausführung verhindern durch Feature-Flag oder Phasen-Trennung
- `tr_lieferung_zugestellt`-Bug-Fix ändert Verhalten — Downstream-Systeme (z.B. Reporting) müssen auf Status 5 angepasst werden

---

## What we chose NOT to do

**Nicht: Trigger beibehalten und zusätzlich App-Code**  
Parallel betriebene Trigger + App-Code würden die Race Condition aus dem Monolith direkt in die neue Architektur übernehmen. Explizit abgelehnt.

**Nicht: Trigger per Migration in Stored Procedures umwandeln**  
Stored Procedures im neuen PostgreSQL würden dasselbe Problem (versteckte Logik, Race Conditions) in einer anderen DB-Engine reproduzieren.

**Nicht: Trigger-Bug (Status 4 statt 5) beibehalten**  
"Wir lassen den Bug für später" wurde abgelehnt. Extraktion ist der natürliche Zeitpunkt für die Korrektur — danach wäre eine Änderung ein separates Breaking-Change-Risiko.

**Nicht: Event-Driven statt synchroner Zustandsübergänge**  
Async-Events (Kafka) für Order-Status-Übergänge würden die Konsistenz-Garantien schwächen und die Debugging-Komplexität erhöhen. Kann in einer späteren Evolutionsstufe evaluiert werden.

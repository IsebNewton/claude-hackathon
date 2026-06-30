# classes/ — God Class und ihre geplanten Nachfolger

## Northwind.php — God Class (30+ Methoden, 5 Domains)

**NICHT refactorn ohne Absprache.** Diese Klasse ist der Ausgangspunkt für Challenge 5 (The Cut).

### Methoden nach Domain

| Domain | Methoden | Risiko |
|---|---|---|
| Bank-Validation | `validiereKundendaten()` (teilweise) | Niedrig — Extraktion zu BankValidationService |
| Artikel | `getArtikel()`, `getArtikelListe()`, `speichereArtikel()` | Niedrig |
| Kunden | `getKunde()`, `getKundenListe()`, `speichereKunde()`, `loescheKunde()`, `validiereKundendaten()` | Mittel |
| Aufträge | `erstelleAuftrag()`, `getAuftrag()`, `aktualisiereAuftragStatus()`, `storniereAuftrag()`, `berechneAuftragSumme()` | Mittel |
| Lieferungen | `erstelleLieferung()`, `aktualisiereLieferstatus()`, `getLieferungenFuerAuftrag()` | Hoch |
| Rechnungen | `erstelleRechnung()`, `verarbeiteZahlung()`, `sendeRechnung()`, `_sendeZahlungsbestaetigung()` | Sehr Hoch |

### Extraktionsreihenfolge (niedrigstes Risiko zuerst)

1. **Bank-Validation → BankValidationService** (eigene Klasse/Microservice)
   - Alle BAV-Aufrufe aus `verarbeiteZahlung()` herauslösen
   - `BankValidator.php` ersetzen durch HTTP-Client gegen neuen Service

2. **Artikel-CRUD → ArticleRepository**
   - Keine FK-Abhängigkeiten nach außen (außer in auftrag_positionen)
   - Einfachste Extraktion

3. **Kunden-CRUD → CustomerRepository**
   - Risiko: `kunden_id` FK in `auftraege` und `rechnungen`
   - Lösung: Referenzen bleiben im Monolith, Service stellt Lesezugriff bereit

4. **Order-Management → OrderService**
   - Trigger 2+4 müssen EXPLIZIT im Service-Code implementiert werden
   - DB-Trigger entfernen erst NACH Test-Verifikation

5. **Invoice/Payment → InvoiceService** (letztes!)
   - Trigger 1 (Rechnung bezahlt → Auftrag abgeschlossen) muss Event werden
   - Sequence-Deadlock bei Rechnungsnummer-Generierung lösen

### Bekannte Inkonsistenzen

- `$this->db` und `$GLOBALS['db']` koexistieren — je nach Entwickler-Vorliebe
- Error-Strategien: `return false` / `die('Fehler')` / `throw new Exception()`
- Private Methoden mit `_`-Prefix (PHP 4-Konvention): `_sendeZahlungsbestaetigung()`
- `erstelleAuftrag()` hat keine Transaktion — Teilfehler hinterlässt inkomplette Daten

---

## BankValidator.php

Wrapper um `malkusch/bav\BAV`. Wurde 2015 eingeführt als SEPA migriert wurde.

**Hauptproblem:** `ConfigurationRegistry::setConfiguration()` im Konstruktor.
Jede Instanziierung überschreibt die globale BAV-Konfiguration.

**Bei Extraktion:** Diese Klasse komplett ersetzen durch HTTP-Client gegen Bank-Validation-Service.
Kein Code aus dieser Klasse in den neuen Service übernehmen — frisch implementieren.

**IBAN-Bug:** `berechneIBAN()` berechnet keine Prüfziffer nach ISO 7064, sondern
eine vereinfachte Version die für einige Eingaben falsche IBANs generiert.
Bug ist in `tests/BankValidatorTest.php` (Test 9) charakterisiert.

---

## NorthwindDB.php

Thin wrapper um `mysql_*`. Wurde angelegt um "irgendwann auf PDO zu migrieren".
Das ist nie passiert.

**`getConnection()`** gibt die rohe mysql-Ressource zurück — das ist ein Leck.
Bei Migration zu PDO: `getConnection()` entfernen, stattdessen PDO-Connection injizieren.

Alle `mysql_*`-Aufrufe laufen über diese Klasse ODER direkt via `$GLOBALS['db']` —
beides kommt vor, je nachdem welcher Entwickler den Code zuletzt angefasst hat.

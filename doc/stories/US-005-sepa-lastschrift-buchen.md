# US-005 — SEPA-Lastschrift buchen

**Als** Sachbearbeiter Finance  
**möchte ich** eine Zahlung per SEPA-Lastschrift gegen eine offene Rechnung buchen  
**damit** die Rechnung als bezahlt gilt und der Auftrag abgeschlossen wird.

---

## Priorität
MUST — primärer Zahlungsweg, direkt umsatzrelevant

## Stakeholder-Konflikt
**Finance vs. IT:**  
- **Finance** erwartet, dass Bankdaten-Validierung 100% zuverlässig ist.  
- **IT** weiß: BAV-Konfiguration wird 3× pro Request überschrieben (Bug in `init.php`, `BankValidator::__construct()`, `Northwind::verarbeiteZahlung()`). Validierungsergebnis kann je nach Request-Reihenfolge variieren.  

**Konsequenz:** AC-3 und AC-4 sind Zielzustand. Gegen den aktuellen Monolith können diese AC fluktuieren. Die Characterization-Tests müssen das Ist-Verhalten dokumentieren — nicht das gewünschte Verhalten.  
Extraktion des Bank-Validation-Service ist Voraussetzung für zuverlässige Erfüllung dieser Story.

---

## Acceptance Criteria

**AC-1: Zahlung nur gegen offene Rechnung möglich**
- GIVEN eine Rechnung mit Status "bezahlt" oder "storniert"
- WHEN Sachbearbeiter versucht Zahlung zu buchen
- THEN Fehlermeldung, keine Buchung

**AC-2: BLZ-Format muss 8 Ziffern sein**
- GIVEN Sachbearbeiter gibt BLZ ein
- WHEN BLZ nicht aus genau 8 Ziffern besteht
- THEN Formularvalidierung schlägt fehl vor dem Absenden

**AC-3: BLZ muss bei Bundesbank registriert sein**
- GIVEN eine BLZ mit korrektem Format (8 Ziffern)
- AND BLZ existiert nicht in der aktuellen Bundesbank-BLZ-Datei
- WHEN Sachbearbeiter speichert die Zahlung
- THEN Fehlermeldung: "BLZ nicht bekannt."
- AND keine Buchung

**AC-4: Kontonummer muss zum BLZ-Prüfverfahren passen**
- GIVEN eine gültige BLZ
- AND eine Kontonummer die das bankseitige Prüfverfahren nicht besteht
- WHEN Sachbearbeiter speichert die Zahlung
- THEN Fehlermeldung: "Ungültige Kontonummer für diese BLZ."
- AND keine Buchung

**AC-5: Betrag muss dem Rechnungsbetrag entsprechen**
- GIVEN eine offene Rechnung über 1.234,56 EUR
- WHEN Sachbearbeiter gibt abweichenden Betrag ein
- THEN Warnung (kein harter Stopp — Teilzahlungen möglich per Ausnahme, bedürfen Freigabe)

**AC-6: Rechnung wird nach Buchung als bezahlt markiert**
- GIVEN eine gültige Zahlung wurde gebucht
- THEN Rechnung erhält Status "bezahlt"
- AND Auftrag erhält Status "Bezahlt" (5)
- AND Zahlungszeitstempel wird gespeichert

---

## Out of Scope
- SEPA-Datei-Export an Bank
- Rücklastschriften
- Teilzahlungsworkflow

# US-008 — Bankdaten am Kunden pflegen

**Als** Sachbearbeiter Finance  
**möchte ich** BLZ und Kontonummer eines Kunden hinterlegen und validieren lassen  
**damit** SEPA-Lastschriften korrekt durchgeführt werden können.

---

## Priorität
MUST — Voraussetzung für US-005 (SEPA-Lastschrift)

## Stakeholder-Konflikt
**IT vs. Finance:**  
Zwei Validierungslogiken parallel aktiv:  
- `helper.php::validateBankleitzahl()` (2009): prüft nur Format (8 Ziffern) — akzeptiert nicht-existierende BLZs  
- `BankValidator::istGueltigeBLZ()` (2015): prüft Format + BAV-Existenz  

In `kunden_edit.php` werden **beide** aufgerufen. Eine BLZ kann die alte Prüfung bestehen und die neue nicht — oder umgekehrt, je nach Aufrufpfad. Finance sieht sporadische Fehler bei Zahlungseinzug für Kunden, deren BLZ bei Anlage "gültig" war.

**Entscheidungsbedarf:** Nach Extraktion des Bank-Validation-Service ist nur noch eine Prüflogik erlaubt. Die alte Funktion muss entfernt werden.

---

## Acceptance Criteria

**AC-1: BLZ-Format prüfen**
- GIVEN Sachbearbeiter gibt BLZ ein
- WHEN BLZ nicht aus genau 8 Ziffern besteht
- THEN Formularfehler: "BLZ muss 8 Ziffern haben"
- AND Datensatz wird nicht gespeichert

**AC-2: BLZ gegen Bundesbank-Datei prüfen**
- GIVEN BLZ hat korrektes Format
- AND BLZ ist nicht in aktueller Bundesbank-BLZ-Datei
- WHEN Sachbearbeiter speichert
- THEN Fehlermeldung: "BLZ nicht bei Bundesbank registriert."

**AC-3: Kontonummer zum BLZ-Prüfverfahren passen**
- GIVEN gültige BLZ
- AND Kontonummer besteht das bankseitige Prüfverfahren nicht
- WHEN Sachbearbeiter speichert
- THEN Fehlermeldung: "Kontonummer ungültig für diese BLZ."

**AC-4: Validierung konsistent — eine Logik, ein Ergebnis**
- GIVEN dieselbe BLZ + Kontonummer
- WHEN Validierung über jeden Codepfad ausgeführt
- THEN Ergebnis (gültig / ungültig) ist identisch
- AND kein widersprüchliches Verhalten zwischen alter und neuer Prüffunktion

> **Hinweis:** AC-4 schlägt gegen den aktuellen Monolith fehl — bekannter Doppel-Validierungs-Bug. Characterization-Test dokumentiert Ist-Verhalten. Grün erst nach Extraktion Bank-Validation-Service.

**AC-5: Bankdaten löschbar**
- GIVEN Bankdaten sind hinterlegt
- WHEN Sachbearbeiter entfernt BLZ + Konto und speichert
- THEN Bankdaten gelöscht, SEPA-Zahlung für diesen Kunden nicht mehr möglich bis erneute Eingabe

---

## Out of Scope
- IBAN-Hinterlegung (SEPA-Umstellung — eigene Story)
- Mehrere Bankverbindungen pro Kunde

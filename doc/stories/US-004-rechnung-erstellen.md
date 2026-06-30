# US-004 — Rechnung aus Auftrag erstellen

**Als** Sachbearbeiter Finance  
**möchte ich** aus einem bestätigten Auftrag automatisch eine Rechnung generieren  
**damit** die Zahlungsaufforderung korrekt und ohne manuelle Dateneingabe an den Kunden geht.

---

## Priorität
MUST — kein Geldeingang ohne Rechnung

---

## Acceptance Criteria

**AC-1: Rechnung nur aus berechtigtem Auftragsstatus**
- GIVEN ein Auftrag mit Status Bestätigt (2) oder höher (außer Storniert)
- WHEN Sachbearbeiter klickt "Rechnung erstellen"
- THEN Rechnung wird angelegt mit allen Positionen aus dem Auftrag

- GIVEN ein Auftrag mit Status Neu (1) oder Storniert (9)
- WHEN Sachbearbeiter klickt "Rechnung erstellen"
- THEN Fehlermeldung, keine Rechnung wird angelegt

**AC-2: Rechnungsnummer automatisch und eindeutig**
- GIVEN eine neue Rechnung wird erstellt
- THEN Rechnungsnummer im Format `RE-YYYY-NNNNN` wird automatisch vergeben
- AND Nummer ist eindeutig (kein Duplikat über alle Rechnungen)

**AC-3: Rechnung enthält vollständige Pflichtangaben**
- THEN Rechnung enthält: Rechnungsnummer, Datum, Kundendaten, Positionen (Bezeichnung, Menge, Einzelpreis, Summe), Gesamtbetrag, Bankverbindung Northwind

**AC-4: Pro Auftrag maximal eine offene Rechnung**
- GIVEN ein Auftrag hat bereits eine Rechnung mit Status "offen"
- WHEN Sachbearbeiter versucht erneut "Rechnung erstellen"
- THEN Fehlermeldung: "Bereits eine offene Rechnung vorhanden."

**AC-5: Rechnung per E-Mail versenden**
- GIVEN eine Rechnung wurde erstellt
- WHEN Sachbearbeiter klickt "Rechnung senden"
- THEN E-Mail mit Rechnung-PDF geht an die hinterlegte E-Mail-Adresse des Kunden
- AND Versandzeitstempel wird gespeichert

---

## Out of Scope
- PDF-Layout / Branding
- Sammelrechnung über mehrere Aufträge
- Automatischer Versand ohne manuelle Freigabe

# US-007 — Kunde anlegen und bearbeiten

**Als** Sachbearbeiter Vertrieb  
**möchte ich** Kundenstammdaten anlegen und bearbeiten  
**damit** Aufträge und Rechnungen dem richtigen Kunden zugeordnet werden können.

---

## Priorität
MUST — Stammdaten für alle anderen Prozesse

---

## Acceptance Criteria

**AC-1: Pflichtfelder erzwingen**
- GIVEN Sachbearbeiter öffnet Kundenformular
- WHEN er speichert ohne: Name, Adresse, E-Mail
- THEN Fehlermeldung pro fehlendem Feld, kein Datensatz angelegt

**AC-2: Kundennummer automatisch vergeben**
- GIVEN ein neuer Kunde wird angelegt
- THEN Kundennummer wird automatisch vergeben
- AND Nummer ist eindeutig

**AC-3: Kunde bearbeiten möglich**
- GIVEN ein bestehender Kunde
- WHEN Sachbearbeiter ändert Adresse oder E-Mail und speichert
- THEN Änderungen sofort sichtbar in Kundendetail
- AND bestehende Aufträge / Rechnungen bleiben unberührt

**AC-4: Kunden suchen und filtern**
- GIVEN Sachbearbeiter öffnet Kundenliste
- WHEN er Suchbegriff eingibt (Name, Kundennummer)
- THEN nur passende Kunden werden angezeigt

**AC-5: Löschen nur wenn keine offenen Abhängigkeiten**
- GIVEN ein Kunde hat offene Aufträge oder offene Rechnungen
- WHEN Sachbearbeiter klickt "Löschen"
- THEN Fehlermeldung: "Kunde hat offene Vorgänge."
- AND Datensatz bleibt erhalten

---

## Out of Scope
- Kundenportal / Self-Service
- CRM-Funktionen (Kontakthistorie, Opportunities)
- Duplicate-Detection bei Neuanlage

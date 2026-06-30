# US-002 — Auftrag stornieren

**Als** Sachbearbeiter Auftragsabwicklung  
**möchte ich** einen bestehenden Auftrag stornieren  
**damit** der Kunde keine Lieferung und keine Rechnung erhält und der Lagerbestand korrekt bleibt.

---

## Priorität
MUST — tritt täglich auf, Fehler erzeugt direkte Folgekosten

## Stakeholder-Konflikt
**Finance vs. Lager:**  
- **Finance** akzeptiert Storno nur bis Status "Rechnung erstellt". Danach: Gutschrift, kein Storno.  
- **Lager** erwartet Bestandsrückbuchung bei jedem Storno unabhängig vom Status.  

**Aktueller Bug:** Trigger `tr_auftrag_bestaetigt` dekrementiert Bestand bei Bestätigung, aber kein Gegentrigger bei Storno. Bestand wird nie zurückgebucht. Lager kennt den Bug, arbeitet mit manueller Korrektur am Monatsende.  

**Entscheidungsbedarf:** Wird die Rückbuchung im neuen System automatisch? Finance und Lager müssen gemeinsam entscheiden, ob der Fix bei Extraktion oder separat kommt.

---

## Acceptance Criteria

**AC-1: Storno möglich bis Status "Versandt"**
- GIVEN ein Auftrag mit Status Neu (1), Bestätigt (2) oder Rechnung erstellt (5 noch nicht)
- WHEN Sachbearbeiter klickt "Stornieren"
- THEN Auftrag erhält Status 9 (Storniert)
- AND eine Gutschrift wird automatisch erzeugt falls bereits eine Rechnung existiert

**AC-2: Storno nach Versand nicht erlaubt**
- GIVEN ein Auftrag mit Status Versandt (3) oder Zugestellt (4)
- WHEN Sachbearbeiter klickt "Stornieren"
- THEN Fehlermeldung: "Storno nach Versand nicht möglich. Bitte Retoure anlegen."
- AND Status ändert sich nicht

**AC-3: Lagerbestand wird bei Storno zurückgebucht** *(Zielzustand — Bug-Fix erforderlich)*
- GIVEN ein Auftrag im Status Bestätigt (2) mit Position: Artikel A, Menge 3
- AND Lagerbestand Artikel A wurde bei Bestätigung von 10 auf 7 dekrementiert
- WHEN der Auftrag storniert wird
- THEN Lagerbestand Artikel A = 10

> **Hinweis:** AC-3 schlägt gegen den aktuellen Monolith fehl — bekannter Bug. Charakterisierungstest muss das ist-Verhalten pinnen, erst nach Fix grün.

**AC-4: Stornohistorie nachvollziehbar**
- GIVEN ein Auftrag wurde storniert
- WHEN Sachbearbeiter öffnet Auftragsdetail
- THEN Storno-Zeitstempel und ausführender Nutzer sind sichtbar

---

## Out of Scope
- Retouren-Prozess
- Teilstorno einzelner Positionen

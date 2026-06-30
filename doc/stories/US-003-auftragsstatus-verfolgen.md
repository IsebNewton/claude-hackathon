# US-003 — Auftragsstatus verfolgen

**Als** Sachbearbeiter Auftragsabwicklung  
**möchte ich** den aktuellen Status eines Auftrags und seine Statushistorie sehen  
**damit** ich Kundenanfragen sofort beantworten kann ohne Rückfragen ans Lager oder Finance.

---

## Priorität
MUST — Basis für alle anderen Prozesse; ohne Statusübersicht keine Prozesskontrolle

---

## Acceptance Criteria

**AC-1: Auftragsdetail zeigt aktuellen Status**
- GIVEN ein Auftrag existiert
- WHEN Sachbearbeiter öffnet Auftragsdetail
- THEN Status wird als Klartext angezeigt (nicht als Zahl)
- AND folgende Stati sind unterscheidbar: Neu / Bestätigt / Versandt / Zugestellt / Bezahlt / Storniert

**AC-2: Statusübergänge sind regelbasiert**

| Von | Nach | Erlaubt |
|---|---|---|
| Neu (1) | Bestätigt (2) | Ja, manuell |
| Bestätigt (2) | Versandt (3) | Ja, bei Lieferung-Anlage |
| Versandt (3) | Zugestellt (4) | Ja, via Tracking-Update |
| Zugestellt (4) | Bezahlt (5) | Ja, bei Zahlungseingang |
| Jeder außer Storniert | Storniert (9) | Nur bis Versandt (3) |
| Bezahlt (5) | Jeder | Nein |

- WHEN ein nicht erlaubter Übergang versucht wird
- THEN Fehlermeldung, Status ändert sich nicht

**AC-3: Auftragliste filterbar nach Status**
- GIVEN Sachbearbeiter öffnet Auftragsliste
- WHEN er nach Status filtert (z.B. "Nur Versandt")
- THEN nur Aufträge des gewählten Status werden angezeigt

**AC-4: Zugehörige Lieferung und Rechnung verlinkt**
- GIVEN ein Auftrag hat eine Lieferung und/oder Rechnung
- WHEN Sachbearbeiter öffnet Auftragsdetail
- THEN Lieferung und Rechnung sind direkt verlinkt (kein separates Suchen)

---

## Bekannter Defekt (zur Dokumentation)
Trigger `tr_lieferung_zugestellt` setzt `auftraege.status=4` statt 5 bei Zustellung. Aufträge kommen nie automatisch in Status "Bezahlt" nach Zustellung. Manuelles Eingreifen nötig. Characterization-Test muss dieses Fehlverhalten pinnen.

---

## Out of Scope
- Kundenportal / Self-Service-Status
- E-Mail-Benachrichtigung bei Statuswechsel

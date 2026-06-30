# US-006 — Mahnlauf ausführen

**Als** Finance-Leitung  
**möchte ich** dass überfällige Rechnungen automatisch gemahnt werden  
**damit** ich nicht jede Rechnung manuell nachverfolgen muss.

---

## Priorität
MUST — rechtlich und liquiditätsseitig relevant

## Stakeholder-Konflikt
**Finance vs. Vertrieb:**  
- **Finance** will Mahnungen ab Tag 1 nach Fälligkeit.  
- **Vertrieb** will Kulanzzeit von 7 Tagen — zu frühe Mahnungen beschädigen Kundenbeziehung.  
- **Aktuell:** Cron `mahnlauf.php` hat hardcoded 14 Tage Frist und fixe Mahngebühren. Keine Konfiguration möglich. Vertrieb hat den Konflikt bisher ignoriert.

---

## Acceptance Criteria

**AC-1: Mahnlauf läuft nächtlich automatisch**
- GIVEN der Cron `mahnlauf.php` ist eingerichtet
- WHEN Mitternacht (00:00 Uhr)
- THEN Mahnlauf startet automatisch ohne manuelle Interaktion

**AC-2: Nur fällige, unbezahlte Rechnungen werden gemahnt**
- GIVEN eine Rechnung mit Status "offen" und Fälligkeit vor heute
- THEN Rechnung wird in Mahnlauf einbezogen

- GIVEN eine Rechnung mit Status "bezahlt" oder "storniert"
- THEN Rechnung wird nicht gemahnt

**AC-3: Mahnung geht per E-Mail an Kunden**
- GIVEN eine Rechnung ist mahnfällig
- WHEN Mahnlauf läuft
- THEN E-Mail mit Mahnungsinhalt geht an hinterlegte Kunden-E-Mail-Adresse
- AND Mahnungs-Zeitstempel wird gespeichert

**AC-4: Bereits gemahnte Rechnungen nicht doppelt mahnen**
- GIVEN eine Rechnung wurde bereits gemahnt heute
- WHEN Mahnlauf läuft erneut (z.B. nach manuellem Neustart)
- THEN keine zweite Mahnung heute

**AC-5: Fehlgeschlagene E-Mails werden geloggt**
- GIVEN `mail()` schlägt fehl für einen Kunden
- THEN Fehler wird ins Log geschrieben
- AND Mahnlauf bricht nicht ab, restliche Kunden werden weiter gemahnt

> **Hinweis:** Aktueller Code bricht bei mail()-Fehler nicht explizit ab, loggt aber auch nicht. Fehlgeschlagene Mahnungen sind unsichtbar. Characterization-Test muss dieses Verhalten dokumentieren.

---

## Out of Scope
- Mahnstufen (1. / 2. / 3. Mahnung)
- Konfigurierbare Fristen (erst nach Extraktion sinnvoll)
- Inkasso-Übergabe

# US-001 — Auftrag anlegen

**Als** Sachbearbeiter Auftragsabwicklung  
**möchte ich** einen neuen Kundenauftrag mit mehreren Positionen anlegen  
**damit** der Versand und die Rechnungsstellung automatisch angestoßen werden können.

---

## Priorität
MUST — täglich mehrfach, kein manueller Workaround möglich

## Stakeholder-Konflikt
**Lager vs. Vertrieb:** Vertrieb möchte Aufträge auch bei Nullbestand anlegen können ("Auftrag reservieren"). Lager lehnt ab — Überverkauf erzeugt Kulanzlieferungen, die nicht gebucht werden. Aktuell: System erlaubt Anlage, Trigger dekrementiert Bestand bei Bestätigung. Konflikt ungelöst, muss in AC abgebildet werden.

---

## Acceptance Criteria

**AC-1: Pflichtfelder validieren**
- GIVEN ein Sachbearbeiter öffnet das Formular "Neuer Auftrag"
- WHEN er speichert ohne Kunden-ID oder ohne mindestens eine Position
- THEN Fehlermeldung, kein Datensatz angelegt

**AC-2: Lagerbestand prüfen bei Bestätigung**
- GIVEN ein Auftrag mit Position: Artikel A, Menge 5
- AND Lagerbestand Artikel A = 3
- WHEN der Auftrag auf Status "Bestätigt" (2) gesetzt wird
- THEN System blockiert die Bestätigung mit Hinweis auf unzureichenden Bestand

**AC-3: Lagerbestand bei Bestätigung dekrementieren**
- GIVEN ein Auftrag mit Position: Artikel A, Menge 2
- AND Lagerbestand Artikel A = 10
- WHEN der Auftrag auf Status "Bestätigt" (2) gesetzt wird
- THEN Lagerbestand Artikel A = 8

**AC-4: Auftragsnummer automatisch vergeben**
- GIVEN ein neuer Auftrag wird gespeichert
- THEN Auftragsnummer wird automatisch im Format `AUF-YYYY-NNNNN` vergeben
- AND Nummer ist eindeutig

**AC-5: Auftrag erscheint in Auftragsliste**
- GIVEN ein Auftrag wurde erfolgreich angelegt
- WHEN der Sachbearbeiter die Auftragsliste öffnet
- THEN der neue Auftrag erscheint mit Status "Neu" (1)

---

## Out of Scope
- Änderung von Positionen nach Bestätigung (eigene Story)
- Preisberechnung / Rabatte

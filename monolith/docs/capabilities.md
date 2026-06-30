# Northwind Logistics — System Capabilities

> Perspektive: PM-Sicht. Priorisiert nach Geschäftskritikalität.
> Stand: 2026-06-30

---

## Priorisierung (MoSCoW)

### MUST — Kerngeschäft, täglich im Einsatz

#### 1. Auftragsabwicklung
Herzstück des Systems. Vollständiger Lifecycle eines Kundenauftrags.

- Auftrag anlegen (Positionen, Lagerreservierung)
- Auftragsstatus verfolgen (1=Neu → 2=Bestätigt → 3=Versandt → 4=Zugestellt → 5=Bezahlt, 9=Storniert)
- Auftrag stornieren (inkl. Gutschrift-Erstellung)
- Auftragssumme berechnen

**Bekannter Defekt:** Lagerbestand wird bei Storno nicht zurückgebucht (Trigger-Bug). Hohe Fehlerfolgekosten bei häufigen Stornos.

---

#### 2. Rechnungs- & Zahlungsabwicklung
SEPA-Lastschrift ist der primäre Zahlungsweg. Ausfälle hier = kein Geldeingang.

- Rechnung aus Auftrag erzeugen (Rechnungsnummer automatisch: `RE-YYYY-NNNNN`)
- SEPA-Lastschrift buchen (BLZ + Kontonummer validieren via BAV)
- Rechnungsstatus pflegen (offen / bezahlt / storniert)
- Rechnung per E-Mail versenden
- Mahnlauf ausführen (Cron, nachts)

**Kritisches Risiko:** BAV-Konfiguration wird 3× pro Request überschrieben. Bei Race Conditions kann eine falsche Konfiguration aktiv sein → Validierungen liefern unzuverlässige Ergebnisse.

---

#### 3. Kundenverwaltung
Stammdaten für alle anderen Prozesse.

- Kunde anlegen / bearbeiten / löschen
- Kundenliste mit Filter und Pagination
- Kundensuche (Volltext)
- Bankdaten pflegen (BLZ, Kontonummer)
- Kundennummer automatisch generieren

---

### SHOULD — Operativ wichtig, aber nicht stündlich

#### 4. Lieferverwaltung & Tracking
Versand- und Zustellstatus für Kunden und Lager.

- Lieferung zu Auftrag anlegen (Carrier-Code setzen)
- Lieferstatus aktualisieren (Cron: `update_lieferstatus.php`)
- Lieferdetails anzeigen

**Bekannter Defekt:** Trigger `tr_lieferung_zugestellt` setzt `auftraege.status=4` statt 5. Aufträge gelten nie automatisch als abgeschlossen. Cron-Update und Trigger können sich gegenseitig überschreiben (Race Condition).

---

#### 5. Artikelverwaltung / Lagerhaltung
Produktkatalog und Bestandsführung.

- Artikel anlegen / bearbeiten
- Artikelliste mit Filter
- Lagerbestand anpassen (manuell + automatisch via Trigger bei Auftragsbestätigung)

---

### COULD — Nützlich, aber kein Blocker

#### 6. Bankkonten-Validierung
Technische Capability, keine eigene UI. Untermauert Zahlungsabwicklung und Kundenstamm.

- BLZ auf Format + BAV-Existenz prüfen
- IBAN / Kontonummer validieren
- BIC validieren

**Hinweis:** Aktuell doppelt implementiert — alte Format-only-Funktion (`helper.php:validateBankleitzahl`) und neue BAV-basierte Klasse (`BankValidator`). Erste Extraktion empfohlen.

---

### WON'T (in aktuellem Zustand nicht zuverlässig lieferbar)

#### 7. Einheitliches Fehler- & Audit-Logging
Northwind.php verwendet drei verschiedene Fehlerstrategien (`false`, `die()`, `Exception`). Kein zentrales Logging, `error_reporting(0)` in config.php. Fehler verschwinden stillschweigend.

---

## Capability-Map (vereinfacht)

```
Northwind Logistics
│
├── Kundenverwaltung         [MUST] ──────────────────────────────┐
│                                                                  │
├── Artikelverwaltung        [SHOULD]                             │
│   └── Lagerhaltung                                              │
│                                                                  ↓
├── Auftragsabwicklung       [MUST] ← abhängig von Kunden + Artikeln
│   └── Storno + Gutschrift                                       │
│                                                                  ↓
├── Lieferverwaltung         [SHOULD] ← Auftrag muss existieren  │
│   └── Carrier-Tracking                                          │
│                                                                  ↓
├── Rechnungsstellung        [MUST]  ← Auftrag muss existieren   │
│   └── Mahnlauf (Cron)                                           │
│                                                                  ↓
└── Zahlungseinzug (SEPA)    [MUST]  ← Bankdaten-Validierung ───┘
    └── BAV-Integration
```

---

## Technische Schulden mit direktem Capability-Impact

| Schuld | Betroffene Capability | Risiko |
|---|---|---|
| Storno-Bug (Trigger fehlt) | Auftragsabwicklung | Lagerbestand dauerhaft falsch |
| BAV 3-fach Config | Zahlungseinzug | Validierung unzuverlässig |
| `tr_lieferung_zugestellt` falscher Status | Lieferverwaltung | Aufträge nie auto-abgeschlossen |
| Doppelte BLZ-Validierung | Bankkonten-Validierung | Inkonsistente Prüfergebnisse |
| `die()` in Northwind-Methoden | Alle | Unerwartete Request-Abbrüche |
| Credentials in config.php | Alle | Security-Risiko |
| `error_reporting(0)` | Alle | Fehler unsichtbar in Prod |

---

## Empfohlene Extraktionsreihenfolge (Strangler Fig)

1. **Bank-Validation-Service** — niedrigstes Risiko, klare Grenzen, behebt BAV-Bug
2. **Customer-API** — Stammdaten, FK-Risiko beherrschbar
3. **Order-Management-Service** — Trigger 2+4 explizit machen
4. **Shipment-Tracking** — Adress-Denormalisierung + Race Condition lösen
5. **Invoice/Payment** — zuletzt, berührt alles

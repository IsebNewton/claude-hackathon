# Northwind Logistics — Legacy Monolith

## Kontext (Hackathon: Code Modernization)

Dieses Verzeichnis enthält den PHP 5-era Legacy-Monolith von Northwind Logistics GmbH.
Er dient als Ausgangspunkt für alle 9 Modernisierungs-Challenges.

Primäres Business: Auftragsabwicklung, Lagerlogistik, SEPA-Zahlungseinzug.
Kern-Dependency: `malkusch/bav` für deutsche Bankkonten-Validierung (BLZ + Kontonummer).

---

## Was Claude hier NICHT tun soll

- **Keine** direkten Edits an `sql/schema.sql`-Triggern ohne vorherige Characterization-Tests
- **Keine** mysql_* → PDO Migration ohne explizite Anfrage (das ist Challenge 5)
- **Nicht** `classes/Northwind.php` refactorn ohne Absprache — die God-Class ist der Ausgangspunkt
- **Keine** neuen direkten BAV-Aufrufe hinzufügen — lieber den Bank-Validation-Service extrahieren

---

## Architektur-Übersicht

### Entry Point
`index.php` — flacher if/elseif-Router auf `$_GET['page']`. Kein URL-Rewriting.

### Request-Lifecycle
```
index.php
  → init.php (session_start, mysql_connect → global $db, BAV-Config)
  → pages/{seite}.php (Business-Logic + HTML gemischt)
    → classes/Northwind.php (God-Class, optional)
    → classes/BankValidator.php (BAV-Wrapper, optional)
    → includes/header.php + includes/footer.php
```

### Global State — drei problematische Singletons
| Variable | Wo gesetzt | Wo gelesen |
|---|---|---|
| `$db` | `init.php` (mysql_connect) | Alle pages/, alle classes/ |
| `$_SESSION` | `init.php` (session_start) | pages/, helper.php |
| `ConfigurationRegistry` | `init.php`, `BankValidator::__construct()`, `Northwind::verarbeiteZahlung()` | überall wo BAV aufgerufen wird |

---

## Bekannte Probleme (intentional für Hackathon)

### 1. BAV Global State (3-fache Config-Überschreibung)
`ConfigurationRegistry::setConfiguration()` wird aufgerufen in:
1. `init.php` — beim Request-Start
2. `BankValidator::__construct()` — bei jeder BankValidator-Instanz
3. `Northwind::verarbeiteZahlung()` — "sicherheitshalber", weil Entwickler unsicher war

Konsequenz: Die aktive BAV-Konfiguration zur Laufzeit hängt davon ab, was zuletzt aufgerufen wurde.

### 2. DB-Trigger: Versteckte Business-Logic
5 Trigger in `sql/schema.sql` steuern wichtige Zustandsübergänge:

| Trigger | Auslöser | Wirkung |
|---|---|---|
| `tr_rechnung_bezahlt` | `rechnungen.status='bezahlt'` | `auftraege.status=5` |
| `tr_lieferung_erstellt` | INSERT in `lieferungen` | `auftraege.status=3` wenn vorher 2 |
| `tr_lieferung_zugestellt` | `lieferungen.status='zugestellt'` | `auftraege.status=4` (Bug: sollte 5) |
| `tr_auftrag_bestaetigt` | `auftraege.status` 1→2 | Lagerbestand dekrementiert |
| `tr_rechnung_nummer` | INSERT in `rechnungen` | Rechnungsnummer RE-YYYY-NNNNN generieren |

**Storno-Bug**: `tr_auftrag_bestaetigt` dekrementiert Lagerbestand, aber es gibt keinen Trigger
der ihn bei Stornierung (Status 9) zurücksetzt. Lagerbestand läuft bei Storno dauerhaft falsch.

### 3. Doppelte BLZ-Validierung
- `validateBankleitzahl()` in `helper.php`: aus 2009, prüft nur Format (8 Ziffern), kein BAV
- `BankValidator::istGueltigeBLZ()`: aus 2015, prüft Format + BAV-Existenz
- Manche Seiten rufen beide auf (z.B. `kunden_edit.php`)
- Format-gültige aber nicht-existierende BLZs passieren die alte Funktion

### 4. Inkonsistente Fehlerbehandlung in Northwind.php
- Einige Methoden geben `false` zurück bei Fehler
- Einige rufen `die()` auf
- Einige werfen `Exception`
- Kein einheitliches Muster — Aufrufer müssen alle drei Fälle abfangen

---

## Modernisierungs-Seams (nach Extraktionsrisiko)

### 1. Bank-Validation-Service [NIEDRIG — hier anfangen]
**Was extrahieren:** Alles was BAV aufruft
- `classes/BankValidator.php` komplett
- BAV-Aufrufe in `Northwind::verarbeiteZahlung()`
- `validateBankleitzahl()` in `helper.php` (obsolete nach Extraktion)

**Ziel:** Eigenständiger REST-Microservice mit zwei Endpoints:
- `POST /validate/bank-account` → `{blz, kto}` → `{valid: bool}`
- `POST /validate/bic` → `{bic}` → `{valid: bool}`

**Grep-Targets:** `isValidBankAccount`, `isValidBIC`, `isValidBank`, `BankValidator`, `validateBankleitzahl`

**Prefer new service:** Wenn du neue Bank-Validierungslogik hinzufügst, nutze den extrahierten Service
(sobald vorhanden), nicht direkte BAV-Aufrufe.

### 2. Customer-API [MITTEL]
`kunden`-Tabelle, `pages/kunden*.php`, Northwind-Kunden-Methoden.
Risiko: `kunden_id` FK in `auftraege` und `rechnungen`.

### 3. Order-Management-Service [MITTEL]
`auftraege` + `auftrag_positionen`, Trigger 2+4 müssen explizit gemacht werden.

### 4. Shipment-Tracking [HOCH]
3-fach denormalisierte Adresse, stale `tracking_url`, Trigger 3 + Cron-Doppel-Update.

### 5. Invoice/Payment [SEHR HOCH — zuletzt]
Berührt alle anderen Domains. Trigger 1 + Sequence-Deadlock. Letzter Schritt.

---

## Dateien-Übersicht

| Datei | Zweck | Schlimmste Anti-Patterns |
|---|---|---|
| `index.php` | Router | if/elseif auf $_GET, kein Auth-Middleware |
| `config.php` | Konfiguration | Credentials im Klartext, error_reporting(0) |
| `init.php` | Bootstrap | global $db, BAV-Config-Singleton |
| `helper.php` | Utility-Grab-Bag | Business-Logic + Formatierung gemischt |
| `classes/Northwind.php` | God-Class | 30+ Methoden, 3 Error-Strategien, mysql_* |
| `classes/BankValidator.php` | BAV-Wrapper | ConfigurationRegistry im Constructor |
| `sql/schema.sql` | DB-Schema | 5 Trigger mit versteckter Business-Logic |
| `cron/mahnlauf.php` | Mahnlauf | mail() in Loop, hardcoded Beträge |
| `cron/update_lieferstatus.php` | Tracking | Doppel-Update mit Trigger |

---

## ADR-Platzhalter (Challenge 3: The Map)

- `docs/ADR-001-strangler-fig-bank-validation.md` — zu erstellen
- `docs/ADR-002-anti-corruption-layer.md` — zu erstellen
- `docs/ADR-003-trigger-explizit-machen.md` — zu erstellen

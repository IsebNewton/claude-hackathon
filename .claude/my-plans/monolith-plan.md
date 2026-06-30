# Plan: Northwind Logistics — PHP Legacy Monolith generieren

## Context

Hackathon "Code Modernization" (Scenario 1). Ziel: ein realistisches PHP 5-Era Monolith-Repo für "Northwind Logistics" generieren, das als Ausgangspunkt für alle 9 Hackathon-Challenges dient. BAV (bank account validator, bereits vorhanden) wird als eingebettete Abhängigkeit für SEPA-Validierung integriert — mit absichtlichen Anti-Patterns damit das Modernisieren interessant ist.

## Ziel-Verzeichnis

`/Users/jan.hentschel/Projects/adesso/claude/bav/monolith/` (Subfolder im BAV-Repo)

## Dateistruktur

```
bav/
└── monolith/
    ├── index.php                   # if/elseif-Router auf $_GET['page']
    ├── config.php                  # DB-Credentials im Klartext, error_reporting(0)
    ├── init.php                    # session_start, mysql_connect global, BAV-Singleton-Init
    ├── helper.php                  # 400+ Zeilen Mix: Formatierung + Validierung + Business-Logic
    ├── pages/
    │   ├── login.php
    │   ├── kunden.php              # Kundenliste mit Inline-SQL + HTML
    │   ├── kunden_edit.php         # Formular mit Inline-Validierung
    │   ├── artikel.php
    │   ├── artikel_edit.php
    │   ├── auftraege.php
    │   ├── auftrag_neu.php         # Multi-Step via $_SESSION
    │   ├── auftrag_detail.php
    │   ├── lieferungen.php
    │   ├── lieferung_detail.php
    │   ├── rechnungen.php
    │   ├── rechnung_detail.php
    │   └── rechnung_zahlung.php    # Heaviest BAV-Usage, doppelter BAV-Aufruf pro Request
    ├── classes/
    │   ├── Northwind.php           # God Class, 30+ Methoden, 3 verschiedene Error-Strategien
    │   ├── NorthwindDB.php         # Thin Wrapper um mysql_*, leakt Connection
    │   └── BankValidator.php       # BAV-Wrapper, ruft ConfigurationRegistry::setConfiguration() im Constructor
    ├── includes/
    │   ├── header.php
    │   ├── footer.php
    │   └── pagination.php          # Inline SQL + HTML
    ├── cron/
    │   ├── update_lieferstatus.php # Hardcoded Paths, mysql_* direkt
    │   ├── mahnlauf.php            # Mahnlauf: SQL-Injection-Pattern, mail() in Loop
    │   └── bav_update.php          # BAV Bundesbank-Datei-Update
    ├── sql/
    │   └── schema.sql              # Vollschema + 5 Trigger mit versteckter Business-Logic
    ├── tests/
    │   ├── KundenTest.php          # Characterization Tests
    │   ├── BankValidatorTest.php   # Pinnt BAV Global-State-Mutation
    │   ├── RechnungTest.php        # Pinnt Trigger-1-Verhalten
    │   └── AuftragTest.php         # Pinnt Trigger-Bugs (Storno restauriert Lagerbestand nicht)
    ├── composer.json               # Nur malkusch/bav; kein Autoloading für App-Code
    └── CLAUDE.md                   # Verzeichnis-CLAUDE.md mit Kontext für Hackathon-Challenges
```

## Schlüssel-Anti-Patterns (pro Challenge)

| Anti-Pattern | Datei(en) | Challenge |
|---|---|---|
| `mysql_connect()` + global `$db` | `init.php`, alle `pages/` | Alle |
| `ConfigurationRegistry::setConfiguration()` 3x pro Request | `init.php`, `BankValidator.php`, `Northwind::verarbeiteZahlung()` | Challenge 4 (The Pin), 5 (The Cut) |
| Business-Logic in DB-Trigger | `sql/schema.sql` Trigger 1-5 | Challenge 6 (The Fence), 3 (The Map) |
| God Class `Northwind` mit 30+ Methoden | `classes/Northwind.php` | Challenge 2 (The Patient), 5 (The Cut) |
| `validateBankleitzahl()` in helper.php (2009, kein BAV) | `helper.php` | Challenge 4 (The Pin) |
| Kein Transaction bei `erstelleAuftrag()` | `Northwind::erstelleAuftrag()` | Challenge 5 (The Cut) |
| MD5-Passwörter | `benutzer`-Tabelle | Bonus |
| `error_reporting(0)` | `config.php` | Challenge 2 (The Patient) |

## SQL Schema — Trigger-Übersicht

Alle 5 Trigger sind absichtlich "interessant":

1. **`tr_rechnung_bezahlt`** — setzt `auftraege.status = 5` wenn Rechnung bezahlt → unsichtbare Kopplung
2. **`tr_lieferung_erstellt`** — setzt `auftraege.status = 3` bei INSERT in `lieferungen`
3. **`tr_lieferung_zugestellt`** — setzt `auftraege.status = 4` (Bug: sollte 5 sein, aber Trigger 1 macht das über Rechnung)
4. **`tr_auftrag_bestaetigt`** — dekrementiert Lagerbestand; Storno restauriert NICHT (Bug, gepinnt in Tests)
5. **`tr_rechnung_nummer`** — generiert `RE-YYYY-NNNNN` via separater Sequence-Tabelle (Deadlock-anfällig)

## Modernisierungs-Seams (Extraktionskandidaten, nach Risiko)

1. **Bank-Validation-Service** (niedrig) — kapselt BAV, saubere API, greifbares erstes Ziel
2. **Customer-API** (mittel) — CRUD, SEPA-Daten, `kunden_id` FK in 2 Tabellen
3. **Order-Management-Service** (mittel) — Trigger 2+4 müssen explizit gemacht werden
4. **Shipment-Tracking** (hoch) — 3-fach denormalisierte Adresse, stale `tracking_url`
5. **Invoice/Payment** (sehr hoch) — berührt alle anderen Domains, Trigger 1 + Sequence-Deadlock

## BAV-Integrationspunkte (Misuse)

- `init.php`: `ConfigurationRegistry::setConfiguration()` mit `FileDataBackendContainer`
- `BankValidator::__construct()`: überschreibt obige Config bei jeder Instanz
- `Northwind::verarbeiteZahlung()`: erstellt `new BAV()` ohne Config-Injection (liest was zuletzt gesetzt wurde)
- `helper.php::validateBankleitzahl()`: 2009-Regex, kein BAV-Aufruf — noch in manchen Codepfaden aktiv
- `rechnung_zahlung.php`: ruft `BankValidator` UND `Northwind::verarbeiteZahlung()` — BAV wird 2x pro Request aufgerufen, Config 3x überschrieben

## Characterization Tests (The Pin — Challenge 4)

- `BankValidatorTest`: 10 Tests, pinnt BAV-Aufrufverhalten inkl. Config-Mutation und IBAN-Bug für nicht-deutsche Konten
- `RechnungTest`: 6 Tests, pinnt Trigger-1-Verhalten (Rechnung bezahlt → Auftrag abgeschlossen)
- `AuftragTest`: 6 Tests, pinnt Trigger-Bugs (Storno = kein Lagerbestand-Restore, Trigger-3-Status-Bug)
- `KundenTest`: 4 Tests, pinnt inkonsistentes Soft/Hard-Delete-Verhalten

## CLAUDE.md-Struktur (drei Ebenen)

- **User-Level** (`~/.claude/CLAUDE.md`): persönliche Präferenzen (bereits vorhanden)
- **Projekt-Level** (`bav/CLAUDE.md`): Hackathon-Kontext, Repo-Übersicht (BAV-Library + Monolith), ADR-Verweis
- **Verzeichnis-Level** (`bav/monolith/CLAUDE.md`): Monolith-Kontext, Seam-Übersicht, "prefer new service for bank validation", Warnung vor God-Class
- **Unterverzeichnis-Level** (`bav/monolith/classes/CLAUDE.md`): Extraktionsreihenfolge, God-Class-Warnung

## Generierungsreihenfolge

1. `sql/schema.sql` (Fundament, alle anderen Dateien referenzieren Tabellen)
2. `config.php`, `init.php`, `helper.php`
3. `classes/NorthwindDB.php`, `classes/BankValidator.php`, `classes/Northwind.php`
4. `index.php`
5. `pages/` (alle 12 Seiten)
6. `includes/`
7. `cron/` (3 Skripte)
8. `tests/` (4 Test-Dateien)
9. `composer.json`
10. `CLAUDE.md` (alle 3 Ebenen)

## Verifikation

Kein PHP 5 lokal installiert — Lauffähigkeit ist kein Ziel. Prüfung rein inhaltlich:
- Alle Dateien vorhanden und vollständig befüllt
- Anti-Patterns und BAV-Integrationspunkte wie beschrieben implementiert
- `schema.sql` syntaktisch korrekt (MySQL-Dialekt)
- Characterization-Tests inhaltlich korrekt (testen das beschriebene Verhalten, auch wenn sie nicht ausführbar sind)

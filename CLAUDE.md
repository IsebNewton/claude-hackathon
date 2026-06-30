# BAV Repository — Hackathon: Code Modernization

## Repository-Struktur

```
bav/              ← BAV Library (malkusch/bav): PHP-Bibliothek zur Validierung
                    deutscher Bankkonten gegen Bundesbank-Daten
                    Bestehender Code — nicht Teil des Hackathon-Monoliths

monolith/         ← Northwind Logistics Legacy App: PHP 5-era Monolith
                    Hackathon-Ausgangspunkt für alle 9 Modernisierungs-Challenges
                    Siehe monolith/CLAUDE.md für vollständigen Kontext
```

---

## Hackathon: Scenario 1 — Code Modernization

Northwind Logistics läuft auf einem alten PHP-Stack. Die BAV-Library ist als
Composer-Dependency in den Monolith eingebunden — SEPA-Lastschrift-Validierung
für Kundenzahlungen.

**Ziel:** Monolith sicher modernisieren ohne Big-Bang-Rewrite.
**Strategie:** Strangler Fig — schrittweise Extraktion von Services.

---

## BAV-Library (bav/)

### Was die Library macht
- Validiert BLZ + Kontonummer gegen die offizielle Bundesbank-BLZ-Datei
- 157 Validator-Algorithmen (Verfahren 00–99, A–E) für verschiedene Banken
- Datenbasis: Bundesbank-Datei (vierteljährlich aktualisiert: März/Juni/September/Dezember)

### Öffentliche API
```php
$bav = new \malkusch\bav\BAV();
$bav->isValidBankAccount($blz, $kto);  // bool
$bav->isValidBank($blz);               // bool, setzt Context
$bav->isValidAccount($kto);            // bool, braucht vorherigen isValidBank()-Aufruf
$bav->isValidBIC($bic);               // bool
$bav->getMainAgency($blz);            // Agency-Objekt (Name, BIC, etc.)
```

### Kritischer Global State
`ConfigurationRegistry` ist ein **statisches Singleton**. Ein Aufruf von
`ConfigurationRegistry::setConfiguration()` ändert das Verhalten für alle
nachfolgenden BAV-Instanzen im selben PHP-Prozess.

Im Monolith wird dieser Singleton an **3 Stellen** überschrieben — das ist ein
dokumentierter Bug. Siehe `monolith/CLAUDE.md` für Details.

### Backends
| Backend | Klasse | Wann nutzen |
|---|---|---|
| File (Standard) | `FileDataBackendContainer` | Einfach, kein DB nötig |
| PDO | `PDODataBackendContainer($pdo)` | Wenn DB-Integration gewünscht |
| Doctrine ORM | `DoctrineBackendContainer($em)` | Wenn Doctrine schon vorhanden |

---

## Monolith (monolith/)

Siehe `monolith/CLAUDE.md` für vollständigen Kontext, Anti-Patterns, Seams.

**Kurzfassung:**
- PHP 5-era, `mysql_*`-Funktionen, global `$db`
- God-Class `Northwind.php` mit 30+ Methoden
- 5 DB-Trigger mit versteckter Business-Logic
- BAV 3-fach initialisiert pro Request

---

## Neue Services (nach Extraktion)

Wenn du einen extrahierten Service anlegst:

```
bav/
├── monolith/                     ← Legacy (unverändert)
├── bank-validation-service/      ← Beispiel: erster extrahierter Service
│   ├── CLAUDE.md                 ← Service-eigener Kontext
│   ├── src/
│   └── tests/
└── ...
```

**Anti-Corruption-Layer-Regel:**
- Neue Services dürfen `malkusch/bav` direkt referenzieren ✓
- Neue Services dürfen **NICHT** Klassen aus `monolith/classes/` importieren ✗
- Der Monolith-Datentypen (z.B. Feldnamen aus `kunden`-Tabelle) dürfen nicht
  in der öffentlichen API neuer Services erscheinen ✗

---

## ADR-Verzeichnis

`monolith/docs/` (noch anzulegen für Challenge 3: The Map):
- `ADR-001-strangler-fig-bank-validation.md`
- `ADR-002-anti-corruption-layer.md`
- `ADR-003-trigger-explizit-machen.md`

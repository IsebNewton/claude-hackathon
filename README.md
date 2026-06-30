# Northwind Logistics — Modernization Hackathon

**Hackathon Scenario:** Code Modernization | **Track:** Strangler Fig extraction from a PHP 5 monolith

---

## What is this?

Northwind Logistics GmbH runs its entire business on a PHP 5 application built in 2009. The board approved "modernization" without defining what that means. Your job: prove the system can be safely evolved — without shutting it down or rewriting it from scratch.

The monolith is intentionally ugly. Inline SQL, global state, a god class, business logic hiding in database triggers. That's the point — fixing it is the exercise.

---

## Business Domains

Ordered by business criticality (see `monolith/docs/capabilities.md` for full MoSCoW breakdown):

| Priority | Domain | What it does |
|---|---|---|
| MUST | Order Management | Full order lifecycle, stock reservation, cancellation |
| MUST | Invoice & Payment | SEPA direct debit, dunning cron, auto-numbering |
| MUST | Customer Management | Master data, bank details, FK anchor for all other domains |
| SHOULD | Delivery & Tracking | Carrier status updates, delivery lifecycle |
| SHOULD | Article & Inventory | Product catalog, stock levels |
| COULD | Bank Account Validation | BLZ + IBAN + BIC validation via BAV |

---

## Tech Stack (Legacy)

| Component | Technology |
|---|---|
| Runtime | PHP 5.6 |
| Database access | `mysql_*` functions (deprecated since PHP 5.5) |
| Framework | None — flat if/elseif router on `$_GET['page']` |
| Bank validation | `malkusch/bav` ^1 |
| Tests | PHPUnit 4.x |
| Background jobs | Cron (`cron/mahnlauf.php`, `cron/update_lieferstatus.php`) |

## Tech Stack (Zielarchitektur)

| Component | Technology |
|---|---|
| Backend Services | Kotlin + Spring Boot 3.x |
| Build | Gradle (Kotlin DSL) |
| Datenbank | PostgreSQL — jeder Service eigene DB |
| API-Stil | REST/JSON |
| Frontend | Vue.js 3 |
| Tests | JUnit 5 + Testcontainers |

---

## Repo Structure

```
northwind/
├── monolith/               # The legacy PHP application
│   ├── index.php           # Entry point + router
│   ├── config.php          # DB credentials, error suppression
│   ├── init.php            # Bootstrap: session, DB connection, BAV config
│   ├── helper.php          # 616-line utility grab-bag
│   ├── classes/
│   │   ├── Northwind.php   # God class — 30+ methods, 5 business domains
│   │   ├── NorthwindDB.php # mysql_* wrapper
│   │   └── BankValidator.php
│   ├── pages/              # 13 page templates (business logic + HTML mixed)
│   ├── cron/               # 3 background jobs
│   ├── sql/
│   │   └── schema.sql      # DB schema + 5 triggers that contain business logic
│   ├── tests/              # PHPUnit 4 test suite
│   └── docs/               # Capabilities map, ADRs
├── doc/
│   ├── initial context/    # Hackathon scenario descriptions
│   └── stories/            # User stories US-001 to US-008
└── services/               # Extracted microservices (created during modernization)
```

---

## Current Status

Fortschritt nach Hackathon-Challenges:

| Challenge | Status | Artefakte |
|---|---|---|
| **The Patient** — Monolith generieren | ✅ Done | `monolith/` — PHP 5, Gott-Klasse, 5 DB-Trigger, inline SQL |
| **The Stories** — PM User Stories | ✅ Done | `doc/stories/US-001` bis `US-008` |
| **The Map** — Architektur-ADRs + Zerlegungsplan | 🔜 Next | `monolith/docs/ADR-*.md` (ausstehend) |
| **The Pin** — Charakterisierungstests | ⬜ Pending | — |
| **The Cut** — Ersten Service extrahieren | ⬜ Pending | `services/customer-api/` (geplant) |
| **The Fence** — Anti-Corruption Layer | ⬜ Pending | — |
| **The Scorecard** — Eval-Harness | ⬜ Pending | — |

### Nächste Schritte

1. **Architect:** Zielarchitektur ermitteln, Schnitte definieren, ADRs schreiben (`ADR-001` bis `ADR-003`)
2. **Tester:** Charakterisierungstests gegen den Monolith (Pin) — Verhalten fixieren, bevor irgendjemand anfasst
3. **Dev:** Customer API extrahieren (erster Schnitt, niedrigstes Risiko) — Stories US-007 + US-008 als Basis
4. **Dev:** Order Management Service — Stories US-001 bis US-003; Trigger 2+4 werden expliziter App-Code
5. **Dev:** Invoice/Payment Service — Stories US-004 bis US-006; Trigger 1 + Sequenz-Deadlock lösen

---

## Modernization Approach

**Pattern:** [Strangler Fig](https://martinfowler.com/bliki/StranglerFigApplication.html) — extract services one seam at a time. The monolith stays running throughout.

**Extraction order** (business priority, then technical risk):

| # | Service | Tech | Stories |
|---|---|---|---|
| 1 | Customer API | Kotlin + Spring Boot + PostgreSQL | US-007, US-008 |
| 2 | Order Management Service | Kotlin + Spring Boot + PostgreSQL | US-001, US-002, US-003 |
| 3 | Invoice/Payment Service | Kotlin + Spring Boot + PostgreSQL | US-004, US-005, US-006 |
| 4 | Shipment Tracking | Kotlin + Spring Boot + PostgreSQL | US-003 (tracking) |
| 5 | Bank Validation Service | Kotlin + Spring Boot | US-008, US-005 |

**Frontend:** Vue.js 3 replaces PHP page templates incrementally — talks only to the new service APIs.

Each extraction requires: characterization tests pinning current behavior → clean API contract → anti-corruption layer → both monolith and new service green on the same commit.

See `monolith/docs/target-architecture.md` for the full target architecture and `monolith/docs/capabilities.md` for the decomposition plan and known defects.

---

## Documentation

| Resource | Location |
|---|---|
| Business capabilities & extraction plan | `monolith/docs/capabilities.md` |
| Monolith technical details & known bugs | `monolith/CLAUDE.md` |
| User stories (US-001 to US-008) | `doc/stories/` |
| Hackathon scenario description | `doc/initial context/01-code-modernization.md` |
| Architecture Decision Records | `monolith/docs/ADR-*.md` (to be created) |

---

## Getting Started

```bash
# Install PHP dependencies
cd monolith
composer install

# Set up the database (MySQL 5.x or MariaDB)
mysql -u root -p < sql/schema.sql

# Configure DB credentials
cp config.php config.local.php
# Edit config.local.php with your local DB credentials

# Run the test suite
./vendor/bin/phpunit tests/

# Start a local PHP dev server
php -S localhost:8080 index.php
```

Open `http://localhost:8080` — login with the credentials seeded by `schema.sql`.

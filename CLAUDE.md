# Northwind Logistics — Modernization Project

## Hackathon Context

**Scenario:** Code Modernization (Challenge 1 of 5)
**Goal:** Prove the PHP 5 monolith can be evolved safely using the Strangler Fig pattern — no big-bang rewrite.
**Active challenges:** The Patient (done), The Stories (done), The Map (next: ADRs + decomposition plan).

---

## Repo Layout

| Path | Purpose | Own CLAUDE.md |
|---|---|---|
| `monolith/` | Legacy PHP 5 application — the starting point | Yes → `monolith/CLAUDE.md` |
| `monolith/docs/` | Capabilities map, ADRs (to be created) | No |
| `doc/initial context/` | Hackathon scenario descriptions | No |
| `doc/stories/` | User stories US-001 through US-008 | No |
| `services/` | Extracted microservices (to be created) | Each gets its own CLAUDE.md |

For all technical details on the monolith — anti-patterns, known bugs, trigger list, global state — see `monolith/CLAUDE.md`.

---

## Business Capabilities (MoSCoW Order)

### MUST — Core business, in daily use

**1. Order Management**
Full lifecycle of a customer order. Status machine: 1=New → 2=Confirmed → 3=In Progress → 4=Shipped → 5=Complete, 9=Cancelled.
Known defect: stock is decremented on confirmation (DB trigger) but never restored on cancellation. Manual monthly correction required.

**2. Invoice & Payment**
SEPA direct debit is the primary payment method. Failures here = no revenue.
Invoice numbering: `RE-YYYY-NNNNN` via DB sequence (deadlock risk at high volume).
BAV configuration is overwritten three times per request — validation results are unreliable under race conditions.

**3. Customer Management**
Master data. FK anchor for orders and invoices. Includes bank details (BLZ + account number).

### SHOULD — Operationally important, not hourly

**4. Delivery & Tracking**
Carrier status updates via cron. DB trigger `tr_lieferung_zugestellt` sets wrong order status (4 instead of 5). Cron and trigger can overwrite each other.

**5. Article & Inventory**
Product catalog and stock management. Stock decremented by trigger on order confirmation, no negative-stock guard.

### COULD — Useful but not a blocker

**6. Bank Account Validation**
Technical capability, no own UI. Underpins payment and customer master data.
Currently duplicated: old format-only function in `helper.php` and BAV-based `BankValidator` class.

### WON'T — Not reliably deliverable in current state

**7. Unified Error & Audit Logging**
Three incompatible error strategies in `Northwind.php` (`false`, `die()`, `Exception`). `error_reporting(0)` in config. Errors disappear silently.

---

## Extraction Order (Strangler Fig)

Business priority first, then technical risk. Each extraction must leave the monolith fully functional.

| # | Service | Priority | Key risk |
|---|---|---|---|
| 1 | Customer API | MUST | FK in `auftraege` + `rechnungen` |
| 2 | Order Management Service | MUST | Triggers 2+4 must become explicit app code |
| 3 | Invoice/Payment Service | MUST | Touches all other domains; trigger 1 + sequence deadlock |
| 4 | Shipment Tracking | SHOULD | 3-way address denormalization, trigger race condition |
| 5 | Bank Validation Service | COULD | Lowest technical risk; lowest business priority |

---

## Rules for Claude (Project-Wide)

**Never do:**
- Big-bang rewrite — Strangler Fig only
- Write to the monolith DB from a new service (or vice versa)
- Use `mysql_*` or `global $db` in new services
- Expose monolith DB column names in a new service's public API (anti-corruption layer must exist)
- Edit `sql/schema.sql` triggers without characterization tests in place first

**Prefer:**
- Reading `monolith/CLAUDE.md` before touching any monolith code
- Adding contract tests alongside each extraction (characterization suite must stay green)
- Explicit application code over hidden DB trigger logic when building new services
- One seam at a time — the monolith and the extracted service must both be provable from a single test run

---

## ADRs (to be created in `monolith/docs/`)

- `ADR-001-strangler-fig-customer-api.md`
- `ADR-002-anti-corruption-layer.md`
- `ADR-003-triggers-to-application-code.md`

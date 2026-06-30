# Zielarchitektur: Northwind Logistics Modernisierung

**Datum:** 2026-06-30  
**Muster:** Strangler Fig — schrittweise Extraktion, kein Big-Bang-Rewrite  
**Status:** Gültig ab The Map-Phase

---

## Ausgangslage

Der PHP-5-Monolith (`monolith/`) enthält 5 Geschäftsdomänen in einer Gott-Klasse (`Northwind.php`), 5 DB-Trigger mit versteckter Business-Logik, und bekannte kritische Defekte (NW-445, NW-178, Sequenz-Deadlock). Der Monolith bleibt während der gesamten Migration produktiv.

---

## Zielarchitektur

```
┌─────────────────────────────────────────────────────────────────┐
│                        Vue.js Frontend                          │
│         (ersetzt PHP-Page-Templates schrittweise)               │
└────────┬────────┬────────┬────────────┬────────────────────────┘
         │        │        │            │
         ▼        ▼        ▼            ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ Customer │ │  Order   │ │ Invoice/ │ │Shipment  │ │  Bank    │
│   API   │ │  Mgmt    │ │ Payment  │ │Tracking  │ │Validation│
│         │ │ Service  │ │ Service  │ │ Service  │ │ Service  │
│Kotlin+  │ │Kotlin+   │ │Kotlin+   │ │Kotlin+   │ │Kotlin+   │
│Spring   │ │Spring    │ │Spring    │ │Spring    │ │Spring    │
│Boot     │ │Boot      │ │Boot      │ │Boot      │ │Boot      │
│Postgres │ │Postgres  │ │Postgres  │ │Postgres  │ │(stateless│
│         │ │          │ │          │ │          │ │+ BAV lib)│
└──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘
         ▲        ▲        ▲            ▲
         └────────┴────────┴────────────┘
                         │
              ┌──────────────────────┐
              │   PHP-5-Monolith     │
              │  (während Migration) │
              │  Anti-Corruption     │
              │  Layer (HTTP-Facade) │
              └──────────────────────┘
```

---

## Services und Extraktionsreihenfolge

| # | Dienst | Technologie | Stories | Schlüsselrisiko |
|---|---|---|---|---|
| 1 | **Customer API** | Kotlin + Spring Boot 3 + PostgreSQL | US-007, US-008 | FK-Anker in `auftraege` + `rechnungen` |
| 2 | **Order Management Service** | Kotlin + Spring Boot 3 + PostgreSQL | US-001, US-002, US-003 | Trigger 2+4 müssen expliziter App-Code werden |
| 3 | **Invoice/Payment Service** | Kotlin + Spring Boot 3 + PostgreSQL | US-004, US-005, US-006 | Berührt alle Domänen; Trigger 1 + Sequenz-Deadlock |
| 4 | **Shipment Tracking Service** | Kotlin + Spring Boot 3 + PostgreSQL | US-003 (Tracking-Link) | 3-fache Adressdenormalisierung, Trigger-Race |
| 5 | **Bank Validation Service** | Kotlin + Spring Boot 3 (stateless) | US-008, US-005 | Niedrigstes technisches Risiko |

**Frontend:** Vue.js 3 — ersetzt PHP-Page-Templates schrittweise. Kommuniziert ausschließlich via REST/JSON gegen die neuen Services. Kein direkter PHP-Aufruf nach Ablösung.

---

## Tech Stack (neue Services)

| Komponente | Technologie |
|---|---|
| Backend | Kotlin + Spring Boot 3.x |
| Build | Gradle (Kotlin DSL) |
| Datenbank | PostgreSQL — jeder Service eigene DB |
| API-Stil | REST/JSON |
| Frontend | Vue.js 3 |
| Tests | JUnit 5 + Testcontainers (PostgreSQL) |

---

## Kommunikationsmuster

- **Synchron:** REST/HTTP (JSON) für alle Service-zu-Service-Aufrufe und Frontend-Zugriffe
- **Kein** direkter DB-Zugriff eines neuen Services auf die Monolith-DB (und umgekehrt)
- **Monolith-Facade:** Der Monolith ruft neue Services über HTTP-Client-Adapter auf — nie direkt auf deren DB
- **Anti-Corruption Layer:** Zwischen Monolith-Datenschema und Service-APIs (Details: ADR-002)

---

## Migrations-Sequenz

Jeder Extraktionsschritt folgt diesem Ablauf — kein Schritt überspringen:

```
1. Charakterisierungstests schreiben (The Pin)
   → aktuelles Verhalten fixieren, Bugs eingeschlossen

2. Neuen Service erstellen mit eigenem Postgres
   → grüne Contract Tests

3. Monolith: Lesezugriffe auf HTTP-Facade umstellen
   → Monolith + Service parallel grün

4. Monolith: Schreibzugriffe umstellen
   → Dual-Write-Phase (beide Seiten synchron)

5. Monolith-Code für diese Domäne stilllegen
   → Charakterisierungstests + Contract Tests grün auf demselben Commit

6. DB-Trigger entfernen (sofern in App-Code überführt)
```

---

## Was wir NICHT tun

- **Kein Big-Bang-Rewrite** — Monolith bleibt bis zur vollständigen Ablösung produktiv
- **Keine Mehrfach-Extraktion gleichzeitig** — ein Seam nach dem anderen
- **Kein Monolith-DB-Schreibzugriff aus neuen Services** — Anti-Corruption Layer ist Pflicht
- **Keine Monolith-DB-Spaltennamen in Service-APIs** — Feldmapping in ADR-002
- **Keine Trigger-Entfernung vor Charakterisierungstests** — Netz zuerst

---

## Verwandte Dokumente

| Dokument | Pfad |
|---|---|
| ADR-001: Strangler Fig Customer API | `ADR-001-strangler-fig-customer-api.md` |
| ADR-002: Anti-Corruption Layer | `ADR-002-anti-corruption-layer.md` |
| ADR-003: Trigger → Application Code | `ADR-003-triggers-to-application-code.md` |
| Monolith-Technikdetails & Bugs | `../CLAUDE.md` |
| Business Capabilities | `capabilities.md` |

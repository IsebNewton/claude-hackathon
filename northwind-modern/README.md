# Northwind Modern — Spring Boot/Kotlin + MongoDB + Vue

A modern rewrite of the **core slice** of the Northwind Logistics monolith
(`../monolith/`): **Customers → Orders → Invoices → Payments**. The legacy PHP 5
monolith is left untouched and still runnable.

| Layer | Stack | Port |
|---|---|---|
| Backend | Spring Boot 3 · Kotlin · Spring Data MongoDB · Spring Security (JWT) · iban4j | 8080 |
| Database | MongoDB 7 | 27017 |
| Frontend | Vue 3 · Vite · TypeScript · Pinia · Vue Router · Bootstrap 5 | 3000 |

## What was migrated (and how)

- **8 MySQL tables → 5 Mongo collections + a `counters` collection.** Order line
  items are embedded in `orders`; payments are embedded in `invoices`.
- **The 5 MySQL triggers became explicit, tested service code:**
  - `tr_auftrag_bestaetigt` → `OrderService.confirm()` (decrements stock; clamped at 0).
  - `tr_rechnung_bezahlt` → `InvoiceService.recordPayment()` (full payment ⇒ invoice
    `BEZAHLT` **and** order `ABGESCHLOSSEN`).
  - `tr_rechnung_nummer` + `rechnungs_sequence` → `SequenceService` atomic `$inc`
    (removes the race condition / 2023 deadlock and the helper/trigger conflict).
- **Triple BAV config bug** eliminated: one `BankValidationService` (iban4j) is the
  single validation path.
- **Documented legacy bug fixed:** cancelling an order now **restores reserved stock**
  (legacy NW-445 never did).
- **MD5 passwords → BCrypt; JWT auth.**

## Run it

### 1. Backend + MongoDB (Docker)
```bash
cd northwind-modern
docker compose up -d --build      # builds the Spring Boot image, starts mongo + backend
curl http://localhost:8080/actuator/health   # -> {"status":"UP"}
```
First boot seeds: users (`admin`/`admin123`, `buchhaltung`/`buch2009`), ~6 articles,
5 customers with valid German IBANs, and 1 sample order.

To run the backend without Docker (needs a local MongoDB on 27017):
```bash
cd backend
./gradlew bootRun
```

### 2. Frontend (local, port 3000)
```bash
cd frontend
npm install
npm run dev        # http://localhost:3000 (proxies /api → http://localhost:8080)
```

### Tests
```bash
cd backend
./gradlew test     # service tests pin the migrated trigger logic
```

## End-to-end demo flow
1. Log in as `admin` / `admin123`.
2. **Kunden** → create a customer with a valid IBAN (e.g. `DE89370400440532013000`);
   an invalid IBAN is rejected.
3. **Aufträge** → *Neuer Auftrag* wizard: pick customer → add article positions →
   confirm. Confirming the order decrements article stock.
4. On the order detail, **Rechnung erstellen**.
5. **Zahlung erfassen** for the full gross amount → invoice flips to *Bezahlt* and the
   order auto-transitions to *Abgeschlossen*.
6. Cancel a different (confirmed) order → its stock is restored.

## Scope / not included
Shipments (`lieferungen`), standalone Article/Shipment CRUD UI, dunning (`mahnlauf`),
email, cron jobs, and the obsolete `banken_cache`. The anti-corruption rule holds: the
REST API uses clean English field names — no legacy DB column names leak out.

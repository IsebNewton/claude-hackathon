# ADR-002: Anti-Corruption Layer zwischen Monolith und neuen Services

**Status:** Accepted  
**Datum:** 2026-06-30  
**Entscheider:** Architect

---

## Context

Der Monolith verwendet ein gewachsenes, inkonsistentes Datenschema aus 2009. Spaltennamen sind deutsch (`blz`, `kontonummer`, `lieferdatum_soll`), Status-Codes sind Magic Numbers (1,2,3,4,5,9), und Validierungslogik ist doppelt vorhanden (`helper.php` + `BankValidator`).

Wenn diese Konzepte direkt in neue Service-APIs übernommen werden, friert das Monolith-Design für Jahre ein. Jede spätere Umbenennung erfordert Breaking Changes in allen Clients.

Die Projektregeln verlangen explizit: "Monolith-DB-Spaltennamen dürfen nie in einer neuen Service-API erscheinen."

---

## Decision

**Jeder neue Service implementiert einen Anti-Corruption Layer (ACL) als Teil seiner Adapter-Schicht.**

Der ACL übersetzt zwischen:
- Monolith-Datenmodell (DB-Spaltenname, Magic Numbers, deutsche Bezeichner)
- Service-API-Modell (englische Bezeichner, semantische Typen, Enums)

### Feldmapping-Tabelle (vollständig)

**Customer-Domäne:**

| Monolith (DB-Spalte / PHP-Key) | Service-API-Feld | Typ |
|---|---|---|
| `kunden.blz` | `bankCode` | `String` |
| `kunden.kontonummer` | `accountNumber` | `String` |
| `kunden.vorname` + `kunden.nachname` | `firstName` + `lastName` | `String` |
| `kunden.strasse` + `kunden.hausnummer` | `streetAddress` | `String` |
| `kunden.plz` | `postalCode` | `String` |
| `kunden.ort` | `city` | `String` |
| `kunden.angelegt_am` / `kunden.created` | `createdAt` | `Instant` (ISO-8601) |
| `kunden.aktiv` = 0/1 | `active` | `Boolean` |
| `kunden.kundennummer` | `customerNumber` | `String` |

**Order-Domäne:**

| Monolith | Service-API-Feld | Typ |
|---|---|---|
| `auftraege.status` = 1 | `OrderStatus.NEW` | Enum |
| `auftraege.status` = 2 | `OrderStatus.CONFIRMED` | Enum |
| `auftraege.status` = 3 | `OrderStatus.IN_PROGRESS` | Enum |
| `auftraege.status` = 4 | `OrderStatus.SHIPPED` | Enum |
| `auftraege.status` = 5 | `OrderStatus.COMPLETED` | Enum |
| `auftraege.status` = 9 | `OrderStatus.CANCELLED` | Enum |
| `auftraege.auftragsnummer` | `orderNumber` | `String` |
| `auftraege.kunden_id` | `customerId` | `UUID` (nach Extraktion) |
| `auftraege.lieferanschrift_*` | `deliveryAddress.*` | Nested Object |

**Shipment-Domäne:**

| Monolith | Service-API-Feld | Typ |
|---|---|---|
| `lieferungen.lieferdatum_soll` | `scheduledDeliveryDate` | `LocalDate` |
| `lieferungen.lieferdatum_ist` | `actualDeliveryDate` | `LocalDate` |
| `lieferungen.status` = 'in_transit' | `ShipmentStatus.IN_TRANSIT` | Enum |
| `lieferungen.status` = 'zugestellt' | `ShipmentStatus.DELIVERED` | Enum |
| `lieferungen.empfaenger_*` | `recipient.*` | Nested Object |

**Invoice-Domäne:**

| Monolith | Service-API-Feld | Typ |
|---|---|---|
| `rechnungen.rechnungsnummer` | `invoiceNumber` | `String` |
| `rechnungen.status` = 'offen' | `InvoiceStatus.OPEN` | Enum |
| `rechnungen.status` = 'bezahlt' | `InvoiceStatus.PAID` | Enum |
| `rechnungen.status` = 'storniert' | `InvoiceStatus.CANCELLED` | Enum |
| `rechnungen.faellig_am` | `dueDate` | `LocalDate` |
| `rechnungen.mahnstufe` | `dunningLevel` | `Int` |

---

### Implementierung

Der ACL ist **keine eigene Schicht**, sondern Teil des Service-Adapters:

```
Service (Kotlin)
├── domain/           ← reine Domänobjekte, kein Monolith-Bezug
├── application/      ← Use Cases
├── adapter/
│   ├── api/          ← REST Controller mit Service-API-Typen
│   └── legacy/       ← ACL: Übersetzung Monolith ↔ Domain
```

Der `legacy/`-Adapter übersetzt eingehende Monolith-Daten in Domänobjekte und ausgehende Domänobjekte in Service-API-Responses.

### Seam-Test

Ein Test schlägt fehl, sobald ein Monolith-Spaltenname direkt in einer API-Response erscheint:

```kotlin
@Test
fun `no monolith column names in API response`() {
    val forbiddenKeys = listOf("blz", "kontonummer", "kunden_id", "auftrag_id",
        "lieferdatum_soll", "lieferdatum_ist", "mahnstufe", "aktiv")
    val response = restTemplate.getForObject("/customers/1", String::class.java)
    forbiddenKeys.forEach { key ->
        assertFalse(response.contains("\"$key\""), 
            "Monolith column '$key' leaked into API response")
    }
}
```

Dieser Test läuft in CI. Fehlschlag blockiert den Merge.

### Validierungshoheit

| Validierung | Monolith (vorher) | Ziel |
|---|---|---|
| BLZ-Format (8 Stellen) | `helper.php::validateBankleitzahl()` | Bank Validation Service |
| BLZ gegen Bundesbank | `BankValidator::istGueltigeBLZ()` | Bank Validation Service |
| Kontonummer-Prüfziffer | `BankValidator::pruefeKontonummer()` | Bank Validation Service |
| Kundendaten-Pflichtfelder | `Northwind::validiereKundendaten()` | Customer API |

Nach Extraktion des Bank Validation Service wird `helper.php::validateBankleitzahl()` nicht mehr aufgerufen.

---

## Consequences

### Positiv
- Monolith-Schema kann sich ändern ohne Breaking Change in Service-APIs
- Service-APIs sprechen eine konsistente, englische Domänensprache
- Doppelte BLZ-Validierung (Bug NW-612) wird durch klare Validierungshoheit aufgelöst
- Seam-Test verhindert Regressions automatisch

### Negativ / Risiken
- Mapping-Code muss gepflegt werden (Gefahr: Mapping veraltet)
- Temporäre Inkonsistenz während Dual-Write (Monolith-DB und Service-DB können kurzzeitig abweichen)
- `legacy/`-Adapter wird obsolet nach vollständiger Migration — gezielt löschen

---

## What we chose NOT to do

**Nicht: Monolith-Schema direkt übernehmen**  
"Gib dem neuen Service einfach die gleichen Spaltennamen" wurde verworfen. Das würde den Monolith für Jahre einfrieren und die Migration erschweren.

**Nicht: GraphQL als ACL**  
Ein GraphQL-Gateway als Übersetzungsschicht würde zusätzliche Infrastruktur und Expertise erfordern. REST-Adapter im Service selbst ist ausreichend.

**Nicht: ORM-Migration (Flyway/Liquibase auf Monolith-DB)**  
Die Monolith-DB-Spaltennamen per Migration umzubenennen wurde verworfen. Zu hohes Risiko für laufende Produktion ohne vollständige Charakterisierungstests aller DB-Zugriffe.

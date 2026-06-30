package de.northwind.common

/**
 * Order lifecycle. Replaces the legacy `auftraege.status` magic ints (1,2,3,4,5,9).
 * Legacy mapping kept for traceability:
 *   1=NEU, 2=BESTAETIGT, 3=IN_BEARBEITUNG, 4=VERSENDET, 5=ABGESCHLOSSEN, 9=STORNIERT
 */
enum class OrderStatus {
    NEU,
    BESTAETIGT,
    IN_BEARBEITUNG,
    VERSENDET,
    ABGESCHLOSSEN,
    STORNIERT
}

/**
 * Invoice lifecycle. Replaces the legacy `rechnungen.status` strings.
 */
enum class InvoiceStatus {
    OFFEN,
    TEILBEZAHLT,
    BEZAHLT,
    STORNIERT,
    GUTSCHRIFT
}

/**
 * User roles. Replaces the legacy `benutzer.rolle` enum.
 */
enum class Role {
    MITARBEITER,
    ADMIN,
    BUCHHALTUNG
}

enum class CustomerType {
    PRIVAT,
    GESCHAEFT
}

package de.northwind.customer

import de.northwind.common.CustomerType
import org.springframework.data.annotation.Id
import org.springframework.data.mongodb.core.index.Indexed
import org.springframework.data.mongodb.core.mapping.Document
import java.time.Instant

/** Embedded SEPA bank details + cached validation result (replaces kunden.bav_* fields). */
data class BankDetails(
    val iban: String? = null,
    val bic: String? = null,
    val blz: String? = null,
    val accountNumber: String? = null,
    val validated: Boolean? = null,
    val validatedAt: Instant? = null
)

/** Replaces the legacy `kunden` table (clean fields, no `created`/`angelegt_am` duplication). */
@Document(collection = "customers")
data class Customer(
    @Id val id: String? = null,
    @Indexed(unique = true) val customerNumber: String,
    val type: CustomerType = CustomerType.PRIVAT,
    val name: String,
    val company: String? = null,
    val street: String,
    val houseNumber: String? = null,
    val postalCode: String,
    val city: String,
    val country: String = "DEU",
    val email: String? = null,
    val phone: String? = null,
    val bank: BankDetails? = null,
    val active: Boolean = true,
    val createdAt: Instant = Instant.now(),
    val updatedAt: Instant? = null
)

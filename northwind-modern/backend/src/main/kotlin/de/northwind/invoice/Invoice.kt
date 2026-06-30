package de.northwind.invoice

import de.northwind.common.InvoiceStatus
import org.springframework.data.annotation.Id
import org.springframework.data.mongodb.core.index.Indexed
import org.springframework.data.mongodb.core.mapping.Document
import java.math.BigDecimal
import java.time.Instant
import java.time.LocalDate

/** Embedded payment record (replaces the `zahlungen` table). */
data class Payment(
    val amount: BigDecimal,
    val method: String = "lastschrift",
    val iban: String? = null,
    val bic: String? = null,
    val bankValidated: Boolean? = null,
    val date: Instant = Instant.now(),
    val note: String? = null
)

/** Replaces `rechnungen` + embedded `zahlungen`. References order and customer by id. */
@Document(collection = "invoices")
data class Invoice(
    @Id val id: String? = null,
    @Indexed(unique = true) val invoiceNumber: String,
    @Indexed val orderId: String,
    @Indexed val customerId: String,
    val netAmount: BigDecimal,
    val vatAmount: BigDecimal,
    val grossAmount: BigDecimal,
    @Indexed val status: InvoiceStatus = InvoiceStatus.OFFEN,
    val dueDate: LocalDate,
    val paidAmount: BigDecimal = BigDecimal.ZERO,
    val paidAt: Instant? = null,
    val payments: List<Payment> = emptyList(),
    val dunningLevel: Int = 0,
    val createdAt: Instant = Instant.now()
)

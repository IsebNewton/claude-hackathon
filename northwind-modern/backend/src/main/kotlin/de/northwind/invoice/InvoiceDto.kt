package de.northwind.invoice

import de.northwind.common.InvoiceStatus
import de.northwind.customer.Customer
import jakarta.validation.constraints.NotNull
import jakarta.validation.constraints.Positive
import java.math.BigDecimal
import java.time.Instant
import java.time.LocalDate

data class CreateInvoiceRequest(
    @field:NotNull val orderId: String
)

data class PaymentRequest(
    @field:NotNull @field:Positive(message = "Betrag muss positiv sein") val amount: BigDecimal,
    val method: String = "lastschrift",
    val iban: String? = null,
    val bic: String? = null,
    val note: String? = null
)

data class PaymentDto(
    val amount: BigDecimal,
    val method: String,
    val iban: String?,
    val bic: String?,
    val bankValidated: Boolean?,
    val date: Instant,
    val note: String?
)

data class InvoiceDto(
    val id: String,
    val invoiceNumber: String,
    val orderId: String,
    val customerId: String,
    val customerNumber: String?,
    val customerName: String?,
    val netAmount: BigDecimal,
    val vatAmount: BigDecimal,
    val grossAmount: BigDecimal,
    val status: InvoiceStatus,
    val dueDate: LocalDate,
    val paidAmount: BigDecimal,
    val openAmount: BigDecimal,
    val paidAt: Instant?,
    val payments: List<PaymentDto>,
    val dunningLevel: Int,
    val createdAt: Instant
)

fun Payment.toDto() = PaymentDto(amount, method, iban, bic, bankValidated, date, note)

fun Invoice.toDto(customer: Customer? = null) = InvoiceDto(
    id = id!!,
    invoiceNumber = invoiceNumber,
    orderId = orderId,
    customerId = customerId,
    customerNumber = customer?.customerNumber,
    customerName = customer?.name,
    netAmount = netAmount,
    vatAmount = vatAmount,
    grossAmount = grossAmount,
    status = status,
    dueDate = dueDate,
    paidAmount = paidAmount,
    openAmount = grossAmount.subtract(paidAmount),
    paidAt = paidAt,
    payments = payments.map { it.toDto() },
    dunningLevel = dunningLevel,
    createdAt = createdAt
)

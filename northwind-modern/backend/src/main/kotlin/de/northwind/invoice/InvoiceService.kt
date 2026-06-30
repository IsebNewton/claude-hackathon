package de.northwind.invoice

import de.northwind.common.BankValidationService
import de.northwind.common.BusinessException
import de.northwind.common.InvoiceStatus
import de.northwind.common.NotFoundException
import de.northwind.common.SequenceService
import de.northwind.customer.CustomerRepository
import de.northwind.order.OrderService
import de.northwind.order.computeTotals
import org.springframework.data.domain.Page
import org.springframework.data.domain.Pageable
import org.springframework.stereotype.Service
import java.time.Instant
import java.time.LocalDate

@Service
class InvoiceService(
    private val repo: InvoiceRepository,
    private val orderService: OrderService,
    private val customers: CustomerRepository,
    private val sequences: SequenceService,
    private val bankValidation: BankValidationService
) {

    fun list(status: InvoiceStatus?, pageable: Pageable): Page<InvoiceDto> {
        val page = if (status != null) repo.findByStatus(status, pageable) else repo.findAll(pageable)
        val customerMap = customers.findAllById(page.content.map { it.customerId }).associateBy { it.id }
        return page.map { it.toDto(customerMap[it.customerId]) }
    }

    fun getDto(id: String): InvoiceDto {
        val invoice = get(id)
        return invoice.toDto(customers.findById(invoice.customerId).orElse(null))
    }

    fun get(id: String): Invoice =
        repo.findById(id).orElseThrow { NotFoundException("Rechnung $id nicht gefunden") }

    /** One invoice per order (ignoring cancelled ones). Number generated atomically. */
    fun createFromOrder(orderId: String): InvoiceDto {
        val order = orderService.get(orderId)
        val existing = repo.findByOrderIdAndStatusNot(orderId, InvoiceStatus.STORNIERT)
        if (existing.isNotEmpty()) {
            throw BusinessException("Für diesen Auftrag existiert bereits eine Rechnung")
        }
        val totals = computeTotals(order.positions)
        val invoice = Invoice(
            invoiceNumber = sequences.nextInvoiceNumber(),
            orderId = order.id!!,
            customerId = order.customerId,
            netAmount = totals.net,
            vatAmount = totals.vat,
            grossAmount = totals.gross,
            status = InvoiceStatus.OFFEN,
            dueDate = LocalDate.now().plusDays(30)
        )
        val saved = repo.save(invoice)
        return saved.toDto(customers.findById(saved.customerId).orElse(null))
    }

    /**
     * Trigger `tr_rechnung_bezahlt` made explicit: when cumulative payments cover the
     * gross total, the invoice becomes BEZAHLT and the linked order ABGESCHLOSSEN.
     * Bank validation runs once via the single BankValidationService.
     */
    fun recordPayment(invoiceId: String, req: PaymentRequest): InvoiceDto {
        val invoice = get(invoiceId)
        if (invoice.status == InvoiceStatus.BEZAHLT || invoice.status == InvoiceStatus.STORNIERT) {
            throw BusinessException("Rechnung ist bereits abgeschlossen")
        }

        val bankResult = bankValidation.validate(req.iban, req.bic)
        if (!req.iban.isNullOrBlank() && !bankResult.ibanValid) {
            throw BusinessException(bankResult.message ?: "Bankverbindung ungültig")
        }

        val payment = Payment(
            amount = req.amount,
            method = req.method,
            iban = req.iban?.replace(" ", "")?.uppercase(),
            bic = req.bic?.replace(" ", "")?.uppercase(),
            bankValidated = if (req.iban.isNullOrBlank()) null else bankResult.valid,
            date = Instant.now(),
            note = req.note
        )

        val newPaidAmount = invoice.paidAmount.add(req.amount)
        val fullyPaid = newPaidAmount >= invoice.grossAmount
        val updated = invoice.copy(
            payments = invoice.payments + payment,
            paidAmount = newPaidAmount,
            status = if (fullyPaid) InvoiceStatus.BEZAHLT else InvoiceStatus.TEILBEZAHLT,
            paidAt = if (fullyPaid) Instant.now() else invoice.paidAt
        )
        val saved = repo.save(updated)

        if (fullyPaid) {
            orderService.markCompleted(saved.orderId) // trigger tr_rechnung_bezahlt
        }
        return saved.toDto(customers.findById(saved.customerId).orElse(null))
    }
}

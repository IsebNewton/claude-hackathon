package de.northwind.invoice

import de.northwind.common.BankValidationResult
import de.northwind.common.BankValidationService
import de.northwind.common.InvoiceStatus
import de.northwind.common.SequenceService
import de.northwind.customer.CustomerRepository
import de.northwind.order.OrderService
import org.assertj.core.api.Assertions.assertThat
import org.junit.jupiter.api.Test
import org.mockito.kotlin.any
import org.mockito.kotlin.doAnswer
import org.mockito.kotlin.doReturn
import org.mockito.kotlin.mock
import org.mockito.kotlin.never
import org.mockito.kotlin.verify
import org.mockito.kotlin.whenever
import java.math.BigDecimal
import java.time.LocalDate
import java.util.Optional

/** Pins the migrated tr_rechnung_bezahlt behaviour: full payment completes the order. */
class InvoiceServiceTest {

    private val repo: InvoiceRepository = mock()
    private val orderService: OrderService = mock()
    private val customers: CustomerRepository = mock {
        on { findById(any()) } doReturn Optional.empty()
    }
    private val sequences: SequenceService = mock()
    private val bankValidation: BankValidationService = mock {
        on { validate(any(), any()) } doReturn BankValidationResult(true, true, true, null)
    }

    private val service = InvoiceService(repo, orderService, customers, sequences, bankValidation)

    private fun invoice() = Invoice(
        id = "i1",
        invoiceNumber = "RE-2026-00001",
        orderId = "ord1",
        customerId = "c1",
        netAmount = BigDecimal("84.03"),
        vatAmount = BigDecimal("15.97"),
        grossAmount = BigDecimal("100.00"),
        status = InvoiceStatus.OFFEN,
        dueDate = LocalDate.now().plusDays(30)
    )

    @Test
    fun `full payment marks invoice BEZAHLT and completes the order`() {
        doReturn(Optional.of(invoice())).whenever(repo).findById("i1")
        doAnswer { it.getArgument<Invoice>(0) }.whenever(repo).save(any())

        val result = service.recordPayment("i1", PaymentRequest(amount = BigDecimal("100.00")))

        assertThat(result.status).isEqualTo(InvoiceStatus.BEZAHLT)
        assertThat(result.openAmount).isEqualByComparingTo("0.00")
        verify(orderService).markCompleted("ord1")
    }

    @Test
    fun `partial payment marks TEILBEZAHLT and leaves the order open`() {
        doReturn(Optional.of(invoice())).whenever(repo).findById("i1")
        doAnswer { it.getArgument<Invoice>(0) }.whenever(repo).save(any())

        val result = service.recordPayment("i1", PaymentRequest(amount = BigDecimal("40.00")))

        assertThat(result.status).isEqualTo(InvoiceStatus.TEILBEZAHLT)
        assertThat(result.openAmount).isEqualByComparingTo("60.00")
        verify(orderService, never()).markCompleted(any())
    }
}

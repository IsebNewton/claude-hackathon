package de.northwind.common

import de.northwind.order.OrderPosition
import de.northwind.order.computeTotals
import org.assertj.core.api.Assertions.assertThat
import org.junit.jupiter.api.Test
import java.math.BigDecimal

class BankAndTotalsTest {

    private val bankValidation = BankValidationService()

    @Test
    fun `valid German IBAN passes`() {
        // canonical valid German IBAN
        val result = bankValidation.validate("DE89370400440532013000", null)
        assertThat(result.valid).isTrue()
        assertThat(result.ibanValid).isTrue()
    }

    @Test
    fun `invalid IBAN fails`() {
        val result = bankValidation.validate("DE00000000000000000000", null)
        assertThat(result.ibanValid).isFalse()
        assertThat(result.valid).isFalse()
    }

    @Test
    fun `IBAN with spaces is normalised and accepted`() {
        assertThat(bankValidation.isIbanValid("DE89 3704 0044 0532 0130 00")).isTrue()
    }

    @Test
    fun `totals compute net vat and gross`() {
        val positions = listOf(
            OrderPosition(description = "A", quantity = BigDecimal("10"), unitPriceNet = BigDecimal("14.90"), vatRate = BigDecimal("19.00")),
            OrderPosition(description = "B", quantity = BigDecimal("2"), unitPriceNet = BigDecimal("1.20"), vatRate = BigDecimal("19.00"))
        )
        val totals = computeTotals(positions)
        // net = 149.00 + 2.40 = 151.40 ; vat = 19% = 28.766 -> 28.77 ; gross = 180.17
        assertThat(totals.net).isEqualByComparingTo("151.40")
        assertThat(totals.vat).isEqualByComparingTo("28.77")
        assertThat(totals.gross).isEqualByComparingTo("180.17")
    }
}

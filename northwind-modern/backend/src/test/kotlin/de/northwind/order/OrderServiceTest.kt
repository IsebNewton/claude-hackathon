package de.northwind.order

import de.northwind.article.Article
import de.northwind.article.ArticleRepository
import de.northwind.common.BusinessException
import de.northwind.common.OrderStatus
import de.northwind.common.SequenceService
import de.northwind.customer.CustomerRepository
import org.assertj.core.api.Assertions.assertThat
import org.assertj.core.api.Assertions.assertThatThrownBy
import org.junit.jupiter.api.Test
import org.mockito.kotlin.any
import org.mockito.kotlin.argumentCaptor
import org.mockito.kotlin.doAnswer
import org.mockito.kotlin.doReturn
import org.mockito.kotlin.mock
import org.mockito.kotlin.never
import org.mockito.kotlin.verify
import org.mockito.kotlin.whenever
import java.math.BigDecimal
import java.util.Optional

/**
 * Pins the migrated trigger behaviour (tr_auftrag_bestaetigt + the documented
 * NW-445 stock-restore fix) without a database.
 */
class OrderServiceTest {

    private val repo: OrderRepository = mock()
    private val customers: CustomerRepository = mock {
        on { findById(any()) } doReturn Optional.empty()
    }
    private val articles: ArticleRepository = mock()
    private val sequences: SequenceService = mock()

    private val service = OrderService(repo, customers, articles, sequences)

    private fun order(status: OrderStatus) = Order(
        id = "o1",
        orderNumber = "AUF-2026-00001",
        customerId = "c1",
        status = status,
        positions = listOf(
            OrderPosition(articleId = "a1", sku = "PAL-001", description = "Europalette", quantity = BigDecimal("10"), unitPriceNet = BigDecimal("14.90"))
        )
    )

    private val article = Article(id = "a1", sku = "PAL-001", name = "Europalette", priceNet = BigDecimal("14.90"), stock = 100)

    @Test
    fun `confirm decrements stock and sets BESTAETIGT`() {
        doReturn(Optional.of(order(OrderStatus.NEU))).whenever(repo).findById("o1")
        doReturn(Optional.of(article)).whenever(articles).findById("a1")
        doAnswer { it.getArgument<Article>(0) }.whenever(articles).save(any())
        doAnswer { it.getArgument<Order>(0) }.whenever(repo).save(any())

        val result = service.confirm("o1")

        assertThat(result.status).isEqualTo(OrderStatus.BESTAETIGT)
        val captor = argumentCaptor<Article>()
        verify(articles).save(captor.capture())
        assertThat(captor.firstValue.stock).isEqualTo(90)
    }

    @Test
    fun `cancel restores reserved stock (NW-445 fix)`() {
        doReturn(Optional.of(order(OrderStatus.BESTAETIGT))).whenever(repo).findById("o1")
        doReturn(Optional.of(article.copy(stock = 90))).whenever(articles).findById("a1")
        doAnswer { it.getArgument<Article>(0) }.whenever(articles).save(any())
        doAnswer { it.getArgument<Order>(0) }.whenever(repo).save(any())

        val result = service.cancel("o1")

        assertThat(result.status).isEqualTo(OrderStatus.STORNIERT)
        val captor = argumentCaptor<Article>()
        verify(articles).save(captor.capture())
        assertThat(captor.firstValue.stock).isEqualTo(100)
    }

    @Test
    fun `cancel on a NEU order does not touch stock`() {
        doReturn(Optional.of(order(OrderStatus.NEU))).whenever(repo).findById("o1")
        doAnswer { it.getArgument<Order>(0) }.whenever(repo).save(any())

        service.cancel("o1")

        verify(articles, never()).save(any())
    }

    @Test
    fun `confirm rejects a non-NEU order`() {
        doReturn(Optional.of(order(OrderStatus.BESTAETIGT))).whenever(repo).findById("o1")
        assertThatThrownBy { service.confirm("o1") }.isInstanceOf(BusinessException::class.java)
    }
}

package de.northwind.order

import de.northwind.article.ArticleRepository
import de.northwind.common.BusinessException
import de.northwind.common.NotFoundException
import de.northwind.common.OrderStatus
import de.northwind.common.SequenceService
import de.northwind.customer.Customer
import de.northwind.customer.CustomerRepository
import org.springframework.data.domain.Page
import org.springframework.data.domain.Pageable
import org.springframework.stereotype.Service
import java.time.Instant

@Service
class OrderService(
    private val repo: OrderRepository,
    private val customers: CustomerRepository,
    private val articles: ArticleRepository,
    private val sequences: SequenceService
) {

    fun list(status: OrderStatus?, customerId: String?, pageable: Pageable): Page<OrderDto> {
        val page = when {
            status != null && customerId != null -> repo.findByStatusAndCustomerId(status, customerId, pageable)
            status != null -> repo.findByStatus(status, pageable)
            customerId != null -> repo.findByCustomerId(customerId, pageable)
            else -> repo.findAll(pageable)
        }
        val customerMap = customers.findAllById(page.content.map { it.customerId }).associateBy { it.id }
        return page.map { it.toDto(customerMap[it.customerId]) }
    }

    fun getDto(id: String): OrderDto {
        val order = get(id)
        return order.toDto(customers.findById(order.customerId).orElse(null))
    }

    fun get(id: String): Order =
        repo.findById(id).orElseThrow { NotFoundException("Auftrag $id nicht gefunden") }

    fun create(req: OrderRequest, createdBy: String?): OrderDto {
        val customer = customers.findById(req.customerId)
            .orElseThrow { NotFoundException("Kunde ${req.customerId} nicht gefunden") }

        val positions = req.positions.map { buildPosition(it) }
        val order = Order(
            orderNumber = sequences.nextOrderNumber(),
            customerId = customer.id!!,
            status = OrderStatus.NEU,
            priority = req.priority,
            positions = positions,
            deliveryDateTarget = req.deliveryDateTarget,
            notes = req.notes,
            createdBy = createdBy
        )
        return repo.save(order).toDto(customer)
    }

    /**
     * Trigger `tr_auftrag_bestaetigt` made explicit: NEU -> BESTAETIGT decrements stock.
     * Stock is clamped at 0 (legacy allowed negative stock — fixed here).
     */
    fun confirm(id: String): OrderDto {
        val order = get(id)
        if (order.status != OrderStatus.NEU) {
            throw BusinessException("Nur neue Aufträge können bestätigt werden")
        }
        adjustStock(order.positions, decrement = true)
        val saved = repo.save(order.copy(status = OrderStatus.BESTAETIGT, confirmedAt = Instant.now()))
        return saved.toDto(customers.findById(saved.customerId).orElse(null))
    }

    /**
     * Documented fix vs. legacy NW-445: cancelling an order RESTORES reserved stock
     * (the legacy trigger never reversed the decrement). Completed/cancelled orders
     * cannot be cancelled.
     */
    fun cancel(id: String): OrderDto {
        val order = get(id)
        if (order.status == OrderStatus.ABGESCHLOSSEN || order.status == OrderStatus.STORNIERT) {
            throw BusinessException("Auftrag kann nicht mehr storniert werden")
        }
        val stockWasReserved = order.status in setOf(
            OrderStatus.BESTAETIGT, OrderStatus.IN_BEARBEITUNG, OrderStatus.VERSENDET
        )
        if (stockWasReserved) {
            adjustStock(order.positions, decrement = false)
        }
        val saved = repo.save(order.copy(status = OrderStatus.STORNIERT))
        return saved.toDto(customers.findById(saved.customerId).orElse(null))
    }

    /** Called by InvoiceService when an invoice is fully paid (trigger tr_rechnung_bezahlt). */
    fun markCompleted(orderId: String) {
        val order = repo.findById(orderId).orElse(null) ?: return
        if (order.status == OrderStatus.STORNIERT || order.status == OrderStatus.ABGESCHLOSSEN) return
        repo.save(order.copy(status = OrderStatus.ABGESCHLOSSEN, completedAt = Instant.now()))
    }

    private fun buildPosition(req: OrderPositionRequest): OrderPosition {
        if (req.articleId != null) {
            val article = articles.findById(req.articleId)
                .orElseThrow { NotFoundException("Artikel ${req.articleId} nicht gefunden") }
            return OrderPosition(
                articleId = article.id,
                sku = article.sku,
                description = req.description?.takeIf { it.isNotBlank() } ?: article.name,
                quantity = req.quantity,
                unitPriceNet = req.unitPriceNet ?: article.priceNet,
                vatRate = req.vatRate ?: article.vatRate
            )
        }
        val description = req.description?.takeIf { it.isNotBlank() }
            ?: throw BusinessException("Manuelle Position benötigt eine Bezeichnung")
        val price = req.unitPriceNet ?: throw BusinessException("Manuelle Position benötigt einen Preis")
        return OrderPosition(
            description = description,
            quantity = req.quantity,
            unitPriceNet = price,
            vatRate = req.vatRate ?: java.math.BigDecimal("19.00")
        )
    }

    private fun adjustStock(positions: List<OrderPosition>, decrement: Boolean) {
        positions.filter { it.articleId != null }.forEach { p ->
            val article = articles.findById(p.articleId!!).orElse(null) ?: return@forEach
            val delta = p.quantity.toInt()
            val newStock = if (decrement) (article.stock - delta).coerceAtLeast(0) else article.stock + delta
            articles.save(article.copy(stock = newStock))
        }
    }
}

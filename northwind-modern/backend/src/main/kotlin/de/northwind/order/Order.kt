package de.northwind.order

import de.northwind.common.OrderStatus
import org.springframework.data.annotation.Id
import org.springframework.data.mongodb.core.index.Indexed
import org.springframework.data.mongodb.core.mapping.Document
import java.math.BigDecimal
import java.time.Instant
import java.time.LocalDate

enum class OrderPriority { NORMAL, HOCH, EXPRESS }

/** Embedded line item. Snapshots name/price/vat as ordered (legacy auftrag_positionen). */
data class OrderPosition(
    val articleId: String? = null,
    val sku: String? = null,
    val description: String,
    val quantity: BigDecimal,
    val unit: String = "Stück",
    val unitPriceNet: BigDecimal,
    val vatRate: BigDecimal = BigDecimal("19.00")
)

/** Replaces `auftraege` + embedded `auftrag_positionen`. References the customer by id. */
@Document(collection = "orders")
data class Order(
    @Id val id: String? = null,
    @Indexed(unique = true) val orderNumber: String,
    @Indexed val customerId: String,
    @Indexed val status: OrderStatus = OrderStatus.NEU,
    val priority: OrderPriority = OrderPriority.NORMAL,
    val positions: List<OrderPosition> = emptyList(),
    val deliveryDateTarget: LocalDate? = null,
    val deliveryDateActual: LocalDate? = null,
    val notes: String? = null,
    val createdAt: Instant = Instant.now(),
    val confirmedAt: Instant? = null,
    val completedAt: Instant? = null,
    val createdBy: String? = null
)

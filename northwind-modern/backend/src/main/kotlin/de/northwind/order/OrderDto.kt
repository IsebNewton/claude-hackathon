package de.northwind.order

import de.northwind.common.OrderStatus
import de.northwind.customer.Customer
import jakarta.validation.constraints.NotEmpty
import jakarta.validation.constraints.NotNull
import java.math.BigDecimal
import java.math.RoundingMode
import java.time.Instant
import java.time.LocalDate

// ---- Requests ----

data class OrderPositionRequest(
    val articleId: String? = null,
    val description: String? = null,
    @field:NotNull val quantity: BigDecimal,
    val unitPriceNet: BigDecimal? = null,
    val vatRate: BigDecimal? = null
)

data class OrderRequest(
    @field:NotNull val customerId: String,
    val priority: OrderPriority = OrderPriority.NORMAL,
    val deliveryDateTarget: LocalDate? = null,
    val notes: String? = null,
    @field:NotEmpty(message = "Mindestens eine Position erforderlich")
    val positions: List<OrderPositionRequest>
)

// ---- Responses ----

data class OrderTotals(val net: BigDecimal, val vat: BigDecimal, val gross: BigDecimal)

data class OrderPositionDto(
    val articleId: String?,
    val sku: String?,
    val description: String,
    val quantity: BigDecimal,
    val unit: String,
    val unitPriceNet: BigDecimal,
    val vatRate: BigDecimal,
    val lineNet: BigDecimal,
    val lineGross: BigDecimal
)

data class OrderDto(
    val id: String,
    val orderNumber: String,
    val customerId: String,
    val customerNumber: String?,
    val customerName: String?,
    val status: OrderStatus,
    val priority: OrderPriority,
    val positions: List<OrderPositionDto>,
    val totals: OrderTotals,
    val deliveryDateTarget: LocalDate?,
    val deliveryDateActual: LocalDate?,
    val notes: String?,
    val createdAt: Instant,
    val confirmedAt: Instant?,
    val completedAt: Instant?
)

/** Net/VAT/gross totals for a set of positions. Shared by orders and invoice creation. */
fun computeTotals(positions: List<OrderPosition>): OrderTotals {
    var net = BigDecimal.ZERO
    var vat = BigDecimal.ZERO
    for (p in positions) {
        val lineNet = p.unitPriceNet.multiply(p.quantity)
        net = net.add(lineNet)
        vat = vat.add(lineNet.multiply(p.vatRate).divide(BigDecimal(100)))
    }
    net = net.setScale(2, RoundingMode.HALF_UP)
    vat = vat.setScale(2, RoundingMode.HALF_UP)
    return OrderTotals(net, vat, net.add(vat))
}

fun OrderPosition.toDto(): OrderPositionDto {
    val lineNet = unitPriceNet.multiply(quantity).setScale(2, RoundingMode.HALF_UP)
    val lineGross = lineNet.add(lineNet.multiply(vatRate).divide(BigDecimal(100)))
        .setScale(2, RoundingMode.HALF_UP)
    return OrderPositionDto(
        articleId, sku, description, quantity, unit, unitPriceNet, vatRate, lineNet, lineGross
    )
}

fun Order.toDto(customer: Customer? = null) = OrderDto(
    id = id!!,
    orderNumber = orderNumber,
    customerId = customerId,
    customerNumber = customer?.customerNumber,
    customerName = customer?.name,
    status = status,
    priority = priority,
    positions = positions.map { it.toDto() },
    totals = computeTotals(positions),
    deliveryDateTarget = deliveryDateTarget,
    deliveryDateActual = deliveryDateActual,
    notes = notes,
    createdAt = createdAt,
    confirmedAt = confirmedAt,
    completedAt = completedAt
)

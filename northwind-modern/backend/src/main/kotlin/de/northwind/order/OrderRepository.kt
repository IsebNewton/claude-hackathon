package de.northwind.order

import de.northwind.common.OrderStatus
import org.springframework.data.domain.Page
import org.springframework.data.domain.Pageable
import org.springframework.data.mongodb.repository.MongoRepository

interface OrderRepository : MongoRepository<Order, String> {
    fun existsByCustomerId(customerId: String): Boolean
    fun findByStatus(status: OrderStatus, pageable: Pageable): Page<Order>
    fun findByCustomerId(customerId: String, pageable: Pageable): Page<Order>
    fun findByStatusAndCustomerId(status: OrderStatus, customerId: String, pageable: Pageable): Page<Order>
}

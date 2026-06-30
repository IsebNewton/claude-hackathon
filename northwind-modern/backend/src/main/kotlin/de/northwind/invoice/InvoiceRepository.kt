package de.northwind.invoice

import de.northwind.common.InvoiceStatus
import org.springframework.data.domain.Page
import org.springframework.data.domain.Pageable
import org.springframework.data.mongodb.repository.MongoRepository

interface InvoiceRepository : MongoRepository<Invoice, String> {
    fun findByStatus(status: InvoiceStatus, pageable: Pageable): Page<Invoice>
    fun findByOrderIdAndStatusNot(orderId: String, status: InvoiceStatus): List<Invoice>
}

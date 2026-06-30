package de.northwind.invoice

import de.northwind.common.InvoiceStatus
import de.northwind.common.PageResponse
import jakarta.validation.Valid
import org.springframework.data.domain.PageRequest
import org.springframework.data.domain.Sort
import org.springframework.http.HttpStatus
import org.springframework.web.bind.annotation.GetMapping
import org.springframework.web.bind.annotation.PathVariable
import org.springframework.web.bind.annotation.PostMapping
import org.springframework.web.bind.annotation.RequestBody
import org.springframework.web.bind.annotation.RequestMapping
import org.springframework.web.bind.annotation.RequestParam
import org.springframework.web.bind.annotation.ResponseStatus
import org.springframework.web.bind.annotation.RestController

@RestController
@RequestMapping("/api/invoices")
class InvoiceController(private val service: InvoiceService) {

    @GetMapping
    fun list(
        @RequestParam(required = false) status: InvoiceStatus?,
        @RequestParam(defaultValue = "0") page: Int,
        @RequestParam(defaultValue = "25") size: Int
    ): PageResponse<InvoiceDto> {
        val pageable = PageRequest.of(page, size, Sort.by("dueDate").ascending())
        val result = service.list(status, pageable)
        return PageResponse(result.content, result.number, result.size, result.totalElements, result.totalPages)
    }

    @GetMapping("/{id}")
    fun get(@PathVariable id: String): InvoiceDto = service.getDto(id)

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    fun create(@Valid @RequestBody req: CreateInvoiceRequest): InvoiceDto =
        service.createFromOrder(req.orderId)

    @PostMapping("/{id}/payments")
    fun recordPayment(@PathVariable id: String, @Valid @RequestBody req: PaymentRequest): InvoiceDto =
        service.recordPayment(id, req)
}

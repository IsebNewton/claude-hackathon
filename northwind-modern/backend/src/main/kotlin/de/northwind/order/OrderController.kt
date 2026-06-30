package de.northwind.order

import de.northwind.common.OrderStatus
import jakarta.validation.Valid
import org.springframework.data.domain.PageRequest
import org.springframework.data.domain.Sort
import org.springframework.http.HttpStatus
import org.springframework.security.core.Authentication
import org.springframework.web.bind.annotation.GetMapping
import org.springframework.web.bind.annotation.PathVariable
import org.springframework.web.bind.annotation.PostMapping
import org.springframework.web.bind.annotation.RequestBody
import org.springframework.web.bind.annotation.RequestMapping
import org.springframework.web.bind.annotation.RequestParam
import org.springframework.web.bind.annotation.ResponseStatus
import org.springframework.web.bind.annotation.RestController
import de.northwind.common.PageResponse

@RestController
@RequestMapping("/api/orders")
class OrderController(private val service: OrderService) {

    @GetMapping
    fun list(
        @RequestParam(required = false) status: OrderStatus?,
        @RequestParam(required = false) customerId: String?,
        @RequestParam(defaultValue = "0") page: Int,
        @RequestParam(defaultValue = "25") size: Int
    ): PageResponse<OrderDto> {
        val pageable = PageRequest.of(page, size, Sort.by("createdAt").descending())
        val result = service.list(status, customerId, pageable)
        return PageResponse(result.content, result.number, result.size, result.totalElements, result.totalPages)
    }

    @GetMapping("/{id}")
    fun get(@PathVariable id: String): OrderDto = service.getDto(id)

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    fun create(@Valid @RequestBody req: OrderRequest, auth: Authentication?): OrderDto =
        service.create(req, auth?.name)

    @PostMapping("/{id}/confirm")
    fun confirm(@PathVariable id: String): OrderDto = service.confirm(id)

    @PostMapping("/{id}/cancel")
    fun cancel(@PathVariable id: String): OrderDto = service.cancel(id)
}

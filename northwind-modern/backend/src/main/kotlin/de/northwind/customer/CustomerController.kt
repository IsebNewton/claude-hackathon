package de.northwind.customer

import de.northwind.common.PageResponse
import de.northwind.common.toResponse
import jakarta.validation.Valid
import org.springframework.data.domain.PageRequest
import org.springframework.data.domain.Sort
import org.springframework.http.HttpStatus
import org.springframework.http.ResponseEntity
import org.springframework.web.bind.annotation.DeleteMapping
import org.springframework.web.bind.annotation.GetMapping
import org.springframework.web.bind.annotation.PathVariable
import org.springframework.web.bind.annotation.PostMapping
import org.springframework.web.bind.annotation.PutMapping
import org.springframework.web.bind.annotation.RequestBody
import org.springframework.web.bind.annotation.RequestMapping
import org.springframework.web.bind.annotation.RequestParam
import org.springframework.web.bind.annotation.ResponseStatus
import org.springframework.web.bind.annotation.RestController

@RestController
@RequestMapping("/api/customers")
class CustomerController(private val service: CustomerService) {

    @GetMapping
    fun list(
        @RequestParam(required = false) search: String?,
        @RequestParam(defaultValue = "0") page: Int,
        @RequestParam(defaultValue = "25") size: Int
    ): PageResponse<CustomerDto> {
        val pageable = PageRequest.of(page, size, Sort.by("name").ascending())
        return service.list(search, pageable).toResponse { it.toDto() }
    }

    @GetMapping("/{id}")
    fun get(@PathVariable id: String): CustomerDto = service.get(id).toDto()

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    fun create(@Valid @RequestBody req: CustomerRequest): CustomerDto = service.create(req).toDto()

    @PutMapping("/{id}")
    fun update(@PathVariable id: String, @Valid @RequestBody req: CustomerRequest): CustomerDto =
        service.update(id, req).toDto()

    @DeleteMapping("/{id}")
    fun delete(@PathVariable id: String): ResponseEntity<Void> {
        service.delete(id)
        return ResponseEntity.noContent().build()
    }
}

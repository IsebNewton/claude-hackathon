package de.northwind.common

import org.springframework.http.HttpStatus
import org.springframework.http.ResponseEntity
import org.springframework.web.bind.MethodArgumentNotValidException
import org.springframework.web.bind.annotation.ExceptionHandler
import org.springframework.web.bind.annotation.RestControllerAdvice
import java.time.Instant

/** 404 — entity not found. */
class NotFoundException(message: String) : RuntimeException(message)

/** 400/422 — business rule violation (e.g. invalid IBAN, order not cancellable). */
class BusinessException(message: String) : RuntimeException(message)

data class ApiError(
    val timestamp: Instant,
    val status: Int,
    val error: String,
    val message: String,
    val fieldErrors: Map<String, String>? = null
)

@RestControllerAdvice
class GlobalExceptionHandler {

    @ExceptionHandler(NotFoundException::class)
    fun handleNotFound(ex: NotFoundException) =
        build(HttpStatus.NOT_FOUND, ex.message ?: "Not found")

    @ExceptionHandler(BusinessException::class)
    fun handleBusiness(ex: BusinessException) =
        build(HttpStatus.UNPROCESSABLE_ENTITY, ex.message ?: "Business rule violation")

    @ExceptionHandler(MethodArgumentNotValidException::class)
    fun handleValidation(ex: MethodArgumentNotValidException): ResponseEntity<ApiError> {
        val fields = ex.bindingResult.fieldErrors.associate { it.field to (it.defaultMessage ?: "invalid") }
        return ResponseEntity.status(HttpStatus.BAD_REQUEST).body(
            ApiError(Instant.now(), HttpStatus.BAD_REQUEST.value(), "Bad Request", "Validation failed", fields)
        )
    }

    private fun build(status: HttpStatus, message: String): ResponseEntity<ApiError> =
        ResponseEntity.status(status).body(
            ApiError(Instant.now(), status.value(), status.reasonPhrase, message)
        )
}

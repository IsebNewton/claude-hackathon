package de.northwind.customer

import de.northwind.common.CustomerType
import jakarta.validation.constraints.NotBlank
import jakarta.validation.constraints.Pattern
import java.time.Instant

/** Request body for create/update. Clean API contract — no legacy DB field names. */
data class CustomerRequest(
    val type: CustomerType = CustomerType.PRIVAT,
    @field:NotBlank(message = "Name ist erforderlich") val name: String,
    val company: String? = null,
    @field:NotBlank(message = "Straße ist erforderlich") val street: String,
    val houseNumber: String? = null,
    @field:Pattern(regexp = "\\d{5}", message = "PLZ muss 5-stellig sein") val postalCode: String,
    @field:NotBlank(message = "Ort ist erforderlich") val city: String,
    val country: String = "DEU",
    val email: String? = null,
    val phone: String? = null,
    val iban: String? = null,
    val bic: String? = null
)

data class BankDetailsDto(
    val iban: String?,
    val bic: String?,
    val validated: Boolean?,
    val validatedAt: Instant?
)

data class CustomerDto(
    val id: String,
    val customerNumber: String,
    val type: CustomerType,
    val name: String,
    val company: String?,
    val street: String,
    val houseNumber: String?,
    val postalCode: String,
    val city: String,
    val country: String,
    val email: String?,
    val phone: String?,
    val bank: BankDetailsDto?,
    val active: Boolean,
    val createdAt: Instant,
    val updatedAt: Instant?
)

fun Customer.toDto() = CustomerDto(
    id = id!!,
    customerNumber = customerNumber,
    type = type,
    name = name,
    company = company,
    street = street,
    houseNumber = houseNumber,
    postalCode = postalCode,
    city = city,
    country = country,
    email = email,
    phone = phone,
    bank = bank?.let { BankDetailsDto(it.iban, it.bic, it.validated, it.validatedAt) },
    active = active,
    createdAt = createdAt,
    updatedAt = updatedAt
)

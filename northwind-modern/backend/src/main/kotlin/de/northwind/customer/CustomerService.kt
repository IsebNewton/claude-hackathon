package de.northwind.customer

import de.northwind.common.BankValidationService
import de.northwind.common.BusinessException
import de.northwind.common.NotFoundException
import de.northwind.common.SequenceService
import de.northwind.order.OrderRepository
import org.springframework.data.domain.Page
import org.springframework.data.domain.Pageable
import org.springframework.stereotype.Service
import java.time.Instant

@Service
class CustomerService(
    private val repo: CustomerRepository,
    private val orders: OrderRepository,
    private val sequences: SequenceService,
    private val bankValidation: BankValidationService
) {

    fun list(search: String?, pageable: Pageable): Page<Customer> =
        if (search.isNullOrBlank()) repo.findByActiveTrue(pageable)
        else repo.search(search.trim(), pageable)

    fun get(id: String): Customer =
        repo.findById(id).orElseThrow { NotFoundException("Kunde $id nicht gefunden") }

    fun create(req: CustomerRequest): Customer {
        val customer = Customer(
            customerNumber = sequences.nextCustomerNumber(),
            type = req.type,
            name = req.name,
            company = req.company,
            street = req.street,
            houseNumber = req.houseNumber,
            postalCode = req.postalCode,
            city = req.city,
            country = req.country,
            email = req.email,
            phone = req.phone,
            bank = buildBank(req)
        )
        return repo.save(customer)
    }

    fun update(id: String, req: CustomerRequest): Customer {
        val existing = get(id)
        val updated = existing.copy(
            type = req.type,
            name = req.name,
            company = req.company,
            street = req.street,
            houseNumber = req.houseNumber,
            postalCode = req.postalCode,
            city = req.city,
            country = req.country,
            email = req.email,
            phone = req.phone,
            bank = buildBank(req),
            updatedAt = Instant.now()
        )
        return repo.save(updated)
    }

    /**
     * Mirrors the legacy `loescheKunde()` hard/soft split, made explicit:
     * soft-delete (active=false) when orders reference the customer, hard-delete otherwise.
     */
    fun delete(id: String) {
        val customer = get(id)
        if (orders.existsByCustomerId(id)) {
            repo.save(customer.copy(active = false, updatedAt = Instant.now()))
        } else {
            repo.deleteById(id)
        }
    }

    /** Validate IBAN/BIC via the single BankValidationService; reject an invalid IBAN. */
    private fun buildBank(req: CustomerRequest): BankDetails? {
        if (req.iban.isNullOrBlank() && req.bic.isNullOrBlank()) return null
        val result = bankValidation.validate(req.iban, req.bic)
        if (!req.iban.isNullOrBlank() && !result.ibanValid) {
            throw BusinessException(result.message ?: "Bankverbindung ungültig")
        }
        if (!req.bic.isNullOrBlank() && !result.bicValid) {
            throw BusinessException("BIC ungültig")
        }
        return BankDetails(
            iban = req.iban?.replace(" ", "")?.uppercase(),
            bic = req.bic?.replace(" ", "")?.uppercase(),
            validated = result.valid,
            validatedAt = Instant.now()
        )
    }
}

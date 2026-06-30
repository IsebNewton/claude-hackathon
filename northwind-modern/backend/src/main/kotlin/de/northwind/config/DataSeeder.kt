package de.northwind.config

import de.northwind.article.Article
import de.northwind.article.ArticleRepository
import de.northwind.auth.User
import de.northwind.auth.UserRepository
import de.northwind.common.CustomerType
import de.northwind.common.OrderStatus
import de.northwind.common.Role
import de.northwind.common.SequenceService
import de.northwind.customer.BankDetails
import de.northwind.customer.Customer
import de.northwind.customer.CustomerRepository
import de.northwind.order.Order
import de.northwind.order.OrderPosition
import de.northwind.order.OrderRepository
import org.iban4j.CountryCode
import org.iban4j.Iban
import org.slf4j.LoggerFactory
import org.springframework.boot.CommandLineRunner
import org.springframework.security.crypto.password.PasswordEncoder
import org.springframework.stereotype.Component
import java.math.BigDecimal
import java.time.Instant

/**
 * Idempotent seed data — runs only for empty collections. Replaces the legacy
 * schema.sql seed (admin/buchhaltung users) plus sample customers/articles/orders
 * so the demo has something to show on first boot.
 */
@Component
class DataSeeder(
    private val users: UserRepository,
    private val articles: ArticleRepository,
    private val customers: CustomerRepository,
    private val orders: OrderRepository,
    private val sequences: SequenceService,
    private val encoder: PasswordEncoder
) : CommandLineRunner {

    private val log = LoggerFactory.getLogger(javaClass)

    override fun run(vararg args: String?) {
        seedUsers()
        val seededArticles = seedArticles()
        val seededCustomers = seedCustomers()
        seedOrders(seededCustomers, seededArticles)
    }

    private fun seedUsers() {
        if (users.count() > 0) return
        users.saveAll(
            listOf(
                User(
                    username = "admin",
                    passwordHash = encoder.encode("admin123"),
                    email = "admin@northwind-logistics.de",
                    name = "Administrator",
                    role = Role.ADMIN
                ),
                User(
                    username = "buchhaltung",
                    passwordHash = encoder.encode("buch2009"),
                    email = "buchhaltung@northwind-logistics.de",
                    name = "Buchhaltung",
                    role = Role.BUCHHALTUNG
                )
            )
        )
        log.info("Seeded users: admin / admin123, buchhaltung / buch2009")
    }

    private fun seedArticles(): List<Article> {
        if (articles.count() > 0) return articles.findAll()
        val list = listOf(
            Article(sku = "PAL-001", name = "Europalette", description = "Holzpalette 120x80", priceNet = BigDecimal("14.90"), vatRate = BigDecimal("19.00"), stock = 500),
            Article(sku = "KAR-010", name = "Faltkarton M", description = "Karton 400x300x300", priceNet = BigDecimal("1.20"), vatRate = BigDecimal("19.00"), stock = 2000),
            Article(sku = "KAR-020", name = "Faltkarton L", description = "Karton 600x400x400", priceNet = BigDecimal("1.95"), vatRate = BigDecimal("19.00"), stock = 1500),
            Article(sku = "FOL-100", name = "Stretchfolie", description = "Palettenfolie 500mm", priceNet = BigDecimal("6.50"), vatRate = BigDecimal("19.00"), stock = 300),
            Article(sku = "TAP-005", name = "Paketband", description = "PP-Klebeband 50mm", priceNet = BigDecimal("2.10"), vatRate = BigDecimal("19.00"), stock = 800),
            Article(sku = "LIT-001", name = "Transport-Versicherung", description = "Servicepauschale", priceNet = BigDecimal("25.00"), vatRate = BigDecimal("19.00"), stock = 9999)
        )
        return articles.saveAll(list)
    }

    private fun seedCustomers(): List<Customer> {
        if (customers.count() > 0) return customers.findAll()
        val seeds = listOf(
            Triple("Müller Handels GmbH", "37040044" to "0532013000", CustomerType.GESCHAEFT),
            Triple("Schmidt Logistik AG", "50010517" to "0648489890", CustomerType.GESCHAEFT),
            Triple("Anna Becker", "10000000" to "0123456789", CustomerType.PRIVAT),
            Triple("Weber & Söhne KG", "20070024" to "0987654321", CustomerType.GESCHAEFT),
            Triple("Thomas Klein", "60050101" to "0001234567", CustomerType.PRIVAT)
        )
        val list = seeds.mapIndexed { i, (name, bank, type) ->
            val iban = Iban.Builder()
                .countryCode(CountryCode.DE)
                .bankCode(bank.first)
                .accountNumber(bank.second)
                .build()
                .toString()
            Customer(
                customerNumber = sequences.nextCustomerNumber(),
                type = type,
                name = name,
                company = if (type == CustomerType.GESCHAEFT) name else null,
                street = "Logistikstraße",
                houseNumber = "${i + 1}",
                postalCode = "1234${i}",
                city = "Hamburg",
                email = "kontakt${i}@example.de",
                bank = BankDetails(iban = iban, validated = true, validatedAt = Instant.now())
            )
        }
        log.info("Seeded ${list.size} customers with valid German IBANs")
        return customers.saveAll(list)
    }

    private fun seedOrders(seedCustomers: List<Customer>, seedArticles: List<Article>) {
        if (orders.count() > 0 || seedCustomers.isEmpty() || seedArticles.size < 2) return
        val customer = seedCustomers.first()
        val a1 = seedArticles[0]
        val a2 = seedArticles[1]
        val order = Order(
            orderNumber = sequences.nextOrderNumber(),
            customerId = customer.id!!,
            status = OrderStatus.NEU,
            positions = listOf(
                OrderPosition(articleId = a1.id, sku = a1.sku, description = a1.name, quantity = BigDecimal("10"), unitPriceNet = a1.priceNet, vatRate = a1.vatRate),
                OrderPosition(articleId = a2.id, sku = a2.sku, description = a2.name, quantity = BigDecimal("50"), unitPriceNet = a2.priceNet, vatRate = a2.vatRate)
            ),
            createdBy = "seed"
        )
        orders.save(order)
        log.info("Seeded 1 sample order (${order.orderNumber}) for ${customer.name}")
    }
}

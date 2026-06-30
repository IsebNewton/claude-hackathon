package de.northwind.article

import org.springframework.data.annotation.Id
import org.springframework.data.mongodb.core.index.Indexed
import org.springframework.data.mongodb.core.mapping.Document
import java.math.BigDecimal

/** Replaces the legacy `artikel` table. Supporting entity (seeded; read-only UI). */
@Document(collection = "articles")
data class Article(
    @Id val id: String? = null,
    @Indexed(unique = true) val sku: String,
    val name: String,
    val description: String? = null,
    val priceNet: BigDecimal,
    val vatRate: BigDecimal = BigDecimal("19.00"),
    val stock: Int = 0,
    val active: Boolean = true
)

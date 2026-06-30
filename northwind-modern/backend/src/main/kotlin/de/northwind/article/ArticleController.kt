package de.northwind.article

import de.northwind.common.NotFoundException
import org.springframework.web.bind.annotation.GetMapping
import org.springframework.web.bind.annotation.PathVariable
import org.springframework.web.bind.annotation.RequestMapping
import org.springframework.web.bind.annotation.RestController
import java.math.BigDecimal

data class ArticleDto(
    val id: String,
    val sku: String,
    val name: String,
    val description: String?,
    val priceNet: BigDecimal,
    val vatRate: BigDecimal,
    val stock: Int
)

fun Article.toDto() = ArticleDto(id!!, sku, name, description, priceNet, vatRate, stock)

@RestController
@RequestMapping("/api/articles")
class ArticleController(private val repo: ArticleRepository) {

    @GetMapping
    fun list(): List<ArticleDto> = repo.findByActiveTrueOrderByName().map { it.toDto() }

    @GetMapping("/{id}")
    fun get(@PathVariable id: String): ArticleDto =
        repo.findById(id).orElseThrow { NotFoundException("Artikel $id nicht gefunden") }.toDto()
}

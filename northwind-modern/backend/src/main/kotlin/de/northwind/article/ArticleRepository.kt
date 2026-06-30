package de.northwind.article

import org.springframework.data.mongodb.repository.MongoRepository

interface ArticleRepository : MongoRepository<Article, String> {
    fun findByActiveTrueOrderByName(): List<Article>
}

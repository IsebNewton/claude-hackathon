package de.northwind.customer

import org.springframework.data.domain.Page
import org.springframework.data.domain.Pageable
import org.springframework.data.mongodb.repository.MongoRepository
import org.springframework.data.mongodb.repository.Query

interface CustomerRepository : MongoRepository<Customer, String> {

    fun findByActiveTrue(pageable: Pageable): Page<Customer>

    @Query(
        """
        { 'active': true, '${'$'}or': [
            { 'name': { '${'$'}regex': ?0, '${'$'}options': 'i' } },
            { 'company': { '${'$'}regex': ?0, '${'$'}options': 'i' } },
            { 'customerNumber': { '${'$'}regex': ?0, '${'$'}options': 'i' } },
            { 'email': { '${'$'}regex': ?0, '${'$'}options': 'i' } },
            { 'city': { '${'$'}regex': ?0, '${'$'}options': 'i' } }
        ] }
        """
    )
    fun search(term: String, pageable: Pageable): Page<Customer>
}

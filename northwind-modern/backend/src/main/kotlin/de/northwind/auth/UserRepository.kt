package de.northwind.auth

import org.springframework.data.mongodb.repository.MongoRepository

interface UserRepository : MongoRepository<User, String> {
    fun findByUsernameAndActiveTrue(username: String): User?
}

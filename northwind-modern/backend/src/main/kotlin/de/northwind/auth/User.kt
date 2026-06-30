package de.northwind.auth

import de.northwind.common.Role
import org.springframework.data.annotation.Id
import org.springframework.data.mongodb.core.index.Indexed
import org.springframework.data.mongodb.core.mapping.Document

@Document(collection = "users")
data class User(
    @Id val id: String? = null,
    @Indexed(unique = true) val username: String,
    val passwordHash: String,
    val email: String? = null,
    val name: String? = null,
    val role: Role = Role.MITARBEITER,
    val active: Boolean = true
)

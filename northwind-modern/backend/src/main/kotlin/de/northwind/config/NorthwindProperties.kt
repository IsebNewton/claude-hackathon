package de.northwind.config

import org.springframework.boot.context.properties.ConfigurationProperties

@ConfigurationProperties(prefix = "northwind")
data class NorthwindProperties(
    val jwt: Jwt = Jwt(),
    val cors: Cors = Cors()
) {
    data class Jwt(
        val secret: String = "change-me-in-production-please-32bytes-minimum-secret",
        val ttlMinutes: Long = 480
    )

    data class Cors(
        val allowedOrigin: String = "http://localhost:3000"
    )
}

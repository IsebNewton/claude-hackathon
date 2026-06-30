package de.northwind.auth

import de.northwind.config.NorthwindProperties
import io.jsonwebtoken.Jwts
import io.jsonwebtoken.security.Keys
import org.springframework.stereotype.Service
import java.util.Date
import javax.crypto.SecretKey

@Service
class JwtService(props: NorthwindProperties) {

    private val key: SecretKey = Keys.hmacShaKeyFor(props.jwt.secret.toByteArray())
    private val ttlMillis: Long = props.jwt.ttlMinutes * 60_000

    fun generate(username: String, role: String): String {
        val now = System.currentTimeMillis()
        return Jwts.builder()
            .subject(username)
            .claim("role", role)
            .issuedAt(Date(now))
            .expiration(Date(now + ttlMillis))
            .signWith(key)
            .compact()
    }

    /** Returns the username (subject) and role if the token is valid, else null. */
    fun parse(token: String): Pair<String, String>? {
        return try {
            val claims = Jwts.parser().verifyWith(key).build().parseSignedClaims(token).payload
            val username = claims.subject ?: return null
            val role = claims["role", String::class.java] ?: "MITARBEITER"
            username to role
        } catch (e: Exception) {
            null
        }
    }
}

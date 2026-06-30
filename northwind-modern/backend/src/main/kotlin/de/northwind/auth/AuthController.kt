package de.northwind.auth

import jakarta.validation.constraints.NotBlank
import org.springframework.http.HttpStatus
import org.springframework.http.ResponseEntity
import org.springframework.security.crypto.password.PasswordEncoder
import org.springframework.web.bind.annotation.PostMapping
import org.springframework.web.bind.annotation.RequestBody
import org.springframework.web.bind.annotation.RequestMapping
import org.springframework.web.bind.annotation.RestController

data class LoginRequest(
    @field:NotBlank val username: String,
    @field:NotBlank val password: String
)

data class LoginResponse(
    val token: String,
    val username: String,
    val name: String?,
    val role: String
)

@RestController
@RequestMapping("/api/auth")
class AuthController(
    private val users: UserRepository,
    private val passwordEncoder: PasswordEncoder,
    private val jwtService: JwtService
) {

    @PostMapping("/login")
    fun login(@RequestBody req: LoginRequest): ResponseEntity<Any> {
        val user = users.findByUsernameAndActiveTrue(req.username)
        if (user == null || !passwordEncoder.matches(req.password, user.passwordHash)) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
                .body(mapOf("message" to "Benutzername oder Passwort falsch"))
        }
        val token = jwtService.generate(user.username, user.role.name)
        return ResponseEntity.ok(LoginResponse(token, user.username, user.name, user.role.name))
    }
}

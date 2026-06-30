package de.northwind

import org.springframework.boot.autoconfigure.SpringBootApplication
import org.springframework.boot.context.properties.ConfigurationPropertiesScan
import org.springframework.boot.runApplication

@SpringBootApplication
@ConfigurationPropertiesScan
class NorthwindApplication

fun main(args: Array<String>) {
    runApplication<NorthwindApplication>(*args)
}

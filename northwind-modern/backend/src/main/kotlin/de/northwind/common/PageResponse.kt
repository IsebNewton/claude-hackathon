package de.northwind.common

import org.springframework.data.domain.Page

/** Compact pagination envelope for the frontend (avoids Spring's verbose Page JSON). */
data class PageResponse<T>(
    val content: List<T>,
    val page: Int,
    val size: Int,
    val totalElements: Long,
    val totalPages: Int
)

fun <T, R> Page<T>.toResponse(map: (T) -> R) = PageResponse(
    content = content.map(map),
    page = number,
    size = size,
    totalElements = totalElements,
    totalPages = totalPages
)

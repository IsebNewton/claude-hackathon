package de.northwind.common

import org.springframework.data.mongodb.core.FindAndModifyOptions
import org.springframework.data.mongodb.core.MongoOperations
import org.springframework.data.mongodb.core.query.Criteria
import org.springframework.data.mongodb.core.query.Query
import org.springframework.data.mongodb.core.query.Update
import org.springframework.stereotype.Service
import java.time.Year

/**
 * Counter document backing the `counters` collection.
 * `id` is the sequence key (e.g. "invoice-2026"); `value` is the last issued number.
 */
data class Counter(
    val id: String,
    val value: Long
)

/**
 * Replaces the legacy `rechnungs_sequence` table + the `tr_rechnung_nummer` trigger
 * AND the competing `getNextRechnungsNr()` helper. A single atomic `$inc` per key
 * removes the race condition and the 2023 deadlock class entirely.
 */
@Service
class SequenceService(private val mongo: MongoOperations) {

    /** Atomically increment and return the next value for [key]. */
    fun next(key: String): Long {
        val result = mongo.findAndModify(
            Query(Criteria.where("_id").`is`(key)),
            Update().inc("value", 1L),
            FindAndModifyOptions().returnNew(true).upsert(true),
            Counter::class.java
        )
        return result?.value ?: 1L
    }

    /** Formatted document numbers, e.g. RE-2026-00001 / AUF-2026-00042 / KD-2026-00007. */
    fun nextInvoiceNumber(year: Int = Year.now().value): String = formatted("RE", "invoice", year)
    fun nextOrderNumber(year: Int = Year.now().value): String = formatted("AUF", "order", year)
    fun nextCustomerNumber(year: Int = Year.now().value): String = formatted("KD", "customer", year)

    private fun formatted(prefix: String, key: String, year: Int): String {
        val seq = next("$key-$year")
        return "%s-%d-%05d".format(prefix, year, seq)
    }
}

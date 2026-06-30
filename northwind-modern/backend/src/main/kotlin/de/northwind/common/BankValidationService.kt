package de.northwind.common

import org.iban4j.BicUtil
import org.iban4j.IbanUtil
import org.springframework.stereotype.Service

data class BankValidationResult(
    val valid: Boolean,
    val ibanValid: Boolean,
    val bicValid: Boolean,
    val message: String? = null
)

/**
 * Lightweight SEPA bank validation via iban4j (IBAN checksum + BIC format).
 *
 * This is the SINGLE validation entry point for the whole application. The legacy
 * monolith configured the BAV `ConfigurationRegistry` singleton in three competing
 * places (init.php, BankValidator ctor, Northwind::verarbeiteZahlung) — "last one
 * wins" caused the January-2020 incident. Centralising here removes that bug by
 * construction. We intentionally do NOT reimplement the 157 Bundesbank BLZ algorithms.
 */
@Service
class BankValidationService {

    /** Validate an IBAN (required) and an optional BIC. Never throws. */
    fun validate(iban: String?, bic: String?): BankValidationResult {
        val ibanValid = iban?.isNotBlank() == true && isIbanValid(iban)
        val bicProvided = bic?.isNotBlank() == true
        val bicValid = !bicProvided || isBicValid(bic!!)

        val valid = ibanValid && bicValid
        val message = when {
            valid -> null
            !ibanValid -> "IBAN ungültig"
            else -> "BIC ungültig"
        }
        return BankValidationResult(valid, ibanValid, bicValid, message)
    }

    fun isIbanValid(iban: String): Boolean = try {
        IbanUtil.validate(iban.replace(" ", "").uppercase())
        true
    } catch (e: RuntimeException) {
        false
    }

    fun isBicValid(bic: String): Boolean = try {
        BicUtil.validate(bic.replace(" ", "").uppercase())
        true
    } catch (e: RuntimeException) {
        false
    }
}

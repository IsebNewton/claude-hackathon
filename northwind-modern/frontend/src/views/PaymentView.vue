<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api, apiError } from '@/api/client'
import type { Invoice, PaymentRequest } from '@/api/types'
import { euro } from '@/utils/format'

const props = defineProps<{ id: string }>()
const router = useRouter()

const invoice = ref<Invoice | null>(null)
const error = ref('')
const saving = ref(false)

const form = ref<PaymentRequest>({
  amount: 0,
  method: 'lastschrift',
  iban: '',
  bic: '',
  note: ''
})

async function load() {
  try {
    const { data } = await api.get<Invoice>(`/invoices/${props.id}`)
    invoice.value = data
    form.value.amount = data.openAmount
    // prefill bank from the most recent payment is not available; leave empty
  } catch (e) {
    error.value = apiError(e)
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    await api.post(`/invoices/${props.id}/payments`, form.value)
    router.push({ name: 'invoice-detail', params: { id: props.id } })
  } catch (e) {
    error.value = apiError(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <h2>Zahlung erfassen</h2>
  <div v-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-if="invoice" class="row">
    <div class="col-md-6">
      <div class="alert alert-info">
        Rechnung <strong>{{ invoice.invoiceNumber }}</strong><br />
        Brutto: {{ euro(invoice.grossAmount) }} · Offen: <strong>{{ euro(invoice.openAmount) }}</strong>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-2">
          <label class="form-label">Betrag *</label>
          <input v-model.number="form.amount" type="number" step="0.01" class="form-control" required />
        </div>
        <div class="mb-2">
          <label class="form-label">Zahlungsart</label>
          <select v-model="form.method" class="form-select">
            <option value="lastschrift">SEPA-Lastschrift</option>
            <option value="ueberweisung">Überweisung</option>
            <option value="bar">Bar</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">IBAN</label>
          <input v-model="form.iban" class="form-control" placeholder="DE…" />
          <div class="form-text">Wird per iban4j geprüft.</div>
        </div>
        <div class="mb-2">
          <label class="form-label">BIC</label>
          <input v-model="form.bic" class="form-control" />
        </div>
        <div class="mb-3">
          <label class="form-label">Bemerkung</label>
          <input v-model="form.note" class="form-control" />
        </div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Buchen…' : 'Zahlung buchen' }}</button>
        <RouterLink class="btn btn-link" :to="{ name: 'invoice-detail', params: { id: props.id } }">Abbrechen</RouterLink>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, apiError } from '@/api/client'
import type { Invoice } from '@/api/types'
import { euro, dateDe, dateTimeDe, invoiceStatusLabel, invoiceStatusClass } from '@/utils/format'

const props = defineProps<{ id: string }>()
const invoice = ref<Invoice | null>(null)
const error = ref('')

async function load() {
  try {
    const { data } = await api.get<Invoice>(`/invoices/${props.id}`)
    invoice.value = data
  } catch (e) {
    error.value = apiError(e)
  }
}

onMounted(load)
</script>

<template>
  <div v-if="error" class="alert alert-danger">{{ error }}</div>
  <div v-if="invoice">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Rechnung {{ invoice.invoiceNumber }}</h2>
      <span class="badge fs-6" :class="invoiceStatusClass(invoice.status)">{{ invoiceStatusLabel(invoice.status) }}</span>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <div class="card"><div class="card-body">
          <h5>Beträge</h5>
          <p class="mb-0">
            Netto: {{ euro(invoice.netAmount) }}<br />
            MwSt: {{ euro(invoice.vatAmount) }}<br />
            <strong>Brutto: {{ euro(invoice.grossAmount) }}</strong><br />
            Bezahlt: {{ euro(invoice.paidAmount) }}<br />
            Offen: {{ euro(invoice.openAmount) }}
          </p>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="card"><div class="card-body">
          <h5>Details</h5>
          <p class="mb-0">
            Kunde: {{ invoice.customerName }}<br />
            Fällig: {{ dateDe(invoice.dueDate) }}<br />
            <span v-if="invoice.paidAt">Bezahlt am: {{ dateTimeDe(invoice.paidAt) }}</span>
          </p>
          <RouterLink class="btn btn-sm btn-outline-secondary mt-2" :to="{ name: 'order-detail', params: { id: invoice.orderId } }">Zum Auftrag</RouterLink>
        </div></div>
      </div>
    </div>

    <div class="mb-3" v-if="invoice.status === 'OFFEN' || invoice.status === 'TEILBEZAHLT'">
      <RouterLink class="btn btn-primary" :to="{ name: 'invoice-payment', params: { id: invoice.id } }">Zahlung erfassen</RouterLink>
    </div>

    <h5>Zahlungen</h5>
    <table class="table table-sm">
      <thead><tr><th>Datum</th><th class="text-end">Betrag</th><th>Zahlungsart</th><th>IBAN</th><th>Bank geprüft</th><th>Bemerkung</th></tr></thead>
      <tbody>
        <tr v-for="(p, i) in invoice.payments" :key="i">
          <td>{{ dateTimeDe(p.date) }}</td>
          <td class="text-end">{{ euro(p.amount) }}</td>
          <td>{{ p.method }}</td>
          <td>{{ p.iban || '—' }}</td>
          <td>
            <span v-if="p.bankValidated === true" class="text-success">✓ Gültig</span>
            <span v-else-if="p.bankValidated === false" class="text-danger">✗ Ungültig</span>
            <span v-else class="text-muted">—</span>
          </td>
          <td>{{ p.note || '—' }}</td>
        </tr>
        <tr v-if="invoice.payments.length === 0"><td colspan="6" class="text-muted text-center">Noch keine Zahlungen</td></tr>
      </tbody>
    </table>

    <RouterLink class="btn btn-link" :to="{ name: 'invoices' }">← Zurück zur Liste</RouterLink>
  </div>
</template>

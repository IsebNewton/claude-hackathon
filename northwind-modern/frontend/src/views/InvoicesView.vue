<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, apiError } from '@/api/client'
import type { Invoice, InvoiceStatus, PageResponse } from '@/api/types'
import { euro, dateDe, invoiceStatusLabel, invoiceStatusClass } from '@/utils/format'

const invoices = ref<Invoice[]>([])
const status = ref<InvoiceStatus | ''>('')
const loading = ref(false)
const error = ref('')

const STATUSES: InvoiceStatus[] = ['OFFEN', 'TEILBEZAHLT', 'BEZAHLT', 'STORNIERT', 'GUTSCHRIFT']

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<PageResponse<Invoice>>('/invoices', {
      params: { status: status.value || undefined, size: 50 }
    })
    invoices.value = data.content
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <h2 class="mb-3">Rechnungen</h2>

  <form class="row g-2 mb-3">
    <div class="col-auto">
      <select v-model="status" class="form-select" @change="load">
        <option value="">Alle Status</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ invoiceStatusLabel(s) }}</option>
      </select>
    </div>
  </form>

  <div v-if="error" class="alert alert-danger">{{ error }}</div>

  <table class="table table-striped table-hover">
    <thead><tr><th>Rechnungs-Nr</th><th>Kunde</th><th class="text-end">Brutto</th><th class="text-end">Offen</th><th>Fällig</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <tr v-for="inv in invoices" :key="inv.id">
        <td><RouterLink :to="{ name: 'invoice-detail', params: { id: inv.id } }">{{ inv.invoiceNumber }}</RouterLink></td>
        <td>{{ inv.customerName }}</td>
        <td class="text-end">{{ euro(inv.grossAmount) }}</td>
        <td class="text-end">{{ euro(inv.openAmount) }}</td>
        <td>{{ dateDe(inv.dueDate) }}</td>
        <td><span class="badge" :class="invoiceStatusClass(inv.status)">{{ invoiceStatusLabel(inv.status) }}</span></td>
        <td class="text-end">
          <RouterLink v-if="inv.status === 'OFFEN' || inv.status === 'TEILBEZAHLT'"
            class="btn btn-sm btn-outline-primary" :to="{ name: 'invoice-payment', params: { id: inv.id } }">Zahlung erfassen</RouterLink>
        </td>
      </tr>
      <tr v-if="!loading && invoices.length === 0"><td colspan="7" class="text-center text-muted py-4">Keine Rechnungen gefunden</td></tr>
    </tbody>
  </table>
</template>

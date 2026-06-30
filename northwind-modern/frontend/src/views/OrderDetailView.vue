<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api, apiError } from '@/api/client'
import type { Order } from '@/api/types'
import { euro, dateDe, dateTimeDe, orderStatusLabel, orderStatusClass } from '@/utils/format'

const props = defineProps<{ id: string }>()
const router = useRouter()
const order = ref<Order | null>(null)
const error = ref('')
const info = ref('')

async function load() {
  try {
    const { data } = await api.get<Order>(`/orders/${props.id}`)
    order.value = data
  } catch (e) {
    error.value = apiError(e)
  }
}

async function act(action: 'confirm' | 'cancel') {
  if (action === 'cancel' && !confirm('Auftrag stornieren?')) return
  error.value = ''
  try {
    await api.post(`/orders/${props.id}/${action}`)
    await load()
  } catch (e) {
    error.value = apiError(e)
  }
}

async function createInvoice() {
  error.value = ''
  try {
    const { data } = await api.post('/invoices', { orderId: props.id })
    router.push({ name: 'invoice-detail', params: { id: data.id } })
  } catch (e) {
    error.value = apiError(e)
  }
}

onMounted(load)
</script>

<template>
  <div v-if="error" class="alert alert-danger">{{ error }}</div>
  <div v-if="order">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Auftrag {{ order.orderNumber }}</h2>
      <span class="badge fs-6" :class="orderStatusClass(order.status)">{{ orderStatusLabel(order.status) }}</span>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <div class="card"><div class="card-body">
          <h5>Kunde</h5>
          <p class="mb-0">{{ order.customerName }} ({{ order.customerNumber }})</p>
        </div></div>
      </div>
      <div class="col-md-6">
        <div class="card"><div class="card-body">
          <h5>Eckdaten</h5>
          <p class="mb-0">
            Priorität: {{ order.priority }}<br />
            Lieferdatum (soll): {{ dateDe(order.deliveryDateTarget) }}<br />
            Angelegt: {{ dateTimeDe(order.createdAt) }}<br />
            <span v-if="order.completedAt">Abgeschlossen: {{ dateTimeDe(order.completedAt) }}</span>
          </p>
        </div></div>
      </div>
    </div>

    <div class="mb-3">
      <button v-if="order.status === 'NEU'" class="btn btn-info me-2" @click="act('confirm')">Bestätigen</button>
      <button v-if="order.status !== 'ABGESCHLOSSEN' && order.status !== 'STORNIERT'" class="btn btn-outline-danger me-2" @click="act('cancel')">Stornieren</button>
      <button v-if="order.status !== 'STORNIERT'" class="btn btn-success" @click="createInvoice">Rechnung erstellen</button>
    </div>

    <table class="table">
      <thead><tr><th>Bezeichnung</th><th class="text-end">Menge</th><th class="text-end">Einzelpreis</th><th class="text-end">Netto</th><th class="text-end">MwSt %</th><th class="text-end">Brutto</th></tr></thead>
      <tbody>
        <tr v-for="(p, i) in order.positions" :key="i">
          <td>{{ p.description }}<span v-if="p.sku" class="text-muted small"> · {{ p.sku }}</span></td>
          <td class="text-end">{{ p.quantity }}</td>
          <td class="text-end">{{ euro(p.unitPriceNet) }}</td>
          <td class="text-end">{{ euro(p.lineNet) }}</td>
          <td class="text-end">{{ p.vatRate }}</td>
          <td class="text-end">{{ euro(p.lineGross) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr><th colspan="5" class="text-end">Netto</th><th class="text-end">{{ euro(order.totals.net) }}</th></tr>
        <tr><th colspan="5" class="text-end">MwSt</th><th class="text-end">{{ euro(order.totals.vat) }}</th></tr>
        <tr><th colspan="5" class="text-end">Brutto</th><th class="text-end">{{ euro(order.totals.gross) }}</th></tr>
      </tfoot>
    </table>

    <RouterLink class="btn btn-link" :to="{ name: 'orders' }">← Zurück zur Liste</RouterLink>
  </div>
</template>

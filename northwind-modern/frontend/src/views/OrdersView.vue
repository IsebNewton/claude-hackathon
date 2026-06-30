<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, apiError } from '@/api/client'
import type { Order, OrderStatus, PageResponse } from '@/api/types'
import { euro, dateDe, orderStatusLabel, orderStatusClass } from '@/utils/format'

const orders = ref<Order[]>([])
const status = ref<OrderStatus | ''>('')
const loading = ref(false)
const error = ref('')

const STATUSES: OrderStatus[] = ['NEU', 'BESTAETIGT', 'IN_BEARBEITUNG', 'VERSENDET', 'ABGESCHLOSSEN', 'STORNIERT']

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<PageResponse<Order>>('/orders', {
      params: { status: status.value || undefined, size: 50 }
    })
    orders.value = data.content
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}

async function act(o: Order, action: 'confirm' | 'cancel') {
  if (action === 'cancel' && !confirm(`Auftrag ${o.orderNumber} stornieren?`)) return
  try {
    await api.post(`/orders/${o.id}/${action}`)
    await load()
  } catch (e) {
    error.value = apiError(e)
  }
}

onMounted(load)
</script>

<template>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Aufträge</h2>
    <RouterLink class="btn btn-success" :to="{ name: 'order-new' }">+ Neuer Auftrag</RouterLink>
  </div>

  <form class="row g-2 mb-3" @submit.prevent="load">
    <div class="col-auto">
      <select v-model="status" class="form-select" @change="load">
        <option value="">Alle Status</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ orderStatusLabel(s) }}</option>
      </select>
    </div>
  </form>

  <div v-if="error" class="alert alert-danger">{{ error }}</div>

  <table class="table table-striped table-hover">
    <thead>
      <tr><th>Auftrag-Nr</th><th>Kunde</th><th>Status</th><th class="text-end">Brutto</th><th>Lieferdatum</th><th></th></tr>
    </thead>
    <tbody>
      <tr v-for="o in orders" :key="o.id">
        <td><RouterLink :to="{ name: 'order-detail', params: { id: o.id } }">{{ o.orderNumber }}</RouterLink></td>
        <td>{{ o.customerName }}</td>
        <td><span class="badge" :class="orderStatusClass(o.status)">{{ orderStatusLabel(o.status) }}</span></td>
        <td class="text-end">{{ euro(o.totals.gross) }}</td>
        <td>{{ dateDe(o.deliveryDateTarget) }}</td>
        <td class="text-end">
          <button v-if="o.status === 'NEU'" class="btn btn-sm btn-outline-info" @click="act(o, 'confirm')">Bestätigen</button>
          <button v-if="o.status !== 'ABGESCHLOSSEN' && o.status !== 'STORNIERT'" class="btn btn-sm btn-outline-danger ms-1" @click="act(o, 'cancel')">Stornieren</button>
        </td>
      </tr>
      <tr v-if="!loading && orders.length === 0">
        <td colspan="6" class="text-center text-muted py-4">Keine Aufträge gefunden</td>
      </tr>
    </tbody>
  </table>
</template>

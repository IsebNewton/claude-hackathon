<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, apiError } from '@/api/client'
import type { Customer, PageResponse } from '@/api/types'

const customers = ref<Customer[]>([])
const search = ref('')
const loading = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<PageResponse<Customer>>('/customers', {
      params: { search: search.value || undefined, size: 50 }
    })
    customers.value = data.content
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}

async function remove(c: Customer) {
  if (!confirm(`Kunde "${c.name}" löschen?`)) return
  try {
    await api.delete(`/customers/${c.id}`)
    await load()
  } catch (e) {
    error.value = apiError(e)
  }
}

onMounted(load)
</script>

<template>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Kunden</h2>
    <RouterLink class="btn btn-success" :to="{ name: 'customer-new' }">+ Neuer Kunde</RouterLink>
  </div>

  <form class="row g-2 mb-3" @submit.prevent="load">
    <div class="col-auto">
      <input v-model="search" class="form-control" placeholder="Suche Name, Nummer, Ort…" />
    </div>
    <div class="col-auto">
      <button class="btn btn-outline-primary" type="submit">Suchen</button>
    </div>
  </form>

  <div v-if="error" class="alert alert-danger">{{ error }}</div>

  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th>Nummer</th><th>Name / Firma</th><th>Ort</th><th>E-Mail</th><th>Typ</th><th>IBAN gültig</th><th></th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="c in customers" :key="c.id">
        <td>{{ c.customerNumber }}</td>
        <td>{{ c.name }}<span v-if="c.company" class="text-muted"> · {{ c.company }}</span></td>
        <td>{{ c.postalCode }} {{ c.city }}</td>
        <td>{{ c.email || '—' }}</td>
        <td><span class="badge bg-secondary">{{ c.type === 'GESCHAEFT' ? 'Geschäft' : 'Privat' }}</span></td>
        <td>
          <span v-if="c.bank?.validated === true" class="text-success">✓</span>
          <span v-else-if="c.bank?.validated === false" class="text-danger">✗</span>
          <span v-else class="text-muted">—</span>
        </td>
        <td class="text-end">
          <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'customer-edit', params: { id: c.id } }">Bearbeiten</RouterLink>
          <button class="btn btn-sm btn-outline-danger ms-1" @click="remove(c)">Löschen</button>
        </td>
      </tr>
      <tr v-if="!loading && customers.length === 0">
        <td colspan="7" class="text-center text-muted py-4">Keine Kunden gefunden</td>
      </tr>
    </tbody>
  </table>
</template>

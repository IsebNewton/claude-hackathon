<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { api, apiError } from '@/api/client'
import type { Article, Customer, PageResponse, OrderRequest } from '@/api/types'
import { useOrderWizardStore } from '@/stores/orderWizard'
import { euro } from '@/utils/format'

const wizard = useOrderWizardStore()
const { step, customer, positions, priority, deliveryDateTarget, notes, net, vat, gross } = storeToRefs(wizard)
const router = useRouter()

const customerResults = ref<Customer[]>([])
const customerSearch = ref('')
const articles = ref<Article[]>([])
const selectedArticleId = ref('')
const quantity = ref(1)
const price = ref<number | null>(null)
const error = ref('')
const saving = ref(false)

onMounted(() => {
  wizard.reset()
  searchCustomers()
  api.get<Article[]>('/articles').then(({ data }) => (articles.value = data)).catch((e) => (error.value = apiError(e)))
})

async function searchCustomers() {
  try {
    const { data } = await api.get<PageResponse<Customer>>('/customers', {
      params: { search: customerSearch.value || undefined, size: 20 }
    })
    customerResults.value = data.content
  } catch (e) {
    error.value = apiError(e)
  }
}

function onArticleChange() {
  const a = articles.value.find((x) => x.id === selectedArticleId.value)
  price.value = a ? a.priceNet : null
}

function addPosition() {
  const a = articles.value.find((x) => x.id === selectedArticleId.value)
  if (!a || quantity.value <= 0) return
  wizard.addArticle(a, quantity.value, price.value ?? undefined)
  selectedArticleId.value = ''
  quantity.value = 1
  price.value = null
}

async function submit() {
  if (!customer.value || positions.value.length === 0) return
  saving.value = true
  error.value = ''
  try {
    const payload: OrderRequest = {
      customerId: customer.value.id,
      priority: priority.value,
      deliveryDateTarget: deliveryDateTarget.value || null,
      notes: notes.value || null,
      positions: positions.value.map((p) => ({
        articleId: p.articleId,
        quantity: p.quantity,
        unitPriceNet: p.unitPriceNet,
        vatRate: p.vatRate
      }))
    }
    const { data } = await api.post('/orders', payload)
    const id = data.id
    wizard.reset()
    router.push({ name: 'order-detail', params: { id } })
  } catch (e) {
    error.value = apiError(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <h2>Neuer Auftrag</h2>

  <ul class="nav nav-pills mb-4">
    <li class="nav-item"><span class="nav-link" :class="{ active: step === 1 }">1. Kunde</span></li>
    <li class="nav-item"><span class="nav-link" :class="{ active: step === 2 }">2. Positionen</span></li>
    <li class="nav-item"><span class="nav-link" :class="{ active: step === 3 }">3. Bestätigung</span></li>
  </ul>

  <div v-if="error" class="alert alert-danger">{{ error }}</div>

  <!-- Step 1: Customer -->
  <section v-if="step === 1">
    <form class="row g-2 mb-3" @submit.prevent="searchCustomers">
      <div class="col-auto"><input v-model="customerSearch" class="form-control" placeholder="Kunde suchen…" /></div>
      <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Suchen</button></div>
    </form>
    <table class="table table-hover">
      <thead><tr><th>Nummer</th><th>Name</th><th>Ort</th><th></th></tr></thead>
      <tbody>
        <tr v-for="c in customerResults" :key="c.id">
          <td>{{ c.customerNumber }}</td><td>{{ c.name }}</td><td>{{ c.city }}</td>
          <td class="text-end"><button class="btn btn-sm btn-primary" @click="wizard.selectCustomer(c)">Auswählen</button></td>
        </tr>
      </tbody>
    </table>
  </section>

  <!-- Step 2: Positions -->
  <section v-if="step === 2">
    <div class="alert alert-info">Kunde: <strong>{{ customer?.name }}</strong> ({{ customer?.customerNumber }})</div>
    <div class="row g-2 align-items-end mb-3">
      <div class="col-md-5">
        <label class="form-label">Artikel</label>
        <select v-model="selectedArticleId" class="form-select" @change="onArticleChange">
          <option value="">— wählen —</option>
          <option v-for="a in articles" :key="a.id" :value="a.id">{{ a.sku }} · {{ a.name }} ({{ euro(a.priceNet) }})</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Menge</label>
        <input v-model.number="quantity" type="number" min="1" class="form-control" />
      </div>
      <div class="col-md-3">
        <label class="form-label">Einzelpreis (netto)</label>
        <input v-model.number="price" type="number" step="0.01" class="form-control" />
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-success w-100" :disabled="!selectedArticleId" @click="addPosition">Hinzufügen</button>
      </div>
    </div>

    <table class="table table-sm">
      <thead><tr><th>Bezeichnung</th><th class="text-end">Menge</th><th class="text-end">Einzelpreis</th><th class="text-end">Summe netto</th><th></th></tr></thead>
      <tbody>
        <tr v-for="(p, i) in positions" :key="i">
          <td>{{ p.description }}</td>
          <td class="text-end">{{ p.quantity }}</td>
          <td class="text-end">{{ euro(p.unitPriceNet) }}</td>
          <td class="text-end">{{ euro(p.quantity * p.unitPriceNet) }}</td>
          <td class="text-end"><button class="btn btn-sm btn-outline-danger" @click="wizard.removePosition(i)">×</button></td>
        </tr>
        <tr v-if="positions.length === 0"><td colspan="5" class="text-muted text-center">Noch keine Positionen</td></tr>
      </tbody>
    </table>

    <div class="d-flex justify-content-between">
      <button class="btn btn-link" @click="step = 1">← Zurück</button>
      <button class="btn btn-primary" :disabled="positions.length === 0" @click="step = 3">Weiter zur Bestätigung →</button>
    </div>
  </section>

  <!-- Step 3: Confirm -->
  <section v-if="step === 3">
    <div class="row">
      <div class="col-md-6">
        <h5>Kunde</h5>
        <p>{{ customer?.name }}<br />{{ customer?.street }} {{ customer?.houseNumber }}<br />{{ customer?.postalCode }} {{ customer?.city }}</p>
      </div>
      <div class="col-md-6">
        <div class="mb-2">
          <label class="form-label">Priorität</label>
          <select v-model="priority" class="form-select">
            <option value="NORMAL">Normal</option><option value="HOCH">Hoch</option><option value="EXPRESS">Express</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Lieferdatum (soll)</label>
          <input v-model="deliveryDateTarget" type="date" class="form-control" />
        </div>
        <div class="mb-2">
          <label class="form-label">Bemerkungen</label>
          <textarea v-model="notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
    </div>

    <table class="table table-sm mt-3">
      <thead><tr><th>Bezeichnung</th><th class="text-end">Menge</th><th class="text-end">Einzelpreis</th><th class="text-end">Summe netto</th></tr></thead>
      <tbody>
        <tr v-for="(p, i) in positions" :key="i">
          <td>{{ p.description }}</td><td class="text-end">{{ p.quantity }}</td>
          <td class="text-end">{{ euro(p.unitPriceNet) }}</td><td class="text-end">{{ euro(p.quantity * p.unitPriceNet) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr><th colspan="3" class="text-end">Netto</th><th class="text-end">{{ euro(net) }}</th></tr>
        <tr><th colspan="3" class="text-end">MwSt</th><th class="text-end">{{ euro(vat) }}</th></tr>
        <tr><th colspan="3" class="text-end">Brutto</th><th class="text-end">{{ euro(gross) }}</th></tr>
      </tfoot>
    </table>

    <div class="d-flex justify-content-between">
      <button class="btn btn-link" @click="step = 2">← Zurück</button>
      <button class="btn btn-success" :disabled="saving" @click="submit">{{ saving ? 'Anlegen…' : 'Auftrag anlegen' }}</button>
    </div>
  </section>
</template>

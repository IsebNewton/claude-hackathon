<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api, apiError } from '@/api/client'
import type { CustomerRequest } from '@/api/types'

const props = defineProps<{ id?: string }>()
const router = useRouter()

const form = ref<CustomerRequest>({
  type: 'PRIVAT',
  name: '',
  company: '',
  street: '',
  houseNumber: '',
  postalCode: '',
  city: '',
  country: 'DEU',
  email: '',
  phone: '',
  iban: '',
  bic: ''
})
const error = ref('')
const saving = ref(false)
const isEdit = !!props.id

async function load() {
  if (!props.id) return
  try {
    const { data } = await api.get(`/customers/${props.id}`)
    form.value = {
      type: data.type,
      name: data.name,
      company: data.company ?? '',
      street: data.street,
      houseNumber: data.houseNumber ?? '',
      postalCode: data.postalCode,
      city: data.city,
      country: data.country,
      email: data.email ?? '',
      phone: data.phone ?? '',
      iban: data.bank?.iban ?? '',
      bic: data.bank?.bic ?? ''
    }
  } catch (e) {
    error.value = apiError(e)
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (isEdit) {
      await api.put(`/customers/${props.id}`, form.value)
    } else {
      await api.post('/customers', form.value)
    }
    router.push({ name: 'customers' })
  } catch (e) {
    error.value = apiError(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <h2>{{ isEdit ? 'Kunde bearbeiten' : 'Neuer Kunde' }}</h2>
  <div v-if="error" class="alert alert-danger">{{ error }}</div>

  <form @submit.prevent="save" class="row g-4" style="max-width: 900px;">
    <div class="col-md-6">
      <h5>Stammdaten</h5>
      <div class="mb-2">
        <label class="form-label">Typ</label>
        <select v-model="form.type" class="form-select">
          <option value="PRIVAT">Privatkunde</option>
          <option value="GESCHAEFT">Geschäftskunde</option>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Name *</label>
        <input v-model="form.name" class="form-control" required />
      </div>
      <div class="mb-2">
        <label class="form-label">Firma</label>
        <input v-model="form.company" class="form-control" />
      </div>
      <div class="mb-2">
        <label class="form-label">E-Mail</label>
        <input v-model="form.email" type="email" class="form-control" />
      </div>
      <div class="mb-2">
        <label class="form-label">Telefon</label>
        <input v-model="form.phone" class="form-control" />
      </div>
    </div>

    <div class="col-md-6">
      <h5>Adresse</h5>
      <div class="row g-2">
        <div class="col-8 mb-2">
          <label class="form-label">Straße *</label>
          <input v-model="form.street" class="form-control" required />
        </div>
        <div class="col-4 mb-2">
          <label class="form-label">Nr.</label>
          <input v-model="form.houseNumber" class="form-control" />
        </div>
      </div>
      <div class="row g-2">
        <div class="col-4 mb-2">
          <label class="form-label">PLZ *</label>
          <input v-model="form.postalCode" class="form-control" maxlength="5" required />
        </div>
        <div class="col-8 mb-2">
          <label class="form-label">Ort *</label>
          <input v-model="form.city" class="form-control" required />
        </div>
      </div>

      <h5 class="mt-3">Bankverbindung (SEPA)</h5>
      <div class="mb-2">
        <label class="form-label">IBAN</label>
        <input v-model="form.iban" class="form-control" placeholder="DE…" />
        <div class="form-text">Wird per iban4j geprüft (Prüfsumme).</div>
      </div>
      <div class="mb-2">
        <label class="form-label">BIC</label>
        <input v-model="form.bic" class="form-control" />
      </div>
    </div>

    <div class="col-12">
      <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Speichern…' : 'Speichern' }}</button>
      <RouterLink class="btn btn-link" :to="{ name: 'customers' }">Abbrechen</RouterLink>
    </div>
  </form>
</template>

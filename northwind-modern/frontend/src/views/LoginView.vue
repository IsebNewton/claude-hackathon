<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiError } from '@/api/client'

const auth = useAuthStore()
const router = useRouter()

const username = ref('admin')
const password = ref('admin123')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(username.value, password.value)
    router.push({ name: 'orders' })
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container" style="max-width: 420px; margin-top: 8rem;">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title mb-1">Northwind Logistics</h3>
        <p class="text-muted mb-4">Bitte anmelden</p>
        <form @submit.prevent="submit">
          <div class="mb-3">
            <label class="form-label">Benutzername</label>
            <input v-model="username" class="form-control" autofocus />
          </div>
          <div class="mb-3">
            <label class="form-label">Passwort</label>
            <input v-model="password" type="password" class="form-control" />
          </div>
          <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
          <button class="btn btn-primary w-100" :disabled="loading">
            {{ loading ? 'Anmelden…' : 'Anmelden' }}
          </button>
        </form>
        <p class="text-muted small mt-3 mb-0">Demo: admin / admin123</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

function logout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
      <RouterLink class="navbar-brand" to="/">Northwind Logistics</RouterLink>
      <div class="navbar-nav me-auto">
        <RouterLink class="nav-link" to="/customers">Kunden</RouterLink>
        <RouterLink class="nav-link" to="/orders">Aufträge</RouterLink>
        <RouterLink class="nav-link" to="/invoices">Rechnungen</RouterLink>
      </div>
      <div class="navbar-nav">
        <span class="navbar-text me-3">{{ auth.name }}</span>
        <button class="btn btn-outline-light btn-sm" @click="logout">Logout</button>
      </div>
    </div>
  </nav>
</template>

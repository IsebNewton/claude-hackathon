import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, TOKEN_KEY } from '@/api/client'

interface LoginResponse {
  token: string
  username: string
  name: string | null
  role: string
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const name = ref<string | null>(localStorage.getItem('northwind.name'))
  const role = ref<string | null>(localStorage.getItem('northwind.role'))

  const isAuthenticated = computed(() => !!token.value)

  async function login(username: string, password: string) {
    const { data } = await api.post<LoginResponse>('/auth/login', { username, password })
    token.value = data.token
    name.value = data.name ?? data.username
    role.value = data.role
    localStorage.setItem(TOKEN_KEY, data.token)
    localStorage.setItem('northwind.name', name.value ?? '')
    localStorage.setItem('northwind.role', data.role)
  }

  function logout() {
    token.value = null
    name.value = null
    role.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem('northwind.name')
    localStorage.removeItem('northwind.role')
  }

  return { token, name, role, isAuthenticated, login, logout }
})

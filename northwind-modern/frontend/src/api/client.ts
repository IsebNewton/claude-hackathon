import axios from 'axios'

const TOKEN_KEY = 'northwind.token'

export const api = axios.create({
  baseURL: '/api'
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !error.config?.url?.includes('/auth/login')) {
      localStorage.removeItem(TOKEN_KEY)
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

/** Extract a human-friendly error message from an axios error. */
export function apiError(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const data = e.response?.data as any
    if (data?.message) return data.message
    if (data?.fieldErrors) return Object.values(data.fieldErrors).join(', ')
    return e.message
  }
  return String(e)
}

export { TOKEN_KEY }

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
    { path: '/', redirect: '/orders' },
    { path: '/customers', name: 'customers', component: () => import('@/views/CustomersView.vue') },
    { path: '/customers/new', name: 'customer-new', component: () => import('@/views/CustomerEditView.vue') },
    { path: '/customers/:id/edit', name: 'customer-edit', component: () => import('@/views/CustomerEditView.vue'), props: true },
    { path: '/orders', name: 'orders', component: () => import('@/views/OrdersView.vue') },
    { path: '/orders/new', name: 'order-new', component: () => import('@/views/OrderWizardView.vue') },
    { path: '/orders/:id', name: 'order-detail', component: () => import('@/views/OrderDetailView.vue'), props: true },
    { path: '/invoices', name: 'invoices', component: () => import('@/views/InvoicesView.vue') },
    { path: '/invoices/:id', name: 'invoice-detail', component: () => import('@/views/InvoiceDetailView.vue'), props: true },
    { path: '/invoices/:id/payment', name: 'invoice-payment', component: () => import('@/views/PaymentView.vue'), props: true }
  ]
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'orders' }
  }
})

export default router

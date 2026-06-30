import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Article, Customer, OrderPriority } from '@/api/types'

export interface WizardPosition {
  articleId: string | null
  description: string
  quantity: number
  unitPriceNet: number
  vatRate: number
}

/**
 * Replaces the legacy `$_SESSION['neuer_auftrag']` server-side wizard state.
 * Three steps: 1) pick customer, 2) add positions, 3) confirm.
 */
export const useOrderWizardStore = defineStore('orderWizard', () => {
  const step = ref(1)
  const customer = ref<Customer | null>(null)
  const positions = ref<WizardPosition[]>([])
  const priority = ref<OrderPriority>('NORMAL')
  const deliveryDateTarget = ref<string | null>(null)
  const notes = ref<string>('')

  const net = computed(() =>
    positions.value.reduce((sum, p) => sum + p.quantity * p.unitPriceNet, 0)
  )
  const vat = computed(() =>
    positions.value.reduce((sum, p) => sum + (p.quantity * p.unitPriceNet * p.vatRate) / 100, 0)
  )
  const gross = computed(() => net.value + vat.value)

  function selectCustomer(c: Customer) {
    customer.value = c
    step.value = 2
  }

  function addArticle(article: Article, quantity: number, price?: number) {
    positions.value.push({
      articleId: article.id,
      description: article.name,
      quantity,
      unitPriceNet: price ?? article.priceNet,
      vatRate: article.vatRate
    })
  }

  function removePosition(index: number) {
    positions.value.splice(index, 1)
  }

  function reset() {
    step.value = 1
    customer.value = null
    positions.value = []
    priority.value = 'NORMAL'
    deliveryDateTarget.value = null
    notes.value = ''
  }

  return {
    step, customer, positions, priority, deliveryDateTarget, notes,
    net, vat, gross,
    selectCustomer, addArticle, removePosition, reset
  }
})

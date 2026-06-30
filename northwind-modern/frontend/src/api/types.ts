export type OrderStatus =
  | 'NEU' | 'BESTAETIGT' | 'IN_BEARBEITUNG' | 'VERSENDET' | 'ABGESCHLOSSEN' | 'STORNIERT'

export type InvoiceStatus =
  | 'OFFEN' | 'TEILBEZAHLT' | 'BEZAHLT' | 'STORNIERT' | 'GUTSCHRIFT'

export type CustomerType = 'PRIVAT' | 'GESCHAEFT'
export type OrderPriority = 'NORMAL' | 'HOCH' | 'EXPRESS'

export interface PageResponse<T> {
  content: T[]
  page: number
  size: number
  totalElements: number
  totalPages: number
}

export interface BankDetails {
  iban: string | null
  bic: string | null
  validated: boolean | null
  validatedAt: string | null
}

export interface Customer {
  id: string
  customerNumber: string
  type: CustomerType
  name: string
  company: string | null
  street: string
  houseNumber: string | null
  postalCode: string
  city: string
  country: string
  email: string | null
  phone: string | null
  bank: BankDetails | null
  active: boolean
  createdAt: string
  updatedAt: string | null
}

export interface CustomerRequest {
  type: CustomerType
  name: string
  company?: string | null
  street: string
  houseNumber?: string | null
  postalCode: string
  city: string
  country: string
  email?: string | null
  phone?: string | null
  iban?: string | null
  bic?: string | null
}

export interface Article {
  id: string
  sku: string
  name: string
  description: string | null
  priceNet: number
  vatRate: number
  stock: number
}

export interface OrderPosition {
  articleId: string | null
  sku: string | null
  description: string
  quantity: number
  unit: string
  unitPriceNet: number
  vatRate: number
  lineNet: number
  lineGross: number
}

export interface OrderTotals {
  net: number
  vat: number
  gross: number
}

export interface Order {
  id: string
  orderNumber: string
  customerId: string
  customerNumber: string | null
  customerName: string | null
  status: OrderStatus
  priority: OrderPriority
  positions: OrderPosition[]
  totals: OrderTotals
  deliveryDateTarget: string | null
  deliveryDateActual: string | null
  notes: string | null
  createdAt: string
  confirmedAt: string | null
  completedAt: string | null
}

export interface OrderPositionRequest {
  articleId?: string | null
  description?: string | null
  quantity: number
  unitPriceNet?: number | null
  vatRate?: number | null
}

export interface OrderRequest {
  customerId: string
  priority: OrderPriority
  deliveryDateTarget?: string | null
  notes?: string | null
  positions: OrderPositionRequest[]
}

export interface Payment {
  amount: number
  method: string
  iban: string | null
  bic: string | null
  bankValidated: boolean | null
  date: string
  note: string | null
}

export interface Invoice {
  id: string
  invoiceNumber: string
  orderId: string
  customerId: string
  customerNumber: string | null
  customerName: string | null
  netAmount: number
  vatAmount: number
  grossAmount: number
  status: InvoiceStatus
  dueDate: string
  paidAmount: number
  openAmount: number
  paidAt: string | null
  payments: Payment[]
  dunningLevel: number
  createdAt: string
}

export interface PaymentRequest {
  amount: number
  method: string
  iban?: string | null
  bic?: string | null
  note?: string | null
}

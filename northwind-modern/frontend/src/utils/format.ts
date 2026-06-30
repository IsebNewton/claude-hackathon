/** Mirrors the legacy formatEuro / German date formatting helpers. */

export function euro(amount: number | null | undefined): string {
  if (amount === null || amount === undefined) return '—'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount)
}

export function dateDe(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return '—'
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(d)
}

export function dateTimeDe(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return '—'
  return new Intl.DateTimeFormat('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  }).format(d)
}

const ORDER_STATUS_LABELS: Record<string, string> = {
  NEU: 'Neu',
  BESTAETIGT: 'Bestätigt',
  IN_BEARBEITUNG: 'In Bearbeitung',
  VERSENDET: 'Versendet',
  ABGESCHLOSSEN: 'Abgeschlossen',
  STORNIERT: 'Storniert'
}

const ORDER_STATUS_CLASSES: Record<string, string> = {
  NEU: 'bg-secondary',
  BESTAETIGT: 'bg-info',
  IN_BEARBEITUNG: 'bg-primary',
  VERSENDET: 'bg-warning text-dark',
  ABGESCHLOSSEN: 'bg-success',
  STORNIERT: 'bg-dark'
}

const INVOICE_STATUS_LABELS: Record<string, string> = {
  OFFEN: 'Offen',
  TEILBEZAHLT: 'Teilbezahlt',
  BEZAHLT: 'Bezahlt',
  STORNIERT: 'Storniert',
  GUTSCHRIFT: 'Gutschrift'
}

const INVOICE_STATUS_CLASSES: Record<string, string> = {
  OFFEN: 'bg-warning text-dark',
  TEILBEZAHLT: 'bg-info',
  BEZAHLT: 'bg-success',
  STORNIERT: 'bg-dark',
  GUTSCHRIFT: 'bg-secondary'
}

export const orderStatusLabel = (s: string) => ORDER_STATUS_LABELS[s] ?? s
export const orderStatusClass = (s: string) => ORDER_STATUS_CLASSES[s] ?? 'bg-secondary'
export const invoiceStatusLabel = (s: string) => INVOICE_STATUS_LABELS[s] ?? s
export const invoiceStatusClass = (s: string) => INVOICE_STATUS_CLASSES[s] ?? 'bg-secondary'

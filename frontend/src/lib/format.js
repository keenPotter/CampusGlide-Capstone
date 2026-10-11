const DATE_OPTIONS = { year: 'numeric', month: 'short', day: 'numeric' }

export function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value.length <= 10 ? `${value}T00:00:00` : value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('en-PH', DATE_OPTIONS)
}

export function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('en-PH', { ...DATE_OPTIONS, hour: 'numeric', minute: '2-digit' })
}

/** API returns times as "H:i" or "H:i:s". */
export function formatTime(value) {
  if (!value) return '—'
  const [hours, minutes] = String(value).split(':')
  const date = new Date()
  date.setHours(Number(hours), Number(minutes ?? 0), 0, 0)
  return date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })
}

/** Normalises "07:30:00" -> "07:30" for <input type="time"> and H:i validation. */
export function toTimeInput(value) {
  if (!value) return ''
  return String(value).slice(0, 5)
}

export function todayIso() {
  return new Date().toISOString().slice(0, 10)
}

export function initials(name = '') {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

export const ROLE_LABELS = {
  administrator: 'Administrator',
  faculty: 'Faculty',
}

export const STATUS_LABELS = {
  pending: 'Pending',
  approved: 'Approved',
  disapproved: 'Disapproved',
}